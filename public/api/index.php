<?php

namespace think;

require __DIR__ . "/../../vendor/autoload.php";
header('Content-Type: text/html;charset=utf-8');//utf-8格式
// CORS 统一由 config/cors.php + app/middleware/Cors.php 处理（来源走 env 白名单）。
// 此处原有硬编码的 Allow-Origin:* 与 Allow-Credentials:true 属于危险且互斥的组合，
// 且会覆盖上述正确策略，故移除。需放开跨域请配置 cors.allowed_origins。
// 执行HTTP应用并响应
$http = (new App())->http;

$response =$http->name("api")->run();

$response->send();

$http->end($response);

