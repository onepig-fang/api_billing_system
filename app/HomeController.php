<?php

declare(strict_types=1);

namespace app;

use app\common\library\Auth;
use app\common\model\Users;
use app\common\model\Setting;
use think\facade\Session;
use think\facade\View;

/**
 * 前台基础控制器
 */
class HomeController extends BaseController
{
    /**
     * 当前登录用户
     */
    protected $user = null;
    
    /**
     * Auth实例
     */
    protected $auth = null;
    
    /**
     * 网站配置
     */
    protected $config = null;
    
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
        
        // 获取Auth实例
        $this->auth = Auth::instance();
        
        // 获取网站配置
        $this->config = Setting::find(1);
        
        // 设置全局模板变量
        View::assign('config', $this->config);
        
        // 检查是否需要登录
        $action = strtolower($this->request->action());
        if (!in_array($action, array_map('strtolower', $this->noNeedLogin))) {
            $this->checkLogin();
        } else {
            // 即使不需要登录也尝试获取用户信息
            $this->tryGetUser();
        }
        
        // 设置用户模板变量
        if ($this->user) {
            View::assign('user', $this->user);
            View::assign('isLogin', true);
        } else {
            View::assign('isLogin', false);
        }
    }
    
    /**
     * 检查用户登录
     */
    protected function checkLogin()
    {
        $userId = session('user_id');
        
        if (!$userId) {
            if ($this->request->isAjax()) {
                throw new \think\exception\HttpResponseException(json(['code' => 401, 'msg' => '请先登录']));
            }
            $this->redirect(url('/index/login'));
        }
        
        $this->user = Users::find($userId);
        
        if (!$this->user) {
            Session::delete('user_id');
            Session::delete('user_uid');
            Session::delete('user_name');
            session('user', null);
            if ($this->request->isAjax()) {
                throw new \think\exception\HttpResponseException(json(['code' => 401, 'msg' => '登录已过期']));
            }
            $this->redirect(url('/index/login'));
        }
        
        // 检查账号状态
        if ($this->user->state != 1) {
            Session::delete('user_id');
            Session::delete('user_uid');
            Session::delete('user_name');
            session('user', null);
            if ($this->request->isAjax()) {
                throw new \think\exception\HttpResponseException(json(['code' => 403, 'msg' => '账号已被封禁']));
            }
            $this->redirect(url('/index/login'));
        }
    }
    
    /**
     * 尝试获取用户信息（不强制登录）
     */
    protected function tryGetUser()
    {
        $userId = session('user_id');
        if ($userId) {
            $this->user = Users::find($userId);
            if ($this->user && $this->user->state != 1) {
                $this->user = null;
                Session::delete('user_id');
                session('user', null);
            }
        }
    }
    
    /**
     * 获取当前用户
     */
    protected function getUser()
    {
        return $this->user;
    }

    /**
     * 获取当前登录用户ID（兼容历史session键）
     */
    protected function getCurrentUserId(): int
    {
        $userId = (int) session('user_id');
        if ($userId > 0) {
            return $userId;
        }

        if ($this->user) {
            return (int) ($this->user['id'] ?? $this->user->id ?? 0);
        }

        $sessionUser = session('user');
        if (is_array($sessionUser) && !empty($sessionUser['id'])) {
            return (int) $sessionUser['id'];
        }

        return (int) session('user.id');
    }

    /**
     * 获取当前登录用户UID（兼容历史session键）
     */
    protected function getCurrentUserUid(): string
    {
        $uid = trim((string) session('user_uid'));
        if ($uid !== '') {
            return $uid;
        }

        if ($this->user) {
            $uid = trim((string) ($this->user['uid'] ?? $this->user->uid ?? ''));
            if ($uid !== '') {
                return $uid;
            }
        }

        $sessionUser = session('user');
        if (is_array($sessionUser) && !empty($sessionUser['uid'])) {
            return trim((string) $sessionUser['uid']);
        }

        $uid = trim((string) session('user.uid'));
        if ($uid !== '') {
            return $uid;
        }

        return '';
    }

    /**
     * 获取网站配置
     */
    protected function getConfig()
    {
        return $this->config;
    }
    
    /**
     * 检查用户套餐是否有效
     */
    protected function checkPackage(): array
    {
        if (!$this->user) {
            return ['valid' => false, 'msg' => '请先登录'];
        }

        return $this->auth->checkPackage($this->user->id);
    }
}
