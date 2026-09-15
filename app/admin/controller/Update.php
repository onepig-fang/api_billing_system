<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\AdminController;
use app\common\model\Setting;
use think\facade\Db;
use think\facade\Log;
use think\facade\View;

/**
 * 系统更新控制器
 */
class Update extends AdminController
{
    /**
     * 更新页面
     */
    public function index()
    {
        // 获取当前版本
        $setting = Setting::where('id', 1)->find();
        $version = $setting['version'] ?? '1.0.0';

        View::assign('version', $version);
        return View::fetch();
    }

    /**
     * 检查版本更新
     */
    public function check_version()
    {
        // 获取当前版本
        $setting = Setting::where('id', 1)->find();
        $currentVersion = $setting['version'] ?? '1.0.0';

        // 本地验证模式：直接返回已是最新版本
        // 原云端验证已移除
        return json([
            'code' => 201,
            'msg' => '系统已是最新版本',
            'data' => [
                'version' => $currentVersion,
                'content' => '当前版本已是最新版本，无需更新。'
            ]
        ]);
    }

    /**
     * 执行系统更新
     */
    public function system_update()
    {
        // 本地验证模式：不支持云端更新
        return json([
            'code' => 1,
            'msg' => '本地部署版本，不支持自动更新'
        ]);
    }

    /**
     * 数据迁移页面
     */
    public function zhuanyi()
    {
        return View::fetch();
    }

    /**
     * 执行数据迁移
     */
    public function doZhuanyi()
    {
        if (!$this->request->isPost()) {
            return json(['code' => 1, 'msg' => '请求方式错误']);
        }

        try {
            $tables = ['admin', 'user', 'shop', 'cmsapi', 'reurl', 'userlog', 'new'];
            $tableStats = [];
            $missingTables = [];

            foreach ($tables as $table) {
                if (!$this->tableExists($table)) {
                    $missingTables[] = $table;
                    continue;
                }

                $tableStats[$table] = Db::name($table)->count();
            }

            $message = '检测完成，当前环境无需执行自动迁移';
            if (!empty($missingTables)) {
                $message .= '，缺失数据表：' . implode('、', $missingTables);
            }

            return json([
                'code' => 0,
                'msg' => $message,
                'data' => [
                    'executed' => false,
                    'checked_tables' => $tableStats,
                    'missing_tables' => $missingTables,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('migration check failed', ['error' => $e->getMessage()]);
            return json(['code' => 1, 'msg' => '迁移检测失败']);
        }
    }

    protected function tableExists(string $table): bool
    {
        try {
            Db::name($table)->limit(1)->select();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
