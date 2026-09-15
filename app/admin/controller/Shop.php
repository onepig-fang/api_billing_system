<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\AdminController;
use app\common\model\Shops;
use app\common\model\Json;
use think\facade\Db;
use think\facade\Log;
use think\facade\View;

/**
 * 套餐管理控制器
 */
class Shop extends AdminController
{
    /**
     * 套餐列表页面
     */
    public function index()
    {
        return view('shop/index');
    }
    
    /**
     * 获取套餐列表数据
     */
    public function list()
    {
        $page = $this->request->post('current_page', 1, 'intval');
        $limit = $this->request->post('limit', 15, 'intval');
        
        $list = Shops::order('id', 'asc')
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
     * 套餐详情页面
     */
    public function details()
    {
        $id = $this->request->param('id', 0, 'intval');
        
        $shop = null;
        if ($id) {
            $shop = Shops::find($id);
        }

        // 获取解析接口列表供绑定选择
        $jsonList = Json::order('id', 'asc')->select();

        View::assign('shop', $shop);
        View::assign('jsonList', $jsonList);
        return view('shop/details');
    }
    
    /**
     * 保存套餐信息
     */
    public function edit()
    {
        if (!$this->request->isPost()) {
            return $this->jsonError('请求方式错误');
        }

        $id = $this->request->post('id', 0, 'intval');
        $data = $this->request->post();

        // 处理 bind_json_ids 复选框数组 → 逗号分隔字符串
        if (isset($data['bind_json_ids']) && is_array($data['bind_json_ids'])) {
            $data['bind_json_ids'] = implode(',', array_filter(array_map('intval', $data['bind_json_ids'])));
        }

        try {
            if ($id) {
                $shop = Shops::find($id);
                if (!$shop) {
                    return $this->jsonError('套餐不存在');
                }

                $shopAllowFields = ['name', 'price', 'content', 'dd', 'fs', 'fullnum', 'bind_enable', 'bind_json_ids'];
                foreach ($data as $key => $value) {
                    if (in_array($key, $shopAllowFields)) {
                        $shop->$key = $value;
                    }
                }
                $shop->save();
            } else {
                if (empty($data['name'])) {
                    return $this->jsonError('请输入套餐名称');
                }

                $shopAllowFields = ['name', 'price', 'content', 'dd', 'fs', 'fullnum', 'bind_enable', 'bind_json_ids'];
                Shops::create(array_intersect_key($data, array_flip($shopAllowFields)));
            }

            return $this->jsonSuccess('保存成功');
        } catch (\Exception $e) {
            Log::error('admin save shop failed', ['error' => $e->getMessage()]);
            return $this->jsonError('保存失败');
        }
    }
    
    /**
     * 删除套餐
     */
    public function del()
    {
        $ids = $this->request->post('id', '');
        
        if (empty($ids)) {
            return $this->jsonError('请选择要删除的套餐');
        }
        
        $idArr = explode(',', $ids);
        
        try {
            Shops::whereIn('id', $idArr)->delete();
            return $this->jsonSuccess('删除成功');
        } catch (\Exception $e) {
            Log::error('admin delete shop failed', ['error' => $e->getMessage()]);
            return $this->jsonError('删除失败');
        }
    }
    
    /**
     * 解析配置页面
     */
    public function jiexi()
    {
        return view('shop/jiexi');
    }
    
    /**
     * 获取解析接口列表
     */
    public function jiexiList()
    {
        $page = $this->request->post('current_page', 1, 'intval');
        $limit = $this->request->post('limit', 15, 'intval');
        
        $list = Json::order('id', 'asc')
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
     * 解析接口详情页面
     */
    public function jiexiDetails()
    {
        $id = $this->request->param('id', 0, 'intval');
        
        $json = null;
        if ($id) {
            $json = Json::find($id);
        }

        View::assign('json', $json);
        return view('shop/jsondetails');
    }
    
    /**
     * 保存解析接口
     */
    public function jiexiEdit()
    {
        if (!$this->request->isPost()) {
            return $this->jsonError('请求方式错误');
        }
        
        $id = $this->request->post('id', 0, 'intval');
        $data = $this->request->post();
        
        try {
            if ($id) {
                // 更新
                $json = Json::find($id);
                if (!$json) {
                    return $this->jsonError('解析接口不存在');
                }
                
                $jsonAllowFields = ['Json_name', 'Json_str', 'Json_api', 'Json_main', 'Json_type', 'status'];
                foreach ($data as $key => $value) {
                    if (in_array($key, $jsonAllowFields)) {
                        $json->$key = $value;
                    }
                }
                $json->save();
            } else {
                if (empty($data['Json_name'])) {
                    return $this->jsonError('请输入接口名称');
                }
                
                $jsonAllowFields = ['Json_name', 'Json_str', 'Json_api', 'Json_main', 'Json_type', 'status'];
                Json::create(array_intersect_key($data, array_flip($jsonAllowFields)));
            }
            
            return $this->jsonSuccess('保存成功');
        } catch (\Exception $e) {
            Log::error('admin save json failed', ['error' => $e->getMessage()]);
            return $this->jsonError('保存失败');
        }
    }
    
    /**
     * 删除解析接口
     */
    public function jiexiDel()
    {
        $ids = $this->request->post('id', '');
        
        if (empty($ids)) {
            return $this->jsonError('请选择要删除的解析接口');
        }
        
        $idArr = explode(',', $ids);
        
        try {
            Json::whereIn('id', $idArr)->delete();
            return $this->jsonSuccess('删除成功');
        } catch (\Exception $e) {
            Log::error('admin delete json failed', ['error' => $e->getMessage()]);
            return $this->jsonError('删除失败');
        }
    }
}
