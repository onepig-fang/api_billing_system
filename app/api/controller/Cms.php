<?php

declare(strict_types=1);

namespace app\api\controller;

use app\ApiController;
use think\facade\Db;
use think\Request;

/**
 * API采集接口控制器
 * 处理苹果CMS采集接口请求
 */
class Cms extends ApiController
{
    /**
     * 无需鉴权的方法（采集接口使用独立鉴权）
     */
    protected $noNeedAuth = ['index'];

    /**
     * 采集接口主方法
     * GET /api/cms/?cmsapi_id=xxx&uid=xxx
     */
    public function index(Request $request)
    {
        // 获取参数
        $cmsapiId = $request->param('cmsapi_id', '');
        $uid = $request->param('uid', '');
        $pg = (int) $request->param('pg', 1);
        $t = $request->param('t', '');
        $h = $request->param('h', '');
        $ids = $request->param('ids', '');
        $wd = $request->param('wd', '');
        $ac = $request->param('ac', 'list');
        
        // 密钥（支持 my / key 两种参数名）
        $key = $request->param('my', '');
        if ($key === '') {
            $key = $request->param('key', '');
        }

        if (empty($cmsapiId) || empty($uid)) {
            return $this->apiError('参数不完整');
        }

        if (empty($key)) {
            return $this->apiError('缺少授权密钥');
        }

        // 获取用户信息：必须 uid 与密钥同时匹配，防止 uid 枚举
        $user = Db::name('user')
            ->where('uid', $uid)
            ->where('my', $key)
            ->find();
        if (!$user) {
            return $this->apiError('授权验证失败，请检查参数');
        }

        // 检查账号状态
        if ((int) ($user['state'] ?? 0) !== 1) {
            return $this->apiError('账号已被封禁');
        }

        // 获取采集接口信息
        $cmsapi = Db::name('cmsapi')->where('cmsapi_id', $cmsapiId)->find();
        if (!$cmsapi) {
            return $this->apiError('采集接口不存在');
        }

        // 检查是否需要授权
        if ($cmsapi['cmsapi_auth'] == 1) {
            // 检查用户是否有该采集接口的使用权
            $shopTime = Db::name('shop_time')
                ->where('uid', $uid)
                ->where('cmsapi_id', $cmsapiId)
                ->where('end_time', '>', date('Y-m-d H:i:s'))
                ->find();

            if (!$shopTime) {
                return $this->apiError('未购买该采集接口或已过期');
            }

            // 检查IP授权
            $ip = $request->ip();
            $authIp = $shopTime['cj_auth_ip'] ?? '';

            if (!empty($authIp)) {
                $authIps = array_map('trim', explode(',', $authIp));
                if (!in_array($ip, $authIps)) {
                    return $this->apiError('IP未授权: ' . $ip);
                }
            }
        }
        
        // 构建采集请求URL
        $apiUrl = $cmsapi['cmsapi_url'];
        
        // 解析原URL的查询参数
        $urlParts = parse_url($apiUrl);
        $baseUrl = ($urlParts['scheme'] ?? 'http') . '://' . $urlParts['host'];
        if (isset($urlParts['port'])) {
            $baseUrl .= ':' . $urlParts['port'];
        }
        $baseUrl .= $urlParts['path'] ?? '';
        
        // 构建查询参数
        $params = [];
        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $params);
        }
        
        // 添加采集参数
        $params['ac'] = $ac;
        $params['pg'] = $pg;
        
        if (!empty($t)) {
            $params['t'] = $t;
        }
        if (!empty($h)) {
            $params['h'] = $h;
        }
        if (!empty($ids)) {
            $params['ids'] = $ids;
        }
        if (!empty($wd)) {
            $params['wd'] = $wd;
        }
        
        $requestUrl = $baseUrl . '?' . http_build_query($params);
        
        // 请求采集接口
        $result = $this->fetchCmsData($requestUrl, $cmsapi['cmsapi_format'] ?? 'json');
        
        if ($result['success']) {
            // 记录采集日志
            Db::name('cms_log')->insert([
                'uid' => (int) $uid,
                'cmsapi_id' => (int) $cmsapiId,
                'ip' => $request->ip(),
                'time' => date('Y-m-d H:i:s')
            ]);
            
            return $result['data'];
        }
        
        return $this->apiError($result['msg']);
    }
    
    /**
     * 获取采集数据
     */
    protected function fetchCmsData(string $url, string $format = 'json'): array
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; CmsCollector/1.0)');
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                return ['success' => false, 'msg' => 'CURL错误: ' . $error, 'data' => null];
            }
            
            if ($httpCode != 200) {
                return ['success' => false, 'msg' => '采集接口返回错误码: ' . $httpCode, 'data' => null];
            }
            
            if ($format == 'xml') {
                // XML格式，直接返回
                header('Content-Type: application/xml; charset=utf-8');
                return ['success' => true, 'msg' => '成功', 'data' => $response];
            }
            
            // JSON格式
            $data = json_decode($response, true);
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                // 可能是XML格式，尝试转换
                $xml = @simplexml_load_string($response);
                if ($xml !== false) {
                    header('Content-Type: application/xml; charset=utf-8');
                    return ['success' => true, 'msg' => '成功', 'data' => $response];
                }

                return ['success' => false, 'msg' => '数据格式解析失败', 'data' => null];
            }

            header('Content-Type: application/json; charset=utf-8');
            return ['success' => true, 'msg' => '成功', 'data' => $response];
            
        } catch (\Exception $e) {
            \think\facade\Log::error('采集异常', ['error' => $e->getMessage()]);
            return ['success' => false, 'msg' => '采集异常，请稍后重试', 'data' => null];
        }
    }
    
    /**
     * 获取采集接口列表
     */
    public function list(Request $request)
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->apiError('用户验证失败');
        }

        $shopTimes = Db::name('shop_time')
            ->field('cmsapi_id,end_time,cj_auth_ip')
            ->where('uid', $user->uid)
            ->where('end_time', '>', date('Y-m-d H:i:s'))
            ->select()
            ->toArray();

        if (empty($shopTimes)) {
            return $this->apiSuccess([]);
        }

        $cmsapiIds = array_values(array_unique(array_column($shopTimes, 'cmsapi_id')));
        $cmsapis = Db::name('cmsapi')
            ->field('cmsapi_id,cmsapi_name')
            ->whereIn('cmsapi_id', $cmsapiIds)
            ->select()
            ->toArray();
        $cmsapiMap = array_column($cmsapis, null, 'cmsapi_id');

        $list = [];
        foreach ($shopTimes as $item) {
            $cmsapi = $cmsapiMap[$item['cmsapi_id']] ?? null;
            if ($cmsapi) {
                $list[] = [
                    'cmsapi_id' => $cmsapi['cmsapi_id'],
                    'cmsapi_name' => $cmsapi['cmsapi_name'],
                    'end_time' => $item['end_time'],
                    'cj_auth_ip' => $item['cj_auth_ip'] ?? ''
                ];
            }
        }

        return $this->apiSuccess($list);
    }
}
