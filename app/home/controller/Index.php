<?php
declare(strict_types=1);

namespace app\home\controller;

use app\HomeController;
use app\common\model\Setting;
use app\common\model\News;
use app\common\model\Users;
use think\facade\View;
use think\facade\Db;
use think\facade\Session;
use think\captcha\facade\Captcha;

/**
 * 前台首页控制器
 */
class Index extends HomeController
{
    // 无需登录验证的方法
    protected $noNeedLogin = ['index', 'login', 'register', 'reset', 'captcha', 'quick', 'quickbd'];
    
    /**
     * 首页
     */
    public function index()
    {
        // 获取系统设置
        $setting = Setting::where('id', 1)->find();
        $template = $setting['template'] ?? 'index1';
        
        // 获取公告列表（兼容无 status 字段的情况）
        try {
            $news = News::where('status', 1)
                ->order('id', 'desc')
                ->limit(5)
                ->select();
        } catch (\Exception $e) {
            // 如果 status 字段不存在，获取所有公告
            $news = News::order('id', 'desc')
                ->limit(5)
                ->select();
        }
        
        View::assign([
            'setting' => $setting,
            'news' => $news
        ]);
        
        return View::fetch($template . '/index');
    }
    
    /**
     * 登录页面
     */
    public function login()
    {
        // 如果已登录，跳转到用户中心
        if (session('user_id')) {
            return redirect((string)url('/user/index'));
        }
        
        // 获取系统设置
        $setting = Setting::where('id', 1)->find();
        $template = $setting['template'] ?? 'index1';
        
        return View::fetch($template . '/login');
    }
    
    /**
     * 注册页面
     */
    public function register()
    {
        // 如果已登录，跳转到用户中心
        if (session('user_id')) {
            return redirect((string)url('/user/index'));
        }
        
        // 获取系统设置
        $setting = Setting::where('id', 1)->find();
        $template = $setting['template'] ?? 'index1';
        
        // 获取邀请码
        $invite = input('invite', '');
        $agent = input('agent', '');
        View::assign('invite', $invite);
        View::assign('agent', $agent);
        
        return View::fetch($template . '/register');
    }
    
    /**
     * 找回密码页面
     */
    public function reset()
    {
        return View::fetch('reset');
    }
    
    /**
     * 快捷登录中转页面
     */
    public function quick()
    {
        // 获取系统设置
        $setting = Setting::where('id', 1)->find();
        $template = $setting['template'] ?? 'index1';

        View::assign([
            'type' => Session::get('quick_type', ''),
            'state' => request()->param('state', ''),
            'code' => request()->param('code', ''),
        ]);

        return View::fetch($template . '/quick');
    }

    /**
     * 快捷绑定/注册提交
     */
    public function quickbd(\think\Request $request)
    {
        if (!$request->isPost()) {
            return json(['code' => 1, 'msg' => '请求方式错误']);
        }

        $type = (string) Session::get('quick_type', '');
        $openid = trim((string) Session::get('quick_openid', ''));
        $quickUserInfo = Session::get('quick_userinfo', []);

        if ($type === '' || $openid === '') {
            return json(['code' => 1, 'msg' => '快捷登录信息已失效，请重新授权']);
        }

        $bindField = $type === 'qq' ? 'access_token' : 'access_token_wx';
        $openidField = $type === 'qq' ? 'openid' : 'openid_wx';
        $mode = (int) $request->post('quick', 1);
        $setting = Setting::where('id', 1)->find();

        $alreadyBoundUser = Db::name('user')->where($bindField, $openid)->find();
        if ($alreadyBoundUser) {
            $this->loginQuickUser($alreadyBoundUser);
            $this->clearQuickSession();
            return json(['code' => 0, 'msg' => '登录成功']);
        }

        Db::startTrans();
        try {
            if ($mode === 2) {
                $username = trim((string) $request->post('user', ''));
                $password = trim((string) $request->post('pass', ''));

                if ($username === '' || $password === '') {
                    Db::rollback();
                    return json(['code' => 1, 'msg' => '请输入账号和密码']);
                }

                $user = Db::name('user')->where('user', $username)->find();
                $storedHash = (string) ($user['pass'] ?? '');
                if (!$user || !secure_password_verify($password, $storedHash)) {
                    Db::rollback();
                    return json(['code' => 1, 'msg' => '账号或密码错误']);
                }

                if (secure_password_needs_rehash($storedHash)) {
                    Db::name('user')->where('id', $user['id'])->update([
                        'pass' => secure_password_hash($password)
                    ]);
                }

                $boundValue = (string) ($user[$bindField] ?? '');
                if ($boundValue !== '' && $boundValue !== $openid) {
                    Db::rollback();
                    return json(['code' => 1, 'msg' => '该账号已绑定其他快捷登录']);
                }

                $updateData = [$bindField => $openid];
                try {
                    Db::name('user')->where('id', $user['id'])->update($updateData + [$openidField => $openid]);
                } catch (\Throwable $e) {
                    Db::name('user')->where('id', $user['id'])->update($updateData);
                }

                $user = Db::name('user')->where('id', $user['id'])->find();
                Db::commit();

                $this->loginQuickUser($user ?: []);
                $this->clearQuickSession();
                return json(['code' => 0, 'msg' => '绑定成功']);
            }

            $email = trim((string) $request->post('email', ''));
            $clientName = trim((string) $request->post('jxname', ''));

            if ($email === '' || $clientName === '') {
                Db::rollback();
                return json(['code' => 1, 'msg' => '请填写完整信息']);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Db::rollback();
                return json(['code' => 1, 'msg' => '邮箱格式不正确']);
            }

            $emailExists = Db::name('user')->where('email', $email)->find();
            if ($emailExists) {
                Db::rollback();
                return json(['code' => 1, 'msg' => '该邮箱已存在，请选择老用户绑定']);
            }

            $username = $this->generateQuickUsername($email, $clientName);
            $now = date('Y-m-d H:i:s');
            $randomPassword = bin2hex(random_bytes(16));
            $insertData = [
                'uid' => $this->generateQuickUid(),
                'user' => $username,
                'pass' => secure_password_hash($randomPassword),
                'email' => $email,
                'client_name' => $clientName,
                'money' => 0,
                'points' => (int) (($setting['reg_points'] ?? 0) ?: 0),
                'way' => ($setting['default_way'] ?? '') ?: '包点',
                'time' => $now,
                'ip' => $request->ip(),
                $bindField => $openid,
            ];

            try {
                $insertData[$openidField] = $openid;
                $userId = Db::name('user')->insertGetId($insertData);
            } catch (\Throwable $e) {
                unset($insertData[$openidField]);
                $userId = Db::name('user')->insertGetId($insertData);
            }

            $user = Db::name('user')->where('id', $userId)->find();
            if ($user) {
                $nickname = '';
                if (is_array($quickUserInfo)) {
                    $nickname = (string) ($quickUserInfo['nickname'] ?? $quickUserInfo['nickName'] ?? '');
                }
                if ($nickname !== '') {
                    $qqValue = trim((string) ($user['qq'] ?? ''));
                    if ($qqValue === '') {
                        Db::name('user')->where('id', $userId)->update(['qq' => $nickname]);
                        $user['qq'] = $nickname;
                    }
                }
            }

            Db::commit();

            $this->loginQuickUser($user ?: []);
            $this->clearQuickSession();
            return json(['code' => 0, 'msg' => '注册并绑定成功，请在个人中心设置登录密码']);
        } catch (\Throwable $e) {
            Db::rollback();
            return json(['code' => 1, 'msg' => '操作失败，请稍后重试']);
        }
    }

    /**
     * 验证码输出
     */
    public function captcha()
    {
        return Captcha::create();
    }

    /**
     * 快捷登录写入会话
     */
    protected function loginQuickUser(array $user): void
    {
        if (empty($user)) {
            return;
        }

        session('user', [
            'id' => $user['id'] ?? 0,
            'uid' => $user['uid'] ?? 0,
            'user' => $user['user'] ?? '',
            'email' => $user['email'] ?? '',
        ]);
        Session::set('user_id', $user['id'] ?? 0);
        Session::set('user_uid', $user['uid'] ?? 0);
        Session::set('user_name', $user['user'] ?? '');
    }

    /**
     * 清理快捷登录会话
     */
    protected function clearQuickSession(): void
    {
        Session::delete('quick_type');
        Session::delete('quick_openid');
        Session::delete('quick_access_token');
        Session::delete('quick_userinfo');
    }

    /**
     * 生成快捷注册用户名
     */
    protected function generateQuickUsername(string $email, string $clientName): string
    {
        $base = preg_replace('/[^a-zA-Z0-9_]/', '', (string) strstr($email, '@', true));
        if ($base === '') {
            $base = preg_replace('/[^a-zA-Z0-9_]/', '', $clientName);
        }
        if ($base === '') {
            $base = 'quickuser';
        }
        $base = strtolower(substr($base, 0, 12));
        if (strlen($base) < 3) {
            $base .= 'user';
        }

        for ($i = 0; $i < 50; $i++) {
            $username = $base . random_int(1000, 9999);
            if (!Db::name('user')->where('user', $username)->find()) {
                return $username;
            }
        }

        return 'quick' . date('His') . random_int(100, 999);
    }

    /**
     * 生成快捷注册 UID
     */
    protected function generateQuickUid(): int
    {
        for ($i = 0; $i < 100; $i++) {
            $uid = random_int(100000, 999999);
            if (!Db::name('user')->where('uid', $uid)->find()) {
                return $uid;
            }
        }

        return random_int(1000000, 9999999);
    }
}
