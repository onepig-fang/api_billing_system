<?php

declare(strict_types=1);

namespace app\api\controller;

use app\ApiController;
use app\common\model\Setting;
use think\facade\Db;
use think\Request;

/**
 * API配置控制器
 * 处理API配置相关接口
 */
class Set extends ApiController
{
    /**
     * 无需鉴权的方法
     */
    protected $noNeedAuth = ['info'];
    
    /**
     * 获取API信息
     * GET /api/set/info
     */
    public function info(Request $request)
    {
        $setting = Setting::find(1);
        
        return $this->apiSuccess([
            'name' => $setting['name'] ?? '视频解析系统',
            'version' => $setting['version'] ?? '1.0.0',
            'notice' => $setting['notice'] ?? '',
            'time' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * 获取用户API信息
     * GET /api/set/user?key=xxx
     */
    public function user(Request $request)
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->apiError('用户验证失败');
        }
        
        // 获取用户基本信息
        $data = [
            'uid' => $user->uid,
            'user' => $user->user,
            'way' => $user->way,
            'points' => $user->points ?? 0,
            'money' => $user->money ?? 0,
            'daynum' => $user->daynum ?? 0,
            'daily_limit' => $user->daily_limit ?? 0,
            'fullnum' => $user->fullnum ?? 0,
            'bytime' => $user->bytime,
            'auth_ip' => $user->auth_ip ?? '',
            'cj_auth_ip' => $user->cj_auth_ip ?? ''
        ];
        
        // 计算剩余可用
        if ($user->way == '包点') {
            $data['remain'] = $user->points ?? 0;
            $data['remain_type'] = '点';
        } else {
            // 包月
            if ($user->bytime && strtotime($user->bytime) > time()) {
                $data['remain'] = ceil((strtotime($user->bytime) - time()) / 86400);
                $data['remain_type'] = '天';
            } else {
                $data['remain'] = 0;
                $data['remain_type'] = '天';
            }
        }
        
        return $this->apiSuccess($data);
    }
    
    /**
     * 获取解析接口列表
     * GET /api/set/json?key=xxx
     */
    public function json(Request $request)
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->apiError('用户验证失败');
        }

        $page = max(1, (int)$request->param('page', $request->param('current_page', 1)));
        $limit = min(max(1, (int)$request->param('limit', 20)), 100);

        // 仅取实际存在的列；group_id/Json_g 是可选的分组列，由 getJsonGroupField 动态判定
        // 用户侧一览只展示启用（status=1）的解析接口
        $jsonPage = Db::name('json')
            ->where('status', 1)
            ->order('id', 'asc')
            ->paginate([
                'list_rows' => $limit,
                'page' => $page
            ]);
        $jsons = $jsonPage->items();
        $groups = $this->getJsonGroups();
        $groupField = $this->getJsonGroupField($jsons);

        if (empty($groups) || $groupField === '') {
            return $this->apiSuccess([
                'total' => $jsonPage->total(),
                'per_page' => $jsonPage->listRows(),
                'current_page' => $jsonPage->currentPage(),
                'last_page' => $jsonPage->lastPage(),
                'data' => [
                    [
                        'id' => 0,
                        'name' => '默认分组',
                        'items' => $this->formatJsonItems($jsons)
                    ]
                ]
            ]);
        }

        $list = [];
        foreach ($groups as $group) {
            $groupData = [
                'id' => $group['id'],
                'name' => $group['name'],
                'items' => []
            ];

            foreach ($jsons as $json) {
                if ((string)($json[$groupField] ?? '') === (string)$group['id']) {
                    $groupData['items'][] = $this->formatJsonItem($json);
                }
            }

            if (!empty($groupData['items'])) {
                $list[] = $groupData;
            }
        }

        if (empty($list)) {
            $list[] = [
                'id' => 0,
                'name' => '默认分组',
                'items' => $this->formatJsonItems($jsons)
            ];
        }

        return $this->apiSuccess([
            'total' => $jsonPage->total(),
            'per_page' => $jsonPage->listRows(),
            'current_page' => $jsonPage->currentPage(),
            'last_page' => $jsonPage->lastPage(),
            'data' => $list
        ]);
    }
    
    private function getJsonGroups(): array
    {
        try {
            return Db::name('json_group')->order('id', 'asc')->select()->toArray();
        } catch (\Throwable $e) {
            try {
                return Db::name('json_g')->order('id', 'asc')->select()->toArray();
            } catch (\Throwable $e) {
                return [];
            }
        }
    }

    private function getJsonGroupField(array $jsons): string
    {
        $first = $jsons[0] ?? [];

        if (array_key_exists('group_id', $first)) {
            return 'group_id';
        }

        if (array_key_exists('Json_g', $first)) {
            return 'Json_g';
        }

        return '';
    }

    private function formatJsonItems(array $jsons): array
    {
        $items = [];
        foreach ($jsons as $json) {
            $items[] = $this->formatJsonItem($json);
        }

        return $items;
    }

    private function formatJsonItem(array $json): array
    {
        return [
            'id' => $json['id'],
            'name' => $json['Json_name'],
            'type' => $json['Json_type'],
            'main' => $json['Json_main'] ?? 0
        ];
    }

    /**
     * 获取采集接口列表
     * GET /api/set/cms?key=xxx
     */
    public function cms(Request $request)
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->apiError('用户验证失败');
        }
        
        $shopTimes = Db::name('shop_time')
            ->field('cmsapi_id,end_time,cj_auth_ip')
            ->where('uid', $user->uid)
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
                $isExpired = strtotime($item['end_time']) < time();
                
                $list[] = [
                    'cmsapi_id' => $cmsapi['cmsapi_id'],
                    'cmsapi_name' => $cmsapi['cmsapi_name'],
                    'end_time' => $item['end_time'],
                    'cj_auth_ip' => $item['cj_auth_ip'] ?? '',
                    'status' => $isExpired ? 0 : 1,
                    'status_text' => $isExpired ? '已过期' : '正常'
                ];
            }
        }
        
        return $this->apiSuccess($list);
    }
    
    /**
     * 更新用户解析IP授权
     * POST /api/set/authip?key=xxx
     */
    public function authip(Request $request)
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->apiError('用户验证失败');
        }
        
        $ip = $request->param('ip', '');
        
        if (empty($ip)) {
            return $this->apiError('IP不能为空');
        }
        
        // 验证IP格式
        $ips = array_map('trim', explode(',', $ip));
        foreach ($ips as $singleIp) {
            if (!filter_var($singleIp, FILTER_VALIDATE_IP)) {
                return $this->apiError('IP格式不正确: ' . $singleIp);
            }
        }
        
        // 更新用户IP授权
        Db::name('user')->where('uid', $user->uid)->update([
            'auth_ip' => $ip
        ]);
        
        return $this->apiSuccess([], 'IP授权更新成功');
    }
    
    /**
     * 更新用户采集IP授权
     * POST /api/set/cjip?key=xxx
     */
    public function cjip(Request $request)
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->apiError('用户验证失败');
        }
        
        $ip = $request->param('ip', '');
        $cmsapiId = $request->param('cmsapi_id', '');
        
        if (empty($ip)) {
            return $this->apiError('IP不能为空');
        }
        
        // 验证IP格式
        $ips = array_map('trim', explode(',', $ip));
        foreach ($ips as $singleIp) {
            if (!filter_var($singleIp, FILTER_VALIDATE_IP)) {
                return $this->apiError('IP格式不正确: ' . $singleIp);
            }
        }
        
        if (!empty($cmsapiId)) {
            $affected = Db::name('shop_time')
                ->where('uid', $user->uid)
                ->where('cmsapi_id', $cmsapiId)
                ->update(['cj_auth_ip' => $ip]);

            if ($affected === 0) {
                $exists = Db::name('shop_time')
                    ->where('uid', $user->uid)
                    ->where('cmsapi_id', $cmsapiId)
                    ->find();
                if (!$exists) {
                    return $this->apiError('采集接口不存在或未购买');
                }
            }
        } else {
            // 更新所有采集接口的IP授权
            Db::name('shop_time')
                ->where('uid', $user->uid)
                ->update(['cj_auth_ip' => $ip]);

            // 同时更新用户表
            Db::name('user')->where('uid', $user->uid)->update([
                'cj_auth_ip' => $ip
            ]);
        }
        
        return $this->apiSuccess([], '采集IP授权更新成功');
    }
    
    /**
     * 获取调用统计
     * GET /api/set/stats?key=xxx
     */
    public function stats(Request $request)
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->apiError('用户验证失败');
        }
        
        // 今日统计
        $today = date('Y-m-d');
        $todayCount = Db::name('userlog')
            ->where('uid', $user->uid)
            ->whereDay('intime', $today)
            ->count();
        
        $todaySuccess = Db::name('userlog')
            ->where('uid', $user->uid)
            ->whereDay('intime', $today)
            ->where('status', 'like', '%成功%')
            ->count();
        
        // 本月统计
        $monthCount = Db::name('userlog')
            ->where('uid', $user->uid)
            ->whereMonth('intime')
            ->count();
        
        // 总计
        $totalCount = Db::name('userlog')
            ->where('uid', $user->uid)
            ->count();
        
        return $this->apiSuccess([
            'today_total' => $todayCount,
            'today_success' => $todaySuccess,
            'today_fail' => $todayCount - $todaySuccess,
            'month_total' => $monthCount,
            'all_total' => $totalCount
        ]);
    }
}
