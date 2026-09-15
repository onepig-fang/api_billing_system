<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\AdminController;
use app\common\model\Admin;
use app\common\model\Users;
use think\facade\View;
use think\facade\Session;
use think\facade\Db;
use think\facade\Log;

/**
 * 代理管理控制器
 */
class Agent extends AdminController
{
    /**
     * 允许通过编辑接口写入的字段白名单
     * 注意：不包含 auth（身份）与 id，防止代理越权提权
     */
    private const EDITABLE_FIELDS = [
        'username', 'password', 'name', 'zsnum',
        'json', 'shop', 'pay', 'ban',
    ];

    protected function initialize()
    {
        parent::initialize();
        $this->requireSuperAdmin();
    }

    /**
     * 仅允许超级管理员操作代理
     */
    private function requireSuperAdmin(): void
    {
        if (!$this->isSuperAdmin()) {
            throw new \think\exception\HttpResponseException($this->jsonError('无权操作', 403));
        }
    }

    /**
     * 从请求数据中提取白名单字段
     */
    private function filterEditableFields(array $data): array
    {
        return array_intersect_key($data, array_flip(self::EDITABLE_FIELDS));
    }

    /**
     * 代理列表页面
     */
    public function index()
    {
        return view('agent/index');
    }
    
    /**
     * 获取代理列表数据
     */
    public function list()
    {
        $page = $this->request->post('current_page', 1, 'intval');
        $limit = $this->request->post('limit', 15, 'intval');
        $key = $this->request->post('key', '');
        
        $query = Admin::where('auth', '<>', '超级管理员');
        
        if (!empty($key)) {
            $query->where('username|name', 'like', "%{$key}%");
        }
        
        $list = $query->order('id', 'desc')
            ->paginate([
                'page' => $page,
                'list_rows' => $limit
            ]);

        $items = $list->items();
        foreach ($items as $item) {
            $item->downline_count = Users::where('sjuser', $item->id)->count();
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
     * 代理详情页面
     */
    public function details()
    {
        $id = $this->request->param('id', 0, 'intval');
        
        $agent = null;
        if ($id) {
            $agent = Admin::find($id);
        }
        
        View::assign('agent', $agent);
        View::assign('user', $agent);
        return view('agent/details');
    }
    
    /**
     * 保存代理信息
     */
    public function edit()
    {
        if (!$this->request->isPost()) {
            return $this->jsonError('请求方式错误');
        }
        
        $id = $this->request->post('id', 0, 'intval');
        $data = $this->request->post();

        try {
            if ($id) {
                // 更新代理
                $agent = Admin::find($id);
                if (!$agent) {
                    return $this->jsonError('代理不存在');
                }

                // 禁止通过本接口修改超级管理员
                if ($agent->auth === '超级管理员') {
                    return $this->jsonError('无权操作超级管理员');
                }

                // 仅提取白名单字段，防止越权修改 auth 等敏感字段
                $update = $this->filterEditableFields($data);

                // 如果修改了密码
                if (!empty($update['password'])) {
                    $update['password'] = secure_password_hash((string)$update['password']);
                } else {
                    unset($update['password']);
                }

                foreach ($update as $key => $value) {
                    $agent->$key = $value;
                }
                // 强制保持代理身份，防止提权
                $agent->auth = '代理';
                $agent->save();
            } else {
                // 新增代理
                if (empty($data['username'])) {
                    return $this->jsonError('请输入用户名');
                }
                if (empty($data['password'])) {
                    return $this->jsonError('请输入密码');
                }

                // 检查用户名是否存在
                $exists = Admin::where('username', $data['username'])->find();
                if ($exists) {
                    return $this->jsonError('用户名已存在');
                }

                // 仅提取白名单字段
                $create = $this->filterEditableFields($data);
                $create['password'] = secure_password_hash((string)$data['password']);
                $create['auth'] = '代理';
                $create['login_time'] = date('Y-m-d H:i:s');

                Admin::create($create);
            }
            
            return $this->jsonSuccess('保存成功');
        } catch (\Exception $e) {
            Log::error('admin save agent failed', ['error' => $e->getMessage()]);
            return $this->jsonError('保存失败');
        }
    }
    
    /**
     * 删除代理
     */
    public function del()
    {
        $ids = $this->request->post('id', '');
        
        if (empty($ids)) {
            return $this->jsonError('请选择要删除的代理');
        }
        
        $idArr = explode(',', $ids);
        
        try {
            // 不能删除超级管理员
            Admin::whereIn('id', $idArr)
                ->where('auth', '<>', '超级管理员')
                ->delete();
            return $this->jsonSuccess('删除成功');
        } catch (\Exception $e) {
            Log::error('admin delete agent failed', ['error' => $e->getMessage()]);
            return $this->jsonError('删除失败');
        }
    }
    
    public function agent()
    {
        $id = $this->request->param('id', 0, 'intval');
        View::assign('id', $id);
        return view('agent/agent');
    }

    /**
     * 修改代理状态
     */
    public function status()
    {
        $id = $this->request->post('id', 0, 'intval');
        $status = $this->request->post('status', 0, 'intval');
        
        if (!$id) {
            return $this->jsonError('参数错误');
        }
        
        try {
            $agent = Admin::find($id);
            if (!$agent) {
                return $this->jsonError('代理不存在');
            }

            // 禁止通过本接口封禁超级管理员
            if ($agent->auth === '超级管理员') {
                return $this->jsonError('无权操作超级管理员');
            }

            $agent->ban = $status;
            $agent->save();
            
            return $this->jsonSuccess('修改成功');
        } catch (\Exception $e) {
            Log::error('admin update agent status failed', ['error' => $e->getMessage()]);
            return $this->jsonError('修改失败');
        }
    }
}
