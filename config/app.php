<?php
// +----------------------------------------------------------------------
// | 应用设置
// +----------------------------------------------------------------------

return [
    // 应用地址
    'app_host'         => env('APP_HOST', ''),
    // 应用的命名空间
    'app_namespace'    => '',
    // 是否启用路由
    'with_route'       => true,
    // 开启应用快速访问
    'app_express'    =>    true,
    // 默认应用
    'default_app'    =>    'home',
    // 默认时区
    'default_timezone' => 'Asia/Shanghai',

    // 应用映射（自动多应用模式有效）
    'app_map'          => [
     
        ],
    // 域名绑定（自动多应用模式有效）
    'domain_bind'      => [],
    // 禁止URL访问的应用列表（自动多应用模式有效）
    'deny_app_list'    => ['comm'],
     // 自定义页面abort抛出异常
    'http_exception_template'    =>  [
        401 =>  'error/401',
		404 =>  'error/404',
		500 =>  'error/500',
    ],

    // 默认跳转页面对应的模板文件
    'dispatch_error'   => app_path() . '/view/admin/public/error.html',
    'dispatch_success' =>  root_path(). 'view/admin/public/jumptpl.html',
    // 异常页面的模板文件
    'exception_tmpl'   => app()->getThinkPath() . 'tpl/think_exception.tpl',
    // 错误显示信息,非调试模式有效
    'error_message'    => '页面错误！请稍后再试～是',
    // 显示错误信息
    'show_error_msg'   => false,
];
