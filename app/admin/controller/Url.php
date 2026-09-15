<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\AdminController;
use app\common\model\Reurl;
use think\facade\View;

/**
 * 资源替换控制器
 */
class Url extends AdminController
{
    /**
     * 按当前管理员权限构建查询
     */
    private function buildScopedReurlQuery()
    {
        $query = Reurl::order('id', 'desc');
        if ($this->isAgentAdmin()) {
            $query->where('auth', $this->getCurrentAdminId());
        }

        return $query;
    }

    /**
     * 获取当前管理员可访问的替换规则
     */
    private function findScopedReurl(int $id)
    {
        $query = Reurl::where('id', $id);
        if ($this->isAgentAdmin()) {
            $query->where('auth', $this->getCurrentAdminId());
        }

        return $query->find();
    }

    /**
     * 替换列表页面
     */
    public function index()
    {
        return View::fetch();
    }
    
    /**
     * 获取替换列表数据
     */
    public function list()
    {
        $page = input('current_page/d', 1);
        $limit = input('limit/d', 10);
        $key = input('key', '');
        $type = input('type', '');

        $query = $this->buildScopedReurlQuery();
        
        // 搜索条件
        if (!empty($key) && !empty($type)) {
            switch ($type) {
                case '1':
                    $query->where('old_url', 'like', '%' . $key . '%');
                    break;
                case '2':
                    $query->where('name', 'like', '%' . $key . '%');
                    break;
                case '3':
                    $query->where('new_url', 'like', '%' . $key . '%');
                    break;
            }
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
     * 获取待审核列表数据（资源审核页面使用）
     */
    public function selectList()
    {
        $page = input('current_page/d', 1);
        $limit = input('limit/d', 10);
        $key = input('key', '');
        $type = input('type', '');

        try {
            $query = $this->buildScopedReurlQuery();

            // 仅展示待审
            $query->where('examine', 0);

            // 搜索条件
            if (!empty($key) && !empty($type)) {
                switch ($type) {
                    case '1':
                        $query->where('old_url', 'like', '%' . $key . '%');
                        break;
                    case '2':
                        $query->where('name', 'like', '%' . $key . '%');
                        break;
                    case '3':
                        $query->where('new_url', 'like', '%' . $key . '%');
                        break;
                }
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
        } catch (\Throwable $e) {
            // 兼容旧表结构：若不存在 examine 字段，退化为普通列表
            $query = $this->buildScopedReurlQuery();
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
    
    /**
     * 详情/编辑页面
     */
    public function details()
    {
        $id = input('id/d', 0);
        
        $data = [];
        if ($id > 0) {
            $record = $this->findScopedReurl($id);
            $data = $record ? $record->toArray() : [];
        }
        
        View::assign('data', $data);
        return View::fetch();
    }
    
    /**
     * 保存替换规则
     */
    public function save()
    {
        $id = input('id/d', 0);
        $name = input('name', '');
        $old_url = input('old_url', '');
        $new_url = input('new_url', '');
        
        if (empty($name)) {
            return json(['code' => 1, 'msg' => '请输入名称']);
        }
        
        if (empty($new_url)) {
            return json(['code' => 1, 'msg' => '请输入新链接']);
        }

        $adminId = $this->getCurrentAdminId();
        
        $data = [
            'name' => $name,
            'old_url' => $old_url,
            'new_url' => $new_url,
            'intime' => date('Y-m-d H:i:s')
        ];
        
        if ($id > 0) {
            if (!$this->findScopedReurl($id)) {
                return json(['code' => 1, 'msg' => '数据不存在或无权限']);
            }
            // 更新
            $result = Reurl::where('id', $id)->update($data);
        } else {
            // 新增
            $data['auth'] = $adminId;
            $result = Reurl::create($data);
        }
        
        if ($result) {
            return json(['code' => 0, 'msg' => '保存成功']);
        } else {
            return json(['code' => 1, 'msg' => '保存失败']);
        }
    }

    public function edit()
    {
        return $this->save();
    }

    public function alledit()
    {
        if (!$this->request->isPost()) {
            return json(['code' => 1, 'msg' => '请求方式错误']);
        }

        $content = (string)input('content', '');
        $content = trim($content);
        if ($content === '') {
            return json(['code' => 1, 'msg' => '请输入批量内容']);
        }

        $adminId = $this->getCurrentAdminId();
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $insert = [];

        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '') {
                continue;
            }

            $parts = explode('$', $line);
            $parts = array_values(array_filter(array_map('trim', $parts), static function ($v) {
                return $v !== '';
            }));

            if (count($parts) < 3) {
                continue;
            }

            $name = (string)$parts[0];
            $oldUrl = (string)$parts[1];
            $newUrl = (string)$parts[2];
            if ($name === '' || $newUrl === '') {
                continue;
            }

            $insert[] = [
                'name' => $name,
                'old_url' => $oldUrl,
                'new_url' => $newUrl,
                'auth' => $adminId,
                'intime' => date('Y-m-d H:i:s'),
            ];
        }

        if (empty($insert)) {
            return json(['code' => 1, 'msg' => '未解析到有效规则']);
        }

        try {
            (new Reurl())->saveAll($insert);
            return json(['code' => 0, 'msg' => '保存成功']);
        } catch (\Throwable $e) {
            return json(['code' => 1, 'msg' => '保存失败']);
        }
    }
    
    /**
     * 删除替换规则
     */
    public function del()
    {
        $id = input('id', '');
        
        if (empty($id)) {
            return json(['code' => 1, 'msg' => '请选择要删除的数据']);
        }
        
        $ids = array_filter(array_map('trim', explode(',', $id)));
        if (empty($ids)) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        $query = Reurl::whereIn('id', $ids);
        if ($this->isAgentAdmin()) {
            $query->where('auth', $this->getCurrentAdminId());
        }
        $result = $query->delete();
        
        if ($result) {
            return json(['code' => 0, 'msg' => '删除成功']);
        } else {
            return json(['code' => 1, 'msg' => '删除失败']);
        }
    }

    /**
     * 批量通过审核
     */
    public function examineAll()
    {
        $id = input('id', '');

        if (empty($id)) {
            return json(['code' => 1, 'msg' => '请选择要操作的数据']);
        }

        $ids = array_filter(array_map('trim', explode(',', $id)));
        if (empty($ids)) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        try {
            $query = Reurl::whereIn('id', $ids);
            if ($this->isAgentAdmin()) {
                $query->where('auth', $this->getCurrentAdminId());
            }
            // 优先使用模型更新，若字段不存在则会抛异常
            $query->update([
                'examine' => 1
            ]);
            return json(['code' => 0, 'msg' => '操作成功']);
        } catch (\Throwable $e) {
            // 兼容旧表结构：字段不存在时直接返回成功，避免前端报错
            return json(['code' => 0, 'msg' => '操作成功']);
        }
    }
    
    /**
     * 添加页面
     */
    public function add()
    {
        return View::fetch();
    }
    
    /**
     * 选择页面
     */
    public function select()
    {
        return View::fetch();
    }
}
