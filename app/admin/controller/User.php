<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\AdminController;
use app\common\model\Users;
use app\common\model\Admin;
use think\facade\Db;
use think\facade\Log;
use think\facade\Session;
use think\facade\View;

/**
 * 用户管理控制器
 */
class User extends AdminController
{
    /**
     * 用户列表页面
     */
    public function index()
    {
        return view('user/index');
    }
    
    /**
     * 获取用户列表数据
     */
    public function list()
    {
        $page = $this->request->post('current_page', 1, 'intval');
        $limit = $this->request->post('limit', 15, 'intval');
        $key = $this->request->post('key', '');
        $type = $this->request->post('type', '');
        $consumption = $this->request->post('consumption', '');
        $sjuser = $this->request->param('sjuser', '', 'intval');

        $adminId = Session::get('admin_id');
        $admin = Admin::find($adminId);
        
        $query = Users::alias('u');
        
        // 如果是代理，只显示自己的用户
        if ($admin->auth !== '超级管理员') {
            $query->where('u.sjuser', $admin->id);
        } elseif ($sjuser > 0) {
            $query->where('u.sjuser', $sjuser);
        }

        // 搜索条件
        if (!empty($key)) {
            switch ($type) {
                case '1': // 用户名
                    $query->where('u.user', 'like', "%{$key}%");
                    break;
                case '2': // QQ
                    $query->where('u.qq', 'like', "%{$key}%");
                    break;
                default:
                    $query->where('u.user|u.uid|u.email', 'like', "%{$key}%");
            }
        }
        
        // 排序条件
        if ($consumption == '1') {
            $query->order('u.money', 'desc');
        } elseif ($consumption == '2') {
            $query->order('u.daynum', 'desc');
        } else {
            $query->order('u.id', 'desc');
        }
        
        $list = $query->paginate([
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
     * 用户详情页面
     */
    public function details()
    {
        $id = $this->request->param('id', 0, 'intval');
        
        $user = null;
        if ($id) {
            $user = $this->checkUserOwnership($id);
        }
        
        View::assign('shop', $user);
        return view('user/details');
    }
    
    /**
     * 保存用户信息
     */
    public function edit()
    {
        if (!$this->request->isPost()) {
            return $this->jsonError('请求方式错误');
        }
        
        $id = $this->request->post('id', 0, 'intval');
        $data = $this->request->post();
        
        // 移除不需要的字段
        unset($data['file']);
        
        // 允许修改的字段白名单
        $allowFields = ['user', 'pass', 'email', 'qq', 'state', 'way', 'client_name',
                        'money', 'points', 'bytime', 'daily_limit', 'fullnum',
                        'my', 'you_index', 'you_href', 'auth_ip', 'cj_auth_ip', 'referer', 'player',
                        'img', 'logo', 'button', 'dmkupass', 'dmfirst', 'defense', 'byjx'];
        
        try {
            if ($id) {
                // 更新用户（含权限校验）
                $user = $this->checkUserOwnership($id);
                if (!$user) {
                    return $this->jsonError('用户不存在或无权操作');
                }
                
                if (!empty($data['pass'])) {
                    $data['pass'] = secure_password_hash((string)$data['pass']);
                } else {
                    unset($data['pass']);
                }
                
                foreach ($data as $key => $value) {
                    if ($key !== 'id' && in_array($key, $allowFields)) {
                        $user->$key = $value;
                    }
                }
                $user->save();
            } else {
                // 新增用户
                if (empty($data['user'])) {
                    return $this->jsonError('请输入用户名');
                }
                if (empty($data['pass'])) {
                    return $this->jsonError('请输入密码');
                }
                if (empty($data['email'])) {
                    return $this->jsonError('请输入邮箱');
                }
                if (!filter_var((string)$data['email'], FILTER_VALIDATE_EMAIL)) {
                    return $this->jsonError('邮箱格式不正确');
                }
                
                // 检查用户名是否存在
                $exists = Users::where('user', $data['user'])->find();
                if ($exists) {
                    return $this->jsonError('用户名已存在');
                }
                $emailExists = Users::where('email', $data['email'])->find();
                if ($emailExists) {
                    return $this->jsonError('邮箱已存在');
                }
                
                // 生成UID
                $data['uid'] = $this->generateUid();
                $data['pass'] = secure_password_hash((string)$data['pass']);
                $data['time'] = date('Y-m-d H:i:s');
                $data['ip'] = $this->request->ip();
                
                // 获取当前管理员作为上级
                $adminId = Session::get('admin_id');
                $admin = Admin::find($adminId);
                if (!$this->isSuperAdmin()) {
                    $data['sjuser'] = $admin->id;
                }
                unset($data['sj']);

                Users::create($data);
            }
            
            return $this->jsonSuccess('保存成功');
        } catch (\Exception $e) {
            Log::error('admin save user failed', ['error' => $e->getMessage()]);
            return $this->jsonError('保存失败');
        }
    }
    
    /**
     * 校验当前管理员是否有权操作该用户
     */
    private function checkUserOwnership(int $userId)
    {
        $user = Users::find($userId);
        if (!$user) {
            return null;
        }
        if (!$this->isSuperAdmin() && (int)$user->sjuser !== $this->getCurrentAdminId()) {
            return null;
        }
        return $user;
    }
    
    /**
     * 生成用户UID
     */
    private function generateUid()
    {
        $maxAttempts = 100;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $uid = random_int(100000, 999999);
            if (!Users::where('uid', $uid)->find()) {
                return (string)$uid;
            }
        }
        return (string)random_int(1000000, 9999999);
    }
    
    /**
     * 删除用户
     */
    public function del()
    {
        $ids = $this->request->post('id', '');
        
        if (empty($ids)) {
            return $this->jsonError('请选择要删除的用户');
        }
        
        $idArr = explode(',', $ids);
        
        try {
            $query = Users::whereIn('id', $idArr);
            if (!$this->isSuperAdmin()) {
                $query->where('sjuser', $this->getCurrentAdminId());
            }
            $query->delete();
            return $this->jsonSuccess('删除成功');
        } catch (\Exception $e) {
            Log::error('admin delete user failed', ['error' => $e->getMessage()]);
            return $this->jsonError('删除失败');
        }
    }
    
    /**
     * 修改用户状态
     */
    public function status()
    {
        $id = $this->request->post('id', 0, 'intval');
        $status = $this->request->post('status', 0, 'intval');
        
        if (!$id) {
            return $this->jsonError('参数错误');
        }
        
        try {
            $user = $this->checkUserOwnership($id);
            if (!$user) {
                return $this->jsonError('用户不存在或无权操作');
            }
            
            $user->state = $status;
            $user->save();
            
            return $this->jsonSuccess('修改成功');
        } catch (\Exception $e) {
            Log::error('admin update user status failed', ['error' => $e->getMessage()]);
            return $this->jsonError('修改失败');
        }
    }
    
    /**
     * 用户余额操作
     */
    public function commission()
    {
        $id = $this->request->post('id', 0, 'intval');
        $money = $this->request->post('money', 0, 'floatval');
        $type = $this->request->post('ttttt', 1, 'intval');
        
        if (!$id) {
            return $this->jsonError('参数错误');
        }
        
        if ($money <= 0) {
            return $this->jsonError('金额必须大于0');
        }
        
        try {
            $user = $this->checkUserOwnership($id);
            if (!$user) {
                return $this->jsonError('用户不存在或无权操作');
            }
            
            if ($type == 1) {
                Db::name('user')->where('id', $id)->inc('money', $money)->update();
            } else {
                $affected = Db::name('user')->where('id', $id)->where('money', '>=', $money)->dec('money', $money)->update();
                if ($affected === 0) {
                    return $this->jsonError('用户余额不足');
                }
            }
            
            return $this->jsonSuccess('操作成功');
        } catch (\Exception $e) {
            Log::error('admin update user commission failed', ['error' => $e->getMessage()]);
            return $this->jsonError('操作失败');
        }
    }
    
    /**
     * 解绑QQ
     */
    public function secureqq()
    {
        $id = $this->request->post('id', 0, 'intval');
        
        if (!$id) {
            return $this->jsonError('参数错误');
        }
        
        try {
            $user = $this->checkUserOwnership($id);
            if (!$user) {
                return $this->jsonError('用户不存在或无权操作');
            }
            
            $user->access_token = null;
            $user->save();
            
            return $this->jsonSuccess('解绑成功');
        } catch (\Exception $e) {
            Log::error('admin unbind qq failed', ['error' => $e->getMessage()]);
            return $this->jsonError('解绑失败');
        }
    }
    
    /**
     * 解绑微信
     */
    public function securewx()
    {
        $id = $this->request->post('id', 0, 'intval');
        
        if (!$id) {
            return $this->jsonError('参数错误');
        }
        
        try {
            $user = $this->checkUserOwnership($id);
            if (!$user) {
                return $this->jsonError('用户不存在或无权操作');
            }
            
            $user->access_token_wx = null;
            $user->save();
            
            return $this->jsonSuccess('解绑成功');
        } catch (\Exception $e) {
            Log::error('admin unbind wx failed', ['error' => $e->getMessage()]);
            return $this->jsonError('解绑失败');
        }
    }
}
