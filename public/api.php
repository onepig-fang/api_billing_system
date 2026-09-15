<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// [ API应用入口文件 ]
namespace think;

// 检查PHP版本
if (version_compare("7.3", PHP_VERSION, ">=")) {
    die("PHP 7.3 or greater is required");
}

require __DIR__ . '/../vendor/autoload.php';

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (empty($_SERVER['PATH_INFO']) && \in_array($requestPath, ['/api.php', '/api.php/'], true)) {
    $_SERVER['PATH_INFO'] = '/index/index';
}

// 执行HTTP应用并响应
$http = (new App())->http;

$response = $http->name('api')->run();

$response->send();

$http->end($response);
