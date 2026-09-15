<?php
declare(strict_types=1);

namespace app\home\controller;

use app\HomeController;
use app\common\library\LotteryService;
use app\common\model\Users;
use app\common\model\Setting;
use app\common\model\News;
use app\common\model\Shops;
use app\common\model\Userlogs;
use app\common\model\Recharge;
use app\common\model\Blacklist;
use app\common\model\Json;
use app\common\model\Qiupian;
use think\facade\View;
use think\facade\Db;
use think\facade\Cache;
use think\facade\Log;
use think\facade\Session;
use think\Request;

/**
 * 用户中心控制器
 */
class User extends HomeController
{
    /**
     * 用户中心框架页
     */
    public function index()
    {
        $user = $this->getUser();
        if (!$user) {
            return redirect((string)url('/index/login'));
        }
        View::assign('user', $user);
        return View::fetch('user/inside1/index');
    }
    
    /**
     * 用户中心主页
     */
    public function main()
    {
        $user = $this->getUser();
        if (!$user) {
            return redirect((string)url('/index/login'));
        }
        
        // 统计今日调用次数
        $todayStart = date('Y-m-d 00:00:00');
        $todayCount = Userlogs::where('uid', $user['uid'])
            ->where('intime', '>=', $todayStart)
            ->count();
        
        // 统计总调用次数
        $totalCount = Userlogs::where('uid', $user['uid'])->count();

        // 统计充值次数
        $payc = Recharge::where('user_id', $user['uid'])->count();

        // 服务剩余天数（main.html 使用 daysLeft）
        $daysLeft = 0;
        $bytime = trim((string)($user['bytime'] ?? ''));
        if ($bytime !== '') {
            $byTimestamp = strtotime($bytime . ' 23:59:59');
            if ($byTimestamp !== false) {
                $daysLeft = (int) max(0, ceil(($byTimestamp - time()) / 86400));
            }
        }

        // 采集套餐授权/有效期列表（main.html 使用 volist name="list"）
        $list = [];
        try {
            // 优先按 cmsapi_id 关联
            $list = Db::name('shop_time')
                ->alias('st')
                ->leftJoin('cmsapi c', 'st.cmsapi_id = c.cmsapi_id')
                ->where('st.uid', $user['uid'])
                ->field('st.end_time,st.cmsapi_id,c.cmsapi_name,st.cj_auth_ip')
                ->order('st.id', 'desc')
                ->select()
                ->toArray();
        } catch (\Throwable $e) {
            try {
                // 兼容部分库 cmsapi 主键为 id 的情况
                $list = Db::name('shop_time')
                    ->alias('st')
                    ->leftJoin('cmsapi c', 'st.cmsapi_id = c.id')
                    ->where('st.uid', $user['uid'])
                    ->field('st.end_time,st.cmsapi_id,c.cmsapi_name,st.cj_auth_ip')
                    ->order('st.id', 'desc')
                    ->select()
                    ->toArray();
            } catch (\Throwable $e2) {
                $list = [];
            }
        }

        // API 调用量图表数据（今日按时间段统计）
        $apiBuckets = array_fill(0, 7, 0);
        try {
            $rows = Userlogs::where('uid', $user['uid'])
                ->where('intime', 'between', [$todayStart, date('Y-m-d 23:59:59')])
                ->fieldRaw('HOUR(intime) as hour, COUNT(*) as total')
                ->group('hour')
                ->select()
                ->toArray();

            foreach ($rows as $row) {
                $hour = (int)($row['hour'] ?? 0);
                if ($hour < 4) {
                    $index = 0;
                } elseif ($hour < 8) {
                    $index = 1;
                } elseif ($hour < 12) {
                    $index = 2;
                } elseif ($hour < 16) {
                    $index = 3;
                } elseif ($hour < 20) {
                    $index = 4;
                } elseif ($hour < 23) {
                    $index = 5;
                } else {
                    $index = 6;
                }
                $apiBuckets[$index] += (int)($row['total'] ?? 0);
            }
        } catch (\Throwable $e) {
            $apiBuckets = array_fill(0, 7, 0);
        }
        $api = implode(',', $apiBuckets);

        // 进度条百分比计算
        $pointsPercent = 0;
        $totalPoints = (int)($user['points'] ?? 0) + $todayCount;
        if ($totalPoints > 0) {
            $pointsPercent = (int) round(((int)($user['points'] ?? 0)) * 100 / $totalPoints);
        }
        $daysPercent = 0;
        $byDays = (int)($user['dd'] ?? 0);
        if ($byDays > 0) {
            $daysPercent = (int) round($daysLeft * 100 / $byDays);
        }

        View::assign([
            'user' => $user,
            'todayCount' => $todayCount,
            'totalCount' => $totalCount,
            'payc' => $payc,
            'daysLeft' => $daysLeft,
            'list' => $list,
            'api' => $api,
            'pointsPercent' => $pointsPercent,
            'daysPercent' => $daysPercent,
        ]);
        
        return View::fetch('user/inside1/main');
    }
    
    /**
     * 账户信息页面
     */
    public function inf()
    {
        $user = $this->getUser();
        View::assign('user', $user);
        return View::fetch('user/inside1/inf');
    }
    
    /**
     * 在线充值页面
     */
    public function recharge()
    {
        $user = $this->getUser();
        
        // 获取支付方式
        $setting = Setting::where('id', 1)->find();

        // 累计充值次数
        $payc = Recharge::where('user_id', $user['uid'])->count();
        
        View::assign([
            'user' => $user,
            'setting' => $setting,
            'payc' => $payc
        ]);
        
        return View::fetch('user/inside1/recharge');
    }
    
    /**
     * 卡密充值页面
     */
    public function card()
    {
        $user = $this->getUser();

        // 累计充值次数
        $payc = Recharge::where('user_id', $user['uid'])->count();

        View::assign([
            'user' => $user,
            'payc' => $payc
        ]);
        return View::fetch('user/inside1/card');
    }
    
    /**
     * 邀请链接页面
     */
    public function invite()
    {
        $user = $this->getUser();
        
        // 生成邀请链接
        $domain = request()->domain();
        $inviteUrl = $domain . '/index/register?invite=' . $user['uid'];
        
        // 获取邀请统计
        $inviteCount = Users::where('sj', $user['uid'])->count();
        
        View::assign([
            'user' => $user,
            'inviteUrl' => $inviteUrl,
            'inviteCount' => $inviteCount
        ]);
        
        return View::fetch('user/inside1/invite');
    }
    
    /**
     * 授权配置页面
     */
    public function auth()
    {
        $user = $this->getUser();
        View::assign('user', $user);
        return View::fetch('user/inside1/auth');
    }

    public function edit()
    {
        $user = $this->getUser();
        if (!$user) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }

        $method = trim((string)request()->post('method', ''));
        if ($method === '') {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        $data = [];
        // 是否需要同步采集授权IP到 shop_time（采集接口校验以 shop_time.cj_auth_ip 为数据源）
        $syncCjAuthIp = false;
        $cjAuthIp = '';
        if ($method === 'auth_ip') {
            $data['auth_ip'] = trim((string)request()->post('auth_ip', ''));
        } elseif ($method === 'cj_ip') {
            $cjAuthIp = trim((string)request()->post('cj_auth_ip', ''));
            // 校验IP格式（留空表示不限制）
            if ($cjAuthIp !== '') {
                foreach (array_map('trim', explode(',', $cjAuthIp)) as $singleIp) {
                    if ($singleIp === '' || !filter_var($singleIp, FILTER_VALIDATE_IP)) {
                        return json(['code' => 1, 'msg' => 'IP格式不正确: ' . $singleIp]);
                    }
                }
            }
            $data['cj_auth_ip'] = $cjAuthIp;
            $syncCjAuthIp = true;
        } elseif ($method === 'password') {
            $password = trim((string)request()->post('password', ''));
            if (strlen($password) < 6) {
                return json(['code' => 1, 'msg' => '密码至少6位']);
            }
            $data['pass'] = secure_password_hash($password);
        } else {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        try {
            Db::name('user')->where('uid', $user['uid'])->update($data);

            // 采集授权IP需同步到该用户所有采集接口的 shop_time 记录，
            // 否则采集接口校验（Cms::index 读 shop_time.cj_auth_ip）无法生效
            if ($syncCjAuthIp) {
                Db::name('shop_time')
                    ->where('uid', $user['uid'])
                    ->update(['cj_auth_ip' => $cjAuthIp]);
            }

            return json(['code' => 0, 'msg' => '更新成功']);
        } catch (\Throwable $e) {
            return json(['code' => 1, 'msg' => '更新失败，请稍后再试']);
        }
    }
    
    /**
     * 黑户管理页面
     */
    public function hei()
    {
        $user = $this->getUser();
        
        // 获取黑名单列表
        $blacklist = Blacklist::where('user_id', $user['uid'])
            ->order('id', 'desc')
            ->select();
        
        View::assign([
            'user' => $user,
            'blacklist' => $blacklist
        ]);
        
        return View::fetch('user/inside1/hei');
    }
    
    /**
     * 客户端配置页面
     */
    public function api()
    {
        $user = $this->getUser();
        if (!$user) {
            return redirect((string)url('/index/login'));
        }

        // 获取解析配置（兼容无 status 字段的情况）
        try {
            $json = Json::where('status', 1)->select();
        } catch (\Exception $e) {
            $json = Json::select();
        }

        View::assign([
            'user' => $user,
            'json' => $json
        ]);

        return View::fetch('user/inside1/api');
    }

    /**
     * 保存客户端配置/其他信息
     */
    public function update(Request $request)
    {
        $user = $this->getUser();
        if (!$user) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }

        if (!$request->isPost()) {
            return json(['code' => 1, 'msg' => '请求方式错误']);
        }

        $method = trim((string) $request->post('method', ''));
        if (!in_array($method, ['khd', 'khd2'], true)) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        if ($method === 'khd') {
            $clientName = trim((string) $request->post('jxname', ''));
            if ($clientName === '') {
                return json(['code' => 1, 'msg' => '平台名不能为空']);
            }

            $dmkupass = trim((string) $request->post('dmkupass', '0'));
            if (!in_array($dmkupass, ['0', '1'], true)) {
                return json(['code' => 1, 'msg' => '弹幕开关只能填写0或1']);
            }

            $defense = trim((string) $request->post('defense', '1'));
            if (!in_array($defense, ['0', '1'], true)) {
                return json(['code' => 1, 'msg' => '反调试开关参数错误']);
            }

            $player = trim((string) $request->post('player', 'dm'));
            if ($player === '') {
                $player = 'dm';
            }

            $data = [
                'client_name' => $clientName,
                'img' => trim((string) $request->post('img', '')),
                'logo' => trim((string) $request->post('logo', '')),
                'button' => trim((string) $request->post('button', '')),
                'you_href' => trim((string) $request->post('you_href', '')),
                'dmkupass' => $dmkupass,
                'dmfirst' => trim((string) $request->post('dmfirst', '')),
                'defense' => $defense,
                'player' => $player,
            ];
        } else {
            $data = [
                'you_index' => trim((string) $request->post('cont', '')),
                'tj' => trim((string) $request->post('tj', '')),
                'byjx' => trim((string) $request->post('byjx', '')),
                'referer' => trim((string) $request->post('referer', '')),
            ];
        }

        try {
            Db::name('user')->where('uid', $user['uid'])->update($data);
            return json(['code' => 0, 'msg' => '保存成功']);
        } catch (\Throwable $e) {
            return json(['code' => 1, 'msg' => '保存失败，请稍后再试']);
        }
    }

    /**
     * 生成并下载 ART 播放器
     */
    public function dw(Request $request)
    {
        $user = $this->getUser();
        if (!$user) {
            return json(['success' => false, 'message' => '请先登录']);
        }

        if (!$request->isPost()) {
            return json(['success' => false, 'message' => '请求方式错误']);
        }

        $bfq = trim((string) $request->post('bfq', 'drbfq'));
        if ($bfq !== 'drbfq') {
            return json(['success' => false, 'message' => '暂不支持该播放器类型']);
        }

        $sourceDir = app()->getRootPath() . 'public' . DIRECTORY_SEPARATOR . 'drbfq';
        if (!is_dir($sourceDir)) {
            return json(['success' => false, 'message' => '播放器模板不存在']);
        }

        $buildRoot = app()->getRuntimePath() . 'player_builds' . DIRECTORY_SEPARATOR;
        $publicRoot = app()->getRootPath() . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'players' . DIRECTORY_SEPARATOR;
        if (!is_dir($buildRoot)) {
            @mkdir($buildRoot, 0755, true);
        }
        if (!is_dir($publicRoot)) {
            @mkdir($publicRoot, 0755, true);
        }

        $buildName = 'artplayer_' . $user['uid'] . '_' . date('YmdHis');
        $workDir = $buildRoot . $buildName;
        $zipPath = $publicRoot . $buildName . '.zip';

        try {
            $this->copyDirectory($sourceDir, $workDir);

            $configFile = $workDir . DIRECTORY_SEPARATOR . 'config.php';
            file_put_contents($configFile, $this->buildArtPlayerConfig($user));

            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                $this->removeDirectory($workDir);
                return json(['success' => false, 'message' => '压缩播放器失败']);
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($workDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $filePath = $file->getRealPath();
                if ($filePath === false) {
                    continue;
                }
                $localName = substr($filePath, strlen($workDir) + 1);
                $zip->addFile($filePath, str_replace('\\', '/', $localName));
            }

            $zip->close();
            $this->removeDirectory($workDir);

            return json([
                'success' => true,
                'downloadLink' => '/uploads/players/' . $buildName . '.zip',
                'fileName' => $buildName . '.zip',
                'message' => '播放器生成成功',
            ]);
        } catch (\Throwable $e) {
            $this->removeDirectory($workDir);
            if (is_file($zipPath)) {
                @unlink($zipPath);
            }
            return json(['success' => false, 'message' => '播放器生成失败，请稍后再试']);
        }
    }

    /**
     * 客户端调试页面
     */
    public function video()
    {
        $user = $this->getUser();
        View::assign('user', $user);
        return View::fetch('user/inside1/video');
    }
    
    /**
     * 接口调用记录
     */
    public function record()
    {
        $user = $this->getUser();
        if (!$user) {
            return redirect((string)url('/index/login'));
        }

        // 今日全站成功/失败排行榜（record.html 使用 cg/sb）
        $cg = [];
        $sb = [];
        try {
            $todayStart = date('Y-m-d 00:00:00');
            $todayEnd = date('Y-m-d 23:59:59');

            $cg = Db::name('userlog')
                ->where('intime', 'between', [$todayStart, $todayEnd])
                ->where('status', 'like', '成功%')
                ->field('url, COUNT(*) as num')
                ->group('url')
                ->order('num', 'desc')
                ->limit(20)
                ->select()
                ->toArray();

            $sb = Db::name('userlog')
                ->where('intime', 'between', [$todayStart, $todayEnd])
                ->where('status', 'not like', '成功%')
                ->field('url, COUNT(*) as num')
                ->group('url')
                ->order('num', 'desc')
                ->limit(20)
                ->select()
                ->toArray();
        } catch (\Throwable $e) {
            $cg = [];
            $sb = [];
        }

        View::assign([
            'user' => $user,
            'cg' => $cg,
            'sb' => $sb,
        ]);
        return View::fetch('user/inside1/record');
    }
    
    /**
     * 获取调用记录列表
     */
    public function recordList()
    {
        $user = $this->getUser();
        $page = input('current_page/d', 1);
        $limit = input('limit/d', 10);
        
        $list = Userlogs::where('uid', $user['uid'])
            ->order('id', 'desc')
            ->paginate([
                'list_rows' => $limit,
                'page' => $page
            ]);
        
        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'data' => $list
            ]
        ]);
    }
    
    /**
     * 资源提交页面
     */
    public function submit()
    {
        $user = $this->getUser();
        View::assign('user', $user);
        return View::fetch('user/inside1/submit');
    }

    public function lottery()
    {
        $user = $this->getUser();
        if (!$user) {
            return redirect((string)url('/index/login'));
        }

        $service = new LotteryService();
        $config = $service->getConfig();
        $todayCount = $service->getTodayCount((int)$user['id']);
        $remainingCount = max(0, (int)$config['daily_limit'] - $todayCount);

        View::assign([
            'config' => $config,
            'todayCount' => $todayCount,
            'remainingCount' => $remainingCount,
        ]);

        if ((int)($config['is_enabled'] ?? 0) !== 1) {
            return View::fetch('user/inside1/lottery_disabled');
        }

        return View::fetch('user/inside1/lottery');
    }

    public function lotteryPrizes()
    {
        try {
            $service = new LotteryService();
            $prizes = $service->getUserPrizes();
            return json(['code' => 0, 'msg' => 'success', 'data' => $prizes]);
        } catch (\Throwable $e) {
            return json(['code' => 1, 'msg' => '获取奖品失败', 'data' => []]);
        }
    }

    public function lotteryRecords()
    {
        $user = $this->getUser();
        if (!$user) {
            return json(['code' => 1, 'msg' => '请先登录', 'data' => []]);
        }

        try {
            $service = new LotteryService();
            $records = $service->getUserRecords((int)$user['id'], 10);
            return json(['code' => 0, 'msg' => 'success', 'data' => $records]);
        } catch (\Throwable $e) {
            return json(['code' => 1, 'msg' => '获取记录失败', 'data' => []]);
        }
    }

    public function doLottery()
    {
        if (!$this->request->isPost()) {
            return json(['code' => 1, 'msg' => '请求方式错误']);
        }

        $user = $this->getUser();
        if (!$user) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }

        try {
            $service = new LotteryService();
            $result = $service->draw($user->toArray(), (string)$this->request->ip());
            return json(['code' => 0, 'msg' => '抽奖成功', 'data' => $result]);
        } catch (\Throwable $e) {
            Log::error('抽奖失败', ['error' => $e->getMessage()]);
            return json(['code' => 1, 'msg' => '抽奖失败，请稍后重试']);
        }
    }
    
    /**
     * 在线求片页面
     */
    public function qiupian()
    {
        $user = $this->getUser();
        
        // 获取求片列表
        $list = Qiupian::where('user_id', $user['uid'])
            ->order('id', 'desc')
            ->select();

        // 统计
        $total = Qiupian::where('user_id', $user['uid'])->count();
        // 兼容字段：status / state
        try {
            $status1 = Qiupian::where('user_id', $user['uid'])->where('status', 1)->count();
            $status2 = Qiupian::where('user_id', $user['uid'])->where('status', 2)->count();
            $status3 = Qiupian::where('user_id', $user['uid'])->where('status', 3)->count();
        } catch (\Throwable $e) {
            $status1 = Qiupian::where('user_id', $user['uid'])->where('state', 1)->count();
            $status2 = Qiupian::where('user_id', $user['uid'])->where('state', 2)->count();
            $status3 = Qiupian::where('user_id', $user['uid'])->where('state', 3)->count();
        }
        $type1Percent = $total > 0 ? round($status1 * 100 / $total, 2) : 0;
        $type2Percent = $total > 0 ? round($status2 * 100 / $total, 2) : 0;
        $type3Percent = $total > 0 ? round($status3 * 100 / $total, 2) : 0;
        
        View::assign([
            'user' => $user,
            'list' => $list,
            'total' => $total,
            'status1' => $status1,
            'status2' => $status2,
            'status3' => $status3,
            'type1Percent' => $type1Percent,
            'type2Percent' => $type2Percent,
            'type3Percent' => $type3Percent
        ]);
        
        return View::fetch('user/inside1/qiupian');
    }
    
    /**
     * 弹幕接口页面
     */
    public function dmku()
    {
        $user = $this->getUser();
        View::assign('user', $user);
        return View::fetch('user/inside1/dmku');
    }
    
    /**
     * 使用帮助页面
     */
    public function help()
    {
        $user = $this->getUser();
        View::assign('user', $user);
        return View::fetch('user/inside1/help');
    }
    
    /**
     * APP打包页面
     */
    public function app()
    {
        $user = $this->getUser();
        $packConfig = $this->getPackConfig();
        $packQuota = $this->getUserPackQuota($user);

        View::assign([
            'user' => $user,
            'pack_quota' => $packQuota,
            'pack_cost' => $packConfig['cost'],
            'pack_enabled' => $packConfig['enabled'],
            'pack_api_url' => $packConfig['api_url'],
        ]);

        return View::fetch('user/inside1/app');
    }

    public function packinfo()
    {
        $user = $this->getUser();
        if (!$user) {
            return json([
                'success' => false,
                'code' => 1,
                'msg' => '请先登录',
                'message' => '请先登录',
                'data' => [],
            ]);
        }

        $packConfig = $this->getPackConfig();
        $packQuota = $this->getUserPackQuota($user);

        return json([
            'success' => true,
            'code' => 0,
            'msg' => 'success',
            'message' => 'success',
            'data' => [
                'pack_quota' => $packQuota,
                'pack_cost' => $packConfig['cost'],
                'pack_enabled' => (bool)$packConfig['enabled'],
            ],
        ]);
    }

    public function packbuild_async()
    {
        return $this->submitPackBuild(true);
    }

    public function packbuild()
    {
        return $this->submitPackBuild(false);
    }

    public function packbuild_status()
    {
        $user = $this->getUser();
        if (!$user) {
            return json([
                'success' => false,
                'code' => 1,
                'msg' => '请先登录',
                'message' => '请先登录',
                'data' => [],
            ]);
        }

        $taskId = trim((string)$this->request->param('task_id', ''));
        if ($taskId === '') {
            return json([
                'success' => false,
                'code' => 1,
                'msg' => '任务ID不能为空',
                'message' => '任务ID不能为空',
                'data' => [],
            ]);
        }

        $currentUserId = (int)($user['id'] ?? 0);
        if ($currentUserId <= 0) {
            return json([
                'success' => false,
                'code' => 1,
                'msg' => '用户信息异常',
                'message' => '用户信息异常',
                'data' => [],
            ]);
        }

        $ownerUserId = Cache::get($this->packTaskOwnerCacheKey($taskId));
        if ($ownerUserId === null) {
            return json([
                'success' => false,
                'code' => 1,
                'msg' => '任务不存在或无权限访问',
                'message' => '任务不存在或无权限访问',
                'data' => [],
            ]);
        }

        if ((int)$ownerUserId !== $currentUserId) {
            return json([
                'success' => false,
                'code' => 1,
                'msg' => '无权限访问该任务',
                'message' => '无权限访问该任务',
                'data' => [],
            ]);
        }

        $cacheKey = $this->packTaskCacheKey($taskId, $currentUserId);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['status'])) {
            $cachedStatus = (string)($cached['status'] ?? '');
            if (in_array($cachedStatus, ['success', 'failed'], true)) {
                return json([
                    'success' => true,
                    'code' => 0,
                    'msg' => 'success',
                    'message' => 'success',
                    'data' => $cached,
                ]);
            }
        }

        $packConfig = $this->getPackConfig();
        if ((int)$packConfig['enabled'] !== 1 || $packConfig['api_url'] === '') {
            return json([
                'success' => false,
                'code' => 1,
                'msg' => '打包服务未开启',
                'message' => '打包服务未开启',
                'data' => [],
            ]);
        }

        $params = [
            'task_id' => $taskId,
            'uid' => (string)($user['uid'] ?? ''),
            'user_id' => (string)($user['id'] ?? ''),
        ];

        $apiResult = $this->callPackApiByPaths($packConfig, ['api/build_status', 'api/packbuild_status', 'api/task_status'], 'GET', $params);
        if (!$apiResult['success']) {
            return json([
                'success' => false,
                'code' => 1,
                'msg' => $apiResult['message'] ?: '获取任务状态失败',
                'message' => $apiResult['message'] ?: '获取任务状态失败',
                'data' => [],
            ]);
        }

        $statusData = is_array($apiResult['data']) ? $apiResult['data'] : [];
        if (!isset($statusData['status'])) {
            $statusData['status'] = 'running';
        }

        if (in_array((string)$statusData['status'], ['success', 'failed'], true)) {
            Cache::set($cacheKey, $statusData, 3600);
        }

        return json([
            'success' => true,
            'code' => 0,
            'msg' => 'success',
            'message' => 'success',
            'data' => $statusData,
        ]);
    }

    public function generatejs()
    {
        if (!$this->request->isPost()) {
            return json([
                'success' => false,
                'code' => 1,
                'msg' => '请求方式错误',
                'message' => '请求方式错误',
                'data' => [],
            ]);
        }

        $user = $this->getUser();
        if (!$user) {
            return json([
                'success' => false,
                'code' => 1,
                'msg' => '请先登录',
                'message' => '请先登录',
                'data' => [],
            ]);
        }

        $packConfig = $this->getPackConfig();
        if ((int)$packConfig['enabled'] !== 1 || $packConfig['api_url'] === '') {
            return json([
                'success' => false,
                'code' => 1,
                'msg' => '打包服务未开启',
                'message' => '打包服务未开启',
                'data' => [],
            ]);
        }

        $serverIp = trim((string)$this->request->post('server_ip', ''));
        if ($serverIp === '') {
            return json([
                'success' => false,
                'code' => 1,
                'msg' => '请先输入服务器IP或域名',
                'message' => '请先输入服务器IP或域名',
                'data' => [],
            ]);
        }

        if (mb_strlen($serverIp) > 255 || !preg_match('/^[a-zA-Z0-9._:\/\-]+$/', $serverIp)) {
            return json([
                'success' => false,
                'code' => 1,
                'msg' => '服务器IP或域名格式不正确',
                'message' => '服务器IP或域名格式不正确',
                'data' => [],
            ]);
        }

        $params = [
            'server_ip' => $serverIp,
            'uid' => (string)($user['uid'] ?? ''),
            'user_id' => (string)($user['id'] ?? ''),
            'username' => (string)($user['user'] ?? ''),
        ];

        $apiResult = $this->callPackApiByPaths($packConfig, ['api/generate_js', 'api/generatejs', 'api/js/generate'], 'POST', $params);
        if (!$apiResult['success']) {
            return json([
                'success' => false,
                'code' => 1,
                'msg' => $apiResult['message'] ?: '生成失败',
                'message' => $apiResult['message'] ?: '生成失败',
                'data' => [],
            ]);
        }

        return json([
            'success' => true,
            'code' => 0,
            'msg' => $apiResult['message'] ?: '生成成功',
            'message' => $apiResult['message'] ?: '生成成功',
            'data' => is_array($apiResult['data']) ? $apiResult['data'] : [],
        ]);
    }
    
    /**
     * 下载页面
     */
    public function download()
    {
        $user = $this->getUser();
        View::assign('user', $user);
        return View::fetch('user/inside1/download');
    }
    
    /**
     * 修改密码页面
     */
    public function edit_password()
    {
        $user = $this->getUser();
        View::assign('user', $user);
        return View::fetch('user/inside1/edit_password');
    }

    public function user()
    {
        $user = $this->getUser();
        if (!$user) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }

        if ($this->request->isPost()) {
            $currentWay = (string)($user['way'] ?? '');
            $targetWay = $currentWay === '包点' ? '包月' : '包点';

            if ($targetWay === '包月') {
                $hasTimePackage = Db::name('shop')->where('fs', '包月')->count() > 0;
                if (!$hasTimePackage) {
                    return json(['code' => 1, 'msg' => '当前没有有效的时效套餐，无法切换']);
                }
            }

            Db::name('user')->where('id', $user['id'])->update([
                'way' => $targetWay,
            ]);

            return json(['code' => 0, 'msg' => '切换成功']);
        }

        return json(['code' => 0, 'msg' => 'success', 'data' => $user]);
    }

    public function user_generate_key()
    {
        $user = $this->getUser();
        if (!$user) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }

        try {
            $newKey = bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            return json(['code' => 1, 'msg' => '生成失败，请重试']);
        }

        Db::name('user')->where('id', $user['id'])->update([
            'my' => $newKey,
        ]);

        return json(['code' => 0, 'msg' => '更换成功']);
    }

    public function shop()
    {
        return redirect((string)url('/shop/index'));
    }

    public function pay(Request $request)
    {
        $user = $this->getUser();
        if (!$user) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }

        $money = round((float)$request->post('money', 0), 2);
        if ($money < 0.01 || $money > 50000) {
            return json(['code' => 1, 'msg' => '充值金额不合法']);
        }

        $type = trim((string)$request->post('type', 'alipay'));

        try {
            $payController = app()->make(Pay::class);
            $order = $payController->createRechargeOrder($this->getCurrentUserUid(), $money, $type, $request);
            return json(['code' => 0, 'msg' => 'success', 'data' => ['id' => $order['id']]]);
        } catch (\Throwable $e) {
            Log::error('创建订单失败', ['error' => $e->getMessage()]);
            return json(['code' => 1, 'msg' => '创建订单失败，请稍后重试']);
        }
    }

    public function san(Request $request)
    {
        $user = $this->getUser();
        if (!$user) {
            return redirect((string)url('/index/login'));
        }

        $id = (int)$request->param('id', 0);
        $pay = $id > 0 ? Db::name('recharge')->where('id', $id)->where('user_id', $user['uid'])->find() : null;
        if (!$pay) {
            return $this->error('订单不存在');
        }

        View::assign([
            'user' => $user,
            'pay' => $pay,
        ]);

        return View::fetch('user/inside1/san2');
    }

    public function epayap(Request $request)
    {
        return redirect((string)url('/pay/epayap', $request->param()));
    }

    public function redeem_card(Request $request)
    {
        $user = $this->getUser();
        if (!$user) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }

        $code = trim((string)$request->post('card_code', $request->post('code', $request->post('kmcode', ''))));
        if ($code === '') {
            return json(['code' => 1, 'msg' => '请输入卡密']);
        }

        try {
            $payController = app()->make(Pay::class);
            return $payController->card($request);
        } catch (\Throwable $e) {
            Log::error('卡密充值失败', ['error' => $e->getMessage()]);
            return json(['code' => 1, 'msg' => '充值失败，请稍后重试']);
        }
    }

    public function jcqq(Request $request)
    {
        $user = $this->getUser();
        if (!$user) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }

        $userId = (int)$request->post('user_id', 0);
        if ($userId <= 0 || $userId !== (int)$user['uid']) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }

        try {
            // 兼容不同字段命名，优先清空常见 qq 绑定字段
            Db::name('user')->where('uid', $user['uid'])->update([
                'qq' => '',
                'qq_openid' => '',
            ]);
        } catch (\Throwable $e) {
            // 字段不存在时忽略
        }

        return json(['code' => 0, 'msg' => '解绑成功']);
    }

    public function jcwx(Request $request)
    {
        $user = $this->getUser();
        if (!$user) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }

        try {
            Db::name('user')->where('uid', $user['uid'])->update([
                'wx_openid' => '',
                'access_token_wx' => '',
            ]);
        } catch (\Throwable $e) {
            // 字段不存在时忽略
        }

        return json(['code' => 0, 'msg' => '解绑成功']);
    }

    public function jsonup(Request $request)
    {
        $user = $this->getUser();
        if (!$user) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }

        $name = trim((string)$request->post('name', ''));
        $oldUrlsText = trim((string)$request->post('url', ''));
        $newUrl = trim((string)$request->post('newurl', ''));

        if ($name === '' || $oldUrlsText === '' || $newUrl === '') {
            return json(['code' => 1, 'msg' => '请填写完整信息']);
        }

        // 支持多行，一行一个
        $oldUrls = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $oldUrlsText))));
        if (empty($oldUrls)) {
            return json(['code' => 1, 'msg' => '原链接不能为空']);
        }

        Db::startTrans();
        try {
            foreach ($oldUrls as $oldUrl) {
                $data = [
                    'name' => $name,
                    'old_url' => $oldUrl,
                    'new_url' => $newUrl,
                    // 用户中心这里用 uid 作为 auth 标识，后台也能筛选
                    'auth' => $user['uid'],
                    'intime' => date('Y-m-d H:i:s'),
                ];

                // 兼容：部分表有 examine 字段用于审核
                try {
                    $data['examine'] = 0;
                    Db::name('reurl')->insert($data);
                } catch (\Throwable $e) {
                    unset($data['examine']);
                    Db::name('reurl')->insert($data);
                }
            }

            Db::commit();
            return json(['code' => 0, 'msg' => '提交成功']);
        } catch (\Throwable $e) {
            Db::rollback();
            return json(['code' => 1, 'msg' => '提交失败，请稍后再试']);
        }
    }

    public function keyup(Request $request)
    {
        $user = $this->getUser();
        if (!$user) {
            return json(['code' => 1, 'msg' => '请先登录']);
        }

        $key = trim((string)$request->post('key', ''));
        if ($key === '') {
            return json(['code' => 1, 'msg' => 'KEY不能为空']);
        }

        try {
            Db::name('user')->where('uid', $user['uid'])->update(['key' => $key]);
            return json(['code' => 0, 'msg' => '设置成功']);
        } catch (\Throwable $e) {
            return json(['code' => 1, 'msg' => '设置失败，请稍后再试']);
        }
    }
    
    /**
     * 保存密码修改
     */
    public function savePassword()
    {
        $user = $this->getUser();
        $oldPass = input('old_pass', '');
        $newPass = input('new_pass', '');
        // 确认密码为可选：前台修改密码表单未提供确认输入框时，默认与新密码一致
        $confirmPass = input('confirm_pass', $newPass);
        
        if (empty($oldPass) || empty($newPass)) {
            return json(['code' => 1, 'msg' => '请填写完整信息']);
        }
        
        if ($newPass !== $confirmPass) {
            return json(['code' => 1, 'msg' => '两次密码输入不一致']);
        }
        
        if (strlen($newPass) < 6) {
            return json(['code' => 1, 'msg' => '密码长度不能少于6位']);
        }
        
        $storedHash = (string)($user['pass'] ?? '');
        if (!secure_password_verify((string)$oldPass, $storedHash)) {
            return json(['code' => 1, 'msg' => '原密码错误']);
        }
        
        // 更新密码
        $result = Users::where('uid', $user['uid'])->update([
            'pass' => secure_password_hash((string)$newPass)
        ]);
        
        if ($result) {
            return json(['code' => 0, 'msg' => '密码修改成功']);
        } else {
            return json(['code' => 1, 'msg' => '密码修改失败']);
        }
    }
    
    public function editPasswords()
    {
        return $this->savePassword();
    }

    /**
     * 退出登录
     */
    public function logOut()
    {
        Session::delete('user_id');
        Session::delete('user_uid');
        Session::delete('user_name');
        session('user', null);

        return json(['code' => 0, 'msg' => '退出成功']);
    }

    protected function submitPackBuild(bool $expectAsync)
    {
        if (!$this->request->isPost()) {
            return json([
                'success' => false,
                'code' => 1,
                'msg' => '请求方式错误',
                'message' => '请求方式错误',
                'data' => [],
            ]);
        }

        $user = $this->getUser();
        if (!$user) {
            return json([
                'success' => false,
                'code' => 1,
                'msg' => '请先登录',
                'message' => '请先登录',
                'data' => [],
            ]);
        }

        $packConfig = $this->getPackConfig();
        if ((int)$packConfig['enabled'] !== 1 || $packConfig['api_url'] === '') {
            return json([
                'success' => false,
                'code' => 1,
                'msg' => '打包服务未开启',
                'message' => '打包服务未开启',
                'data' => [],
            ]);
        }

        $params = $this->request->post();
        if (!is_array($params)) {
            $params = [];
        }

        $validateResult = $this->validatePackBuildParams($params);
        if ((int)$validateResult['valid'] !== 1) {
            return json([
                'success' => false,
                'code' => 1,
                'msg' => (string)$validateResult['message'],
                'message' => (string)$validateResult['message'],
                'data' => [],
            ]);
        }

        $cost = max(1, (int)$packConfig['cost']);
        if ((int)($user['fullnum'] ?? 0) < $cost) {
            return json([
                'success' => false,
                'code' => 1,
                'msg' => '打包次数不足',
                'message' => '打包次数不足',
                'data' => [],
            ]);
        }

        if (!$this->consumePackQuota((int)$user['id'], $cost)) {
            return json([
                'success' => false,
                'code' => 1,
                'msg' => '打包次数不足',
                'message' => '打包次数不足',
                'data' => [],
            ]);
        }

        $params['uid'] = (string)($user['uid'] ?? '');
        $params['user_id'] = (string)($user['id'] ?? '');
        $params['username'] = (string)($user['user'] ?? '');
        $files = $this->collectPackUploadFiles();

        $paths = $expectAsync
            ? ['api/build_async', 'api/packbuild_async', 'api/build']
            : ['api/build', 'api/packbuild', 'api/build_async'];

        try {
            $apiResult = $this->callPackApiByPaths($packConfig, $paths, 'POST', $params, $files);
        } catch (\Throwable $e) {
            $refundOk = $this->refundPackQuota((int)$user['id'], $cost);
            Log::error('packbuild submit exception', [
                'user_id' => (int)($user['id'] ?? 0),
                'uid' => (string)($user['uid'] ?? ''),
                'cost' => $cost,
                'refund_ok' => $refundOk,
                'error' => $e->getMessage(),
            ]);

            return json([
                'success' => false,
                'code' => 1,
                'msg' => $refundOk ? '提交失败，请稍后重试' : '提交失败且配额回滚失败，请联系管理员处理',
                'message' => $refundOk ? '提交失败，请稍后重试' : '提交失败且配额回滚失败，请联系管理员处理',
                'data' => [],
            ]);
        }

        if (!$apiResult['success']) {
            $refundOk = $this->refundPackQuota((int)$user['id'], $cost);
            if (!$refundOk) {
                Log::error('packbuild refund failed after submit failure', [
                    'user_id' => (int)($user['id'] ?? 0),
                    'uid' => (string)($user['uid'] ?? ''),
                    'cost' => $cost,
                    'api_message' => (string)($apiResult['message'] ?? ''),
                ]);

                return json([
                    'success' => false,
                    'code' => 1,
                    'msg' => '提交失败且配额回滚失败，请联系管理员处理',
                    'message' => '提交失败且配额回滚失败，请联系管理员处理',
                    'data' => [],
                ]);
            }

            return json([
                'success' => false,
                'code' => 1,
                'msg' => $apiResult['message'] ?: '提交失败',
                'message' => $apiResult['message'] ?: '提交失败',
                'data' => [],
            ]);
        }

        $responseData = is_array($apiResult['data']) ? $apiResult['data'] : [];
        if (empty($responseData) && isset($apiResult['raw']) && is_array($apiResult['raw'])) {
            $responseData = $apiResult['raw'];
        }
        $taskId = trim((string)($responseData['task_id'] ?? ''));

        if ($taskId === '') {
            $taskId = 'local_' . date('YmdHis') . mt_rand(1000, 9999);
        }

        $cachedTask = [
            'status' => !empty($responseData['download_url']) ? 'success' : 'queued',
            'message' => (string)($apiResult['message'] ?: '任务已提交'),
            'download_url' => (string)($responseData['download_url'] ?? ''),
            'task_id' => $taskId,
            'owner_user_id' => (int)($user['id'] ?? 0),
        ];

        Cache::set($this->packTaskCacheKey($taskId, (int)($user['id'] ?? 0)), $cachedTask, 3600);
        Cache::set($this->packTaskOwnerCacheKey($taskId), (int)($user['id'] ?? 0), 86400);

        $responseData['task_id'] = $taskId;
        $quotaAfter = $this->getUserPackQuota($user);

        return json([
            'success' => true,
            'code' => 0,
            'msg' => $apiResult['message'] ?: '提交成功',
            'message' => $apiResult['message'] ?: '提交成功',
            'data' => array_merge($responseData, [
                'pack_quota' => $quotaAfter,
                'pack_cost' => $cost,
            ]),
        ]);
    }

    protected function validatePackBuildParams(array $params): array
    {
        $type = trim((string)($params['type'] ?? ''));
        if ($type === '') {
            return ['valid' => 0, 'message' => '缺少打包类型'];
        }

        if ($type === 'generic') {
            $appName = trim((string)($params['app_name'] ?? ''));
            $indexUrl = trim((string)($params['index_url'] ?? ''));
            $platform = trim((string)($params['platform'] ?? 'android'));
            $packageName = trim((string)($params['package_name'] ?? ''));
            if ($appName === '' || $indexUrl === '') {
                return ['valid' => 0, 'message' => '请填写应用名称与索引地址'];
            }
            if (mb_strlen($indexUrl) !== 37) {
                return ['valid' => 0, 'message' => '索引地址必须为 37 位'];
            }
            if ($platform === 'android' && $packageName === '') {
                return ['valid' => 0, 'message' => 'Android 平台须填写包名'];
            }

            $licenseUrl = trim((string)($params['player_license_url'] ?? ''));
            if ($licenseUrl !== '' && mb_strlen($licenseUrl) !== 72) {
                return ['valid' => 0, 'message' => '播放器许可证URL必须为 72 位'];
            }

            $playerSecret = trim((string)($params['player_secret'] ?? $params['player_key'] ?? ''));
            if ($playerSecret !== '' && mb_strlen($playerSecret) !== 32) {
                return ['valid' => 0, 'message' => '播放器密钥必须为 32 位'];
            }

            return ['valid' => 1, 'message' => 'ok'];
        }

        if ($type === 'turtle') {
            if (trim((string)($params['app_name'] ?? '')) === '' || trim((string)($params['domain_url'] ?? '')) === '') {
                return ['valid' => 0, 'message' => '请填写应用名称与域名地址'];
            }

            return ['valid' => 1, 'message' => 'ok'];
        }

        if ($type === 'ludou') {
            $required = ['app_name', 'package_name', 'domain_url', 'app_id', 'app_secret'];
            foreach ($required as $field) {
                if (trim((string)($params[$field] ?? '')) === '') {
                    return ['valid' => 0, 'message' => '请完整填写所有必填项'];
                }
            }

            return ['valid' => 1, 'message' => 'ok'];
        }

        if ($type === 'sm9') {
            $required = ['app_name', 'package_name', 'field_six', 'field_eight'];
            foreach ($required as $field) {
                if (trim((string)($params[$field] ?? '')) === '') {
                    return ['valid' => 0, 'message' => '请完整填写所有必填项'];
                }
            }

            return ['valid' => 1, 'message' => 'ok'];
        }

        return ['valid' => 0, 'message' => '暂不支持该打包类型'];
    }

    protected function getPackConfig(): array
    {
        $all = conf();
        if (!is_array($all)) {
            $all = [];
        }

        $cacheConfig = Cache::get('system_pack_config');
        if (is_array($cacheConfig)) {
            foreach (['pack_enabled', 'pack_cost', 'pack_api_url', 'pack_api_key', 'pack_api_appid', 'pack_api_secret', 'pack_api', 'app_pack_api_url', 'build_api_url', 'pack_api_token', 'pack_key', 'pack_token', 'pack_appid', 'build_appid', 'pack_secret', 'build_secret'] as $cacheKey) {
                if ((!array_key_exists($cacheKey, $all) || $all[$cacheKey] === '' || $all[$cacheKey] === null) && array_key_exists($cacheKey, $cacheConfig)) {
                    $all[$cacheKey] = $cacheConfig[$cacheKey];
                }
            }
        }

        $apiUrl = '';
        foreach (['pack_api_url', 'pack_api', 'app_pack_api_url', 'build_api_url'] as $key) {
            $value = trim((string)($all[$key] ?? ''));
            if ($value !== '') {
                $apiUrl = rtrim($value, '/');
                break;
            }
        }

        $apiKey = '';
        foreach (['pack_api_key', 'pack_api_token', 'pack_key', 'pack_token'] as $key) {
            $value = trim((string)($all[$key] ?? ''));
            if ($value !== '') {
                $apiKey = $value;
                break;
            }
        }

        $apiAppId = '';
        foreach (['pack_api_appid', 'pack_appid', 'build_appid'] as $key) {
            $value = trim((string)($all[$key] ?? ''));
            if ($value !== '') {
                $apiAppId = $value;
                break;
            }
        }

        $apiSecret = '';
        foreach (['pack_api_secret', 'pack_secret', 'build_secret'] as $key) {
            $value = trim((string)($all[$key] ?? ''));
            if ($value !== '') {
                $apiSecret = $value;
                break;
            }
        }

        $cost = (int)($all['pack_cost'] ?? 1);
        if ($cost <= 0) {
            $cost = 1;
        }

        $enabledConfig = $all['pack_enabled'] ?? null;
        $enabled = $enabledConfig === null ? ($apiUrl !== '' ? 1 : 0) : ((int)$enabledConfig === 1 ? 1 : 0);

        return [
            'api_url' => $apiUrl,
            'api_key' => $apiKey,
            'api_appid' => $apiAppId,
            'api_secret' => $apiSecret,
            'cost' => $cost,
            'enabled' => $enabled,
        ];
    }

    protected function collectPackUploadFiles(): array
    {
        $result = [];
        $files = $this->request->file();
        if (!is_array($files)) {
            return $result;
        }

        foreach ($files as $key => $file) {
            if (is_array($file)) {
                continue;
            }

            if (!is_object($file) || !method_exists($file, 'getRealPath')) {
                continue;
            }

            $realPath = $file->getRealPath();
            if (!is_string($realPath) || $realPath === '' || !is_file($realPath)) {
                continue;
            }

            $originalName = method_exists($file, 'getOriginalName') ? (string)$file->getOriginalName() : basename($realPath);
            $mimeType = method_exists($file, 'getMime') ? (string)$file->getMime() : 'application/octet-stream';
            $result[$key] = new \CURLFile($realPath, $mimeType, $originalName);
        }

        return $result;
    }

    protected function callPackApiByPaths(array $packConfig, array $paths, string $method, array $params = [], array $files = []): array
    {
        $lastError = '请求打包服务失败';

        foreach ($paths as $path) {
            $result = $this->callPackApi($packConfig, $path, $method, $params, $files);
            if ($result['success']) {
                return $result;
            }

            $lastError = $result['message'] ?: $lastError;
        }

        return [
            'success' => false,
            'message' => $lastError,
            'data' => [],
        ];
    }

    protected function callPackApi(array $packConfig, string $path, string $method, array $params = [], array $files = []): array
    {
        $baseUrl = trim((string)($packConfig['api_url'] ?? ''));
        if ($baseUrl === '') {
            return ['success' => false, 'message' => '未配置打包API地址', 'data' => []];
        }

        $path = trim($path, '/');
        $url = rtrim($baseUrl, '/') . '/' . $path;

        $apiKey = trim((string)($packConfig['api_key'] ?? ''));
        $apiAppId = trim((string)($packConfig['api_appid'] ?? ''));
        $apiSecret = trim((string)($packConfig['api_secret'] ?? ''));

        if ($apiKey !== '' && !isset($params['api_key'])) {
            $params['api_key'] = $apiKey;
        }
        if ($apiAppId !== '' && !isset($params['appid'])) {
            $params['appid'] = $apiAppId;
        }
        if ($apiSecret !== '' && !isset($params['app_secret'])) {
            $params['app_secret'] = $apiSecret;
        }

        $headers = [
            'Accept: application/json',
            'X-Requested-With: XMLHttpRequest',
        ];

        if ($apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
            $headers[] = 'X-Api-Key: ' . $apiKey;
        }

        $method = strtoupper($method);
        if ($method === 'GET' && !empty($params)) {
            $query = http_build_query($params);
            $url .= (strpos($url, '?') === false ? '?' : '&') . $query;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            $postData = array_merge($params, $files);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }

        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $curlError !== '') {
            return [
                'success' => false,
                'message' => $curlError !== '' ? $curlError : '连接打包服务失败',
                'data' => [],
            ];
        }

        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            return [
                'success' => false,
                'message' => $httpCode >= 400 ? ('打包服务返回HTTP ' . $httpCode) : '打包服务返回非JSON数据',
                'data' => [],
            ];
        }

        $ok = false;
        if (array_key_exists('success', $decoded)) {
            $ok = (bool)$decoded['success'];
        } elseif (array_key_exists('code', $decoded)) {
            $ok = (int)$decoded['code'] === 0;
        } elseif (isset($decoded['task_id']) || isset($decoded['download_url']) || isset($decoded['status'])) {
            $ok = true;
        }

        $message = (string)($decoded['message'] ?? $decoded['msg'] ?? '');
        if ($message === '') {
            $message = $ok ? 'success' : '请求失败';
        }

        $data = $decoded['data'] ?? [];
        if (!is_array($data)) {
            $data = [];
        }
        if (empty($data)) {
            $data = $decoded;
        }

        return [
            'success' => $ok,
            'message' => $message,
            'data' => $data,
            'raw' => $decoded,
        ];
    }

    protected function getUserPackQuota($user): int
    {
        $quota = max(0, (int)($user['fullnum'] ?? 0));
        $id = (int)($user['id'] ?? 0);
        if ($id <= 0) {
            return $quota;
        }

        try {
            $fresh = Db::name('user')->where('id', $id)->value('fullnum');
            if ($fresh !== null) {
                return max(0, (int)$fresh);
            }
        } catch (\Throwable $e) {
        }

        return $quota;
    }

    protected function consumePackQuota(int $userId, int $cost): bool
    {
        if ($userId <= 0 || $cost <= 0) {
            return false;
        }

        try {
            $affected = Db::name('user')
                ->where('id', $userId)
                ->where('fullnum', '>=', $cost)
                ->dec('fullnum', $cost)
                ->update();

            return (int)$affected > 0;
        } catch (\Throwable $e) {
            Log::error('consume pack quota failed', [
                'user_id' => $userId,
                'cost' => $cost,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    protected function refundPackQuota(int $userId, int $cost): bool
    {
        if ($userId <= 0 || $cost <= 0) {
            return false;
        }

        try {
            $affected = Db::name('user')->where('id', $userId)->inc('fullnum', $cost)->update();
            return (int)$affected > 0;
        } catch (\Throwable $e) {
            Log::error('refund pack quota failed', [
                'user_id' => $userId,
                'cost' => $cost,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    protected function packTaskCacheKey(string $taskId, int $userId): string
    {
        return 'pack_build_task_' . md5($userId . '|' . $taskId);
    }

    protected function packTaskOwnerCacheKey(string $taskId): string
    {
        return 'pack_build_task_owner_' . md5($taskId);
    }

    /**
     * 递归复制目录
     */
    protected function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            throw new \RuntimeException('源目录不存在');
        }

        if (!is_dir($destination) && !@mkdir($destination, 0755, true) && !is_dir($destination)) {
            throw new \RuntimeException('创建目录失败');
        }

        $items = scandir($source);
        if ($items === false) {
            throw new \RuntimeException('读取目录失败');
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $sourcePath = $source . DIRECTORY_SEPARATOR . $item;
            $destinationPath = $destination . DIRECTORY_SEPARATOR . $item;

            if (is_dir($sourcePath)) {
                $this->copyDirectory($sourcePath, $destinationPath);
                continue;
            }

            if (!@copy($sourcePath, $destinationPath)) {
                throw new \RuntimeException('复制文件失败: ' . $item);
            }
        }
    }

    /**
     * 递归删除目录
     */
    protected function removeDirectory(string $directory): void
    {
        if ($directory === '' || !file_exists($directory)) {
            return;
        }

        if (is_file($directory) || is_link($directory)) {
            @unlink($directory);
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }

    /**
     * 构建 ART 播放器配置文件
     */
    protected function buildArtPlayerConfig(array $user): string
    {
        $domain = rtrim((string) request()->domain(), '/');
        $userKey = trim((string) ($user['key'] ?? $user['my'] ?? ''));
        $playerType = trim((string) ($user['player'] ?? 'artplayer'));
        if ($playerType === '' || $playerType === 'dm') {
            $playerType = 'artplayer';
        }

        $secretSeed = $userKey !== '' ? $userKey : (string) ($user['uid'] ?? $user['id'] ?? random_str(16));
        $aesKey = substr(hash('sha256', 'aes-key|' . $secretSeed), 0, 16);
        $aesIv = substr(hash('sha256', 'aes-iv|' . $secretSeed), 0, 16);
        $tokenKey = hash('sha256', 'token|' . $secretSeed);

        $config = [
            'name' => (string) ($user['client_name'] ?? 'ART播放器'),
            'video' => 'https://wallpaperm.cmcm.com/live/preview_video/eb0a79367fc50c4b97a51dff3b8879e9_preview.mp4',
            'aes_key' => $aesKey,
            'aes_iv' => $aesIv,
            'interface' => $domain . '/api.php/?uid=' . (string) ($user['uid'] ?? '') . '&key=' . $userKey . '&url=',
            'Standby' => (string) ($user['byjx'] ?? ''),
            'bofangqi' => $playerType,
            'fanhuileixing' => '1',
            'fangdaoleixing' => '',
            'token_ua' => '',
            'token_key' => $tokenKey,
            'token_url' => (string) ($user['referer'] ?? ''),
            'theme' => '#165DFF',
            'background' => (string) ($user['img'] ?? ''),
            'loading' => 'artplayer/img/load.gif',
            'zantingguanggaoqidong' => (string) ($user['dmkupass'] ?? '0'),
            'zantingguanggaourl' => (string) ($user['dmfirst'] ?? ''),
            'zantingguanggaolianjie' => (string) ($user['you_href'] ?? ''),
            'danmuqidong' => (string) ($user['dmkupass'] ?? '0'),
            'dmapi' => $domain . '/dmku/',
            'sendtime' => 3,
            'pbgjz' => '操ABCDEFGHIJKLMNOPQRSTUVWSYZabcdefghijklmnopqrstuvwsyz',
        ];

        $configCode = var_export($config, true);

        return <<<PHP
<?php
return {$configCode};
PHP;
    }
}
