<?php

declare(strict_types=1);

namespace app\api\controller;

use app\ApiController;
use think\Request;

/**
 * M3U8处理控制器
 * 处理M3U8视频流相关请求
 */
class M3u8 extends ApiController
{
    /**
     * 无需鉴权的方法（proxy 已移除，需要鉴权防止 SSRF 滥用）
     */
    protected $noNeedAuth = [];
    
    /**
     * M3U8代理接口
     * GET /api/m3u8/proxy?url=xxx
     */
    public function proxy(Request $request)
    {
        $url = $request->param('url', '');
        
        if (empty($url)) {
            return $this->apiError('URL不能为空');
        }
        
        $url = urldecode($url);
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->apiError('URL格式不正确');
        }
        
        // 防止 SSRF：仅允许 http/https 协议，禁止内网地址
        if (!$this->isSafeUrl($url)) {
            return $this->apiError('URL不允许访问');
        }
        
        // 获取M3U8内容
        $content = $this->fetchM3u8Content($url);
        
        if ($content === false) {
            return $this->apiError('获取M3U8内容失败');
        }
        
        // 处理M3U8内容，替换相对路径为绝对路径
        $baseUrl = dirname($url);
        $content = $this->processM3u8Content($content, $baseUrl);
        
        // 返回M3U8内容
        header('Content-Type: application/vnd.apple.mpegurl');
        header('Access-Control-Allow-Origin: *');
        echo $content;
        exit;
    }
    
    /**
     * TS分片代理
     * GET /api/m3u8/ts?url=xxx
     */
    public function ts(Request $request)
    {
        $url = $request->param('url', '');
        
        if (empty($url)) {
            return $this->apiError('URL不能为空');
        }
        
        $url = urldecode($url);
        
        if (!filter_var($url, FILTER_VALIDATE_URL) || !$this->isSafeUrl($url)) {
            return $this->apiError('URL不允许访问');
        }
        
        // 获取TS内容（使用安全请求，逐跳校验重定向目标，防 SSRF）
        $result = $this->safeCurlGet($url, 60);
        if (!$result['success']) {
            return $this->apiError('获取TS内容失败');
        }
        $content = $result['content'];
        
        // 返回TS内容
        header('Content-Type: video/mp2t');
        header('Access-Control-Allow-Origin: *');
        echo $content;
        exit;
    }
    
    /**
     * 获取M3U8内容
     */
    protected function fetchM3u8Content(string $url)
    {
        $result = $this->safeCurlGet($url, 30);

        if (!$result['success']) {
            return false;
        }

        return $result['content'];
    }
    
    /**
     * 处理M3U8内容
     */
    protected function processM3u8Content(string $content, string $baseUrl): string
    {
        $lines = explode("\n", $content);
        $result = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line)) {
                $result[] = '';
                continue;
            }
            
            // 跳过注释和标签
            if (strpos($line, '#') === 0) {
                // 处理EXT-X-KEY等包含URI的标签
                if (strpos($line, 'URI="') !== false) {
                    $line = preg_replace_callback('/URI="([^"]+)"/', function($matches) use ($baseUrl) {
                        $uri = $matches[1];
                        if (strpos($uri, 'http') !== 0) {
                            $uri = $this->resolveUrl($baseUrl, $uri);
                        }
                        return 'URI="' . $uri . '"';
                    }, $line);
                }
                $result[] = $line;
                continue;
            }
            
            // 处理分片URL
            if (strpos($line, 'http') !== 0) {
                $line = $this->resolveUrl($baseUrl, $line);
            }
            
            $result[] = $line;
        }
        
        return implode("\n", $result);
    }
    
    /**
     * 解析相对URL为绝对URL
     */
    protected function resolveUrl(string $baseUrl, string $relativePath): string
    {
        if (strpos($relativePath, '/') === 0) {
            // 绝对路径
            $urlParts = parse_url($baseUrl);
            return $urlParts['scheme'] . '://' . $urlParts['host'] . 
                   (isset($urlParts['port']) ? ':' . $urlParts['port'] : '') . 
                   $relativePath;
        }
        
        // 相对路径
        return rtrim($baseUrl, '/') . '/' . ltrim($relativePath, '/');
    }
    
    /**
     * 解析M3U8接口
     * GET /api/m3u8/?url=xxx&key=xxx
     */
    /**
     * 检查 URL 是否安全（防 SSRF）
     */
    protected function isSafeUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['scheme']) || !isset($parsed['host'])) {
            return false;
        }
        if (!in_array(strtolower($parsed['scheme']), ['http', 'https'])) {
            return false;
        }

        // 仅允许标准 HTTP/HTTPS 端口，防止探测内网服务
        if (isset($parsed['port']) && !in_array((int)$parsed['port'], [80, 443, 8080, 8443], true)) {
            return false;
        }

        $host = trim($parsed['host'], '[]');

        // 解析主机的全部 IP（含 IPv4/IPv6），逐一校验，避免多 A 记录绕过
        $ips = $this->resolveHostIps($host);
        if (empty($ips)) {
            return false;
        }

        foreach ($ips as $ip) {
            if (!$this->isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 解析主机名的所有 IP 地址（IPv4 + IPv6）
     */
    protected function resolveHostIps(string $host): array
    {
        // 主机本身就是 IP 的情况
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (!empty($record['ip'])) {
                    $ips[] = $record['ip'];
                }
                if (!empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        // 回退到 gethostbynamel（仅 IPv4）
        if (empty($ips)) {
            $v4 = gethostbynamel($host);
            if (is_array($v4)) {
                $ips = $v4;
            }
        }

        return array_unique($ips);
    }

    /**
     * 判断 IP 是否为公网地址（拒绝私有、环回、保留、链路本地等）
     */
    protected function isPublicIp(string $ip): bool
    {
        // FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE 会拒绝私有与保留段（同时覆盖 IPv4/IPv6）
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * 安全地发起 GET 请求（防 SSRF）
     * 关闭 curl 自动跟随重定向，改为手动逐跳校验，避免 302 跳转到内网绕过 isSafeUrl。
     *
     * @return array{success:bool, content:string|false, httpCode:int, msg:string}
     */
    protected function safeCurlGet(string $url, int $timeout = 30, int $maxRedirects = 5): array
    {
        $currentUrl = $url;

        for ($i = 0; $i <= $maxRedirects; $i++) {
            // 每一跳都重新做 SSRF 校验（防重定向 / DNS rebinding 绕过）
            if (!$this->isSafeUrl($currentUrl)) {
                return ['success' => false, 'content' => false, 'httpCode' => 0, 'msg' => 'URL不允许访问'];
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $currentUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            // 关键：禁用自动跟随，手动处理重定向以逐跳校验
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');

            $content = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            curl_close($ch);

            if ($content === false) {
                return ['success' => false, 'content' => false, 'httpCode' => $httpCode, 'msg' => '请求失败'];
            }

            // 处理重定向
            if (in_array($httpCode, [301, 302, 303, 307, 308], true) && !empty($redirectUrl)) {
                $currentUrl = $redirectUrl;
                continue;
            }

            if ($httpCode !== 200) {
                return ['success' => false, 'content' => false, 'httpCode' => $httpCode, 'msg' => '返回错误码: ' . $httpCode];
            }

            return ['success' => true, 'content' => $content, 'httpCode' => $httpCode, 'msg' => '成功'];
        }

        return ['success' => false, 'content' => false, 'httpCode' => 0, 'msg' => '重定向次数过多'];
    }

    public function index(Request $request)
    {
        $url = $request->param('url', '');
        
        if (empty($url)) {
            return $this->apiError('URL不能为空');
        }
        
        $url = urldecode($url);
        
        if (!filter_var($url, FILTER_VALIDATE_URL) || !$this->isSafeUrl($url)) {
            return $this->apiError('URL不允许访问');
        }
        
        // 获取M3U8内容
        $content = $this->fetchM3u8Content($url);
        
        if ($content === false) {
            return $this->apiError('获取M3U8内容失败');
        }
        
        // 检查是否是主M3U8（包含其他M3U8链接）
        if (strpos($content, '#EXT-X-STREAM-INF') !== false) {
            // 获取最高清晰度的M3U8
            $lines = explode("\n", $content);
            $maxBandwidth = 0;
            $targetUrl = '';
            
            foreach ($lines as $i => $line) {
                if (strpos($line, '#EXT-X-STREAM-INF') !== false) {
                    preg_match('/BANDWIDTH=(\d+)/', $line, $matches);
                    $bandwidth = isset($matches[1]) ? (int)$matches[1] : 0;
                    
                    if ($bandwidth > $maxBandwidth && isset($lines[$i + 1])) {
                        $maxBandwidth = $bandwidth;
                        $targetUrl = trim($lines[$i + 1]);
                    }
                }
            }
            
            if (!empty($targetUrl)) {
                if (strpos($targetUrl, 'http') !== 0) {
                    $targetUrl = $this->resolveUrl(dirname($url), $targetUrl);
                }
                return $this->apiSuccess(['url' => $targetUrl, 'type' => 'hls']);
            }
        }
        
        return $this->apiSuccess(['url' => $url, 'type' => 'hls']);
    }
}
