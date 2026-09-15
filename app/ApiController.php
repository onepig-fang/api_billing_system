<?php

declare(strict_types=1);

namespace app;

use app\common\library\Auth;
use app\common\model\Users;
use app\common\model\Setting;
use app\common\model\Userlogs;
use app\common\model\Json;
use think\facade\Db;
use think\facade\Request;

/**
 * API基础控制器
 */
class ApiController extends BaseController
{
    /**
     * 当前用户
     */
    protected $user = null;
    
    /**
     * Auth实例
     */
    protected $auth = null;
    
    /**
     * 网站配置（懒加载，首次 getConfig() 时查询）
     */
    protected $config = null;
    
    /**
     * 无需鉴权的方法
     */
    protected $noNeedAuth = [];
    
    /**
     * 初始化
     */
    protected function initialize()
    {
        parent::initialize();

        $action = strtolower($this->request->action());
        $controller = strtolower($this->request->controller());
        // 仅解析类接口（Index 控制器）需要 url 参数，采集类接口（Cms）不需要
        if ($controller === 'index'
            && in_array($action, ['index', 'json', 'bfq'], true)
            && $this->request->param('url', '') === '') {
            $this->apiError('请提供视频地址');
        }

        // 获取Auth实例
        $this->auth = Auth::instance();

        // 检查是否需要鉴权
        if (!in_array($action, array_map('strtolower', $this->noNeedAuth))) {
            $this->checkApiAuth();
        }
    }
    
    /**
     * API鉴权
     */
    protected function checkApiAuth()
    {
        // 获取用户标识（支持多种方式）
        $uid = $this->request->param('uid', '');
        $key = $this->request->param('key', '');
        $my = $this->request->param('my', '');
        $authKey = $my !== '' ? $my : $key;
        
        // 通过UID和KEY验证
        if ($uid && $key) {
            $this->user = Users::where('uid', $uid)->where('my', $key)->find();
        }
        // 通过密钥验证
        elseif ($authKey) {
            $this->user = Users::where('my', $authKey)->find();
        }
        
        if (!$this->user) {
            $this->apiError('授权验证失败，请检查参数');
        }
        
        // 检查账号状态
        if ($this->user->state != 1) {
            $this->apiError('账号已被封禁');
        }
        
        // 检查IP授权
        $config = $this->getConfig();
        if ($config && $config->authip == 1) {
            $ip = Request::ip();
            if (!$this->checkIpAuth($ip)) {
                $this->apiError('IP未授权');
            }
        }
        
        // 检查套餐有效性
        $packageCheck = $this->auth->checkPackage($this->user->id);
        if (!$packageCheck['valid']) {
            $this->apiError($packageCheck['msg']);
        }
    }
    
    /**
     * 检查IP授权
     */
    protected function checkIpAuth(string $ip): bool
    {
        if (!$this->user) {
            return false;
        }
        
        // 如果没有设置授权IP，则允许所有IP
        if (empty($this->user->auth_ip)) {
            return true;
        }
        
        // 检查IP是否在授权列表中
        $authIps = array_map('trim', explode(',', $this->user->auth_ip));
        return in_array($ip, $authIps);
    }
    
    /**
     * 记录调用日志
     */
    protected function logApiCall(string $url, string $status, float $time = 0)
    {
        if (!$this->user) {
            return;
        }

        try {
            $log = new Userlogs();
            $log->url = $url;
            $log->ip = Request::ip();
            $log->uid = $this->user->uid;
            $log->sid = $this->user->id;
            $log->intime = date('Y-m-d H:i:s');
            $log->status = $status;
            $log->uselogjxtime = $time;
            $log->save();
        } catch (\Throwable $e) {
            \think\facade\Log::error('API调用日志记录失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 扣除用户点数/增加调用次数
     */
    protected function deductUserQuota(): bool
    {
        if (!$this->user) {
            return false;
        }
        
        $userId = $this->user->id;
        $today = date('Y-m-d');
        $dayTime = $this->user->daytime ? date('Y-m-d', strtotime($this->user->daytime)) : null;
        
        // 跨日重置
        if ($dayTime !== $today) {
            Users::where('id', $userId)->update([
                'daynum' => 0,
                'daytime' => date('Y-m-d H:i:s')
            ]);
        }
        
        // 使用数据库原子操作防止并发竞态
        if ($this->user->way == '包点' || empty($this->user->way)) {
            $affected = Users::where('id', $userId)
                ->where('points', '>=', 1)
                ->dec('points', 1)
                ->inc('daynum', 1)
                ->update();
            return $affected > 0;
        }
        
        if ($this->user->way == '包月') {
            $fullnum = (int)($this->user->fullnum ?? 0);
            if ($fullnum > 0) {
                $affected = Users::where('id', $userId)
                    ->where('daynum', '<', $fullnum)
                    ->inc('daynum', 1)
                    ->update();
                return $affected > 0;
            }

            // fullnum<=0 表示不限每日次数，仅累计调用量
            Users::where('id', $userId)->inc('daynum', 1)->update();
            return true;
        }

        // 未识别的套餐类型一律拒绝，避免计费绕过（与 checkPackage 的“未知套餐类型”保持一致）
        return false;
    }
    
    /**
     * 返还已预占的额度（解析失败时回退，避免误扣）
     * 与 deductUserQuota 对称：包点返还点数并递减 daynum，包月仅递减 daynum
     */
    protected function refundUserQuota(): void
    {
        if (!$this->user) {
            return;
        }

        $userId = $this->user->id;

        if ($this->user->way == '包点' || empty($this->user->way)) {
            Users::where('id', $userId)
                ->where('daynum', '>', 0)
                ->inc('points', 1)
                ->dec('daynum', 1)
                ->update();
            return;
        }

        if ($this->user->way == '包月') {
            Users::where('id', $userId)
                ->where('daynum', '>', 0)
                ->dec('daynum', 1)
                ->update();
        }
    }
    
    /**
     * 获取解析接口地址
     */
    protected function getParseUrl(string $videoUrl): string
    {
        // 主用接口(Json_main=1)优先，失效时回退到备用接口(Json_main=2)
        foreach ([1, 2] as $mainType) {
            $query = Json::where('Json_main', $mainType)
                ->where('Json_type', 'all')
                ->where('status', 1);
            $query = $this->applyBindFilter($query);
            $json = $query->find();

            if ($json && !empty($json->Json_api)) {
                return $json->Json_api . urlencode($videoUrl);
            }
        }

        return '';
    }

    /**
     * 获取指定平台的解析接口
     */
    protected function getPlatformParseUrl(string $videoUrl): string
    {
        // 根据视频URL判断平台
        $matchedDomain = $this->detectPlatformDomain($videoUrl);

        if ($matchedDomain) {
            $query = Json::where('Json_str', 'like', '%' . $matchedDomain . '%')
                ->where('status', 1);
            $query = $this->applyBindFilter($query);
            $platformJson = $query->find();
            if ($platformJson && !empty($platformJson->Json_api)) {
                return $platformJson->Json_api . urlencode($videoUrl);
            }
        }

        // 使用默认解析接口
        return $this->getParseUrl($videoUrl);
    }

    /**
     * 根据用户绑定的解析接口过滤查询
     */
    protected function applyBindFilter($query)
    {
        if ($this->user && !empty($this->user->bind_json_ids)) {
            $bindIds = array_filter(array_map('intval', explode(',', (string)$this->user->bind_json_ids)));
            if (!empty($bindIds)) {
                $query->whereIn('id', $bindIds);
            }
        }
        return $query;
    }
    
    /**
     * 检测视频平台
     */
    protected function detectPlatformDomain(string $url): string
    {
        $domains = [
            'v.qq.com',
            'www.mgtv.com',
            'www.iqiyi.com',
            'v.youku.com',
            'www.bilibili.com',
            'www.ixigua.com',
            'tv.sohu.com'
        ];
        
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return '';
        }
        foreach ($domains as $domain) {
            $suffix = '.' . $domain;
            if ($host === $domain || substr($host, -strlen($suffix)) === $suffix) {
                return $domain;
            }
        }
        
        return '';
    }

    /**
     * API成功响应
     */
    protected function apiSuccess($data = [], $msg = 'success', $code = 200)
    {
        $response = [
            'code' => $code,
            'msg' => $msg,
        ];

        // 将 data 中的 url 提取到顶层
        if (is_array($data) && isset($data['url'])) {
            $response['url'] = $data['url'];
            unset($data['url']);
        }
        $response['data'] = $data;
        
        return json($response);
    }
    
    /**
     * API错误响应
     */
    protected function apiError($msg = 'error', $code = 400, $data = [])
    {
        throw new \think\exception\HttpResponseException(json([
            'code' => $code,
            'msg' => $msg,
            'data' => $data
        ]));
    }
    
    /**
     * 获取当前用户
     */
    protected function getUser()
    {
        return $this->user;
    }
    
    /**
     * 获取网站配置（懒加载）
     */
    protected function getConfig()
    {
        if ($this->config === null) {
            $this->config = Setting::find(1);
        }
        return $this->config;
    }
}
