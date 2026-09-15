<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\AdminController;
use app\common\model\News;
use app\common\model\Users;
use think\facade\Log;
use think\facade\View;

/**
 * 公告管理控制器
 */
class Article extends AdminController
{
    /**
     * 公告列表页面
     */
    public function index()
    {
        return view('article/index');
    }
    
    /**
     * 获取公告列表数据
     */
    public function list()
    {
        $page = $this->request->post('current_page', 1, 'intval');
        $limit = $this->request->post('limit', 15, 'intval');
        $key = $this->request->post('key', '');
        
        $query = News::alias('n');
        
        if (!empty($key)) {
            $query->where('n.title', 'like', "%{$key}%");
        }
        
        $list = $query->order('n.id', 'desc')
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
    
    /**
     * 公告详情页面
     */
    public function details()
    {
        $id = $this->request->param('id', 0, 'intval');
        
        $article = null;
        if ($id) {
            $article = News::find($id);
        }

        // 提供完整的默认字段结构，避免模板访问未定义键（添加时 $article 为 null）
        $default = [
            'id'        => 0,
            'title'     => '',
            'content'   => '',
            'time'      => '',
            'mail_push' => 0,
            'user_id'   => '',
            'status'    => 1,
        ];
        $data = $article ? array_merge($default, $article->toArray()) : $default;

        View::assign('article', $article);
        View::assign('data', $data);
        View::assign('id', $id);
        return view('article/add');
    }
    
    /**
     * 保存公告信息
     */
    public function edit()
    {
        if (!$this->request->isPost()) {
            return $this->jsonError('请求方式错误');
        }
        
        $id = $this->request->post('id', 0, 'intval');
        $data = $this->request->post();
        
        try {
            $title = trim((string) ($data['title'] ?? ''));
            if ($title === '') {
                return $this->jsonError('请输入公告标题');
            }

            $saveData = [
                'title' => $title,
                'content' => (string) ($data['content'] ?? ''),
                'mail_push' => (int) ($data['mail_push'] ?? 0),
                'user_id' => isset($data['user_ids']) ? implode(',', (array) $data['user_ids']) : null,
            ];

            if ($id) {
                // 更新公告
                $article = News::find($id);
                if (!$article) {
                    return $this->jsonError('公告不存在');
                }

                $article->save($saveData);
            } else {
                // 新增公告
                $saveData['time'] = date('Y-m-d H:i:s');
                News::create($saveData);
            }
            
            return $this->jsonSuccess('保存成功');
        } catch (\Exception $e) {
            Log::error('admin save article failed', ['error' => $e->getMessage()]);
            return $this->jsonError('保存失败');
        }
    }
    
    /**
     * 删除公告
     */
    public function del()
    {
        $ids = $this->request->post('id', '');

        if (empty($ids)) {
            return $this->jsonError('请选择要删除的公告');
        }

        $idArr = explode(',', $ids);

        try {
            News::whereIn('id', $idArr)->delete();
            return $this->jsonSuccess('删除成功');
        } catch (\Exception $e) {
            Log::error('admin delete article failed', ['error' => $e->getMessage()]);
            return $this->jsonError('删除失败');
        }
    }

    /**
     * 更新公告状态
     */
    public function status()
    {
        $id = $this->request->post('id', 0, 'intval');
        $status = $this->request->post('status', 0, 'intval');

        if ($id <= 0) {
            return $this->jsonError('参数错误');
        }

        $article = News::find($id);
        if (!$article) {
            return $this->jsonError('公告不存在');
        }

        try {
            $article->save(['status' => $status ? 1 : 0]);
            return $this->jsonSuccess('状态更新成功');
        } catch (\Exception $e) {
            Log::error('admin update article status failed', ['error' => $e->getMessage()]);
            return $this->jsonError('状态更新失败');
        }
    }

    /**
     * 获取可推送用户列表
     */
    public function select()
    {
        $users = Users::field('uid,user')
            ->order('id', 'desc')
            ->select()
            ->toArray();

        $data = array_map(static function (array $user): array {
            return [
                'name' => $user['user'] . '【' . $user['uid'] . '】',
                'value' => (string) $user['uid'],
            ];
        }, $users);

        return json($data);
    }
}
