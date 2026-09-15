<?php
declare(strict_types=1);

namespace app\api\controller;

use app\ApiController;
use app\common\model\Setting;
use app\common\model\Json;
use think\facade\Db;
use think\Request;
use think\facade\View;

/**
 * API解析主接口控制器
 * 处理视频解析请求
 */
class Index extends ApiController
{
    /**
     * 无需鉴权的方法
     */
    protected $noNeedAuth = ['test'];
    
    /**
     * 主解析接口
     * GET/POST /api/?key=xxx&url=xxx
     * GET/POST /api/index/index?key=xxx&url=xxx
     */
    public function index(Request $request)
    {
        $startTime = microtime(true);
        
        // 获取视频URL
        $url = $request->param('url', '');
        
        if (empty($url)) {
            return $this->apiError('请提供视频地址');
        }
        
        // URL解码
        $url = urldecode($url);
        
        // 验证URL格式
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->apiError('视频地址格式不正确');
        }
        
        // 获取用户
        $user = $this->getUser();
        if (!$user) {
            return $this->apiError('用户验证失败');
        }

        // 用户额度（点数/包月到期/日限）已由 ApiController::checkApiAuth 中的
        // checkPackage 统一校验，此处不再重复检查

        // 获取解析接口
        $parseUrl = $this->getPlatformParseUrl($url);

        if (empty($parseUrl)) {
            return $this->apiError('未找到可用的解析接口');
        }

        // 调用解析接口
        $result = $this->doParseRequest($parseUrl);

        // 计算耗时
        $useTime = round(microtime(true) - $startTime, 3);

        if ($result['success']) {
            if (!$this->deductUserQuota()) {
                $this->logApiCall($url, '失败:额度扣除失败', $useTime);
                return $this->apiError($user->way == '包月' ? '今日调用次数已达上限' : '额度不足，请充值');
            }

            // 记录调用日志
            $this->logApiCall($url, '成功', $useTime);

            // 组装返回数据：删除 data，顶层输出 url/type/title/from
            $parsed = is_array($result['data']) ? $result['data'] : [];
            $videoUrl = (string)($parsed['url'] ?? '');

            // type：上流返回优先，否则按视频地址后缀导出
            $type = trim((string)($parsed['type'] ?? ''));
            if ($type === '') {
                $type = strpos($videoUrl, '.m3u8') !== false ? 'hls' : 'mp4';
            }

            return json([
                'code'  => 200,
                'msg'   => '解析成功',
                'type'  => $type,
                'title' => (string)($parsed['title'] ?? ''),
                'url'   => $videoUrl,
                'from'  => (string)($parsed['from'] ?? $url),
            ]);
        } else {
            // 记录失败日志
            $this->logApiCall($url, '失败:' . $result['msg'], $useTime);
            
            return $this->apiError($result['msg']);
        }
    }
    
    /**
     * 播放器接口
     * GET /api/bfq/?key=xxx&url=xxx
     */
    public function bfq(Request $request)
    {
        $startTime = microtime(true);
        
        // 获取视频URL
        $url = $request->param('url', '');
        
        if (empty($url)) {
            return $this->showPlayerEntry();
        }
        
        // URL解码
        $url = urldecode($url);
        
        // 获取用户
        $user = $this->getUser();
        if (!$user) {
            return $this->showError('用户验证失败');
        }

        // 用户额度（点数/包月到期/日限）已由 ApiController::checkApiAuth 中的
        // checkPackage 统一校验，此处不再重复检查

        // 获取解析接口
        $parseUrl = $this->getPlatformParseUrl($url);
        
        if (empty($parseUrl)) {
            return $this->showError('未找到可用的解析接口');
        }
        
        // 调用解析接口
        $result = $this->doParseRequest($parseUrl);
        
        // 计算耗时
        $useTime = round(microtime(true) - $startTime, 3);
        
        if ($result['success'] && !empty($result['data']['url'])) {
            if (!$this->deductUserQuota()) {
                $this->logApiCall($url, '失败:额度扣除失败', $useTime);
                return $this->showError($user->way == '包月' ? '今日调用次数已达上限' : '额度不足，请充值');
            }

            // 记录调用日志
            $this->logApiCall($url, '成功', $useTime);

            // 输出播放器页面
            $videoUrl = $result['data']['url'];
            return $this->showPlayer($videoUrl, $url);
        } else {
            // 记录失败日志
            $this->logApiCall($url, '失败:' . ($result['msg'] ?? '未知错误'), $useTime);
            
            return $this->showError($result['msg'] ?? '解析失败');
        }
    }
    
    /**
     * JSON格式解析接口
     * GET /api/json/?key=xxx&url=xxx
     */
    public function json(Request $request)
    {
        return $this->index($request);
    }
    
    /**
     * 执行解析请求
     */
    protected function doParseRequest(string $parseUrl): array
    {
        try {
            if (!function_exists('curl_init')) {
                return ['success' => false, 'msg' => '服务器未安装curl扩展', 'data' => []];
            }

            $ch = curl_init();
            if ($ch === false) {
                return ['success' => false, 'msg' => '解析请求初始化失败', 'data' => []];
            }

            curl_setopt($ch, CURLOPT_URL, $parseUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($response === false) {
                $curlError = curl_error($ch);
                curl_close($ch);
                return ['success' => false, 'msg' => $curlError !== '' ? '解析接口请求失败：' . $curlError : '解析接口请求失败', 'data' => []];
            }
            curl_close($ch);
            
            if ($httpCode != 200) {
                return ['success' => false, 'msg' => '解析接口请求失败', 'data' => []];
            }
            
            $data = json_decode($response, true);
            
            if (!$data) {
                // 可能是直接返回视频地址
                if (strpos($response, '.m3u8') !== false || strpos($response, '.mp4') !== false) {
                    return ['success' => true, 'msg' => '成功', 'data' => ['url' => trim($response)]];
                }
                return ['success' => false, 'msg' => '解析结果格式错误', 'data' => []];
            }
            
            // 处理不同格式的返回值
            if (isset($data['url']) && !empty($data['url'])) {
                return ['success' => true, 'msg' => '成功', 'data' => $data];
            }
            
            if (isset($data['data']['url']) && !empty($data['data']['url'])) {
                return ['success' => true, 'msg' => '成功', 'data' => $data['data']];
            }
            
            $successCodes = [0, 1, 200];
            if (isset($data['code']) && in_array((int)$data['code'], $successCodes, true)) {
                return ['success' => true, 'msg' => '成功', 'data' => $data];
            }
            
            return ['success' => false, 'msg' => $data['msg'] ?? '解析失败', 'data' => []];
            
        } catch (\Throwable $e) {
            \think\facade\Log::error('解析异常: ' . $e->getMessage());
            return ['success' => false, 'msg' => '解析异常，请稍后重试', 'data' => []];
        }
    }
    
    /**
     * 显示播放器入口页
     */
    protected function showPlayerEntry(): string
    {
        $config = $this->getConfig();
        $title = htmlspecialchars((string) ($config['name'] ?? '视频解析播放器'), ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars((string) ($config['title'] ?? '请输入视频地址后开始解析播放'), ENT_QUOTES, 'UTF-8');

        return '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $title . '</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f8fafc 0%, #dbeafe 100%);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #0f172a;
            padding: 24px;
            box-sizing: border-box;
        }
        .card {
            width: min(100%, 560px);
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.12);
            padding: 32px;
        }
        h1 {
            margin: 0 0 12px;
            font-size: 28px;
        }
        p {
            margin: 0 0 24px;
            color: #475569;
            line-height: 1.7;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            font-size: 15px;
            box-sizing: border-box;
            margin-bottom: 16px;
        }
        input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
        }
        button {
            width: 100%;
            padding: 14px 16px;
            border: none;
            border-radius: 12px;
            background: #2563eb;
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover {
            background: #1d4ed8;
        }
        .tips {
            margin-top: 18px;
            font-size: 13px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>' . $title . '</h1>
        <p>' . $description . '</p>
        <form method="get" action="">
            <label for="url">视频地址</label>
            <input id="url" name="url" type="url" placeholder="请输入完整视频链接，如 https://..." required>
            <button type="submit">开始解析播放</button>
        </form>
        <div class="tips">如接口启用了鉴权，请继续携带 key 参数访问当前地址。</div>
    </div>
</body>
</html>';
    }

    /**
     * 显示播放器页面
     */
    protected function showPlayer(string $videoUrl, string $originalUrl): string
    {
        $user = $this->getUser();
        $config = $this->getConfig();
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars((string)($config['name'] ?? '视频解析'), ENT_QUOTES, 'UTF-8') . '</title>
    <link href="https://cdn.jsdelivr.net/npm/dplayer@1.26.0/dist/DPlayer.min.css" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; background: #000; }
        #dplayer { width: 100%; height: 100vh; }
    </style>
</head>
<body>
    <div id="dplayer"></div>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.2.9/dist/hls.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dplayer@1.26.0/dist/DPlayer.min.js"></script>
    <script>
        const dp = new DPlayer({
            container: document.getElementById("dplayer"),
            autoplay: true,
            video: {
                url: ' . json_encode($videoUrl, JSON_UNESCAPED_SLASHES) . ',
                type: ' . json_encode(strpos($videoUrl, '.m3u8') !== false ? 'hls' : 'auto') . '
            }
        });
    </script>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * 显示错误页面
     */
    protected function showError(string $msg): string
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>解析失败</title>
    <style>
        body { 
            margin: 0; 
            padding: 20px; 
            background: #f5f5f5; 
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .error-box {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
        }
        .error-icon { font-size: 48px; color: #e74c3c; margin-bottom: 20px; }
        .error-msg { color: #333; font-size: 16px; }
    </style>
</head>
<body>
    <div class="error-box">
        <div class="error-icon">&#10060;</div>
        <div class="error-msg">' . htmlspecialchars($msg) . '</div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * 测试接口（无需鉴权）
     */
    public function test()
    {
        return $this->apiSuccess([
            'version' => '1.0.0',
            'time' => date('Y-m-d H:i:s')
        ], 'API服务正常');
    }
}
