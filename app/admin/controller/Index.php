<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\AdminController;
use app\common\model\Admin;
use app\common\model\Users;
use app\common\model\Userlogs;
use app\common\model\Recharge;
use app\common\model\News;
use app\common\model\Reurl;
use think\facade\Session;
use think\facade\View;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;

/**
 * 后台首页控制器
 */
class Index extends AdminController
{
    /**
     * 后台首页框架
     */
    public function index()
    {
        // 获取管理员信息
        $adminId = Session::get('admin_id');
        $adminInfo = Admin::find($adminId);
        
        View::assign('adminInfo', $adminInfo);
        View::assign('user', $adminInfo);
        
        return view('index/index');
    }
    
    /**
     * 后台主页统计数据
     */
    public function main()
    {
        $adminId = Session::get('admin_id');
        $admin = Admin::find($adminId);
        
        // 今日日期
        $today = date('Y-m-d');
        $todayStart = $today . ' 00:00:00';
        $todayEnd = $today . ' 23:59:59';
        $todayStartTimestamp = strtotime($todayStart);
        $todayEndTimestamp = strtotime($todayEnd);

        // 统计数据
        $array = [];

        // 今日调用次数
        $array['todayuse'] = Userlogs::where('intime', 'between', [$todayStart, $todayEnd])->count();

        // 总调用次数
        $array['usecount'] = Userlogs::count();

        // 今日销售额
        $array['todaymoney'] = Recharge::where('status', 1)
            ->where('intime', 'between', [$todayStartTimestamp, $todayEndTimestamp])
            ->sum('money') ?: 0;

        // 总销售额
        $array['czmoney'] = Recharge::where('status', 1)->sum('money') ?: 0;
        
        // 代理总数
        $array['agent'] = Admin::where('auth', '<>', '超级管理员')->count();
        
        // 替换资源数量
        $array['unmm'] = Reurl::count();
        
        // 待审核资源
        $array['examine'] = Reurl::where('examine', 0)->count();
        
        // 获取公告列表
        $new = News::order('id', 'desc')->limit(5)->select();
        
        View::assign('array', $array);
        View::assign('new', $new);
        View::assign('user', $admin);
        
        return view('index/main');
    }
    
    /**
     * 获取公告详情
     */
    public function ver()
    {
        $uid = $this->request->param('uid', 0, 'intval');
        
        if (!$uid) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }
        
        $info = News::find($uid);
        
        if (!$info) {
            return json(['code' => 0, 'msg' => '公告不存在']);
        }
        
        return json(['code' => 1, 'msg' => '获取成功', 'data' => $info]);
    }
    
    /**
     * 修改密码页面
     */
    public function editPassword()
    {
        return view('index/edit_password');
    }
    
    /**
     * 保存密码修改
     */
    public function savePassword()
    {
        if (!$this->request->isPost()) {
            return $this->jsonError('请求方式错误');
        }
        
        $oldPassword = $this->request->post('oldPassword', '');
        $newPassword = $this->request->post('newPassword', '');
        $confirmPassword = $this->request->post('confirmPassword', '');
        
        if (empty($oldPassword)) {
            return $this->jsonError('请输入原密码');
        }
        
        if (empty($newPassword)) {
            return $this->jsonError('请输入新密码');
        }
        
        if ($newPassword !== $confirmPassword) {
            return $this->jsonError('两次输入的密码不一致');
        }
        
        if (strlen($newPassword) < 6) {
            return $this->jsonError('密码长度不能小于6位');
        }
        
        $adminId = Session::get('admin_id');
        $admin = Admin::find($adminId);
        
        // 验证原密码
        if (!secure_password_verify((string)$oldPassword, (string)$admin->password)) {
            return $this->jsonError('原密码错误');
        }
        
        // 更新密码
        $admin->password = secure_password_hash((string)$newPassword);
        $admin->save();
        
        return $this->jsonSuccess('密码修改成功');
    }
    
    public function editPasswords()
    {
        return $this->savePassword();
    }

    /**
     * 退出登录
     */
    public function logOut()
    {
        Session::delete('admin_id');
        Session::delete('admin_name');
        Session::delete('admin_auth');
        
        return $this->jsonSuccess('退出成功');
    }
    
    /**
     * 清除缓存
     */
    public function cache()
    {
        try {
            Cache::clear();
            
            // 清除runtime缓存目录
            $cachePath = app()->getRuntimePath() . 'cache';
            if (is_dir($cachePath)) {
                $this->delDir($cachePath);
            }
            
            return $this->jsonSuccess('清除缓存成功');
        } catch (\Exception $e) {
            Log::error('clear cache failed', ['error' => $e->getMessage()]);
            return $this->jsonError('清除缓存失败');
        }
    }
    
    /**
     * 递归删除目录
     */
    private function delDir($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->delDir($path);
            } else {
                @unlink($path);
            }
        }
        
        @rmdir($dir);
    }
}
