<?php
// +----------------------------------------------------------------------
// | 聚合解析计费系统 - 公共函数文件
// +----------------------------------------------------------------------

use think\facade\Db;
use think\facade\Cache;
use app\common\model\Setting;

/**
 * 获取系统配置
 * @param string $name 配置名称
 * @param mixed $default 默认值
 * @return mixed
 */
if (!function_exists('conf')) {
    function conf($name = null, $default = null)
    {
        static $config = null;
        
        if ($config === null) {
            $config = Cache::get('system_config');
            if (!$config) {
                $setting = Setting::find(1);
                $config = $setting ? $setting->toArray() : [];
                Cache::set('system_config', $config, 3600);
            }
        }
        
        if ($name === null) {
            return $config;
        }
        
        return $config[$name] ?? $default;
    }
}

/**
 * 清除系统配置缓存
 */
if (!function_exists('clear_config_cache')) {
    function clear_config_cache()
    {
        Cache::delete('system_config');
    }
}

/**
 * 返回JSON消息
 * @param string $msg 消息内容
 * @param bool $success 是否成功
 * @param array $data 附加数据
 * @return \think\response\Json
 */
if (!function_exists('message')) {
    function message($msg = '', $success = true, $data = [])
    {
        return json([
            'code' => $success ? 0 : 1,
            'msg' => $msg,
            'data' => $data
        ]);
    }
}

/**
 * 获取当前域名
 * @return string
 */
if (!function_exists('getHost')) {
    function getHost()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // 校验 Host 头防止注入
        if (!preg_match('/^[a-zA-Z0-9._:-]+$/', $host)) {
            $host = 'localhost';
        }
        return $protocol . '://' . $host;
    }
}

/**
 * 生成随机字符串
 * @param int $length 长度
 * @param string $type 类型：all/letter/number
 * @return string
 */
if (!function_exists('random_str')) {
    function random_str($length = 16, $type = 'all')
    {
        $chars = '';
        switch ($type) {
            case 'letter':
                $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
            case 'number':
                $chars = '0123456789';
                break;
            default:
                $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        }
        
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $str;
    }
}

/**
 * 生成订单号
 * @param string $prefix 前缀
 * @return string
 */
if (!function_exists('create_order_no')) {
    function create_order_no($prefix = '')
    {
        return $prefix . date('YmdHis') . substr(microtime(), 2, 5) . random_int(1000, 9999);
    }
}

/**
 * 安全哈希密码
 * @param string $password
 * @return string
 */
if (!function_exists('secure_password_hash')) {
    function secure_password_hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

/**
 * 校验密码（兼容历史 md5 / md5(md5())）
 * @param string $password
 * @param string $storedHash
 * @return bool
 */
if (!function_exists('secure_password_verify')) {
    function secure_password_verify(string $password, string $storedHash): bool
    {
        if ($storedHash === '') {
            return false;
        }

        $info = password_get_info($storedHash);
        if (($info['algo'] ?? null) !== null && (int)($info['algo'] ?? 0) !== 0) {
            return password_verify($password, $storedHash);
        }

        return hash_equals($storedHash, md5($password)) || hash_equals($storedHash, md5(md5($password)));
    }
}

/**
 * 是否需要重哈希
 * @param string $storedHash
 * @return bool
 */
if (!function_exists('secure_password_needs_rehash')) {
    function secure_password_needs_rehash(string $storedHash): bool
    {
        if ($storedHash === '') {
            return true;
        }

        $info = password_get_info($storedHash);
        if (($info['algo'] ?? null) === null || (int)($info['algo'] ?? 0) === 0) {
            return true;
        }

        return password_needs_rehash($storedHash, PASSWORD_DEFAULT);
    }
}

/**
 * 获取客户端IP
 * @return string
 */
if (!function_exists('get_client_ip')) {
    function get_client_ip()
    {
        // 仅信任 REMOTE_ADDR，防止请求头伪造绕过 IP 白名单
        // 如在可信反向代理后运行，可改为信任 X-Forwarded-For
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        if (strpos($ip, ',') !== false) {
            $ips = explode(',', $ip);
            $ip = trim($ips[0]);
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
}

/**
 * CURL GET请求
 * @param string $url 请求地址
 * @param array $headers 请求头
 * @param int $timeout 超时时间
 * @return string|false
 */
if (!function_exists('curl_get')) {
    function curl_get($url, $headers = [], $timeout = 30)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        return $result;
    }
}

/**
 * CURL POST请求
 * @param string $url 请求地址
 * @param array|string $data 请求数据
 * @param array $headers 请求头
 * @param int $timeout 超时时间
 * @return string|false
 */
if (!function_exists('curl_post')) {
    function curl_post($url, $data = [], $headers = [], $timeout = 30)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        return $result;
    }
}

/**
 * 格式化文件大小
 * @param int $bytes 字节数
 * @return string
 */
if (!function_exists('format_bytes')) {
    function format_bytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

/**
 * 时间友好显示
 * @param int|string $time 时间戳或时间字符串
 * @return string
 */
if (!function_exists('time_ago')) {
    function time_ago($time)
    {
        if (!is_numeric($time)) {
            $time = strtotime($time);
        }
        
        $diff = time() - $time;
        
        if ($diff < 60) {
            return '刚刚';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . '分钟前';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . '小时前';
        } elseif ($diff < 2592000) {
            return floor($diff / 86400) . '天前';
        } else {
            return date('Y-m-d', $time);
        }
    }
}

/**
 * 隐藏字符串中间部分
 * @param string $str 字符串
 * @param int $start 开始保留位数
 * @param int $end 结束保留位数
 * @param string $mask 掩码字符
 * @return string
 */
if (!function_exists('hide_str')) {
    function hide_str($str, $start = 3, $end = 4, $mask = '*')
    {
        $len = mb_strlen($str);
        if ($len <= $start + $end) {
            return $str;
        }
        
        return mb_substr($str, 0, $start) . str_repeat($mask, $len - $start - $end) . mb_substr($str, -$end);
    }
}

/**
 * 检查是否为手机号
 * @param string $mobile 手机号
 * @return bool
 */
if (!function_exists('is_mobile')) {
    function is_mobile($mobile)
    {
        return preg_match('/^1[3-9]\d{9}$/', $mobile) === 1;
    }
}

/**
 * 检查是否为邮箱
 * @param string $email 邮箱
 * @return bool
 */
if (!function_exists('is_email')) {
    function is_email($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

/**
 * 安全过滤XSS
 * @param string $str 字符串
 * @return string
 */
if (!function_exists('xss_clean')) {
    function xss_clean($str)
    {
        return htmlspecialchars(strip_tags($str), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * 生成密钥
 * @param int $length 长度
 * @return string
 */
if (!function_exists('generate_key')) {
    function generate_key($length = 32)
    {
        return bin2hex(random_bytes((int)ceil($length / 2)));
    }
}

/**
 * 验证码检查
 * @param string $code 验证码
 * @param string $id 验证码标识
 * @return bool
 */
if (!function_exists('captcha_check')) {
    function captcha_check($code, $id = '')
    {
        if (empty($code)) {
            return false;
        }
        
        // 使用ThinkPHP验证码组件
        return \think\captcha\facade\Captcha::check($code, $id);
    }
}
