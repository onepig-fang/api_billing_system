<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\AdminController;
use app\common\model\Recharge;
use app\common\model\Userlogs;
use app\common\model\Users;
use app\common\model\Bought;
use think\facade\Db;
use think\facade\Log;
use think\facade\View;

/**
 * 记录管理控制器
 */
class Record extends AdminController
{
    /**
     * 充值记录页面
     */
    public function pay()
    {
        return view('record/pay');
    }
    
    /**
     * 获取充值记录列表
     */
    public function payList()
    {
        $page = $this->request->post('current_page', 1, 'intval');
        $limit = $this->request->post('limit', 15, 'intval');
        $key = $this->request->post('key', '');
        $status = $this->request->post('status', '');
        
        $query = Recharge::alias('r')
            ->leftJoin('user u', 'r.user_id = u.uid')
            ->field('r.*, u.user as username');

        if (!empty($key)) {
            $query->where('r.order_id|r.user_id|u.user', 'like', "%{$key}%");
        }

        if ($status !== '') {
            $query->where('r.status', $status);
        }

        $list = $query->order('r.id', 'desc')
            ->paginate([
                'page' => $page,
                'list_rows' => $limit
            ]);
        $items = $list->items();
        foreach ($items as $item) {
            $item->time_text = !empty($item->time) ? date('Y-m-d H:i:s', (int)$item->time) : '';
            $item->intime_text = !empty($item->intime) ? date('Y-m-d H:i:s', (int)$item->intime) : '';
            $item->user_text = trim((string)($item->username ?? '')) !== '' ? $item->username . '（' . $item->user_id . '）' : $item->user_id;
        }
        
        return json([
            'code' => 0,
            'msg' => '获取成功',
            'data' => [
                'data' => $list
            ]
        ]);
    }
    
    /**
     * 套餐购买记录页面
     */
    public function shop()
    {
        return view('record/shop');
    }
    
    /**
     * 获取套餐购买记录列表
     */
    public function shopList()
    {
        $page = $this->request->post('current_page', 1, 'intval');
        $limit = $this->request->post('limit', 15, 'intval');
        $key = $this->request->post('key', '');
        
        $query = Db::name('shop_log')->alias('l')
            ->leftJoin('user u', 'l.uid = u.uid')
            ->field('l.*, u.user as username');

        if (!empty($key)) {
            $query->where('l.uid|l.shop_name|u.user', 'like', "%{$key}%");
        }

        $list = $query->order('l.id', 'desc')
            ->paginate([
                'page' => $page,
                'list_rows' => $limit
            ]);
        
        return json([
            'code' => 0,
            'msg' => '获取成功',
            'data' => [
                'data' => $list
            ]
        ]);
    }
    
    /**
     * 调用记录页面
     */
    public function user()
    {
        // 调用排行榜（前十）
        $user = Users::order('daynum', 'desc')
            ->limit(10)
            ->select();

        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        $yesterdayStart = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $yesterdayEnd = date('Y-m-d 23:59:59', strtotime('-1 day'));

        // 总调用次数
        $cunt = Userlogs::count();
        $todayLogCount = Userlogs::where('intime', 'between', [$todayStart, $todayEnd])->count();
        $yesterdayLogCount = Userlogs::where('intime', 'between', [$yesterdayStart, $yesterdayEnd])->count();

        // 活跃用户（有调用记录的用户数）
        $cuntuser = Users::where('daynum', '>', 0)->count();
        $todayActiveUsers = Userlogs::where('intime', 'between', [$todayStart, $todayEnd])
            ->group('uid')
            ->count();
        $yesterdayActiveUsers = Userlogs::where('intime', 'between', [$yesterdayStart, $yesterdayEnd])
            ->group('uid')
            ->count();

        // 成功率
        $successCount = Userlogs::where('status', 'like', '成功%')->count();
        $cuntsuccess = $cunt > 0 ? round($successCount * 100 / $cunt, 2) : 0;
        $todaySuccessCount = Userlogs::where('intime', 'between', [$todayStart, $todayEnd])
            ->where('status', 'like', '成功%')
            ->count();
        $todaySuccessRate = $todayLogCount > 0 ? round($todaySuccessCount * 100 / $todayLogCount, 2) : 0;
        $yesterdaySuccessCount = Userlogs::where('intime', 'between', [$yesterdayStart, $yesterdayEnd])
            ->where('status', 'like', '成功%')
            ->count();
        $yesterdaySuccessRate = $yesterdayLogCount > 0 ? round($yesterdaySuccessCount * 100 / $yesterdayLogCount, 2) : 0;

        $log = $this->buildTrendData($todayLogCount, $yesterdayLogCount);
        $usera = $this->buildTrendData($todayActiveUsers, $yesterdayActiveUsers);
        $succes = $this->buildTrendData($todaySuccessRate, $yesterdaySuccessRate);

        View::assign([
            'user' => $user,
            'cunt' => $cunt,
            'cuntuser' => $cuntuser,
            'cuntsuccess' => $cuntsuccess,
            'log' => $log,
            'usera' => $usera,
            'succes' => $succes,
        ]);

        return view('record/user');
    }
    
    /**
     * 获取调用记录列表
     */
    public function userList()
    {
        $page = $this->request->post('current_page', 1, 'intval');
        $limit = $this->request->post('limit', 15, 'intval');
        $key = $this->request->post('key', '');
        
        $query = Userlogs::alias('u');
        
        if (!empty($key)) {
            $query->where('u.url|u.ip|u.uid', 'like', "%{$key}%");
        }
        
        $list = $query->order('u.id', 'desc')
            ->paginate([
                'page' => $page,
                'list_rows' => $limit
            ]);
        
        return json([
            'code' => 0,
            'msg' => '获取成功',
            'data' => [
                'data' => $list
            ]
        ]);
    }
    
    /**
     * 调用排行页面
     */
    public function top()
    {
        return view('record/top');
    }
    
    /**
     * 获取调用排行列表
     */
    public function topList()
    {
        $status = $this->request->post('status', '');

        $query = Db::name('userlog');
        if ($status !== '') {
            if ((string)$status === '1') {
                $query->where('status', 'like', '成功%');
            } elseif ((string)$status === '2') {
                $query->where('status', 'not like', '成功%');
            }
        }

        $rows = $query
            ->field('url, COUNT(*) as num')
            ->group('url')
            ->order('num', 'desc')
            ->select()
            ->toArray();

        $data = [
            'total' => count($rows),
            'data' => $rows,
        ];

        return json([
            'code' => 0,
            'msg' => '获取成功',
            'data' => [
                'data' => $data,
            ],
        ]);
    }
    
    /**
     * 删除充值记录
     */
    public function payDel()
    {
        $ids = $this->request->post('id', '');
        
        if (empty($ids)) {
            return $this->jsonError('请选择要删除的记录');
        }
        
        $idArr = explode(',', $ids);
        
        try {
            Recharge::whereIn('id', $idArr)->delete();
            return $this->jsonSuccess('删除成功');
        } catch (\Exception $e) {
            Log::error('admin delete recharge record failed', ['error' => $e->getMessage()]);
            return $this->jsonError('删除失败');
        }
    }
    
    /**
     * 删除调用记录
     */
    public function userDel()
    {
        $ids = $this->request->post('id', '');
        
        if (empty($ids)) {
            return $this->jsonError('请选择要删除的记录');
        }
        
        $idArr = explode(',', $ids);
        
        try {
            Userlogs::whereIn('id', $idArr)->delete();
            return $this->jsonSuccess('删除成功');
        } catch (\Exception $e) {
            Log::error('admin delete user logs failed', ['error' => $e->getMessage()]);
            return $this->jsonError('删除失败');
        }
    }
    
    /**
     * 清空调用记录
     */
    public function userClear()
    {
        try {
            Userlogs::where('id', '>', 0)->delete();
            return $this->jsonSuccess('清空成功');
        } catch (\Exception $e) {
            Log::error('admin clear user logs failed', ['error' => $e->getMessage()]);
            return $this->jsonError('清空失败');
        }
    }

    /**
     * 构建趋势数据
     */
    protected function buildTrendData(float|int $current, float|int $previous): array
    {
        $difference = round($current - $previous, 2);

        if ((float) $previous === 0.0) {
            if ((float) $current === 0.0) {
                $percentage = 0;
                $type = '持平';
            } else {
                $percentage = 100;
                $type = '上升';
            }
        } else {
            $percentage = round(abs($difference) * 100 / abs((float) $previous), 2);
            if ($difference > 0) {
                $type = '上升';
            } elseif ($difference < 0) {
                $type = '下降';
            } else {
                $type = '持平';
            }
        }

        return [
            'type' => $type,
            'percentage' => $percentage,
            'difference' => $difference,
            'current' => round((float) $current, 2),
            'previous' => round((float) $previous, 2),
        ];
    }
}
