<?php
header('Content-Type: application/json; charset=utf-8');
//解析接口转缓存本地调用代码
/*
--------宝塔--伪静态---添加下面设置跨域代码--------
    add_header Access-Control-Allow-Origin * always;
    add_header Access-Control-Allow-Methods GET,POST,DELETE;
    add_header Access-Control-Allow-Header Content-Type,*;
------------------------
*/
$api = '填写你的需要缓存接口地址'; // JSON 接口
//调用方法你的域名+jiexi.php?url=视频地址或者将：域名+jiexi.php?url=放进播放器后台josn接口

// IP 授权功能
$ipsq = true; // true 开启 IP 授权，false 关闭
$ips = '127.0.0.1|192.168.1.1'; // 手动添加的授权服务器 IP 列表，用任意符号隔开

if ($ipsq == true) {
    // 自动获取当前域名的服务器IP地址
    $domain = $_SERVER['HTTP_HOST']; // 获取当前域名
    $serverIp = gethostbyname($domain); // 通过域名解析获取服务器IP地址
    
    // 将自动获取的服务器IP地址与手动添加的IP地址合并
    $ips .= '|' . $serverIp;

    $ip1 = (string)$_SERVER["REMOTE_ADDR"]; // 强制转换为字符串
    $ip2 = isset($_SERVER["HTTP_X_FORWARDED_FOR"]) ? (string)$_SERVER["HTTP_X_FORWARDED_FOR"] : ''; // 强制转换为字符串，并处理可能不存在的情况

    // 检查 IP 是否授权
    $ip1Authorized = strstr($ips, $ip1) !== false;
    $ip2Authorized = !empty($ip2) && strstr($ips, $ip2) !== false; // 只有在 $ip2 非空时才检查

    if (!$ip1Authorized && !$ip2Authorized) {
        $response = [
            'code' => 403,
            'msg' => 'IP未授权',
            'ip1' => $ip1,
            'ip2' => $ip2,
            /*'server_ip' => $serverIp*/ // 返回服务器IP地址用于调试
        ];
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

// 缓存配置
define('CACHE_PATH', 'cache/'); // 缓存文件夹
define('CACHE_EXPIRE', 3600); // 缓存失效时间，单位：秒（默认1小时）
define('CACHE_ENABLED', true); // 缓存开关，true 为开启，false 为关闭

// 确保缓存文件夹存在
if (!file_exists(CACHE_PATH)) {
    mkdir(CACHE_PATH, 0777, true);
}

if (isset($_GET['url'])) {
    $targetUrl = $_GET['url'];
    $cacheFileName = md5($targetUrl) . '.m3u8'; // 缓存文件名，使用 .m3u8 后缀
    $cacheFile = CACHE_PATH . $cacheFileName; // 缓存文件路径

    // 检查缓存是否存在且未过期
    if (CACHE_ENABLED && file_exists($cacheFile) && !is_cache_expired($cacheFile)) {
        $response = [
            'code' => 200,
            'msg' => '缓存调用数据正常',
            'url' => get_current_url() . $cacheFile, // 当前域名 + 缓存文件路径
            'get_url' => $targetUrl, 
            'cache_time' => date('Y-m-d H:i:s', filemtime($cacheFile)), // 缓存时间
            'file_size' => format_file_size(filesize($cacheFile)) // 文件大小
        ];
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $baseUrl = $api;
    $urlWithParams = $baseUrl . urlencode($targetUrl);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $urlWithParams);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo json_encode(['code' => 500, 'msg' => 'cURL Error: ' . curl_error($ch)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        $decodedResponse = json_decode($response, true);

        if (json_last_error() == JSON_ERROR_NONE && isset($decodedResponse['url'])) {
            $parsedUrl = $decodedResponse['url']; // 获取解析出来的 URL

            // 下载文件并保存到缓存
            if (CACHE_ENABLED) {
                $fileContent = download_file($parsedUrl); // 下载文件内容
                if ($fileContent !== false) {
                    $saveResult = save_cache($cacheFile, $fileContent); // 保存文件内容到缓存
                    if (!$saveResult) {
                        // 如果保存缓存失败，直接返回API的URL
                        $response = [
                            'code' => 200,
                            'msg' => '解析成功，缓存保存失败，直接返回API URL',
                            'url' => $parsedUrl,
                            'get_url' => $targetUrl
                        ];
                        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        exit;
                    }
                } else {
                    // 如果下载文件失败，直接返回API的URL
                    $response = [
                        'code' => 200,
                        'msg' => '解析成功，文件下载失败，直接返回API URL',
                        'url' => $parsedUrl,
                        'get_url' => $targetUrl
                    ];
                    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    exit;
                }
            }

            // 返回结果
            $response = [
                'code' => 200,
                'msg' => '解析成功',
                'url' => get_current_url() . $cacheFile,
                'form_url' => $parsedUrl,
                'get_url' => $targetUrl, // 当前域名 + 缓存文件路径
                'cache_time' => date('Y-m-d H:i:s', time()), // 当前时间
                'file_size' => format_file_size(filesize($cacheFile)) // 文件大小
            ];
            echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            echo json_encode(['code' => 500, 'msg' => '无法解析接口返回的数据'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    curl_close($ch);
} else {
    echo json_encode(['code' => 400, 'msg' => 'Error: 空URL'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// 检查缓存是否过期
function is_cache_expired($cacheFile) {
    $cacheTime = filemtime($cacheFile);
    return (time() - $cacheTime) > CACHE_EXPIRE;
}

// 保存缓存
function save_cache($cacheFile, $data) {
    return file_put_contents($cacheFile, $data) !== false;
}

// 下载文件内容
function download_file($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $fileContent = curl_exec($ch);
    curl_close($ch);
    return $fileContent;
}

// 格式化文件大小
function format_file_size($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

// 获取当前完整 URL 路径
function get_current_url() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']); // 获取脚本所在目录
    return $protocol . $host . $scriptPath . '/';
}