<?php
declare (strict_types = 1);

namespace app;

use think\App;
use think\Response;
use think\exception\ValidateException;
use think\exception\HttpResponseException;
use think\Validate;
use think\facade\Cache;

/**
 * 控制器基础类
 */
abstract class BaseController
{
    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var \think\App
     */
    protected $app;

    /**
     * 是否批量验证
     * @var bool
     */
    protected $batchValidate = false;

    /**
     * 控制器中间件
     * @var array
     */
    protected $middleware = [];

    /**
     * 构造方法
     * @access public
     * @param  App  $app  应用对象
     */
    public function __construct(App $app)
    {
        $this->app     = $app;
        $this->request = $this->app->request;

        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize()
    {
 

    }

    /**
     * 验证数据
     * @access protected
     * @param  array        $data     数据
     * @param  string|array $validate 验证器名或者验证规则数组
     * @param  array        $message  提示信息
     * @param  bool         $batch    是否批量验证
     * @return array|string|true
     * @throws ValidateException
     */
    protected function validate(array $data, string|array $validate, array $message = [], bool $batch = false)
    {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                // 支持场景
                [$validate, $scene] = explode('.', $validate);
            }
            $class = false !== strpos($validate, '\\') ? $validate : $this->app->parseClass('validate', $validate);
            $v     = new $class();
            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        $v->message($message);

        // 是否批量验证
        if ($batch || $this->batchValidate) {
            $v->batch(true);
        }

        return $v->failException(true)->check($data);
    }
/**
     * URL重定向
     * @access protected
     * @param  string         $url 跳转的URL表达式
     * @param  array|integer  $params 其它URL参数
     * @param  integer        $code http code
     * @param  array          $with 隐式传参
     * @return
     */
    protected function redirect($url, $params = [], $code = 302, $with = [])
    {
        $response = Response::create($url, 'redirect');

        if (is_integer($params)) {
            $code   = $params;
            $params = [];
        }
     
        $response->code($code);
        throw new HttpResponseException($response);
    }
     /**
     * 操作成功跳转的快捷方法
     * @access protected
     * @param  mixed     $msg 提示信息
     * @param  string    $url 跳转的URL地址
     * @param  mixed     $data 返回的数据
     * @param  integer   $wait 跳转等待时间
     * @param  array     $header 发送的Header信息
     * @return mixed
     */
    protected function success($msg = '', string $url = null, $data = '', int $count = 0,  int $code = 200, int $wait = 3, array $header = []): mixed
    {
        if (is_null($url) && isset($_SERVER["HTTP_REFERER"])) {
            $url = $_SERVER["HTTP_REFERER"];
        } elseif ($url) {
            $url = (strpos($url, '://') || 0 === strpos($url, '/')) ? $url : app('route')->buildUrl($url);
        }

        // 默认消息
        $msg = !empty($msg) ? ($msg) :  ('操作成功！');

        $result = [
            'code'  => $code,
            'msg'   => $msg,
            'data'  => $data,
            'count' => $count,
            'url'   =>(string)$url,
            'wait'  => $wait,
        ];

        $type = $this->getResponseType();
        if (strtolower($type) == 'html'){
          //  var_dump(config('app'));
           $response = view(config('app.dispatch_success'), $result);
        } else if ($type == 'json') {
            $response = json($result);
        }
        
        throw new HttpResponseException($response);
    } /**
     * 操作错误跳转的快捷方法
     * @access protected
     * @param  mixed     $msg 提示信息
     * @param  string    $url 跳转的URL地址
     * @param  mixed     $data 返回的数据
     * @param  integer   $wait 跳转等待时间
     * @param  array     $header 发送的Header信息
     * @return mixed
     */
    protected function error($msg = '',  $url = null, $data = '', int $code = 101, int $wait = 3, array $header = []): mixed
    {
        if (is_null($url)) {
            $url = request()->isAjax() ? '' : 'javascript:history.back(-1);';
        } elseif ($url) {
            $url = (strpos($url, '://') || 0 === strpos($url, '/')) ? $url : $this->app->route->buildUrl($url);
        }
        $msg = !empty($msg) ? ($msg) :  '操作失败！';
        $result = [
            'code' => $code,
            'msg'  => $msg,
            'data' => $data,
            'url'  =>(string)$url,
            'wait' => $wait,
        ];

        $type = $this->getResponseType();
        if ($type == 'html'){
            $response = view(config('app.dispatch_error'), $result);
        } else if ($type == 'json') {
            $response = json($result);
        }
        throw new HttpResponseException($response);
    }
     /**
     * 抛出404异常
     * @access public
     */
	public function throwError(string $msg = 'not found!', int $code = 404) 
    {
        if(request()->isAjax()) {
          
        return message($msg,false);
        }else{
        echo ( '<div style="margin:0 auto;width:90%;text-align: center;padding:10px;color:red;">
   权限不足
   </div> '); die(); 
        }
       
	}
    /**
     * 获取当前的response 输出类型
     * @access protected
     * @return string
     */
    protected function getResponseType(): string
    {
        return request()->isJson() || request()->isAjax() ? 'json' : 'html';
    }

    /**
     * JSON成功响应
     */
    protected function jsonSuccess($msg = '操作成功', $data = [], $code = 0)
    {
        return json([
            'code' => $code,
            'msg' => $msg,
            'data' => $data
        ]);
    }

    /**
     * JSON错误响应
     */
    protected function jsonError($msg = '操作失败', $code = 1, $data = [])
    {
        return json([
            'code' => $code,
            'msg' => $msg,
            'data' => $data
        ]);
    }

    /**
     * 返回分页数据
     */
    protected function pageData($list, $msg = '获取成功')
    {
        return json([
            'code' => 0,
            'msg' => $msg,
            'data' => [
                'data' => $list
            ]
        ]);
    }

}
