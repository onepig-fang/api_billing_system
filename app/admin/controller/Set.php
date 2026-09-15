<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\AdminController;
use app\common\model\Setting;
use think\facade\Db;
use think\facade\Cache;
use think\facade\Log;
use think\facade\View;

/**
 * 系统设置控制器
 */
class Set extends AdminController
{
    private $globalAllowFields = [
        'name', 'keyword', 'content', 'ym', 'template', 'inside', 'beian',
        'logo', 'dy', 'jx', 'authip', 'authip_key', 'cjip', 'cj_authip_key',
        'checkqq', 'khd', 'kefuqq', 'qqgroupurl', 'background', 'fdvideo',
        'notice_type', 'notice', 'notices', 'cjdiyurl', 'Json_m3u8',
        'cache', 'cache_time'
    ];

    private $payAllowFields = [
        'epay_api', 'epay_pid', 'epay_key', 'whatpay',
        'public_key', 'private_key', 'appid'
    ];

    private $regAllowFields = [
        'reg_type', 'reg_points', 'reg_captcha', 'reg_email_code',
        'login_captcha', 'invite_reward', 'default_way', 'mailreg',
        'emailHost', 'emailssl', 'emailport', 'emailuser', 'emailpass'
    ];

    private $quickAllowFields = [
        'qqlogin', 'quick_type', 'quick_rainbow_callback',
        'quick_rainbow_appid', 'quick_rainbow_key'
    ];

    private $packAllowFields = [
        'pack_enabled', 'pack_cost', 'pack_api_url',
        'pack_api_key', 'pack_api_appid', 'pack_api_secret'
    ];

    /**
     * 仅超级管理员可操作
     */
    private function requireSuperAdmin()
    {
        if (!$this->isSuperAdmin()) {
            throw new \think\exception\HttpResponseException($this->jsonError('无权操作'));
        }
    }

    /**
     * 读取支付配置
     */
    private function getPayConfigData(): array
    {
        $setting = Setting::find(1);
        return $setting ? $setting->toArray() : [];
    }

    /**
     * 保存支付配置
     */
    private function savePayConfigData(array $data): void
    {
        $filtered = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $this->payAllowFields, true)) {
                $filtered[$key] = $value;
            }
        }

        if (empty($filtered)) {
            return;
        }

        Db::startTrans();
        try {
            $setting = Setting::find(1);
            if ($setting) {
                $setting->save($filtered);
            } else {
                $filtered['id'] = 1;
                Setting::create($filtered);
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 全局配置页面
     */
    public function index()
    {
        $this->requireSuperAdmin();
        $set = Setting::find(1);
        View::assign('set', $set ? $set->toArray() : []);
        return view('set/index');
    }

    /**
     * 保存配置
     */
    public function edit()
    {
        $this->requireSuperAdmin();
        if (!$this->request->isPost()) {
            return $this->jsonError('请求方式错误');
        }

        $data = $this->request->post();
        unset($data['file']);

        try {
            $setting = Setting::find(1);
            if ($setting) {
                foreach ($data as $key => $value) {
                    if (in_array($key, $this->globalAllowFields)) {
                        $setting->$key = $value;
                    }
                }
                $setting->save();
            } else {
                $filtered = array_intersect_key($data, array_flip($this->globalAllowFields));
                $filtered['id'] = 1;
                Setting::create($filtered);
            }
            return $this->jsonSuccess('保存成功');
        } catch (\Exception $e) {
            return $this->jsonError('保存失败');
        }
    }

    /**
     * 支付配置页面
     */
    public function pay()
    {
        $this->requireSuperAdmin();
        View::assign('set', $this->getPayConfigData());
        return view('set/pay');
    }

    /**
     * 保存支付配置
     */
    public function payEdit()
    {
        $this->requireSuperAdmin();
        if (!$this->request->isPost()) {
            return $this->jsonError('请求方式错误');
        }

        $data = $this->request->post();

        try {
            $this->savePayConfigData($data);
            return $this->jsonSuccess('保存成功');
        } catch (\Throwable $e) {
            Log::error('save pay config failed', ['error' => $e->getMessage()]);
            return $this->jsonError('保存失败');
        }
    }

    /**
     * 注册配置页面
     */
    public function red()
    {
        $this->requireSuperAdmin();
        $set = Setting::find(1);
        View::assign('set', $set ? $set->toArray() : []);
        return view('set/red');
    }

    /**
     * 保存注册配置
     */
    public function redEdit()
    {
        $this->requireSuperAdmin();
        if (!$this->request->isPost()) {
            return $this->jsonError('请求方式错误');
        }

        $data = $this->request->post();

        try {
            $setting = Setting::find(1);
            if ($setting) {
                foreach ($data as $key => $value) {
                    if (in_array($key, $this->regAllowFields)) {
                        $setting->$key = $value;
                    }
                }
                $setting->save();
            }
            return $this->jsonSuccess('保存成功');
        } catch (\Exception $e) {
            return $this->jsonError('保存失败');
        }
    }

    /**
     * 快捷登录配置页面
     */
    public function quick()
    {
        $this->requireSuperAdmin();
        $set = Setting::find(1);
        View::assign('set', $set ? $set->toArray() : []);
        return view('set/quick');
    }

    /**
     * 保存快捷登录配置
     */
    public function quickEdit()
    {
        $this->requireSuperAdmin();
        if (!$this->request->isPost()) {
            return $this->jsonError('请求方式错误');
        }

        $data = $this->request->post();

        try {
            $setting = Setting::find(1);
            if ($setting) {
                foreach ($data as $key => $value) {
                    if (in_array($key, $this->quickAllowFields)) {
                        $setting->$key = $value;
                    }
                }
                $setting->save();
            }
            return $this->jsonSuccess('保存成功');
        } catch (\Exception $e) {
            return $this->jsonError('保存失败');
        }
    }

    /**
     * 打包配置页面
     */
    public function pack()
    {
        $this->requireSuperAdmin();
        $set = Setting::find(1);
        $setData = $set ? $set->toArray() : [];

        $cacheData = Cache::get('system_pack_config');
        if (is_array($cacheData)) {
            foreach ($this->packAllowFields as $field) {
                if (!array_key_exists($field, $setData) || $setData[$field] === '' || $setData[$field] === null) {
                    if (array_key_exists($field, $cacheData)) {
                        $setData[$field] = $cacheData[$field];
                    }
                }
            }
        }

        View::assign('set', $setData);
        return view('set/pack');
    }

    /**
     * 保存打包配置
     */
    public function packEdit()
    {
        $this->requireSuperAdmin();
        if (!$this->request->isPost()) {
            return $this->jsonError('请求方式错误');
        }

        $data = $this->request->post();
        $filtered = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $this->packAllowFields, true)) {
                $filtered[$key] = is_string($value) ? trim($value) : $value;
            }
        }

        $filtered['pack_enabled'] = (int)($filtered['pack_enabled'] ?? 0) === 1 ? 1 : 0;
        $filtered['pack_cost'] = max(1, (int)($filtered['pack_cost'] ?? 1));

        if ($filtered['pack_enabled'] === 1 && ($filtered['pack_api_url'] ?? '') === '') {
            return $this->jsonError('已开启打包时，打包API地址不能为空');
        }

        try {
            $setting = Setting::find(1);
            if ($setting) {
                foreach ($filtered as $key => $value) {
                    $setting->$key = $value;
                }
                $setting->save();
            } else {
                $createData = $filtered;
                $createData['id'] = 1;
                Setting::create($createData);
            }
        } catch (\Throwable $e) {
            Log::error('save pack config failed', [
                'error' => $e->getMessage(),
                'data' => $filtered,
            ]);
            return $this->jsonError('保存失败');
        }

        Cache::set('system_pack_config', $filtered, 0);
        if (function_exists('clear_config_cache')) {
            clear_config_cache();
        }

        return $this->jsonSuccess('保存成功');
    }
}
