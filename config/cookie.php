<?php
// +----------------------------------------------------------------------
// | Cookie设置
// +----------------------------------------------------------------------
return [
    // cookie 保存时间
    'expire'    => 0,
    // cookie 保存路径
    'path'      => '/',
    // cookie 有效域名
    'domain'    => '',
    //  cookie 启用安全传输
    //  站点全站 HTTPS 后请改为 true（当前若强制开启，HTTP 下会话会直接失效）
    'secure'    => false,
    // httponly设置：禁止 JS 读取 cookie，降低 XSS 窃取会话的风险
    'httponly'  => true,
    // 是否使用 setcookie
    'setcookie' => true,
    // samesite 设置，支持 'strict' 'lax'
    // 用 lax 而非 strict：QQ/微信 OAuth 回调是外站顶级跳转，strict 会导致回调时丢失会话
    'samesite'  => 'lax',
];
