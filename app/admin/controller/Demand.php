<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\AdminController;
use app\common\model\Qiupian;
use think\facade\View;
use think\facade\Db;
use think\facade\Log;

/**
 * 求片管理控制器
 */
class Demand extends AdminController
{
    /**
     * 求片列表页面
     */
    public function index()
    {
        return view('demand/index');
    }
    
    /**
     * 获取求片列表数据
     */
    public function list()
    {
        $page = $this->request->post('current_page', 1, 'intval');
        $limit = $this->request->post('limit', 15, 'intval');
        $key = $this->request->post('key', '');
        
        $query = Qiupian::alias('q')
            ->leftJoin('user u', 'q.user_id = u.uid')
            ->field('q.*, u.user as username');

        if (!empty($key)) {
            try {
                $query->whereLike('q.name|q.demand|q.describe|q.user_id|u.user', "%{$key}%");
            } catch (\Throwable $e) {
                $query->whereLike('q.name|q.content|q.description|q.user_id|u.user', "%{$key}%");
            }
        }
        $type = trim((string)$this->request->post('type', ''));
        if ($type !== '') {
            $query->where('q.type', $type);
        }

        $list = $query->order('q.id', 'desc')
            ->paginate([
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
    
    public function details()
    {
        $id = $this->request->param('id', 0, 'intval');
        $data = $id > 0 ? Qiupian::where('id', $id)->find() : null;
        View::assign('data', $data ?: []);
        return view('demand/details');
    }

    public function edit()
    {
        if (!$this->request->isPost()) {
            return $this->jsonError('请求方式错误');
        }

        $id = $this->request->post('id', 0, 'intval');
        if ($id <= 0) {
            return $this->jsonError('参数错误');
        }

        $status = (int)$this->request->post('status', 1);
        if (!in_array($status, [1, 2, 3], true)) {
            return $this->jsonError('状态参数错误');
        }

        $data = [
            'type' => trim((string)$this->request->post('type', '')),
            'status' => $status,
            'img' => trim((string)$this->request->post('img', '')),
            'actors' => trim((string)$this->request->post('actors', '')),
            'director' => trim((string)$this->request->post('director', '')),
            'imdbRating' => trim((string)$this->request->post('IMDb', $this->request->post('imdbRating', ''))),
            'reason' => trim((string)$this->request->post('reason', '')),
        ];

        if ($status !== 1) {
            $data['completion_time'] = date('Y-m-d H:i:s');
        } else {
            $data['processing_time'] = date('Y-m-d H:i:s');
            $data['completion_time'] = null;
        }

        try {
            Qiupian::where('id', $id)->update($data);
            return $this->jsonSuccess('保存成功');
        } catch (\Exception $e) {
            Log::error('admin save demand failed', ['error' => $e->getMessage()]);
            return $this->jsonError('保存失败');
        }
    }

    /**
     * 删除求片
     */
    public function del()
    {
        $ids = $this->request->post('id', '');
        
        if (empty($ids)) {
            return $this->jsonError('请选择要删除的求片');
        }
        
        $idArr = explode(',', $ids);
        
        try {
            Qiupian::whereIn('id', $idArr)->delete();
            return $this->jsonSuccess('删除成功');
        } catch (\Exception $e) {
            Log::error('admin delete demand failed', ['error' => $e->getMessage()]);
            return $this->jsonError('删除失败');
        }
    }
    
    /**
     * 清空求片
     */
    public function clear()
    {
        if (!$this->request->isPost()) {
            return $this->jsonError('请求方式错误');
        }

        if (!$this->isSuperAdmin()) {
            return $this->jsonError('无权操作');
        }

        $confirm = trim((string)$this->request->post('confirm', ''));
        if ($confirm !== 'clear') {
            return $this->jsonError('缺少清空确认参数');
        }

        try {
            Qiupian::where('id', '>', 0)->delete();
            return $this->jsonSuccess('清空成功');
        } catch (\Exception $e) {
            Log::error('admin clear demand failed', ['error' => $e->getMessage()]);
            return $this->jsonError('清空失败');
        }
    }
    
    /**
     * 打包管理页面
     */
    public function dabao()
    {
        return view('demand/dabao');
    }
}
