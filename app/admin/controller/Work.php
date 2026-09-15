<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\AdminController;
use think\facade\Db;
use think\facade\Log;
use think\facade\View;

/**
 * 工单管理控制器
 */
class Work extends AdminController
{
    /**
     * 查询当前管理员可访问的工单
     */
    private function findScopedWork(int $id): ?array
    {
        $query = Db::name('work')->where('id', $id);
        if ($this->isAgentAdmin()) {
            $query->where('admin_id', $this->getCurrentAdminId());
        }

        $work = $query->find();
        return is_array($work) ? $work : null;
    }

    /**
     * 工单列表页面
     */
    public function index()
    {
        return View::fetch();
    }
    
    /**
     * 获取工单列表数据
     */
    public function list()
    {
        $page = input('current_page/d', 1);
        $limit = input('limit/d', 10);

        $adminId = $this->getCurrentAdminId();
        
        $query = Db::name('work')->order('id', 'desc');
        
        // 代理只能查看自己的工单
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
    
    /**
     * 工单详情/编辑页面
     */
    public function details()
    {
        $id = input('id/d', 0);
        
        $work = [];
        $list = [];
        $list_e = [];
        
        if ($id > 0) {
            $work = $this->findScopedWork($id);
            if (!$work) {
                return json(['code' => 1, 'msg' => '工单不存在或无权限']);
            }
            
            // 获取工单回复列表（我方回复）
            $list = Db::name('work_reply')
                ->where('work_id', $id)
                ->where('type', 1)  // 1=管理员回复
                ->order('id', 'asc')
                ->select()
                ->toArray();
            
            // 获取工单回复列表（对方回复）
            $list_e = Db::name('work_reply')
                ->where('work_id', $id)
                ->where('type', 2)  // 2=用户回复
                ->order('id', 'asc')
                ->select()
                ->toArray();
        }
        
        View::assign([
            'id' => $id,
            'work' => $work,
            'list' => $list,
            'list_e' => $list_e
        ]);
        
        return View::fetch();
    }
    
    /**
     * 提交/回复工单
     */
    public function edit()
    {
        $id = input('id/d', 0);
        $title = input('title', '');
        $notice = input('notice', '');
        
        if (empty($title)) {
            return json(['code' => 1, 'msg' => '请输入工单标题']);
        }
        
        if (empty($notice)) {
            return json(['code' => 1, 'msg' => '请输入工单内容']);
        }
        
        // 获取当前管理员ID
        $adminId = $this->getCurrentAdminId();
        
        Db::startTrans();
        try {
            if ($id > 0) {
                if (!$this->findScopedWork($id)) {
                    Db::rollback();
                    return json(['code' => 1, 'msg' => '工单不存在或无权限']);
                }

                // 回复工单
                $replyData = [
                    'work_id' => $id,
                    'admin_id' => $adminId,
                    'details' => $notice,
                    'type' => 1,  // 管理员回复
                    'time' => date('Y-m-d H:i:s')
                ];
                Db::name('work_reply')->insert($replyData);
                
                // 更新工单状态为处理中
                Db::name('work')->where('id', $id)->update([
                    'state' => 1,
                    'update_time' => date('Y-m-d H:i:s')
                ]);
            } else {
                // 新建工单
                $workData = [
                    'admin_id' => $adminId,
                    'name' => $title,
                    'state' => 0,  // 待处理
                    'time' => date('Y-m-d H:i:s')
                ];
                $workId = Db::name('work')->insertGetId($workData);
                
                // 添加工单内容
                $replyData = [
                    'work_id' => $workId,
                    'admin_id' => $adminId,
                    'details' => $notice,
                    'type' => 1,
                    'time' => date('Y-m-d H:i:s')
                ];
                Db::name('work_reply')->insert($replyData);
            }
            
            Db::commit();
            return json(['code' => 0, 'msg' => '提交成功']);
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('工单提交失败', ['error' => $e->getMessage()]);
            return json(['code' => 1, 'msg' => '提交失败，请稍后重试']);
        }
    }
    
    /**
     * 更新工单状态
     */
    public function status()
    {
        $id = input('id/d', 0);
        $state = input('state/d', 0);
        
        if ($id <= 0) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        if (!$this->findScopedWork($id)) {
            return json(['code' => 1, 'msg' => '工单不存在或无权限']);
        }

        $query = Db::name('work')->where('id', $id);
        if ($this->isAgentAdmin()) {
            $query->where('admin_id', $this->getCurrentAdminId());
        }
        
        $result = $query->update([
            'state' => $state,
            'update_time' => date('Y-m-d H:i:s')
        ]);
        
        if ($result !== false) {
            return json(['code' => 0, 'msg' => '更新成功']);
        } else {
            return json(['code' => 1, 'msg' => '更新失败']);
        }
    }
    
    /**
     * 删除工单
     */
    public function del()
    {
        $id = input('id/d', 0);
        
        if ($id <= 0) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        $work = $this->findScopedWork($id);
        if (!$work) {
            return json(['code' => 1, 'msg' => '工单不存在或无权限']);
        }
        
        Db::startTrans();
        try {
            // 删除工单回复
            Db::name('work_reply')->where('work_id', $id)->delete();
            // 删除工单
            $workDelete = Db::name('work')->where('id', $id);
            if ($this->isAgentAdmin()) {
                $workDelete->where('admin_id', $this->getCurrentAdminId());
            }
            $workDelete->delete();
            
            Db::commit();
            return json(['code' => 0, 'msg' => '删除成功']);
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('工单删除失败', ['error' => $e->getMessage()]);
            return json(['code' => 1, 'msg' => '删除失败，请稍后重试']);
        }
    }
}
