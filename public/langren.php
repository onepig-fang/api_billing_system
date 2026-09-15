<?php
// +----------------------------------------------------------------------
// | 后台入口文件
// +----------------------------------------------------------------------

namespace think;

require __DIR__ . "/../vendor/autoload.php";

// 检查安装锁文件
if (!is_file(__DIR__ . '/../install.lock')){
    header('Location: /install.php');
    exit;
}

// 执行HTTP应用并响应
$http = (new App())->http;

$response = $http->name("admin")->run();

$response->send();

$http->end($response);
