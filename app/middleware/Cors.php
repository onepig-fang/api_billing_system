<?php

declare(strict_types=1);

namespace app\middleware;

use think\Request;
use think\Response;

/**
 * 跨域中间件
 */
class Cors
{
    /**
     * 允许的域名
     */
    protected $allowOrigin = [];

    /**
     * 允许的请求方法
     */
    protected $allowMethods = 'GET, POST, PUT, DELETE, OPTIONS, PATCH';

    /**
     * 允许的请求头
     */
    protected $allowHeaders = 'Content-Type, Authorization, X-Requested-With, Accept, Origin, Token';

    /**
     * 处理请求
     */
    public function handle(Request $request, \Closure $next): Response
    {
        $origin = $request->header('origin', '');
        $allowOrigin = $this->resolveAllowOrigin($origin);

        // 处理OPTIONS预检请求
        if ($request->method(true) === 'OPTIONS') {
            $response = response('', 204)
                ->header([
                    'Access-Control-Allow-Methods' => $this->allowMethods,
                    'Access-Control-Allow-Headers' => $this->allowHeaders,
                    'Access-Control-Max-Age' => '86400',
                ]);

            if ($allowOrigin !== '') {
                $response->header([
                    'Access-Control-Allow-Origin' => $allowOrigin,
                    'Access-Control-Allow-Credentials' => 'true',
                    'Vary' => 'Origin',
                ]);
            }

            return $response;
        }

        $response = $next($request);

        if ($allowOrigin !== '') {
            $response->header([
                'Access-Control-Allow-Origin' => $allowOrigin,
                'Access-Control-Allow-Methods' => $this->allowMethods,
                'Access-Control-Allow-Headers' => $this->allowHeaders,
                'Access-Control-Allow-Credentials' => 'true',
                'Vary' => 'Origin',
            ]);
        }

        return $response;
    }

    protected function resolveAllowOrigin(string $origin): string
    {
        if ($origin === '') {
            return '';
        }

        $allowedOrigins = config('cors.allowed_origins', $this->allowOrigin);
        if (!is_array($allowedOrigins) || $allowedOrigins === [] || in_array('*', $allowedOrigins, true)) {
            return '';
        }

        return in_array($origin, $allowedOrigins, true) ? $origin : '';
    }
}
