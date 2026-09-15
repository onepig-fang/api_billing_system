<?php

declare(strict_types=1);

namespace app\home\controller;

use app\HomeController;
use app\common\model\Setting;
use think\facade\Db;
use think\facade\Log;
use think\Request;

/**
 * 前台AJAX控制器
 * 处理前台各种AJAX请求
 */
class Ajax extends HomeController
{
    /**
     * 无需登录的方法
     */
    protected $noNeedLogin = ['login', 'reg', 'sendcode', 'code', 'reset'];

    /**
     * 安全获取分页参数
     */
    private function safePageParams(Request $request): array
    {
        $page = max(1, (int) $request->param('current_page', 1));
        $limit = min(max(1, (int) $request->param('limit', 10)), 100);
        return [$page, $limit];
    }

    /**
     * 统一输出分页数据，避免直接返回分页对象导致前端表格解析异常
     */
    private function paginateResponse($list, int $currentPage = 1, int $limit = 10): \think\response\Json
    {
        $items = method_exists($list, 'items') ? $list->items() : (array)$list;

        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'data' => [
                    'total' => method_exists($list, 'total') ? (int)$list->total() : count($items),
                    'per_page' => method_exists($list, 'listRows') ? (int)$list->listRows() : $limit,
                    'current_page' => method_exists($list, 'currentPage') ? (int)$list->currentPage() : $currentPage,
                    'last_page' => method_exists($list, 'lastPage') ? (int)$list->lastPage() : 1,
                    'data' => $items,
                ],
            ],
        ]);
    }

    /**
     * 返回空分页结果
     */
    private function emptyPaginateResponse(int $currentPage = 1, int $limit = 10): \think\response\Json
    {
        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'data' => [
                    'total' => 0,
                    'per_page' => $limit,
                    'current_page' => $currentPage,
                    'last_page' => 0,
                    'data' => [],
                ],
            ],
        ]);
    }

    /**
     * 格式化充值时间字段，兼容 int 时间戳与 datetime 字符串
     */
    private function formatRechargeTime($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_numeric($value)) {
            $timestamp = (int) $value;
            if ($timestamp <= 0) {
                return '';
            }
            return date('Y-m-d H:i:s', $timestamp);
        }

        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        $timestamp = strtotime($text);
        if ($timestamp === false) {
            return $text;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * 生成唯一 UID
     */
    private function generateUid(): int
    {
        $maxAttempts = 100;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $uid = random_int(100000, 999999);
            if (!Db::name('user')->where('uid', $uid)->find()) {
                return $uid;
            }
        }

        $maxUid = (int)Db::name('user')->max('uid');
        return max(1000000, $maxUid + 1);
    }

    /**
     * 充值记录列表
     */
    public function cardlist(Request $request)
    {
        $uid = $this->getCurrentUserUid();
        [$current_page, $limit] = $this->safePageParams($request);

        if ($uid === '') {
            return $this->emptyPaginateResponse($current_page, $limit);
        }

        $list = Db::name('recharge')
            ->where('user_id', $uid)
            ->field('id,order_id,money,fs,status,user_id,trade_no,qr_code,time,intime,time as time_text,intime as intime_text')
            ->order('id', 'desc')
            ->paginate([
                'list_rows' => $limit,
                'page' => $current_page
            ]);

        $items = $list->items();
        foreach ($items as &$item) {
            $item['time'] = $this->formatRechargeTime($item['time_text'] ?? $item['time'] ?? null);
            $item['intime'] = $this->formatRechargeTime($item['intime_text'] ?? $item['intime'] ?? null);
        }
        unset($item);

        return $this->paginateResponse($list, $current_page, $limit);
    }

    private function getQiupianTypeText($type): string
    {
        $types = [
            '1' => '电影',
            '2' => '电视剧',
            '3' => '纪录片',
            '4' => '动漫',
            '5' => '其他',
        ];

        $key = (string)$type;
        return $types[$key] ?? $key;
    }

    private function formatQiupianImage($image): string
    {
        $image = trim((string)$image);
        if ($image === '') {
            return '';
        }

        if (preg_match('/^(https?:)?\/\//i', $image)) {
            return $image;
        }

        $image = str_replace('\\', '/', $image);
        if (str_starts_with($image, '/storage/')) {
            return $image;
        }

        if (str_starts_with($image, '/uploads/')) {
            return '/storage/' . ltrim(substr($image, strlen('/uploads/')), '/');
        }

        if (str_starts_with($image, 'uploads/')) {
            return '/storage/' . ltrim(substr($image, strlen('uploads/')), '/');
        }

        if (str_starts_with($image, 'storage/')) {
            return '/' . $image;
        }

        return '/storage/' . ltrim($image, '/');
    }

    /**
     * 提交求片
     */
    public function qiupianpost(Request $request)
    {
        $uid = $this->getCurrentUserUid();
        if ($uid === '') {
            return json(['code' => 1, 'message' => '请先登录']);
        }

        $payload = $request->post();
        if (empty($payload)) {
            $raw = (string) $request->getInput();
            $payload = $raw !== '' ? (json_decode($raw, true) ?: []) : [];
        }

        $title = trim((string)($payload['title'] ?? ''));
        $type = trim((string)($payload['type'] ?? ''));
        $year = trim((string)($payload['year'] ?? ''));
        $description = trim((string)($payload['description'] ?? ''));
        $requirements = trim((string)($payload['requirements'] ?? ''));

        if ($title === '' || $type === '' || $requirements === '') {
            return json(['code' => 1, 'message' => '请填写完整信息']);
        }

        try {
            $data = [
                'user_id' => $uid,
                'name' => $title,
                'type' => $type,
                'year' => $year,
                // 主字段：install.sql 为 demand
                'demand' => $requirements,
                'describe' => $description,
                'status' => 1,
                'number' => date('YmdHis') . $uid . random_int(100, 999),
                'time' => date('Y-m-d H:i:s'),
            ];

            try {
                Db::name('qiupian')->insert($data);
            } catch (\Throwable $e) {
                // 兼容旧表：没有 demand 字段时使用 content
                $fallback = $data;
                unset($fallback['demand']);
                $fallback['content'] = $requirements;

                try {
                    Db::name('qiupian')->insert($fallback);
                } catch (\Throwable $e2) {
                    // 兼容旧表：没有 status 字段时使用 state
                    unset($fallback['status']);
                    $fallback['state'] = 1;
                    Db::name('qiupian')->insert($fallback);
                }
            }

            return json(['code' => 0, 'message' => 'success']);
        } catch (\Throwable $e) {
            Log::error('求片提交失败', ['error' => $e->getMessage()]);
            return json(['code' => 1, 'message' => '提交失败，请稍后重试']);
        }
    }

    /**
     * 求片详情
     */
    public function qiupianx(Request $request)
    {
        $uid = $this->getCurrentUserUid();
        if ($uid === '') {
            return json(['code' => 1, 'message' => '请先登录']);
        }

        $id = (int) $request->param('id', 0);
        if ($id <= 0) {
            return json(['code' => 1, 'message' => '参数错误']);
        }

        try {
            $info = Db::name('qiupian')->where('id', $id)->where('user_id', $uid)->find();
            if (!$info) {
                return json(['code' => 1, 'message' => '记录不存在']);
            }

            $status = (int)($info['status'] ?? ($info['state'] ?? 1));
            $createdAt = (string)($info['time'] ?? ($info['create_time'] ?? ''));
            $processingTime = (string)($info['processing_time'] ?? '');
            $completionTime = (string)($info['completion_time'] ?? '');
            $progress = $status === 1 ? 60 : 100;
            $logs = [
                ['message' => '提交求片请求', 'time' => $createdAt, 'icon' => 'fa-paper-plane'],
            ];
            if ($processingTime !== '') {
                $logs[] = ['message' => '管理员开始处理', 'time' => $processingTime, 'icon' => 'fa-search'];
            }
            if ($status === 2) {
                $logs[] = ['message' => '已找到影片资源', 'time' => $completionTime ?: $createdAt, 'icon' => 'fa-check'];
            } elseif ($status === 3) {
                $logs[] = ['message' => '未找到影片资源', 'time' => $completionTime ?: $createdAt, 'icon' => 'fa-times'];
            }

            $result = [
                'id' => $info['id'],
                'name' => $info['name'] ?? ($info['title'] ?? ''),
                'type' => $info['type'] ?? '',
                'category' => $this->getQiupianTypeText($info['type'] ?? ''),
                'year' => $info['year'] ?? '',
                'describe' => $info['describe'] ?? ($info['description'] ?? ''),
                'content' => $info['demand'] ?? ($info['content'] ?? ($info['requirements'] ?? '')),
                'demand' => $info['demand'] ?? ($info['content'] ?? ($info['requirements'] ?? '')),
                'status' => $status,
                'reason' => $info['reason'] ?? '',
                'img' => $this->formatQiupianImage($info['img'] ?? ''),
                'number' => $info['number'] ?? ($info['id'] ?? ''),
                'time' => $createdAt,
                'data' => $createdAt,
                'processing_time' => $processingTime,
                'completion_time' => $completionTime,
                'expectedCompletion' => '',
                'progress' => $progress,
                'director' => $info['director'] ?? '',
                'actors' => $info['actors'] ?? '',
                'imdbRating' => $info['imdbRating'] ?? ($info['IMDb'] ?? ''),
                'processingLogs' => $logs,
            ];

            return json(['code' => 0, 'message' => 'success', 'data' => $result]);
        } catch (\Throwable $e) {
            return json(['code' => 1, 'message' => '获取失败']);
        }
    }

    /**
     * 黑名单新增/编辑
     */
    public function heiedit(Request $request)
    {
        $uid = $this->getCurrentUserUid();
        if ($uid === '') {
            return json(['code' => 1, 'msg' => '请先登录']);
        }

        $id = (int) $request->param('id', 0);
        $ip = trim((string) $request->param('ip', ''));
        $notes = trim((string) $request->param('notes', ''));

        if ($ip === '') {
            return json(['code' => 1, 'msg' => '请输入要拉黑的IP']);
        }

        $data = [
            'user_id' => $uid,
            'ip' => $ip,
            'notes' => $notes,
        ];

        try {
            if ($id > 0) {
                Db::name('blacklist')->where('id', $id)->where('user_id', $uid)->update($data);
            } else {
                $data['time'] = date('Y-m-d H:i:s');
                Db::name('blacklist')->insert($data);
            }
            return json(['code' => 0, 'msg' => '保存成功']);
        } catch (\Exception $e) {
            Log::error('黑名单保存失败', ['error' => $e->getMessage()]);
            return json(['code' => 1, 'msg' => '保存失败，请稍后重试']);
        }
    }

    /**
     * 黑名单删除（支持批量）
     */
    public function heidel(Request $request)
    {
        $uid = $this->getCurrentUserUid();
        if ($uid === '') {
            return json(['code' => 1, 'msg' => '请先登录']);
        }

        $id = trim((string) $request->param('id', ''));
        if ($id === '') {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        $ids = array_filter(array_map('intval', preg_split('/\s*,\s*/', $id)));
        if (empty($ids)) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        try {
            Db::name('blacklist')->where('user_id', $uid)->whereIn('id', $ids)->delete();
            return json(['code' => 0, 'msg' => '删除成功']);
        } catch (\Exception $e) {
            Log::error('黑名单删除失败', ['error' => $e->getMessage()]);
            return json(['code' => 1, 'msg' => '删除失败，请稍后重试']);
        }
    }
    
    /**
     * 邀请注册列表
     */
    public function reglist(Request $request)
    {
        $uid = $this->getCurrentUserUid();
        $current_page = (int) $request->param('current_page', 1);
        $limit = (int) $request->param('limit', 10);

        if ($uid === '') {
            return $this->emptyPaginateResponse($current_page, $limit);
        }

        try {
            $list = Db::name('invite')
                ->where('uid', $uid)
                ->field('id,uid,inviteuser,time,pay as fan,ip')
                ->order('id', 'desc')
                ->paginate([
                    'list_rows' => $limit,
                    'page' => $current_page
                ]);
        } catch (\Throwable $e) {
            return $this->emptyPaginateResponse($current_page, $limit);
        }

        return $this->paginateResponse($list, $current_page, $limit);
    }
    
    /**
     * 用户调用日志列表
     */
    public function userloglist(Request $request)
    {
        $uid = $this->getCurrentUserUid();
        $current_page = (int) $request->param('current_page', 1);
        $limit = (int) $request->param('limit', 10);

        if ($uid === '') {
            return $this->emptyPaginateResponse($current_page, $limit);
        }

        $list = Db::name('userlog')
            ->where('uid', $uid)
            ->order('id', 'desc')
            ->paginate([
                'list_rows' => $limit,
                'page' => $current_page
            ]);

        return $this->paginateResponse($list, $current_page, $limit);
    }
    
    /**
     * 查询支付状态
     */
    public function pay(Request $request)
    {
        $uid = $this->getCurrentUserUid();
        if ($uid === '') {
            return json(['code' => 0, 'msg' => '请先登录']);
        }

        $outTradeNo = trim((string)$request->param('outTradeNo', ''));
        $id = (int)$request->param('id', 0);

        if ($outTradeNo === '' && $id <= 0) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }

        $query = Db::name('recharge')->where('user_id', $uid);
        if ($outTradeNo !== '') {
            $query->where('order_id', $outTradeNo);
        } else {
            $query->where('id', $id);
        }
        $order = $query->find();

        if (!$order) {
            return json(['code' => 0, 'msg' => '订单不存在']);
        }

        if ((int)$order['status'] === 1) {
            // 兼容 san.html：code==1 表示已支付
            return json(['code' => 1, 'msg' => '支付成功', 'data' => $order]);
        }

        return json(['code' => 0, 'msg' => '等待支付', 'data' => $order]);
    }
    
    /**
     * 采集套餐购买记录
     */
    public function shopcjlist(Request $request)
    {
        $uid = $this->getCurrentUserUid();
        [$current_page, $limit] = $this->safePageParams($request);

        if ($uid === '') {
            return $this->emptyPaginateResponse($current_page, $limit);
        }

        try {
            // 优先使用 cmsapi_id 关联
            $list = Db::name('shop_time')
                ->alias('st')
                ->leftJoin('cmsapi c', 'st.cmsapi_id = c.cmsapi_id')
                ->where('st.uid', $uid)
                ->field('st.*,c.cmsapi_name')
                ->order('st.id', 'desc')
                ->paginate([
                    'list_rows' => $limit,
                    'page' => $current_page
                ]);
        } catch (\Throwable $e) {
            try {
                // 兼容部分库 cmsapi 主键为 id 的情况
                $list = Db::name('shop_time')
                    ->alias('st')
                    ->leftJoin('cmsapi c', 'st.cmsapi_id = c.id')
                    ->where('st.uid', $uid)
                    ->field('st.*,c.cmsapi_name')
                    ->order('st.id', 'desc')
                    ->paginate([
                        'list_rows' => $limit,
                        'page' => $current_page
                    ]);
            } catch (\Throwable $e2) {
                return $this->emptyPaginateResponse($current_page, $limit);
            }
        }

        return $this->paginateResponse($list, $current_page, $limit);
    }
    
    /**
     * 解析套餐购买记录
     */
    public function shoplist(Request $request)
    {
        $uid = $this->getCurrentUserUid();
        [$current_page, $limit] = $this->safePageParams($request);

        if ($uid === '') {
            return $this->emptyPaginateResponse($current_page, $limit);
        }

        try {
            // 兼容前端字段：name/fs/time/price
            $list = Db::name('shop_log')
                ->alias('l')
                ->leftJoin('shop s', 'l.shop_id = s.id')
                ->where('l.uid', $uid)
                ->field('l.uid,l.time,l.price,l.shop_name as name,s.fs')
                ->order('l.id', 'desc')
                ->paginate([
                    'list_rows' => $limit,
                    'page' => $current_page
                ]);
        } catch (\Throwable $e) {
            try {
                // 如果 shop 表或 fs 字段不存在，则只返回日志表
                $list = Db::name('shop_log')
                    ->where('uid', $uid)
                    ->order('id', 'desc')
                    ->paginate([
                        'list_rows' => $limit,
                        'page' => $current_page
                    ]);
            } catch (\Throwable $e2) {
                return $this->emptyPaginateResponse($current_page, $limit);
            }
        }

        return $this->paginateResponse($list, $current_page, $limit);
    }
    
    /**
     * 解析接口分组列表
     */
    public function jsonglist(Request $request)
    {
        $current_page = (int) $request->param('current_page', 1);
        $limit = (int) $request->param('limit', 10);

        try {
            $list = Db::name('json_group')
                ->order('id', 'asc')
                ->paginate([
                    'list_rows' => $limit,
                    'page' => $current_page
                ]);

                    return $this->paginateResponse($list, $current_page, $limit);
        } catch (\Throwable $e) {
            // 兼容：部分数据库未创建 json_group 表
            return json([
                'code' => 0,
                'msg' => 'success',
                'data' => [
                    'data' => [
                        'total' => 0,
                        'data' => []
                    ]
                ]
            ]);
        }
    }
    
    /**
     * 解析接口列表
     */
    public function jsonslist(Request $request)
    {
        $current_page = (int) $request->param('current_page', 1);
        $limit = (int) $request->param('limit', 10);
        $group_id = $request->param('group_id');
        
        $where = [];
        if (!empty($group_id)) {
            $where['group_id'] = $group_id;
        }
        
        $list = Db::name('json')
            ->where($where)
            ->order('id', 'asc')
            ->paginate([
                'list_rows' => $limit,
                'page' => $current_page
            ]);
        
                return $this->paginateResponse($list, $current_page, $limit);
    }
    
    /**
     * 发送验证码
     */
    public function sendcode(Request $request)
    {
        $email = $request->param('email');
        $type = $request->param('type', 'register');
        
        if (empty($email)) {
            return json(['code' => 1, 'msg' => '邮箱不能为空']);
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return json(['code' => 1, 'msg' => '邮箱格式不正确']);
        }
        
        // 使用 Cache 做频率限制（基于 IP+邮箱），防止清除 Cookie 绕过
        $rateLimitKey = 'code_limit_' . md5($request->ip() . $email);
        if (\think\facade\Cache::get($rateLimitKey)) {
            return json(['code' => 1, 'msg' => '请60秒后再试']);
        }
        
        // 根据类型检查邮箱
        $user = Db::name('user')->where('email', $email)->find();
        
        if ($type == 'register' && $user) {
            return json(['code' => 1, 'msg' => '该邮箱已被注册']);
        }
        
        if ($type == 'reset' && !$user) {
            return json(['code' => 1, 'msg' => '该邮箱未注册']);
        }
        
        // 生成验证码
        $code = random_int(100000, 999999);
        session('email_code', (string)$code);
        session('email_code_email', $email);
        session('email_code_time', time());
        \think\facade\Cache::set($rateLimitKey, 1, 60);
        
        // 发送邮件
        try {
            $setting = Setting::find(1);
            $siteName = ($setting['name'] ?? '') ?: '视频解析系统';
            $subject = $siteName . ' - 验证码';
            $body = "您的验证码是：{$code}，有效期5分钟，请勿泄露给他人。";
            
            // 调用邮件发送
            $mailConfig = [
                'host' => $setting['mail_host'] ?? ($setting['emailHost'] ?? ''),
                'port' => $setting['mail_port'] ?? ($setting['emailport'] ?? ''),
                'username' => $setting['mail_username'] ?? ($setting['emailuser'] ?? ''),
                'password' => $setting['mail_password'] ?? ($setting['emailpass'] ?? ''),
                'from' => ($setting['mail_from'] ?? '') ?: ($setting['emailuser'] ?? ($setting['mail_username'] ?? '')),
                'fromname' => $siteName
            ];
            
            if (empty($mailConfig['host'])) {
                // 邮件未配置，直接返回成功（开发模式）
                return json(['code' => 0, 'msg' => '验证码已发送']);
            }
            
            // 此处应调用实际的邮件发送逻辑
            return json(['code' => 0, 'msg' => '验证码已发送']);
            
        } catch (\Exception $e) {
            return json(['code' => 1, 'msg' => '发送失败，请稍后重试']);
        }
    }

    public function codes(Request $request)
    {
        return $this->sendcode($request);
    }

    /**
     * 兼容前端 /ajax/code
     */
    public function code(Request $request)
    {
        return $this->sendcode($request);
    }
    
    /**
     * 用户登录
     */
    public function login(Request $request)
    {
        $user = $request->param('user');
        $pass = $request->param('pass');
        $captcha = $request->param('codes', $request->param('captcha'));
        
        if (empty($user) || empty($pass)) {
            return json(['code' => 1, 'msg' => '用户名和密码不能为空']);
        }
        
        // 验证码验证
        $setting = Setting::find(1);
        if (($setting['login_captcha'] ?? false) && !captcha_check($captcha)) {
            return json(['code' => 1, 'msg' => '验证码错误']);
        }
        
        // 查找用户
        $userData = Db::name('user')
            ->where('user', $user)
            ->find();
        
        if (!$userData) {
            return json(['code' => 1, 'msg' => '用户名或密码错误']);
        }
        
        $storedHash = (string)($userData['pass'] ?? '');
        if (!secure_password_verify((string)$pass, $storedHash)) {
            return json(['code' => 1, 'msg' => '用户名或密码错误']);
        }

        if (secure_password_needs_rehash($storedHash)) {
            Db::name('user')->where('uid', $userData['uid'])->update([
                'pass' => secure_password_hash((string)$pass)
            ]);
        }
        
        if (isset($userData['state']) && $userData['state'] != 1) {
            return json(['code' => 1, 'msg' => '账号已被禁用']);
        }
        
        Db::name('user')->where('uid', $userData['uid'])->update([
            'ip' => $request->ip()
        ]);
        
        // 只存储必要字段，避免密码哈希等敏感信息泄露
        session('user', [
            'id' => $userData['id'],
            'uid' => $userData['uid'],
            'user' => $userData['user'],
            'email' => $userData['email'] ?? '',
        ]);
        \think\facade\Session::set('user_id', $userData['id']);
        \think\facade\Session::set('user_uid', $userData['uid']);
        \think\facade\Session::set('user_name', $userData['user']);
        
        return json(['code' => 0, 'msg' => '登录成功']);
    }
    
    /**
     * 用户注册
     */
    public function reg(Request $request)
    {
        $user = $request->param('user');
        $pass = $request->param('pass');
        $email = $request->param('email');
        $code = $request->param('code');
        $captcha = $request->param('codes', $request->param('captcha'));
        $invite = $request->param('invite');
        $agent = $request->param('agent');
        $clientName = trim((string)$request->param('jxname', ''));
        
        if (empty($user) || empty($pass) || empty($email)) {
            return json(['code' => 1, 'msg' => '请填写完整信息']);
        }
        
        // 验证码验证
        $setting = Setting::find(1);
        if (($setting['reg_captcha'] ?? false) && !captcha_check($captcha)) {
            return json(['code' => 1, 'msg' => '验证码错误']);
        }
        
        // 邮箱验证码
        if ($setting['reg_email_code'] ?? false) {
            $sessionCode = session('email_code');
            $sessionEmail = session('email_code_email');
            
            if ((string)$code !== (string)$sessionCode || $email !== $sessionEmail) {
                return json(['code' => 1, 'msg' => '邮箱验证码错误']);
            }
        }
        
        // 用户名格式验证
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $user)) {
            return json(['code' => 1, 'msg' => '用户名格式不正确(3-20位字母数字下划线)']);
        }
        
        // 检查用户名是否存在
        $exists = Db::name('user')->where('user', $user)->find();
        if ($exists) {
            return json(['code' => 1, 'msg' => '用户名已存在']);
        }
        
        // 检查邮箱是否存在
        $emailExists = Db::name('user')->where('email', $email)->find();
        if ($emailExists) {
            return json(['code' => 1, 'msg' => '邮箱已被注册']);
        }
        
        // 处理用户邀请码（invite = 邀请人 user.uid），归属到 sj
        $inviteUid = 0;
        if (!empty($invite)) {
            $inviteUser = Db::name('user')->where('uid', $invite)->find();
            if ($inviteUser) {
                $inviteUid = (int) $invite;
            }
        }

        // 处理代理邀请码（agent = 代理 admin.id），归属到 sjuser
        $agentId = 0;
        if (!empty($agent)) {
            $agentAdmin = Db::name('admin')->where('id', (int) $agent)->find();
            if ($agentAdmin) {
                $agentId = (int) $agent;
            }
        }

        $newUid = $this->generateUid();

        // 注册用户
        $data = [
            'uid' => $newUid,
            'user' => $user,
            'pass' => secure_password_hash((string)$pass),
            'email' => $email,
            'client_name' => $clientName !== '' ? $clientName : $user . '的解析',
            'money' => 0,
            'points' => ($setting['reg_points'] ?? 0) ?: 0,
            'way' => ($setting['default_way'] ?? '') ?: '包点',
            'sj' => $inviteUid,
            'sjuser' => $agentId > 0 ? $agentId : null,
            'time' => date('Y-m-d H:i:s'),
            'ip' => $request->ip()
        ];

        Db::startTrans();
        try {
            $uid = Db::name('user')->insertGetId($data);

            if (!$uid) {
                Db::rollback();
                return json(['code' => 1, 'msg' => '注册失败，请稍后重试']);
            }

            // 清除验证码
            session('email_code', null);
            session('email_code_email', null);

            // 用户邀请：发放奖励并记录邀请
            if ($inviteUid > 0) {
                $reward = ($setting['invite_reward'] ?? 0) ?: 0;
                if ($reward > 0) {
                    Db::name('user')->where('uid', $inviteUid)->inc('money', $reward)->update();
                }

                // 记录邀请关系，供邀请列表/统计使用
                Db::name('invite')->insert([
                    'uid'        => (string) $inviteUid,
                    'inviteuser' => (string) $newUid,
                    'time'       => date('Y-m-d H:i:s'),
                    'pay'        => (string) $reward,
                    'ip'         => $request->ip(),
                    'admin_id'   => $agentId,
                ]);
            }

            Db::commit();
            return json(['code' => 0, 'msg' => '注册成功']);
        } catch (\Throwable $e) {
            Db::rollback();
            \think\facade\Log::error('用户注册失败: ' . $e->getMessage());
            return json(['code' => 1, 'msg' => '注册失败，请稍后重试']);
        }
    }
    
    /**
     * 重置密码
     */
    public function reset(Request $request)
    {
        $email = $request->param('email');
        $code = $request->param('code');
        $pass = $request->param('pass');
        
        if (empty($email) || empty($code) || empty($pass)) {
            return json(['code' => 1, 'msg' => '请填写完整信息']);
        }
        
        // 验证邮箱验证码
        $sessionCode = session('email_code');
        $sessionEmail = session('email_code_email');
        
        if ((string)$code !== (string)$sessionCode || $email !== $sessionEmail) {
            return json(['code' => 1, 'msg' => '验证码错误']);
        }
        
        // 查找用户
        $user = Db::name('user')->where('email', $email)->find();
        if (!$user) {
            return json(['code' => 1, 'msg' => '用户不存在']);
        }
        
        // 更新密码
        $result = Db::name('user')
            ->where('uid', $user['uid'])
            ->update(['pass' => secure_password_hash((string)$pass)]);
        
        if ($result !== false) {
            session('email_code', null);
            session('email_code_email', null);
            return json(['code' => 0, 'msg' => '密码重置成功']);
        }
        
        return json(['code' => 1, 'msg' => '重置失败，请稍后重试']);
    }
    
    public function authset(Request $request)
    {
        return $this->reset($request);
    }

    /**
     * 黑名单列表
     */
    public function heilist(Request $request)
    {
        $uid = $this->getCurrentUserUid();
        $current_page = (int) $request->param('current_page', 1);
        $limit = (int) $request->param('limit', 10);

        if ($uid === '') {
            return $this->emptyPaginateResponse($current_page, $limit);
        }

        $list = Db::name('blacklist')
            ->where('user_id', $uid)
            ->order('id', 'desc')
            ->paginate([
                'list_rows' => $limit,
                'page' => $current_page
            ]);

        return $this->paginateResponse($list, $current_page, $limit);
    }
    
    /**
     * 求片列表
     */
    public function qiupianlist(Request $request)
    {
        $uid = $this->getCurrentUserUid();
        if ($uid === '') {
            return json(['total' => 0, 'data' => []]);
        }

        $page = max(1, (int)$request->param('page', 1));
        $pageSize = min(max(1, (int)$request->param('pageSize', 9)), 50);
        $keyword = trim((string)$request->param('keyword', ''));
        $status = trim((string)$request->param('status', ''));
        $type = trim((string)$request->param('type', ''));

        $query = Db::name('qiupian')->where('user_id', $uid);
        if ($keyword !== '') {
            // 优先按 install.sql 的 demand 字段；旧表才用 content
            try {
                $query->whereLike('name|demand', "%{$keyword}%");
            } catch (\Throwable $e) {
                $query->whereLike('name|content', "%{$keyword}%");
            }
        }
        if ($status !== '') {
            try {
                $query->where('status', (int)$status);
            } catch (\Throwable $e) {
                $query->where('state', (int)$status);
            }
        }
        if ($type !== '') {
            $query->where('type', $type);
        }

        $total = (int)$query->count();
        $rows = $query
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $data = [];
        foreach ($rows as $row) {
            $rowStatus = (int)($row['status'] ?? ($row['state'] ?? 1));
            $createdAt = (string)($row['time'] ?? ($row['create_time'] ?? ''));

            $data[] = [
                'id' => $row['id'],
                'name' => $row['name'] ?? ($row['title'] ?? ''),
                'type' => $row['type'] ?? '',
                'year' => $row['year'] ?? '',
                'describe' => $row['describe'] ?? ($row['description'] ?? ''),
                'content' => $row['demand'] ?? ($row['content'] ?? ''),
                'status' => $rowStatus,
                'reason' => $row['reason'] ?? '',
                'img' => $this->formatQiupianImage($row['img'] ?? ''),
                'number' => $row['number'] ?? ($row['id'] ?? ''),
                'category' => $this->getQiupianTypeText($row['type'] ?? ''),
                'data' => $createdAt,
                // 前端展示占位
                'progress' => $rowStatus === 1 ? 60 : 100,
                'expectedCompletion' => '',
                'processingDays' => 0,
            ];
        }

        return json(['total' => $total, 'data' => $data]);
    }

    /**
     * 公共库（资源替换）列表
     */
    public function reurlPublicList(Request $request)
    {
        [$current_page, $limit] = $this->safePageParams($request);

        try {
            // 优先仅展示已审核（examine=1），字段不存在则展示全部
            $list = Db::name('reurl')
                ->where('examine', 1)
                ->order('id', 'desc')
                ->paginate([
                    'list_rows' => $limit,
                    'page' => $current_page
                ]);
        } catch (\Throwable $e) {
            $list = Db::name('reurl')
                ->order('id', 'desc')
                ->paginate([
                    'list_rows' => $limit,
                    'page' => $current_page
                ]);
        }

                return $this->paginateResponse($list, $current_page, $limit);
    }

    /**
     * 私人库（资源替换）列表
     */
    public function reurlPrivateList(Request $request)
    {
        $uid = $this->getCurrentUserUid();
        [$current_page, $limit] = $this->safePageParams($request);

        if ($uid === '') {
            return $this->emptyPaginateResponse($current_page, $limit);
        }

        $list = Db::name('reurl')
            ->where('auth', $uid)
            ->order('id', 'desc')
            ->paginate([
                'list_rows' => $limit,
                'page' => $current_page
            ]);

        return $this->paginateResponse($list, $current_page, $limit);
    }
}
