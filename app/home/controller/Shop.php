<?php

declare(strict_types=1);

namespace app\home\controller;

use app\HomeController;
use app\common\model\Setting;
use think\facade\Db;
use think\facade\View;
use think\Request;

/**
 * 前台套餐控制器
 * 处理解析套餐和采集套餐购买
 */
class Shop extends HomeController
{
    /**
     * 解析套餐列表页面
     */
    public function index(Request $request)
    {
        $user = $this->getUser();
        if (!$user) {
            return redirect((string)url('/index/login'));
        }

        $uid = $user['uid'];
        $way = $user['way'] ?? '包点';
        
        // 获取套餐列表
        $shops = Db::name('shop')->where('fs', $way)->select()->toArray();
        $cmsapis = Db::name('cmsapi')
            ->field('cmsapi_id,cmsapi_name,cmsapi_type')
            ->select()
            ->toArray();

        // 生成套餐HTML
        $html = '';
        foreach ($shops as $shop) {
            // 获取赠送的采集接口
            $cmsapiGifts = [];
            foreach ($cmsapis as $cms) {
                $giftShopIds = array_filter(array_map('trim', explode(',', (string)($cms['cmsapi_type'] ?? ''))));
                if (in_array((string)$shop['id'], $giftShopIds, true)) {
                    $cmsapiGifts[] = [
                        'cmsapi_id' => $cms['cmsapi_id'],
                        'cmsapi_name' => $cms['cmsapi_name']
                    ];
                }
            }
            
            $giftJson = htmlspecialchars(json_encode($cmsapiGifts), ENT_QUOTES);
            $giftCount = count($cmsapiGifts);
            
            $html .= '<div class="package-card p-6">';
            $html .= '<div class="flex items-center justify-between mb-4">';
            $html .= '<h3 class="text-lg font-bold text-gray-800">' . htmlspecialchars($shop['name']) . '</h3>';
            $html .= '<span class="badge bg-primary/10 text-primary">ID: ' . $shop['id'] . '</span>';
            $html .= '</div>';
            
            $html .= '<div class="space-y-2 mb-4 text-sm text-gray-600">';
            $html .= '<p><i class="fa fa-money text-primary mr-2"></i>价格：<span class="font-bold text-primary">' . $shop['price'] . ' 元</span></p>';
            
            if ($way == '包点') {
                $html .= '<p><i class="fa fa-cube text-primary mr-2"></i>点数：' . $shop['dd'] . ' 点</p>';
            } else {
                $html .= '<p><i class="fa fa-calendar text-primary mr-2"></i>时长：' . $shop['dd'] . ' 天</p>';
                $html .= '<p><i class="fa fa-line-chart text-primary mr-2"></i>日上限：' . $shop['fullnum'] . ' 次</p>';
            }
            
            $html .= '<p><i class="fa fa-info-circle text-primary mr-2"></i>' . htmlspecialchars($shop['content']) . '</p>';
            $html .= '</div>';
            
            if ($giftCount > 0) {
                $html .= '<div class="mb-4">';
                $html .= '<span class="inline-flex items-center px-2 py-1 bg-green-100 text-green-700 rounded text-xs cursor-pointer" onclick=\'showAllCmsapiNames(' . $shop['id'] . ',' . $giftJson . ')\'>';
                $html .= '<i class="fa fa-gift mr-1"></i>赠送 ' . $giftCount . ' 个采集接口';
                $html .= '</span>';
                $html .= '</div>';
            }
            
            $html .= '<button onclick="confirmPurchase(' . $shop['id'] . ', \'' . addslashes($shop['name']) . '\', ' . $shop['price'] . ')" ';
            $html .= 'class="btn-primary w-full flex items-center justify-center">';
            $html .= '<i class="fa fa-shopping-cart mr-2"></i>立即购买';
            $html .= '</button>';
            $html .= '</div>';
        }
        
        View::assign('user', $user);
        View::assign('html', $html);
        return View::fetch('user/inside1/shop/index');
    }
    
    /**
     * 采集套餐列表页面
     */
    public function cms(Request $request)
    {
        $user = $this->getUser();
        if (!$user) {
            return redirect((string)url('/index/login'));
        }

        $uid = $user['uid'];
        
        // 获取采集接口列表
        $cmsapis = Db::name('cmsapi')->where('cmsapi_auth', 1)->select();
        
        // 生成采集套餐HTML
        $html = '';
        foreach ($cmsapis as $cms) {
            $html .= '<div class="package-card p-6">';
            $html .= '<div class="flex items-center justify-between mb-4">';
            $html .= '<h3 class="text-lg font-bold text-gray-800">' . htmlspecialchars($cms['cmsapi_name']) . '</h3>';
            $html .= '<span class="badge bg-primary/10 text-primary">ID: ' . $cms['cmsapi_id'] . '</span>';
            $html .= '</div>';
            
            $html .= '<div class="grid grid-cols-2 gap-4 mb-4 text-sm">';
            $html .= '<div class="p-3 bg-gray-50 rounded-lg">';
            $html .= '<p class="text-gray-500">月套餐</p>';
            $html .= '<p class="font-bold text-primary">' . $cms['cmsapi_price_month'] . ' 元/' . $cms['cmsapi_duration_month'] . '天</p>';
            $html .= '</div>';
            $html .= '<div class="p-3 bg-gray-50 rounded-lg">';
            $html .= '<p class="text-gray-500">季套餐</p>';
            $html .= '<p class="font-bold text-primary">' . $cms['cmsapi_price_quarter'] . ' 元/' . $cms['cmsapi_duration_quarter'] . '天</p>';
            $html .= '</div>';
            $html .= '<div class="p-3 bg-gray-50 rounded-lg">';
            $html .= '<p class="text-gray-500">半年套餐</p>';
            $html .= '<p class="font-bold text-primary">' . $cms['cmsapi_price_half_year'] . ' 元/' . $cms['cmsapi_duration_half_year'] . '天</p>';
            $html .= '</div>';
            $html .= '<div class="p-3 bg-gray-50 rounded-lg">';
            $html .= '<p class="text-gray-500">年套餐</p>';
            $html .= '<p class="font-bold text-primary">' . $cms['cmsapi_price_year'] . ' 元/' . $cms['cmsapi_duration_year'] . '天</p>';
            $html .= '</div>';
            $html .= '</div>';
            
            if (!empty($cms['cmsapi_remark'])) {
                $html .= '<p class="text-sm text-gray-500 mb-4"><i class="fa fa-info-circle mr-1"></i>' . htmlspecialchars($cms['cmsapi_remark']) . '</p>';
            }
            
            $html .= '<button onclick="showPackageOptions(' . $cms['cmsapi_id'] . ', \'' . addslashes($cms['cmsapi_name']) . '\', ';
            $html .= $cms['cmsapi_price_month'] . ', ' . $cms['cmsapi_price_quarter'] . ', ';
            $html .= $cms['cmsapi_price_half_year'] . ', ' . $cms['cmsapi_price_year'] . ', ';
            $html .= $cms['cmsapi_duration_month'] . ', ' . $cms['cmsapi_duration_quarter'] . ', ';
            $html .= $cms['cmsapi_duration_half_year'] . ', ' . $cms['cmsapi_duration_year'] . ')" ';
            $html .= 'class="btn-primary w-full flex items-center justify-center">';
            $html .= '<i class="fa fa-shopping-cart mr-2"></i>选择套餐购买';
            $html .= '</button>';
            $html .= '</div>';
        }
        
        View::assign('user', $user);
        View::assign('html', $html);
        return View::fetch('user/inside1/shop/cms');
    }

    /**
     * 解析套餐购买记录页面
     */
    public function log(Request $request)
    {
        $user = $this->getUser();
        if (!$user) {
            return redirect((string)url('/index/login'));
        }

        View::assign('user', $user);
        return View::fetch('user/inside1/shop/log');
    }

    /**
     * 采集套餐购买记录页面
     */
    public function cjlog(Request $request)
    {
        $user = $this->getUser();
        if (!$user) {
            return redirect((string)url('/index/login'));
        }

        View::assign('user', $user);
        return View::fetch('user/inside1/shop/cjlog');
    }
    
    /**
     * 购买解析套餐
     * POST /shop/purchase
     */
    public function purchase(Request $request)
    {
        $uid = $this->getCurrentUserUid();
        if ($uid === '') {
            return json(['code' => 1, 'msg' => '请先登录']);
        }
        
        $shopId = (int) $request->param('id', 0);
        
        if ($shopId <= 0) {
            return json(['code' => 1, 'msg' => '套餐ID不正确']);
        }
        
        // 获取套餐信息
        $shop = Db::name('shop')->where('id', $shopId)->find();
        if (!$shop) {
            return json(['code' => 1, 'msg' => '套餐不存在']);
        }

        $price = (float)($shop['price'] ?? 0);
        if ($price <= 0) {
            return json(['code' => 1, 'msg' => '套餐价格配置异常']);
        }
        
        // 事务+行锁防止余额竞态
        Db::startTrans();
        try {
            // 在事务内加行锁查询用户，防止并发扣款
            $user = Db::name('user')->where('uid', $uid)->lock(true)->find();
            if (!$user) {
                Db::rollback();
                return json(['code' => 1, 'msg' => '用户不存在']);
            }
            
            if ($shop['fs'] != $user['way']) {
                Db::rollback();
                return json(['code' => 1, 'msg' => '套餐类型不匹配，请切换账户类型']);
            }
            
            if ((float)$user['money'] < $price) {
                Db::rollback();
                return json(['code' => 1, 'msg' => '余额不足，请先充值']);
            }
            
            // 扣除余额
            Db::name('user')->where('uid', $uid)->dec('money', $price)->update();
            
            // 根据套餐类型更新用户数据
            if ($user['way'] == '包点') {
                // 包点模式：增加点数
                Db::name('user')->where('uid', $uid)->inc('points', (int)$shop['dd'])->update();
            } else {
                // 包月模式：增加有效期
                $currentBytime = $user['bytime'];
                $days = (int)$shop['dd'];

                if (empty($currentBytime) || strtotime($currentBytime) < time()) {
                    // 当前无有效期或已过期，从今天开始计算
                    $newBytime = date('Y-m-d', strtotime("+{$days} days"));
                } else {
                    // 当前有有效期，在此基础上叠加
                    $newBytime = date('Y-m-d', strtotime($currentBytime . " +{$days} days"));
                }

                $updateData = ['bytime' => $newBytime];

                // 更新日上限（取最大值或替换）
                $fullnum = (int)$shop['fullnum'];
                if ($fullnum > 0) {
                    $updateData['fullnum'] = $fullnum;
                    $updateData['daily_limit'] = $fullnum;
                }

                Db::name('user')->where('uid', $uid)->update($updateData);
            }

            // 如果套餐开启了绑定解析接口，则将绑定关系写入用户表
            if ((int)($shop['bind_enable'] ?? 0) === 1 && !empty($shop['bind_json_ids'])) {
                $existingBindIds = (string)($user['bind_json_ids'] ?? '');
                $shopBindIds = trim((string)$shop['bind_json_ids']);
                if ($existingBindIds !== '') {
                    $merged = array_unique(array_merge(
                        array_filter(explode(',', $existingBindIds)),
                        array_filter(explode(',', $shopBindIds))
                    ));
                    $newBindIds = implode(',', $merged);
                } else {
                    $newBindIds = $shopBindIds;
                }
                Db::name('user')->where('uid', $uid)->update(['bind_json_ids' => $newBindIds]);
            }
            
            // 处理赠送的采集接口（使用 FIND_IN_SET 精确匹配，避免 LIKE 误匹配）
            $cmsapiList = Db::name('cmsapi')
                ->whereRaw("FIND_IN_SET(?, cmsapi_type)", [$shopId])
                ->select();
            
            foreach ($cmsapiList as $cms) {
                $freetime = (int)($cms['cmsapi_freetime'] ?? 30);
                $endTime = date('Y-m-d H:i:s', strtotime("+{$freetime} days"));
                
                // 检查是否已有该采集套餐
                $existShopTime = Db::name('shop_time')
                    ->where('uid', $uid)
                    ->where('cmsapi_id', $cms['cmsapi_id'])
                    ->find();
                
                if ($existShopTime) {
                    // 延长有效期
                    $currentEndTime = $existShopTime['end_time'];
                    if (strtotime($currentEndTime) > time()) {
                        $newEndTime = date('Y-m-d H:i:s', strtotime($currentEndTime . " +{$freetime} days"));
                    } else {
                        $newEndTime = $endTime;
                    }
                    Db::name('shop_time')->where('id', $existShopTime['id'])->update([
                        'end_time' => $newEndTime
                    ]);
                } else {
                    // 新增采集套餐
                    Db::name('shop_time')->insert([
                        'uid' => $uid,
                        'cmsapi_id' => $cms['cmsapi_id'],
                        'cmsapi_name' => $cms['cmsapi_name'],
                        'intime' => date('Y-m-d H:i:s'),
                        'end_time' => $endTime,
                        'cj_auth_ip' => $user['cj_auth_ip'] ?? ''
                    ]);
                }
            }
            
            // 记录购买日志
            Db::name('shop_log')->insert([
                'uid' => $uid,
                'shop_id' => $shopId,
                'shop_name' => $shop['name'],
                'price' => $price,
                'time' => date('Y-m-d H:i:s')
            ]);
            
            Db::commit();
            return json(['code' => 0, 'msg' => '购买成功']);
            
        } catch (\Exception $e) {
            Db::rollback();
            \think\facade\Log::error('购买套餐失败: ' . $e->getMessage());
            return json(['code' => 1, 'msg' => '购买失败，请稍后重试']);
        }
    }
    
    /**
     * 购买采集套餐
     * POST /shop/buy
     */
    public function buy(Request $request)
    {
        $uid = $this->getCurrentUserUid();
        if ($uid === '') {
            return json(['code' => 1, 'msg' => '请先登录']);
        }
        
        $cmsapiId = (int) $request->param('id', 0);
        $type = $request->param('type', 'month');
        
        if ($cmsapiId <= 0) {
            return json(['code' => 1, 'msg' => '采集接口ID不正确']);
        }
        
        // 获取采集接口信息
        $cmsapi = Db::name('cmsapi')->where('cmsapi_id', $cmsapiId)->find();
        if (!$cmsapi) {
            return json(['code' => 1, 'msg' => '采集接口不存在']);
        }
        
        // 根据类型获取价格和时长
        $priceMap = [
            'month' => ['price' => 'cmsapi_price_month', 'duration' => 'cmsapi_duration_month'],
            'quarter' => ['price' => 'cmsapi_price_quarter', 'duration' => 'cmsapi_duration_quarter'],
            'half_year' => ['price' => 'cmsapi_price_half_year', 'duration' => 'cmsapi_duration_half_year'],
            'year' => ['price' => 'cmsapi_price_year', 'duration' => 'cmsapi_duration_year'],
        ];
        
        if (!isset($priceMap[$type])) {
            return json(['code' => 1, 'msg' => '套餐类型不正确']);
        }
        
        $price = (float)$cmsapi[$priceMap[$type]['price']];
        $duration = (int)$cmsapi[$priceMap[$type]['duration']];
        
        if ($price <= 0 || $duration <= 0) {
            return json(['code' => 1, 'msg' => '该套餐类型未开放']);
        }
        
        // 事务+行锁防止余额竞态
        Db::startTrans();
        try {
            $user = Db::name('user')->where('uid', $uid)->lock(true)->find();
            if (!$user) {
                Db::rollback();
                return json(['code' => 1, 'msg' => '用户不存在']);
            }
            
            if ($user['money'] < $price) {
                Db::rollback();
                return json(['code' => 1, 'msg' => '余额不足，请先充值']);
            }
            
            // 扣除余额
            Db::name('user')->where('uid', $uid)->dec('money', $price)->update();
            
            // 计算到期时间
            $endTime = date('Y-m-d H:i:s', strtotime("+{$duration} days"));
            
            // 检查是否已有该采集套餐
            $existShopTime = Db::name('shop_time')
                ->where('uid', $uid)
                ->where('cmsapi_id', $cmsapiId)
                ->find();
            
            if ($existShopTime) {
                // 延长有效期
                $currentEndTime = $existShopTime['end_time'];
                if (strtotime($currentEndTime) > time()) {
                    $newEndTime = date('Y-m-d H:i:s', strtotime($currentEndTime . " +{$duration} days"));
                } else {
                    $newEndTime = $endTime;
                }
                Db::name('shop_time')->where('id', $existShopTime['id'])->update([
                    'end_time' => $newEndTime
                ]);
            } else {
                // 新增采集套餐
                Db::name('shop_time')->insert([
                    'uid' => $uid,
                    'cmsapi_id' => $cmsapiId,
                    'cmsapi_name' => $cmsapi['cmsapi_name'],
                    'intime' => date('Y-m-d H:i:s'),
                    'end_time' => $endTime,
                    'cj_auth_ip' => $user['cj_auth_ip'] ?? ''
                ]);
            }
            
            // 记录购买日志
            Db::name('cmsapi_log')->insert([
                'uid' => $uid,
                'cmsapi_id' => $cmsapiId,
                'cmsapi_name' => $cmsapi['cmsapi_name'],
                'type' => $type,
                'price' => $price,
                'duration' => $duration,
                'time' => date('Y-m-d H:i:s')
            ]);
            
            Db::commit();
            return json(['code' => 0, 'msg' => '购买成功，有效期' . $duration . '天']);
            
        } catch (\Exception $e) {
            Db::rollback();
            \think\facade\Log::error('购买采集套餐失败: ' . $e->getMessage());
            return json(['code' => 1, 'msg' => '购买失败，请稍后重试']);
        }
    }
}
