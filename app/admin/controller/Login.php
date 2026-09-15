<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\AdminController;
use app\common\model\Admin;
use think\facade\Session;
use think\facade\View;
use think\facade\Cache;

/**
 * 后台登录控制器
 */
class Login extends AdminController
{
    /**
     * 无需登录的方法
     */
    protected $noNeedLogin = ['index', 'login'];
    
    /**
     * 登录页面
     */
    public function index()
    {
        // 如果已登录，跳转到首页
        if (Session::get('admin_id')) {
            return redirect((string)url('/Index/index'));
        }
        
        return view('login/index');
    }
    
    /**
     * 登录处理
     */
    public function login()
    {
        if (!$this->request->isPost()) {
            return $this->jsonError('请求方式错误');
        }
        
        $username = $this->request->post('username', '');
        $password = $this->request->post('password', '');
        
        // 验证参数
        if (empty($username)) {
            return $this->jsonError('请输入用户名');
        }
        
        if (empty($password)) {
            return $this->jsonError('请输入密码');
        }
        
        // 暴力破解防护：同一 IP 5 次失败后锁定 15 分钟
        $ip = $this->request->ip();
        $failKey = 'admin_login_fail_' . md5($ip);
        $failCount = (int) Cache::get($failKey, 0);
        if ($failCount >= 5) {
            return $this->jsonError('登录失败次数过多，请15分钟后再试');
        }
        
        $admin = Admin::where('username', $username)->find();
        
        if (!$admin) {
            Cache::set($failKey, $failCount + 1, 900);
            return $this->jsonError('用户名或密码错误');
        }
        
        if ($admin->ban == 1) {
            return $this->jsonError('账号已被封禁');
        }
        
        if (!secure_password_verify((string)$password, (string)$admin->password)) {
            Cache::set($failKey, $failCount + 1, 900);
            return $this->jsonError('用户名或密码错误');
        }

        if (secure_password_needs_rehash((string)$admin->password)) {
            $admin->password = secure_password_hash((string)$password);
        }
        
        // 登录成功清除失败计数
        Cache::delete($failKey);

        // 登录成功，更新登录信息
        $admin->login_time = date('Y-m-d H:i:s');
        $admin->login_ip = $this->request->ip();
        $admin->save();

        // 防会话固定：提权前更换会话 ID，传 true 同时销毁旧会话数据
        Session::regenerate(true);

        // 设置session
        Session::set('admin_id', $admin->id);
        Session::set('admin_name', $admin->username);
        Session::set('admin_auth', $admin->auth);
        
        return $this->jsonSuccess('登录成功');
    }
    
    /**
     * 退出登录
     */
    public function logout()
    {
        Session::delete('admin_id');
        Session::delete('admin_name');
        Session::delete('admin_auth');
        
        return $this->jsonSuccess('退出成功');
    }
}
