<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\AdminController;
use app\common\model\Invite as InviteModel;
use think\facade\View;

/**
 * 邀请管理控制器
 */
class Invite extends AdminController
{
    /**
     * 邀请链接页面
     */
    public function index()
    {
        // 获取当前管理员ID
        $adminId = $this->getCurrentAdminId();
        
        // 生成代理邀请链接（agent=代理 admin.id，注册后归属到 user.sjuser）
        $domain = request()->domain();
        $url = $domain . '/index/register?agent=' . $adminId;
        
        View::assign('url', $url);
        return View::fetch();
    }
    
    /**
     * 邀请列表
     */
    public function list()
    {
        $page = input('current_page/d', 1);
        $limit = input('limit/d', 10);

        $adminId = $this->getCurrentAdminId();
        
        $query = InviteModel::order('id', 'desc');
        
        // 代理只能查看自己的邀请记录
        if ($this->isAgentAdmin()) {
            $query->where('admin_id', $adminId);
        }
        
        $list = $query->paginate([
            'list_rows' => $limit,
            'page' => $page
        ]);
        
        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'data' => $list
            ]
        ]);
    }
}
