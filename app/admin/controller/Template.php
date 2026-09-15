<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\AdminController;
use think\facade\View;

/**
 * 模板管理控制器
 */
class Template extends AdminController
{
    protected function initialize()
    {
        parent::initialize();

        if (!$this->isSuperAdmin()) {
            throw new \think\exception\HttpResponseException(json(['code' => 403, 'msg' => '无权限操作模板']));
        }
    }

    /**
     * 模板编辑页面
     */
    public function index()
    {
        $subDir = $this->sanitizeSubDir((string)input('subdir', 'index')) ?? 'index';
        $pageName = $this->sanitizeTemplateFileName((string)input('pagename', 'index.html')) ?? 'index.html';
        
        // 视图路径
        $viewPath = $this->buildTemplateFilePath($subDir, $pageName);
        if ($viewPath === null) {
            $viewPath = '';
        }
        
        // 读取模板内容
        $content = '';
        if ($viewPath !== '' && file_exists($viewPath)) {
            $content = file_get_contents($viewPath);
        }
        
        View::assign([
            'subDir' => $subDir,
            'pageName' => $pageName,
            'viewPath' => $viewPath,
            'content' => htmlspecialchars($content)
        ]);
        
        return View::fetch();
    }
    
    /**
     * 加载模板内容
     */
    public function load()
    {
        $subDir = $this->sanitizeSubDir((string)input('subdir', ''));
        $filename = $this->sanitizeTemplateFileName((string)input('filename', ''));
        
        if (empty($subDir) || empty($filename)) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }
        
        $viewPath = $this->buildTemplateFilePath($subDir, $filename);
        if ($viewPath === null) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }
        
        if (!file_exists($viewPath)) {
            return json(['code' => 1, 'msg' => '文件不存在']);
        }
        
        $content = file_get_contents($viewPath);
        
        return json([
            'code' => 0,
            'msg' => 'success',
            'content' => $content
        ]);
    }
    
    /**
     * 保存模板内容
     */
    public function save()
    {
        $pagename = input('pagename', '');
        $subdir = input('subdir', '');
        $content = input('content', '');
        
        if (empty($pagename)) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        $safeSubDir = $this->sanitizeSubDir((string)$subdir);
        $safeFilename = $this->extractTemplateFileName((string)$pagename);
        if (empty($safeSubDir) || empty($safeFilename)) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }
        
        // 解码内容
        $content = urldecode($content);
        
        // 构建视图路径
        $viewPath = $this->buildTemplateFilePath($safeSubDir, $safeFilename, true);
        if ($viewPath === null) {
            return json(['code' => 1, 'msg' => '保存失败']);
        }
        
        // 写入文件
        $result = file_put_contents($viewPath, $content);
        
        if ($result !== false) {
            return json(['code' => 0, 'msg' => '保存成功']);
        } else {
            return json(['code' => 1, 'msg' => '保存失败']);
        }
    }
    
    /**
     * 获取模板列表
     */
    public function list()
    {
        $viewPath = app()->getRootPath() . 'view/home/';
        $templates = [];
        
        if (is_dir($viewPath)) {
            $dirs = scandir($viewPath);
            foreach ($dirs as $dir) {
                if ($dir != '.' && $dir != '..' && is_dir($viewPath . $dir)) {
                    $files = scandir($viewPath . $dir);
                    foreach ($files as $file) {
                        if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'html') {
                            $templates[] = [
                                'dir' => $dir,
                                'file' => $file,
                                'path' => $dir . '/' . $file
                            ];
                        }
                    }
                }
            }
        }
        
        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => $templates
        ]);
    }

    protected function sanitizeSubDir(string $subDir): ?string
    {
        $subDir = trim(str_replace('\\', '/', $subDir), '/');
        if ($subDir === '') {
            return null;
        }

        if (strpos($subDir, '..') !== false || strpos($subDir, "\0") !== false) {
            return null;
        }

        if (!preg_match('/^[a-zA-Z0-9_\/-]+$/', $subDir)) {
            return null;
        }

        return $subDir;
    }

    protected function sanitizeTemplateFileName(string $filename): ?string
    {
        $filename = basename(trim(str_replace('\\', '/', $filename)));
        if ($filename === '') {
            return null;
        }

        if (strpos($filename, "\0") !== false) {
            return null;
        }

        if (!preg_match('/^[a-zA-Z0-9_.-]+\.html$/i', $filename)) {
            return null;
        }

        return $filename;
    }

    protected function extractTemplateFileName(string $pageName): ?string
    {
        $pageName = trim(str_replace('\\', '/', $pageName));
        if ($pageName === '') {
            return null;
        }

        if (strpos($pageName, 'home/') === 0) {
            $pageName = substr($pageName, 5);
        }

        $parts = explode('/', $pageName);
        $filename = end($parts);
        return $this->sanitizeTemplateFileName((string)$filename);
    }

    protected function buildTemplateFilePath(string $subDir, string $filename, bool $ensureDir = false): ?string
    {
        $homeRoot = realpath(app()->getRootPath() . 'view/home');
        if ($homeRoot === false) {
            return null;
        }

        $homeRoot = rtrim(str_replace('\\', '/', $homeRoot), '/');
        $targetDir = $homeRoot . '/' . trim($subDir, '/');

        if ($ensureDir && !is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                return null;
            }
        }

        $realTargetDir = realpath($targetDir);
        if ($realTargetDir === false) {
            return null;
        }

        $realTargetDir = rtrim(str_replace('\\', '/', $realTargetDir), '/');
        if (strpos($realTargetDir . '/', $homeRoot . '/') !== 0) {
            return null;
        }

        return $realTargetDir . '/' . $filename;
    }
}
