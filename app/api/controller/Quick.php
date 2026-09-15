<?php

declare(strict_types=1);

namespace app\api\controller;

use app\ApiController;
use app\common\model\Setting;
use think\facade\Db;
use think\facade\Session;
use think\Request;

/**
 * 快捷登录控制器
 * 处理QQ/微信快捷登录
 */
class Quick extends ApiController
{
    /**
     * 无需鉴权的方法
     */
    protected $noNeedAuth = ['login', 'callback'];

    private function getSettingValue($setting, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            $value = trim((string)($setting[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return $default;
    }

    /**
     * 快捷登录入口
     * GET /api/quick/login?type=qq|wx&terminal=index|userbd
     */
    public function login(Request $request)
    {
        $type = $request->param('type', 'qq');
        $terminal = $request->param('terminal', 'index');

        // 获取配置
        $setting = Setting::find(1);

        if ($type == 'qq') {
            // QQ登录
            $appId = $this->getSettingValue($setting, ['qq_appid', 'quick_rainbow_appid']);
            $callbackUrl = $this->getSettingValue($setting, ['qq_callback', 'quick_rainbow_callback'], $request->domain() . '/api/quick/callback?type=qq&terminal=' . $terminal);

            if (empty($appId)) {
                return $this->showError('QQ登录未配置');
            }

            // 生成state防止CSRF
            $state = md5(uniqid((string)rand(), true));
            Session::set('qq_state', $state);

            $authUrl = 'https://graph.qq.com/oauth2.0/authorize?' . http_build_query([
                'response_type' => 'code',
                'client_id' => $appId,
                'redirect_uri' => $callbackUrl,
                'state' => $state,
                'scope' => 'get_user_info'
            ]);

            return redirect($authUrl);

        } elseif ($type == 'wx') {
            // 微信登录
            $appId = $this->getSettingValue($setting, ['wx_appid', 'quick_rainbow_appid']);
            $callbackUrl = $this->getSettingValue($setting, ['wx_callback', 'quick_rainbow_callback'], $request->domain() . '/api/quick/callback?type=wx&terminal=' . $terminal);

            if (empty($appId)) {
                return $this->showError('微信登录未配置');
            }

            // 生成state防止CSRF
            $state = md5(uniqid((string)rand(), true));
            Session::set('wx_state', $state);

            $authUrl = 'https://open.weixin.qq.com/connect/qrconnect?' . http_build_query([
                'appid' => $appId,
                'redirect_uri' => $callbackUrl,
                'response_type' => 'code',
                'scope' => 'snsapi_login',
                'state' => $state
            ]) . '#wechat_redirect';

            return redirect($authUrl);
        }

        return $this->showError('不支持的登录类型');
    }
    
    /**
     * OAuth回调处理
     * GET /api/quick/callback?type=qq|wx&terminal=xxx&code=xxx&state=xxx
     */
    public function callback(Request $request)
    {
        $type = $request->param('type', 'qq');
        $terminal = $request->param('terminal', 'index');
        $code = $request->param('code', '');
        $state = $request->param('state', '');
        
        if (empty($code)) {
            return $this->showError('授权失败');
        }
        
        // 获取配置
        $setting = Setting::find(1);
        
        if ($type == 'qq') {
            return $this->handleQQCallback($code, $state, $terminal, $setting);
        } elseif ($type == 'wx') {
            return $this->handleWXCallback($code, $state, $terminal, $setting);
        }
        
        return $this->showError('不支持的登录类型');
    }
    
    /**
     * 处理QQ登录回调
     */
    protected function handleQQCallback(string $code, string $state, string $terminal, $setting)
    {
        // 验证state
        $savedState = Session::get('qq_state');
        if ($state !== $savedState) {
            return $this->showError('状态验证失败');
        }
        Session::delete('qq_state');
        
        $appId = $this->getSettingValue($setting, ['qq_appid', 'quick_rainbow_appid']);
        $appKey = $this->getSettingValue($setting, ['qq_appkey', 'quick_rainbow_key']);
        $callbackUrl = $this->getSettingValue($setting, ['qq_callback', 'quick_rainbow_callback'], request()->domain() . '/api/quick/callback?type=qq&terminal=' . $terminal);
        
        // 获取access_token
        $tokenUrl = 'https://graph.qq.com/oauth2.0/token?' . http_build_query([
            'grant_type' => 'authorization_code',
            'client_id' => $appId,
            'client_secret' => $appKey,
            'code' => $code,
            'redirect_uri' => $callbackUrl
        ]);
        
        $tokenResponse = $this->httpGet($tokenUrl);
        parse_str($tokenResponse, $tokenData);
        
        if (!isset($tokenData['access_token'])) {
            return $this->showError('获取access_token失败');
        }
        
        $accessToken = $tokenData['access_token'];
        
        // 获取openid
        $openidUrl = 'https://graph.qq.com/oauth2.0/me?access_token=' . $accessToken;
        $openidResponse = $this->httpGet($openidUrl);
        
        // 解析JSONP格式
        preg_match('/callback\(\s*({.*})\s*\)/s', $openidResponse, $matches);
        $openidData = json_decode($matches[1] ?? '{}', true);
        
        if (!isset($openidData['openid'])) {
            return $this->showError('获取openid失败');
        }
        
        $openid = $openidData['openid'];
        
        // 获取用户信息
        $userInfoUrl = 'https://graph.qq.com/user/get_user_info?' . http_build_query([
            'access_token' => $accessToken,
            'oauth_consumer_key' => $appId,
            'openid' => $openid
        ]);
        
        $userInfoResponse = $this->httpGet($userInfoUrl);
        $userInfo = json_decode($userInfoResponse, true);
        
        // 处理登录/绑定
        return $this->processQuickLogin('qq', $openid, $accessToken, $userInfo, $terminal);
    }
    
    /**
     * 处理微信登录回调
     */
    protected function handleWXCallback(string $code, string $state, string $terminal, $setting)
    {
        // 验证state
        $savedState = Session::get('wx_state');
        if ($state !== $savedState) {
            return $this->showError('状态验证失败');
        }
        Session::delete('wx_state');
        
        $appId = $this->getSettingValue($setting, ['wx_appid', 'quick_rainbow_appid']);
        $appSecret = $this->getSettingValue($setting, ['wx_appsecret', 'quick_rainbow_key']);
        
        // 获取access_token
        $tokenUrl = 'https://api.weixin.qq.com/sns/oauth2/access_token?' . http_build_query([
            'appid' => $appId,
            'secret' => $appSecret,
            'code' => $code,
            'grant_type' => 'authorization_code'
        ]);
        
        $tokenResponse = $this->httpGet($tokenUrl);
        $tokenData = json_decode($tokenResponse, true);
        
        if (!isset($tokenData['access_token']) || !isset($tokenData['openid'])) {
            return $this->showError('获取access_token失败');
        }
        
        $accessToken = $tokenData['access_token'];
        $openid = $tokenData['openid'];
        
        // 获取用户信息
        $userInfoUrl = 'https://api.weixin.qq.com/sns/userinfo?' . http_build_query([
            'access_token' => $accessToken,
            'openid' => $openid
        ]);
        
        $userInfoResponse = $this->httpGet($userInfoUrl);
        $userInfo = json_decode($userInfoResponse, true);
        
        // 处理登录/绑定
        return $this->processQuickLogin('wx', $openid, $accessToken, $userInfo, $terminal);
    }
    
    /**
     * 处理快捷登录/绑定
     */
    protected function processQuickLogin(string $type, string $openid, string $accessToken, $userInfo, string $terminal)
    {
        // 根据终端类型处理
        if ($terminal == 'userbd') {
            // 绑定模式
            $userId = (int)Session::get('user_id');
            if (!$userId) {
                return $this->showError('请先登录后再绑定');
            }
            
            // 检查是否已被其他账号绑定
            $field = $type == 'qq' ? 'access_token' : 'access_token_wx';
            $existUser = Db::name('user')->where($field, $openid)->where('id', '<>', $userId)->find();
            
            if ($existUser) {
                return $this->showError('该账号已被其他用户绑定');
            }
            
            // 更新绑定信息
            Db::name('user')->where('id', $userId)->update([
                $field => $openid
            ]);
            
            return $this->showSuccess('绑定成功', '/user/inf');
        }
        
        // 登录模式
        $field = $type == 'qq' ? 'access_token' : 'access_token_wx';
        $user = Db::name('user')->where($field, $openid)->find();
        
        if ($user) {
            // 防会话固定：与账号密码登录路径保持一致，写入身份前更换会话 ID
            // 此处 OAuth state 已在上游 handleQQCallback/handleWXCallback 校验并删除，重置无副作用
            Session::regenerate(true);

            // 已绑定，直接登录
            Session::set('user', [
                'id' => (int)($user['id'] ?? 0),
                'uid' => (int)($user['uid'] ?? 0),
                'user' => (string)($user['user'] ?? ''),
                'email' => (string)($user['email'] ?? ''),
            ]);
            Session::set('user_id', (int)($user['id'] ?? 0));
            Session::set('user_uid', (int)($user['uid'] ?? 0));
            Session::set('user_name', (string)($user['user'] ?? ''));
            
            return $this->showSuccess('登录成功', '/user/index');
        }
        
        // 未绑定，显示绑定/注册页面
        Session::set('quick_type', $type);
        Session::set('quick_openid', $openid);
        Session::set('quick_access_token', $accessToken);
        Session::set('quick_userinfo', $userInfo);
        
        return redirect('/index/quick');
    }
    
    /**
     * HTTP GET请求
     */
    protected function httpGet(string $url): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        // 必须校验证书：本方法用于 QQ/微信的 access_token 交换与用户信息拉取，
        // 关闭校验会让中间人可伪造 openid 响应，从而登录任意已绑定账号。
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);

        $response = curl_exec($ch);
        curl_close($ch);
        
        return $response ?: '';
    }
    
    /**
     * 显示成功页面
     */
    protected function showSuccess(string $msg, string $url): string
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>操作成功</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background: #f5f5f5; }
        .box { background: white; padding: 30px 50px; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .icon { font-size: 48px; color: #27ae60; margin-bottom: 20px; }
        .msg { color: #333; margin-bottom: 20px; }
        a { color: #3498db; text-decoration: none; }
    </style>
</head>
<body>
    <div class="box">
        <div class="icon">&#10004;</div>
        <div class="msg">' . htmlspecialchars($msg) . '</div>
        <a href="' . $url . '">点击跳转</a>
    </div>
    <script>setTimeout(function(){ window.location.href = "' . $url . '"; }, 2000);</script>
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
    <title>操作失败</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background: #f5f5f5; }
        .box { background: white; padding: 30px 50px; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .icon { font-size: 48px; color: #e74c3c; margin-bottom: 20px; }
        .msg { color: #333; }
    </style>
</head>
<body>
    <div class="box">
        <div class="icon">&#10060;</div>
        <div class="msg">' . htmlspecialchars($msg) . '</div>
    </div>
</body>
</html>';
        return $html;
    }
}
