<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\AdminController;
use think\facade\Filesystem;
use think\facade\Log;

/**
 * 文件上传控制器
 */
class Upload extends AdminController
{
    protected function initialize()
    {
        parent::initialize();

        if (!$this->isSuperAdmin()) {
            throw new \think\exception\HttpResponseException(json(['code' => 403, 'msg' => '无权限上传文件']));
        }
    }

    /**
     * 图片上传
     */
    public function images()
    {
        $file = request()->file('file');
        
        if (empty($file)) {
            return json(['code' => 1, 'msg' => '请选择上传文件']);
        }
        
        try {
            // 验证文件
            validate([
                'file' => [
                    'fileSize' => 10 * 1024 * 1024, // 10MB
                    'fileExt' => 'jpg,jpeg,png,gif,webp,bmp',
                    'fileMime' => 'image/jpeg,image/png,image/gif,image/webp,image/bmp'
                ]
            ])->check(['file' => $file]);
            
            // 上传到public/storage目录
            $saveName = Filesystem::disk('public')->putFile('images', $file);
            $url = '/storage/' . str_replace('\\', '/', $saveName);

            return json([
                'code' => 0,
                'msg' => '上传成功',
                'location' => $url,  // TinyMCE需要的字段
                'data' => [
                    'url' => $url,
                    'path' => $saveName
                ]
            ]);
        } catch (\think\exception\ValidateException $e) {
            Log::error('upload images validate failed', ['error' => $e->getMessage()]);
            return json(['code' => 1, 'msg' => '文件不符合要求']);
        } catch (\Exception $e) {
            Log::error('upload images failed', ['error' => $e->getMessage()]);
            return json(['code' => 1, 'msg' => '上传失败']);
        }
    }
    
    public function image()
    {
        return $this->images();
    }

    /**
     * 文件上传（通用）
     */
    public function file()
    {
        $file = request()->file('file');
        
        if (empty($file)) {
            return json(['code' => 1, 'msg' => '请选择上传文件']);
        }
        
        try {
            // 验证文件
            validate([
                'file' => [
                    'fileSize' => 50 * 1024 * 1024, // 50MB
                    'fileExt' => 'jpg,jpeg,png,gif,webp,bmp,mp4,mp3,pdf,doc,docx,xls,xlsx',
                    'fileMime' => 'image/jpeg,image/png,image/gif,image/webp,image/bmp,video/mp4,audio/mpeg,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                ]
            ])->check(['file' => $file]);
            
            // 上传到public/storage目录
            $saveName = Filesystem::disk('public')->putFile('files', $file);
            $url = '/storage/' . str_replace('\\', '/', $saveName);

            return json([
                'code' => 0,
                'msg' => '上传成功',
                'data' => [
                    'url' => $url,
                    'path' => $saveName,
                    'name' => $file->getOriginalName()
                ]
            ]);
        } catch (\think\exception\ValidateException $e) {
            Log::error('upload file validate failed', ['error' => $e->getMessage()]);
            return json(['code' => 1, 'msg' => '文件不符合要求']);
        } catch (\Exception $e) {
            Log::error('upload file failed', ['error' => $e->getMessage()]);
            return json(['code' => 1, 'msg' => '上传失败']);
        }
    }
    
    /**
     * 编辑器图片上传（兼容layui）
     */
    public function layuiImage()
    {
        $file = request()->file('file');
        
        if (empty($file)) {
            return json(['code' => 1, 'msg' => '请选择上传文件']);
        }
        
        try {
            // 验证文件
            validate([
                'file' => [
                    'fileSize' => 5 * 1024 * 1024, // 5MB
                    'fileExt' => 'jpg,jpeg,png,gif,webp',
                    'fileMime' => 'image/jpeg,image/png,image/gif,image/webp'
                ]
            ])->check(['file' => $file]);
            
            // 上传到public/storage目录
            $saveName = Filesystem::disk('public')->putFile('images', $file);
            $url = '/storage/' . str_replace('\\', '/', $saveName);

            // Layui格式返回
            return json([
                'code' => 0,
                'msg' => '上传成功',
                'data' => [
                    'src' => $url,
                    'title' => $file->getOriginalName()
                ]
            ]);
        } catch (\think\exception\ValidateException $e) {
            Log::error('upload layui image validate failed', ['error' => $e->getMessage()]);
            return json(['code' => 1, 'msg' => '文件不符合要求']);
        } catch (\Exception $e) {
            Log::error('upload layui image failed', ['error' => $e->getMessage()]);
            return json(['code' => 1, 'msg' => '上传失败']);
        }
    }
    
    /**
     * Base64图片上传
     */
    public function base64()
    {
        $base64 = input('base64', '');
        
        if (empty($base64)) {
            return json(['code' => 1, 'msg' => '图片数据不能为空']);
        }
        
        try {
            $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
            $mimeToExt = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'image/bmp' => 'bmp',
            ];

            // 解析base64
            if (preg_match('/^data:image\/(\w+);base64,/', $base64, $matches)) {
                $ext = strtolower((string)$matches[1]);
                $base64 = preg_replace('/^data:image\/\w+;base64,/', '', $base64);
                if (!in_array($ext, $allowedExt, true)) {
                    return json(['code' => 1, 'msg' => '不支持的图片格式']);
                }
            } else {
                $ext = 'png';
            }
            
            $data = base64_decode($base64, true);
            
            if ($data === false) {
                return json(['code' => 1, 'msg' => '图片数据解析失败']);
            }

            if (strlen($data) > 5 * 1024 * 1024) {
                return json(['code' => 1, 'msg' => '图片大小不能超过5MB']);
            }

            $imageInfo = @getimagesizefromstring($data);
            if ($imageInfo === false) {
                return json(['code' => 1, 'msg' => '图片内容无效']);
            }

            $mime = (string)($imageInfo['mime'] ?? '');
            if (!isset($mimeToExt[$mime])) {
                return json(['code' => 1, 'msg' => '不支持的图片类型']);
            }

            $ext = $mimeToExt[$mime];
            
            // 生成文件名
            $filename = date('Ymd') . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
            $savePath = app()->getRootPath() . 'public/storage/images/' . $filename;

            // 确保目录存在
            $dir = dirname($savePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // 保存文件
            if (file_put_contents($savePath, $data) === false) {
                return json(['code' => 1, 'msg' => '图片保存失败']);
            }

            $url = '/storage/images/' . $filename;
            
            return json([
                'code' => 0,
                'msg' => '上传成功',
                'data' => [
                    'url' => $url
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('upload base64 failed', ['error' => $e->getMessage()]);
            return json(['code' => 1, 'msg' => '上传失败']);
        }
    }
}
