<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\AdminController;
use app\common\model\Cards;
use think\facade\Log;

/**
 * 卡密管理控制器
 */
class Card extends AdminController
{
    protected function initialize()
    {
        parent::initialize();
        $this->requireSuperAdmin();
    }

    /**
     * 仅允许超级管理员操作卡密
     */
    private function requireSuperAdmin(): void
    {
        if (!$this->isSuperAdmin()) {
            throw new \think\exception\HttpResponseException($this->jsonError('无权操作', 403));
        }
    }

    /**
     * 卡密列表页面
     */
    public function index()
    {
        return view('card/index');
    }

    public function edit()
    {
        return $this->add();
    }
    
    /**
     * 获取卡密列表数据
     */
    public function list()
    {
        if (!$this->request->isPost()) {
            return $this->jsonError('请求方式错误');
        }

        $page = max(1, (int)$this->request->post('current_page', 1, 'intval'));
        $limit = (int)$this->request->post('limit', 15, 'intval');
        $limit = min(max($limit, 1), 100);
        $key = trim((string)$this->request->post('key', ''));
        $type = (string)$this->request->post('type', '1');
        $status = trim((string)$this->request->post('status', ''));
        
        $query = Cards::alias('c')
            ->field('c.*, c.uid as suid, c.sytime as sytimes');
        
        if ($key !== '') {
            switch ($type) {
                case '2':
                    $query->where('c.uid', 'like', "%{$key}%");
                    break;
                case '3':
                    $query->where('c.ip', 'like', "%{$key}%");
                    break;
                case '4':
                    $query->where('c.remark', 'like', "%{$key}%");
                    break;
                case '1':
                default:
                    $query->where('c.kmcode', 'like', "%{$key}%");
                    break;
            }
        }

        if ($status !== '') {
            if (!in_array($status, ['0', '1'], true)) {
                return $this->jsonError('状态参数错误');
            }
            $query->where('c.status', (int)$status);
        }
        
        $list = $query->order('c.id', 'desc')
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
     * 生成卡密
     */
    public function add()
    {
        if (!$this->request->isPost()) {
            return $this->jsonError('请求方式错误');
        }
        
        $money = round((float)$this->request->post('money', 0, 'floatval'), 3);
        $num = $this->request->post('num', 1, 'intval');
        $remark = trim((string)$this->request->post('remark', ''));
        if (mb_strlen($remark) > 255) {
            $remark = mb_substr($remark, 0, 255);
        }

        if ($money <= 0 || $money > 100000) {
            return $this->jsonError('请输入正确的面额');
        }
        
        if ($num <= 0 || $num > 100) {
            return $this->jsonError('生成数量需要在1-100之间');
        }
        
        try {
            $cards = [];
            for ($i = 0; $i < $num; $i++) {
                $kmcode = $this->generateCode();
                $cards[] = [
                    'kmcode' => $kmcode,
                    'money' => $money,
                    'status' => 0,
                    'remark' => $remark,
                    'time' => date('Y-m-d H:i:s')
                ];
            }
            
            (new Cards())->saveAll($cards);
            
            return $this->jsonSuccess('生成成功');
        } catch (\Throwable $e) {
            Log::error('admin generate cards failed', ['error' => $e->getMessage()]);
            return $this->jsonError('生成失败');
        }
    }
    
    /**
     * 生成卡密码
     */
    private function generateCode(): string
    {
        $maxAttempts = 50;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $code = strtoupper(bin2hex(random_bytes(8)));
            if (!Cards::where('kmcode', $code)->find()) {
                return $code;
            }
        }

        return strtoupper(bin2hex(random_bytes(10)));
    }
    
    /**
     * 删除卡密
     */
    public function del()
    {
        if (!$this->request->isPost()) {
            return $this->jsonError('请求方式错误');
        }

        $ids = $this->request->post('id', '');

        if (empty($ids)) {
            return $this->jsonError('请选择要删除的卡密');
        }

        $idArr = array_values(array_filter(array_map('intval', preg_split('/\s*,\s*/', (string)$ids))));
        if (empty($idArr)) {
            return $this->jsonError('参数错误');
        }

        try {
            Cards::whereIn('id', $idArr)->delete();
            return $this->jsonSuccess('删除成功');
        } catch (\Exception $e) {
            Log::error('admin delete cards failed', ['error' => $e->getMessage()]);
            return $this->jsonError('删除失败');
        }
    }
    
    /**
     * 清空已使用卡密
     */
    public function clear()
    {
        if (!$this->request->isPost()) {
            return $this->jsonError('请求方式错误');
        }

        try {
            Cards::where('status', 1)->delete();
            return $this->jsonSuccess('清空成功');
        } catch (\Exception $e) {
            Log::error('admin clear cards failed', ['error' => $e->getMessage()]);
            return $this->jsonError('清空失败');
        }
    }
    
    /**
     * 导出卡密
     */
    public function export()
    {
        $status = $this->request->param('status', 0, 'intval');
        if (!in_array((string)$status, ['0', '1'], true)) {
            return $this->jsonError('状态参数错误');
        }
        
        $query = Cards::where('status', $status);
        $list = $query->order('id', 'desc')->select();
        
        $content = '';
        foreach ($list as $item) {
            $content .= $item['kmcode'] . ' - ' . $item['money'] . '元' . "\r\n";
        }
        
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="卡密导出_' . date('YmdHis') . '.txt"');
        echo $content;
        exit;
    }
}
