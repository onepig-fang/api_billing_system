<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\AdminController;
use app\common\model\Cmsapis;
use app\common\model\ShopTimes;
use app\common\model\Shops;
use think\facade\View;
use think\facade\Db;
use think\facade\Log;

/**
 * 采集接口管理控制器
 */
class Cmsapi extends AdminController
{
    /**
     * 采集套餐列表页面
     */
    public function index()
    {
        return view('cmsapi/index');
    }
    
    /**
     * 获取采集套餐列表数据
     */
    public function list()
    {
        $page = $this->request->post('current_page', 1, 'intval');
        $limit = $this->request->post('limit', 15, 'intval');
        
        $list = Cmsapis::order('cmsapi_id', 'asc')
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
     * 采集套餐详情页面
     */
    public function details()
    {
        $id = $this->request->param('id', 0, 'intval');
        
        $cmsapi = null;
        if ($id) {
            $cmsapi = Cmsapis::find($id);
        }

        // 兼容模板中使用的变量名（details.html 使用 $shop/$list/$ids）
        $shop = $cmsapi ? $cmsapi->toArray() : [];
        $ids = [];
        if (!empty($shop['cmsapi_type'])) {
            if (is_array($shop['cmsapi_type'])) {
                $ids = $shop['cmsapi_type'];
            } else {
                $ids = array_filter(array_map('intval', preg_split('/\s*,\s*/', (string)$shop['cmsapi_type'])));
            }
        }
        $list = Shops::order('id', 'asc')->select();

        View::assign('cmsapi', $cmsapi);
        View::assign('shop', $shop);
        View::assign('list', $list);
        View::assign('ids', $ids);
        return view('cmsapi/details');
    }
    
    /**
     * 保存采集套餐信息
     */
    public function edit()
    {
        if (!$this->request->isPost()) {
            return $this->jsonError('请求方式错误');
        }
        
        $id = $this->request->post('id', 0, 'intval');
        $data = $this->request->post();

        $types = $data['cmsapi_type'] ?? [];
        if (is_array($types)) {
            $types = array_values(array_unique(array_filter(array_map('intval', $types))));
            $data['cmsapi_type'] = implode(',', $types);
        } else {
            $data['cmsapi_type'] = trim((string) $types);
        }

        try {
            if ($id) {
                $cmsapi = Cmsapis::find($id);
                if (!$cmsapi) {
                    return $this->jsonError('采集套餐不存在');
                }
                
                $cmsAllowFields = ['cmsapi_name', 'cmsapi_url', 'cmsapi_type', 'cmsapi_format',
                    'cmsapi_remark', 'cmsapi_auth', 'cmsapi_price', 'cmsapi_freetime',
                    'cmsapi_price_month', 'cmsapi_price_quarter', 'cmsapi_price_half_year', 'cmsapi_price_year',
                    'cmsapi_duration_month', 'cmsapi_duration_quarter', 'cmsapi_duration_half_year', 'cmsapi_duration_year'];
                foreach ($data as $key => $value) {
                    if (in_array($key, $cmsAllowFields)) {
                        $cmsapi->$key = $value;
                    }
                }
                $cmsapi->save();
            } else {
                if (empty($data['cmsapi_name'])) {
                    return $this->jsonError('请输入套餐名称');
                }
                
                $cmsAllowFields = ['cmsapi_name', 'cmsapi_url', 'cmsapi_type', 'cmsapi_format',
                    'cmsapi_remark', 'cmsapi_auth', 'cmsapi_price', 'cmsapi_freetime',
                    'cmsapi_price_month', 'cmsapi_price_quarter', 'cmsapi_price_half_year', 'cmsapi_price_year',
                    'cmsapi_duration_month', 'cmsapi_duration_quarter', 'cmsapi_duration_half_year', 'cmsapi_duration_year'];
                Cmsapis::create(array_intersect_key($data, array_flip($cmsAllowFields)));
            }
            
            return $this->jsonSuccess('保存成功');
        } catch (\Exception $e) {
            Log::error('admin save cmsapi failed', ['error' => $e->getMessage()]);
            return $this->jsonError('保存失败');
        }
    }
    
    /**
     * 删除采集套餐
     */
    public function del()
    {
        $ids = $this->request->post('id', '');
        
        if (empty($ids)) {
            return $this->jsonError('请选择要删除的套餐');
        }
        
        $idArr = explode(',', $ids);
        
        try {
            Cmsapis::whereIn('cmsapi_id', $idArr)->delete();
            return $this->jsonSuccess('删除成功');
        } catch (\Exception $e) {
            Log::error('admin delete cmsapi failed', ['error' => $e->getMessage()]);
            return $this->jsonError('删除失败');
        }
    }
    
    /**
     * 采集授权用户列表页面
     */
    public function user()
    {
        return view('cmsapi/user');
    }
    
    /**
     * 获取采集授权用户列表
     */
    public function userList()
    {
        $page = $this->request->post('current_page', 1, 'intval');
        $limit = $this->request->post('limit', 15, 'intval');
        $key = $this->request->post('key', '');
        $type = (string)$this->request->post('type', '');

        $query = ShopTimes::alias('s');

        if (!empty($key)) {
            switch ($type) {
                case '2':
                    $query->where('s.cj_auth_ip', 'like', "%{$key}%");
                    break;
                case '3':
                    $query->where('s.cmsapi_name', 'like', "%{$key}%");
                    break;
                case '1':
                default:
                    $query->where('s.uid|s.cmsapi_id', 'like', "%{$key}%");
                    break;
            }
        }
        
        $list = $query->order('s.id', 'desc')
            ->paginate([
                'page' => $page,
                'list_rows' => $limit
            ]);

        $items = $list->items();
        foreach ($items as $item) {
            $endTime = strtotime((string)($item->end_time ?? ''));
            $item->remaining_days = $endTime === false ? 0 : max(0, (int)ceil(($endTime - time()) / 86400));
        }

        return json([
            'code' => 0,
            'msg' => '获取成功',
            'data' => [
                'data' => $list
            ]
        ]);
    }
    
    public function userdetails()
    {
        $id = $this->request->param('id', 0, 'intval');
        $shop = $id > 0 ? ShopTimes::where('id', $id)->find() : null;
        View::assign('shop', $shop ?: []);
        return view('cmsapi/userdetails');
    }

    public function useredit()
    {
        if (!$this->request->isPost()) {
            return $this->jsonError('请求方式错误');
        }

        $id = $this->request->post('id', 0, 'intval');
        $data = [
            'uid' => trim((string)$this->request->post('uid', '')),
            'cj_auth_ip' => trim((string)$this->request->post('cj_auth_ip', '')),
            'cmsapi_id' => (int)$this->request->post('cmsapi_id', 0),
            'cmsapi_name' => trim((string)$this->request->post('cmsapi_name', '')),
            'intime' => trim((string)$this->request->post('intime', '')),
            'end_time' => trim((string)$this->request->post('end_time', '')),
        ];

        if ($data['uid'] === '' || $data['cmsapi_id'] <= 0 || $data['cmsapi_name'] === '') {
            return $this->jsonError('请填写完整信息');
        }

        try {
            if ($id > 0) {
                ShopTimes::where('id', $id)->update($data);
            } else {
                ShopTimes::create($data);
            }
            return $this->jsonSuccess('保存成功');
        } catch (\Exception $e) {
            Log::error('admin save cmsapi user failed', ['error' => $e->getMessage()]);
            return $this->jsonError('保存失败');
        }
    }

    /**
     * 删除采集授权
     */
    public function userDel()
    {
        $ids = $this->request->post('id', '');
        
        if (empty($ids)) {
            return $this->jsonError('请选择要删除的授权');
        }
        
        $idArr = explode(',', $ids);
        
        try {
            ShopTimes::whereIn('id', $idArr)->delete();
            return $this->jsonSuccess('删除成功');
        } catch (\Exception $e) {
            Log::error('admin delete cmsapi user failed', ['error' => $e->getMessage()]);
            return $this->jsonError('删除失败');
        }
    }
}
