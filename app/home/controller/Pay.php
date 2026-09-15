<?php

declare(strict_types=1);

namespace app\home\controller;

use app\HomeController;
use app\common\model\Setting;
use think\facade\Db;
use think\facade\Log;
use think\facade\View;
use think\Request;

/**
 * 前台支付控制器
 * 处理充值订单创建和支付回调
 */
class Pay extends HomeController
{
    protected $noNeedLogin = ['notify', 'return'];

    /**
     * 易支付-创建订单
     * 通道二：/user/epayap
     */
    public function epayap(Request $request)
    {
        $userId = $this->getCurrentUserId();
        $uid = $this->getCurrentUserUid();
        if ($userId <= 0 || $uid === '') {
            return redirect((string)url('/index/login'));
        }

        $money = round((float) $request->param('money', 0), 2);
        $type = trim((string) $request->param('type', 'alipay'));

        if ($money < 0.01 || $money > 50000) {
            return $this->error('充值金额不合法（0.01~50000）');
        }

        $config = $this->getEpayConfig();
        if (!$config['valid']) {
            return $this->error($config['message']);
        }

        try {
            $order = $this->createRechargeOrder($uid, $money, $type, $request, $config);
        } catch (\Throwable $e) {
            Log::error('创建充值订单失败', ['error' => $e->getMessage()]);
            return $this->error('创建充值订单失败，请稍后重试');
        }

        return redirect((string)url('/user/san', ['id' => $order['id']]));
    }

    /**
     * 易支付-异步通知
     */
    public function notify(Request $request)
    {
        $config = $this->getEpayConfig();
        $epayKey = $config['key'];
        if ($epayKey === '') {
            return 'config error';
        }

        $params = $request->param();
        $sign = (string)($params['sign'] ?? '');
        unset($params['sign'], $params['sign_type']);

        ksort($params);
        $signStr = '';
        foreach ($params as $k => $v) {
            if ($v !== '') {
                $signStr .= $k . '=' . $v . '&';
            }
        }
        $signStr = rtrim($signStr, '&');
        $checkSign = md5($signStr . $epayKey);

        if ($sign !== $checkSign) {
            return 'sign error';
        }

        $tradeStatus = (string)($params['trade_status'] ?? '');
        if ($tradeStatus !== 'TRADE_SUCCESS') {
            return 'status error';
        }

        $orderId = trim((string)($params['out_trade_no'] ?? ''));
        $order = Db::name('recharge')->where('order_id', $orderId)->find();

        if (!$order) {
            return 'order not found';
        }

        if ((int)$order['status'] === 1) {
            return 'success';
        }

        $notifyMoney = (int)round((float)($params['money'] ?? 0) * 100);
        $orderMoney = (int)round((float)$order['money'] * 100);
        if ($notifyMoney !== $orderMoney) {
            return 'money mismatch';
        }

        Db::startTrans();
        try {
            $affected = Db::name('recharge')
                ->where('order_id', $orderId)
                ->where('status', 0)
                ->update([
                    'status' => 1,
                    'trade_no' => (string)($params['trade_no'] ?? ''),
                    'intime' => time()
                ]);

            if ($affected > 0) {
                Db::name('user')->where('uid', $order['user_id'])->inc('money', $order['money'])->update();
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            Log::error('支付回调处理失败', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            return 'fail';
        }

        return 'success';
    }

    /**
     * 易支付-同步回调
     */
    public function return(Request $request)
    {
        $orderId = trim((string)$request->param('out_trade_no', ''));

        if ($orderId === '') {
            return $this->error('订单不存在');
        }

        $order = Db::name('recharge')->where('order_id', $orderId)->find();

        if (!$order) {
            return $this->error('订单不存在');
        }

        if ((int)$order['status'] === 1) {
            return $this->success('支付成功', '/user/recharge');
        }

        return redirect((string)url('/user/san', ['id' => $order['id']]));
    }

    /**
     * 查询订单状态
     */
    public function query(Request $request)
    {
        $userId = $this->getCurrentUserId();
        $uid = $this->getCurrentUserUid();
        if ($userId <= 0 || $uid === '') {
            return json(['code' => 1, 'msg' => '请先登录']);
        }

        $orderId = trim((string)$request->param('order_id', ''));
        if ($orderId === '') {
            return json(['code' => 1, 'msg' => '订单号不能为空']);
        }

        $order = Db::name('recharge')
            ->where('order_id', $orderId)
            ->where('user_id', $uid)
            ->find();

        if (!$order) {
            return json(['code' => 1, 'msg' => '订单不存在']);
        }

        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'status' => (int)$order['status'],
                'money' => $order['money']
            ]
        ]);
    }

    /**
     * 卡密充值
     */
    public function card(Request $request)
    {
        $uid = $this->getCurrentUserUid();
        if ($uid === '') {
            return json(['code' => 1, 'msg' => '请先登录']);
        }

        $code = trim((string)$request->param('card_code', $request->param('code', $request->param('kmcode', ''))));
        if ($code === '') {
            return json(['code' => 1, 'msg' => '请输入卡密']);
        }

        Db::startTrans();
        try {
            $affected = Db::name('card')
                ->where('kmcode', $code)
                ->where('status', 0)
                ->update([
                    'status' => 1,
                    'uid' => $uid,
                    'sytime' => date('Y-m-d H:i:s'),
                    'ip' => request()->ip()
                ]);

            if ($affected === 0) {
                Db::rollback();
                return json(['code' => 1, 'msg' => '卡密不存在或已使用']);
            }

            $card = Db::name('card')->where('kmcode', $code)->find();
            $money = round((float)($card['money'] ?? 0), 2);
            if ($money <= 0) {
                Db::rollback();
                return json(['code' => 1, 'msg' => '卡密金额异常']);
            }

            Db::name('user')->where('uid', $uid)->inc('money', $money)->update();
            Db::name('recharge')->insert($this->buildRechargeRecordData([
                'order_id' => 'CARD' . date('YmdHis') . random_int(1000, 9999),
                'user_id' => $uid,
                'kami' => $code,
                'money' => $money,
                'status' => 1,
                'fs' => '卡密充值',
            ]));

            Db::commit();
            return json(['code' => 0, 'msg' => '充值成功，金额：' . $money . '元']);
        } catch (\Throwable $e) {
            Db::rollback();
            return json(['code' => 1, 'msg' => '充值失败，请稍后重试']);
        }
    }

    /**
     * 创建充值订单
     */
    public function createRechargeOrder(string $uid, float $money, string $type, Request $request, ?array $config = null): array
    {
        // 说明：本方法需保持 public，因为 User::pay 会跨控制器调用它。
        // 但 public 方法同时是 URL 可达的 action（框架仅用 is_callable 判断），
        // 因此归属与金额校验必须落在方法内部，不能只依赖调用方 epayap/User::pay。
        $sessionUid = $this->getCurrentUserUid();
        if ($sessionUid === '') {
            throw new \RuntimeException('请先登录');
        }
        // 只允许为当前登录用户建单，防止传入他人 uid
        if ($uid !== $sessionUid) {
            throw new \RuntimeException('订单归属校验失败');
        }

        $money = round($money, 2);
        if ($money < 0.01 || $money > 50000) {
            throw new \RuntimeException('充值金额不合法（0.01~50000）');
        }

        $type = trim($type);
        if ($type === '') {
            throw new \RuntimeException('支付方式不能为空');
        }

        $config = $config ?? $this->getEpayConfig();
        if (!$config['valid']) {
            throw new \RuntimeException($config['message']);
        }

        $orderId = $this->generateRechargeOrderId($uid);
        $params = [
            'pid' => $config['pid'],
            'type' => $type,
            'out_trade_no' => $orderId,
            'notify_url' => $request->domain() . '/pay/notify',
            'return_url' => $request->domain() . '/pay/return',
            'name' => '账户充值',
            'money' => $money,
            'timestamp'=>time(),
        ];
        $payUrl = $this->buildEpayUrl($config['api'], $config['key'], $params);

        $record = $this->buildRechargeRecordData([
            'order_id' => $orderId,
            'user_id' => $uid,
            'money' => $money,
            'status' => 0,
            'fs' => $type,
            'qr_code' => $payUrl,
        ]);
        $id = Db::name('recharge')->insertGetId($record);

        return [
            'id' => $id,
            'order_id' => $orderId,
            'qr_code' => $payUrl,
            'money' => $money,
            'type' => $type,
        ];
    }

    /**
     * 获取易支付配置
     */
    protected function getEpayConfig(): array
    {
        $setting = Setting::find(1);
        $api = trim((string)($setting['epay_api'] ?? ''));
        $pid = trim((string)($setting['epay_pid'] ?? ''));
        $key = trim((string)($setting['epay_key'] ?? ''));

        if ($api === '' || $pid === '' || $key === '') {
            $admin = Db::name('admin')->where('id', 1)->find() ?: [];
            $api = $api !== '' ? $api : trim((string)($admin['zfurl'] ?? ''));
            $pid = $pid !== '' ? $pid : trim((string)($admin['zfid'] ?? ''));
            $key = $key !== '' ? $key : trim((string)($admin['zfkey'] ?? ''));
        }

        return [
            'api' => $api,
            'pid' => $pid,
            'key' => $key,
            'valid' => $api !== '' && $pid !== '' && $key !== '',
            'message' => ($api !== '' && $pid !== '' && $key !== '') ? '' : '支付接口未配置，请联系管理员完善易支付参数',
        ];
    }

    /**
     * 构建支付链接
     */
    protected function buildEpayUrl(string $epayUrl, string $epayKey, array $params): string
    {
        ksort($params);
        $signStr = '';
        foreach ($params as $k => $v) {
            if ($k !== 'sign' && $k !== 'sign_type' && $v !== '') {
                $signStr .= $k . '=' . $v . '&';
            }
        }
        $signStr = rtrim($signStr, '&');
        $params['sign'] = md5($signStr . $epayKey);
        $params['sign_type'] = 'MD5';

        $base = trim($epayUrl);
        if ($base !== '' && !preg_match('#^https?://#i', $base)) {
            $base = 'http://' . $base;
        }
        $base = rtrim($base, '/');

        if (stripos($base, 'submit.php') !== false) {
            return $base . (strpos($base, '?') !== false ? '&' : '?') . http_build_query($params);
        }

        return $base . '/submit.php?' . http_build_query($params);
    }

    /**
     * 生成充值订单号
     */
    protected function generateRechargeOrderId(string $uid): string
    {
        return date('YmdHis') . $uid . bin2hex(random_bytes(4));
    }

    /**
     * 构建充值记录
     */
    protected function buildRechargeRecordData(array $data): array
    {
        $timestamp = time();

        $data['time'] = $timestamp;
        $data['intime'] = $timestamp;

        return $data;
    }
}
