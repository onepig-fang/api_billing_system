<?php

declare(strict_types=1);

namespace app\common\library;

use app\common\model\Users;
use app\common\model\Setting;
use think\facade\Session;
use think\facade\Request;

/**
 * 授权验证类 - 本地验证版本
 * 移除云端验证，改为本地验证
 */
class Auth
{
    /**
     * 当前登录用户
     */
    protected $user = null;
    
    /**
     * 用户ID
     */
    protected $userId = 0;
    
    /**
     * 错误信息
     */
    protected $error = '';
    
    /**
     * 单例实例
     */
    protected static $instance = null;
    
    /**
     * 获取单例
     */
    public static function instance(): Auth
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 构造函数
     */
    protected function __construct()
    {
        $this->init();
    }
    
    /**
     * 初始化
     */
    protected function init(): void
    {
        $userId = Session::get('user_id');
        if ($userId) {
            $this->userId = $userId;
            $this->user = Users::where('id', $userId)->find();
        }
    }
    
    /**
     * 检查系统授权 - 本地验证，永久有效
     */
    public function checkAuth(): array
    {
        return [
            'code' => 1,
            'msg' => '授权有效',
            'data' => [
                'status' => 1,
                'expire_time' => '永久',
                'domain' => Request::host()
            ]
        ];
    }
    
    /**
     * 验证用户是否登录
     */
    public function isLogin(): bool
    {
        return $this->userId > 0 && $this->user !== null;
    }
    
    /**
     * 获取当前登录用户
     */
    public function getUser()
    {
        return $this->user;
    }
    
    /**
     * 获取用户ID
     */
    public function getUserId(): int
    {
        return $this->userId;
    }
    
    /**
     * 用户登录
     */
    public function login(string $username, string $password): bool
    {
        $user = Users::where('user', $username)->find();
        
        if (!$user) {
            $this->error = '用户不存在';
            return false;
        }
        
        if ($user->state != 1) {
            $this->error = '账号已被封禁';
            return false;
        }
        
        $storedHash = (string)$user->pass;
        if (!secure_password_verify($password, $storedHash)) {
            $this->error = '密码错误';
            return false;
        }

        if (secure_password_needs_rehash($storedHash)) {
            $user->pass = secure_password_hash($password);
            $user->save();
        }

        // 防会话固定：提权前更换会话 ID，避免攻击者预置的 PHPSESSID 变成已认证会话
        // 传 true 同时销毁旧会话数据，使旧 ID 彻底失效
        Session::regenerate(true);

        // 登录成功，设置session
        Session::set('user_id', $user->id);
        Session::set('user_uid', $user->uid);
        Session::set('user_name', $user->user);
        
        $this->user = $user;
        $this->userId = $user->id;
        
        return true;
    }
    
    /**
     * 用户注册
     */
    public function register(array $data): bool
    {
        // 检查用户名是否存在
        if (Users::where('user', $data['user'])->find()) {
            $this->error = '用户名已存在';
            return false;
        }
        
        // 检查邮箱是否存在
        if (Users::where('email', $data['email'])->find()) {
            $this->error = '邮箱已被注册';
            return false;
        }
        
        // 生成UID
        $uid = $this->generateUid();
        
        // 生成密钥
        $my = bin2hex(random_bytes(16));
        
        $user = new Users();
        $user->uid = $uid;
        $user->user = $data['user'];
        $user->pass = secure_password_hash((string)$data['pass']);
        $user->email = $data['email'];
        $user->my = $my;
        $user->client_name = $data['jxname'] ?? $data['user'] . '的解析';
        $user->state = 1;
        $user->time = date('Y-m-d H:i:s');
        $user->ip = Request::ip();
        $user->points = 0;
        $user->money = 0;
        $user->way = '包点';
        $user->daily_limit = 0;
        $user->daynum = 0;
        $user->daytime = date('Y-m-d H:i:s');
        
        // 处理邀请关系
        if (isset($data['invite_uid']) && $data['invite_uid']) {
            $user->sj = $data['invite_uid'];
        }
        
        if (!$user->save()) {
            $this->error = '注册失败';
            return false;
        }

        // 防会话固定：与登录路径保持一致，自动登录前更换会话 ID
        Session::regenerate(true);

        // 自动登录
        Session::set('user_id', $user->id);
        Session::set('user_uid', $user->uid);
        Session::set('user_name', $user->user);
        
        $this->user = $user;
        $this->userId = $user->id;
        
        return true;
    }
    
    /**
     * 生成唯一UID
     */
    protected function generateUid(): int
    {
        $maxAttempts = 100;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $uid = random_int(100000, 999999);
            if (!Users::where('uid', $uid)->find()) {
                return $uid;
            }
        }
        return random_int(1000000, 9999999);
    }
    
    /**
     * 退出登录
     */
    public function logout(): bool
    {
        Session::delete('user_id');
        Session::delete('user_uid');
        Session::delete('user_name');
        $this->user = null;
        $this->userId = 0;
        return true;
    }
    
    /**
     * 验证用户IP授权
     */
    public function checkIpAuth(string $ip, int $userId = 0): bool
    {
        $userId = $userId ?: $this->userId;
        if (!$userId) {
            return false;
        }
        
        $user = Users::find($userId);
        if (!$user) {
            return false;
        }
        
        // 如果没有设置授权IP，则允许所有IP
        if (empty($user->auth_ip)) {
            return true;
        }
        
        // 检查IP是否在授权列表中
        $authIps = array_map('trim', explode(',', $user->auth_ip));
        return in_array($ip, $authIps);
    }
    
    /**
     * 检查用户套餐是否有效
     */
    public function checkPackage(int $userId = 0): array
    {
        $userId = $userId ?: $this->userId;
        if (!$userId) {
            return ['valid' => false, 'msg' => '用户未登录'];
        }
        
        $user = Users::find($userId);
        if (!$user) {
            return ['valid' => false, 'msg' => '用户不存在'];
        }

        // 检查账号状态
        if ($user->state != 1) {
            return ['valid' => false, 'msg' => '账号已被封禁'];
        }

        // 跨日重置每日调用次数（必须在日限校验之前，否则包月用户到达上限后会被永久锁死）
        $this->resetDailyNumIfNeeded($user);

        // 包月用户检查到期时间
        if ($user->way == '包月') {
            if ($user->bytime && strtotime($user->bytime) < time()) {
                return ['valid' => false, 'msg' => '套餐已过期'];
            }
            // 检查每日调用限制
            if ($user->fullnum > 0 && $user->daynum >= $user->fullnum) {
                return ['valid' => false, 'msg' => '今日调用次数已用完'];
            }
            return ['valid' => true, 'msg' => '套餐有效', 'type' => 'month'];
        }
        
        // 包点用户检查点数
        if ($user->way == '包点' || empty($user->way)) {
            if ($user->points <= 0) {
                return ['valid' => false, 'msg' => '点数不足'];
            }
            // 检查每日调用限制
            if ($user->daily_limit > 0 && $user->daynum >= $user->daily_limit) {
                return ['valid' => false, 'msg' => '今日调用次数已用完'];
            }
            return ['valid' => true, 'msg' => '套餐有效', 'type' => 'point'];
        }
        
        return ['valid' => false, 'msg' => '未知套餐类型'];
    }
    
    /**
     * 跨日重置每日调用次数
     * 当 daytime 不是今天时，将 daynum 归零并更新 daytime。
     * 同时同步内存中的 $user 对象，供后续校验使用。
     */
    protected function resetDailyNumIfNeeded(Users $user): void
    {
        $today = date('Y-m-d');
        $dayTime = $user->daytime ? date('Y-m-d', strtotime((string) $user->daytime)) : null;

        if ($dayTime !== $today) {
            Users::where('id', $user->id)->update([
                'daynum'  => 0,
                'daytime' => date('Y-m-d H:i:s'),
            ]);
            // 同步内存对象，避免同一请求内使用陈旧的 daynum
            $user->daynum = 0;
            $user->daytime = date('Y-m-d H:i:s');
        }
    }

    /**
     * 扣除用户点数
     */
    public function deductPoints(int $points = 1, int $userId = 0): bool
    {
        $userId = $userId ?: $this->userId;
        if (!$userId) {
            return false;
        }
        
        $user = Users::find($userId);
        if (!$user) {
            return false;
        }
        
        // 使用数据库原子操作防止并发竞态
        if ($user->way == '包点' || empty($user->way)) {
            $affected = Users::where('id', $userId)
                ->where('points', '>=', $points)
                ->dec('points', $points)
                ->inc('daynum', 1)
                ->update();
            return $affected > 0;
        }

        if ($user->way == '包月') {
            $fullnum = (int) ($user->fullnum ?? 0);
            if ($fullnum > 0) {
                $affected = Users::where('id', $userId)
                    ->where('daynum', '<', $fullnum)
                    ->inc('daynum', 1)
                    ->update();
                return $affected > 0;
            }

            // fullnum<=0 表示不限每日次数，仅累计调用量
            Users::where('id', $userId)->inc('daynum', 1)->update();
            return true;
        }

        // 未识别的套餐类型一律拒绝，避免计费绕过（与 checkPackage、deductUserQuota 保持一致）
        return false;
    }
    
    /**
     * 获取错误信息
     */
    public function getError(): string
    {
        return $this->error;
    }
    
    /**
     * 设置错误信息
     */
    public function setError(string $error): void
    {
        $this->error = $error;
    }
}
