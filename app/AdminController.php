<?php

declare(strict_types=1);

namespace app;

use app\common\model\Admin;
use app\common\model\Setting;
use think\facade\Session;
use think\facade\View;

/**
 * 后台基础控制器
 */
class AdminController extends BaseController
{
    /**
     * 当前管理员信息
     */
    protected $admin = null;
    
    /**
     * 无需登录的方法
     */
    protected $noNeedLogin = [];
    
    /**
     * 初始化
     */
    protected function initialize()
    {
        parent::initialize();
        
        // 检查是否需要登录
        $action = strtolower($this->request->action());
        if (!in_array($action, array_map('strtolower', $this->noNeedLogin))) {
            $this->checkLogin();
        }
        
        // 设置模板变量
        if ($this->admin) {
            View::assign('user', $this->admin);
            View::assign('adminInfo', [
                'nickname' => $this->admin['name'] ?? $this->admin['username'],
                'avatar' => ''
            ]);
        }
    }
    
    /**
     * 检查管理员登录
     */
    protected function checkLogin()
    {
        $adminId = Session::get('admin_id');
        
        if (!$adminId) {
            if ($this->request->isAjax()) {
                throw new \think\exception\HttpResponseException(json(['code' => 401, 'msg' => '请先登录']));
            }
            $this->redirect(url('/Login/index'));
        }
        
        $this->admin = Admin::find($adminId);
        
        if (!$this->admin) {
            Session::delete('admin_id');
            if ($this->request->isAjax()) {
                throw new \think\exception\HttpResponseException(json(['code' => 401, 'msg' => '登录已过期']));
            }
            $this->redirect(url('/Login/index'));
        }
        
        // 检查账号状态
        if ($this->admin['ban'] == 1) {
            Session::delete('admin_id');
            if ($this->request->isAjax()) {
                throw new \think\exception\HttpResponseException(json(['code' => 403, 'msg' => '账号已被封禁']));
            }
            $this->redirect(url('/Login/index'));
        }
    }
    
    /**
     * 获取当前管理员
     */
    protected function getAdmin()
    {
        return $this->admin;
    }
    
    /**
     * 检查是否为超级管理员
     */
    protected function isSuperAdmin(): bool
    {
        return $this->admin && $this->admin['auth'] === '超级管理员';
    }

    /**
     * 获取当前管理员ID
     */
    protected function getCurrentAdminId(): int
    {
        if ($this->admin && !empty($this->admin['id'])) {
            return (int) $this->admin['id'];
        }

        return (int) Session::get('admin_id', 0);
    }

    /**
     * 是否为代理管理员
     */
    protected function isAgentAdmin(): bool
    {
        if (!$this->admin) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return false;
        }

        $auth = $this->admin['auth'] ?? '';
        if (is_numeric($auth)) {
            return (int) $auth === 2;
        }

        return trim((string) $auth) === '代理';
    }
    
    /**
     * 检查权限
     */
    protected function checkAuth(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (!$this->admin) {
            return false;
        }

        switch ($permission) {
            case 'json':
                return $this->admin['json'] == 1;
            case 'shop':
                return $this->admin['shop'] == 1;
            case 'pay':
                return $this->admin['pay'] == 1;
            default:
                return false;
        }
    }
}
