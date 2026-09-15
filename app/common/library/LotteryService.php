<?php

declare(strict_types=1);

namespace app\common\library;

use think\facade\Db;

class LotteryService
{
    protected string $configTable = 'lottery_config';

    protected string $recordTable = 'lottery_record';

    public function __construct()
    {
        $this->ensureDefaultConfig();
    }

    public function getConfig(): array
    {
        $row = Db::name($this->configTable)->where('id', 1)->find();
        if (!$row) {
            $this->ensureDefaultConfig();
            $row = Db::name($this->configTable)->where('id', 1)->find();
        }

        return $this->formatConfigRow((array) $row);
    }

    public function saveConfig(array $data): void
    {
        $config = $this->normalizeConfig($data);
        $now = date('Y-m-d H:i:s');

        $payload = [
            'is_enabled' => $config['is_enabled'],
            'daily_limit' => $config['daily_limit'],
            'prizes' => json_encode($config['prizes'], JSON_UNESCAPED_UNICODE),
            'update_time' => $now,
        ];

        $exists = Db::name($this->configTable)->where('id', 1)->find();
        if ($exists) {
            Db::name($this->configTable)->where('id', 1)->update($payload);
            return;
        }

        $payload['id'] = 1;
        $payload['create_time'] = $now;
        Db::name($this->configTable)->insert($payload);
    }

    public function getUserPrizes(): array
    {
        $prizes = $this->getConfig()['prizes'];
        foreach ($prizes as &$prize) {
            if (($prize['type'] ?? '') === 'pack_quota') {
                $prize['type'] = 'time';
            }
        }

        return $prizes;
    }

    public function getUserRecords(int $userId, int $limit = 10): array
    {
        if ($userId <= 0) {
            return [];
        }

        if ($limit <= 0) {
            $limit = 10;
        }

        return Db::name($this->recordTable)
            ->where('user_id', $userId)
            ->order('id', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    public function getTodayCount(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        return (int) Db::name($this->recordTable)
            ->where('user_id', $userId)
            ->whereTime('create_time', 'today')
            ->count();
    }

    public function getRemainingCount(int $userId): int
    {
        $config = $this->getConfig();
        $remaining = (int) $config['daily_limit'] - $this->getTodayCount($userId);
        return max(0, $remaining);
    }

    public function draw(array $user, string $ip = ''): array
    {
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            throw new \RuntimeException('用户信息异常');
        }

        $username = trim((string) ($user['user'] ?? ''));
        $uid = trim((string) ($user['uid'] ?? ''));

        $config = $this->getConfig();
        if ((int) $config['is_enabled'] !== 1) {
            throw new \RuntimeException('抽奖活动暂未开启');
        }

        $dailyLimit = max(1, (int) $config['daily_limit']);
        $prizes = $config['prizes'];
        if (empty($prizes)) {
            throw new \RuntimeException('奖品配置异常，请联系管理员');
        }

        Db::startTrans();
        try {
            $todayCount = (int) Db::name($this->recordTable)
                ->where('user_id', $userId)
                ->whereTime('create_time', 'today')
                ->count();

            if ($todayCount >= $dailyLimit) {
                throw new \RuntimeException('今日抽奖次数已用完');
            }

            $userRow = Db::name('user')->where('id', $userId)->lock(true)->find();
            if (!$userRow) {
                throw new \RuntimeException('用户不存在');
            }

            $prize = $this->pickPrize($prizes);
            $grantType = $this->normalizePrizeType((string) ($prize['type'] ?? 'empty'));
            $grantValue = (float) ($prize['value'] ?? 0);
            $displayType = $grantType === 'pack_quota' ? 'time' : $grantType;

            if ($grantType === 'points' && $grantValue > 0) {
                $points = (int) ($userRow['points'] ?? 0);
                Db::name('user')->where('id', $userId)->update([
                    'points' => $points + (int) round($grantValue),
                ]);
            } elseif ($grantType === 'money' && $grantValue > 0) {
                $money = (float) ($userRow['money'] ?? 0);
                Db::name('user')->where('id', $userId)->update([
                    'money' => round($money + $grantValue, 3),
                ]);
            } elseif ($grantType === 'pack_quota' && $grantValue > 0) {
                $fullnum = (int) ($userRow['fullnum'] ?? 0);
                Db::name('user')->where('id', $userId)->update([
                    'fullnum' => $fullnum + (int) round($grantValue),
                ]);
            }

            $now = date('Y-m-d H:i:s');
            Db::name($this->recordTable)->insert([
                'user_id' => $userId,
                'uid' => $uid,
                'username' => $username,
                'prize_name' => (string) ($prize['name'] ?? '谢谢参与'),
                'prize_type' => $displayType,
                'prize_value' => $grantValue,
                'status' => 1,
                'ip' => $ip,
                'create_time' => $now,
            ]);

            Db::commit();

            $todayCount++;
            return [
                'prize_name' => (string) ($prize['name'] ?? '谢谢参与'),
                'prize_type' => $displayType,
                'prize_value' => $grantValue,
                'today_count' => $todayCount,
                'remaining_count' => max(0, $dailyLimit - $todayCount),
            ];
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function getRecordList(array $filters, int $page = 1, int $limit = 15)
    {
        if ($page <= 0) {
            $page = 1;
        }

        if ($limit <= 0) {
            $limit = 15;
        }

        $query = Db::name($this->recordTable);

        $username = trim((string) ($filters['username'] ?? ''));
        if ($username !== '') {
            $query->where('username', 'like', "%{$username}%");
        }

        $prizeType = trim((string) ($filters['prize_type'] ?? ''));
        if ($prizeType !== '') {
            if ($prizeType === 'time' || $prizeType === 'pack_quota') {
                $query->whereIn('prize_type', ['time', 'pack_quota']);
            } else {
                $query->where('prize_type', $prizeType);
            }
        }

        $dateRange = trim((string) ($filters['date_range'] ?? ''));
        if ($dateRange !== '') {
            [$startDate, $endDate] = $this->parseDateRange($dateRange);
            if ($startDate !== '' && $endDate !== '') {
                $query->whereBetweenTime('create_time', $startDate, $endDate);
            }
        }

        return $query->order('id', 'desc')->paginate([
            'page' => $page,
            'list_rows' => $limit,
        ]);
    }

    public function getStatistics(): array
    {
        return [
            'today_count' => (int) Db::name($this->recordTable)->whereTime('create_time', 'today')->count(),
            'month_count' => (int) Db::name($this->recordTable)->whereTime('create_time', 'month')->count(),
            'total_count' => (int) Db::name($this->recordTable)->count(),
        ];
    }

    protected function ensureDefaultConfig(): void
    {
        $exists = Db::name($this->configTable)->where('id', 1)->find();
        if ($exists) {
            return;
        }

        $default = $this->getDefaultConfig();
        $now = date('Y-m-d H:i:s');

        Db::name($this->configTable)->insert([
            'id' => 1,
            'is_enabled' => $default['is_enabled'],
            'daily_limit' => $default['daily_limit'],
            'prizes' => json_encode($default['prizes'], JSON_UNESCAPED_UNICODE),
            'create_time' => $now,
            'update_time' => $now,
        ]);
    }

    protected function formatConfigRow(array $row): array
    {
        $isEnabled = (int) ($row['is_enabled'] ?? 0);
        $dailyLimit = (int) ($row['daily_limit'] ?? 3);
        if ($dailyLimit <= 0) {
            $dailyLimit = 1;
        }

        $prizesRaw = $row['prizes'] ?? '';
        $prizes = [];
        if (is_string($prizesRaw) && $prizesRaw !== '') {
            $decoded = json_decode($prizesRaw, true);
            if (is_array($decoded)) {
                $prizes = $decoded;
            }
        } elseif (is_array($prizesRaw)) {
            $prizes = $prizesRaw;
        }

        if (empty($prizes)) {
            $prizes = $this->getDefaultConfig()['prizes'];
        }

        $prizes = $this->normalizePrizes($prizes);

        return [
            'is_enabled' => $isEnabled,
            'daily_limit' => $dailyLimit,
            'prizes' => $prizes,
        ];
    }

    protected function normalizeConfig(array $data): array
    {
        $isEnabled = (int) ($data['is_enabled'] ?? 0) === 1 ? 1 : 0;
        $dailyLimit = (int) ($data['daily_limit'] ?? 3);
        if ($dailyLimit <= 0) {
            $dailyLimit = 1;
        }

        $prizes = $data['prizes'] ?? [];
        $prizes = $this->normalizePrizes(is_array($prizes) ? $prizes : []);
        if (empty($prizes)) {
            $prizes = $this->getDefaultConfig()['prizes'];
        }

        return [
            'is_enabled' => $isEnabled,
            'daily_limit' => $dailyLimit,
            'prizes' => $prizes,
        ];
    }

    protected function normalizePrizes(array $prizes): array
    {
        $normalized = [];
        foreach ($prizes as $index => $prize) {
            if (!is_array($prize)) {
                continue;
            }

            $name = trim((string) ($prize['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $type = $this->normalizePrizeType((string) ($prize['type'] ?? 'empty'));
            $value = (float) ($prize['value'] ?? 0);
            $probability = (float) ($prize['probability'] ?? 0);
            $sort = (int) ($prize['sort'] ?? ($index + 1));
            if ($sort <= 0) {
                $sort = $index + 1;
            }

            if ($type === 'empty') {
                $value = 0;
            }

            $normalized[] = [
                'name' => $name,
                'type' => $type,
                'value' => $value,
                'probability' => $probability,
                'sort' => $sort,
            ];
        }

        usort($normalized, static function (array $a, array $b): int {
            return (int) $a['sort'] <=> (int) $b['sort'];
        });

        return $normalized;
    }

    protected function normalizePrizeType(string $type): string
    {
        $type = trim($type);
        if ($type === 'time') {
            return 'pack_quota';
        }

        if (in_array($type, ['points', 'money', 'pack_quota', 'empty'], true)) {
            return $type;
        }

        return 'empty';
    }

    protected function pickPrize(array $prizes): array
    {
        $total = 0.0;
        foreach ($prizes as $prize) {
            $total += max(0.0, (float) ($prize['probability'] ?? 0));
        }

        if ($total <= 0) {
            $index = array_rand($prizes);
            return $prizes[$index];
        }

        $rand = mt_rand(1, (int) round($total * 1000));
        $cursor = 0;
        foreach ($prizes as $prize) {
            $cursor += (int) round(max(0.0, (float) ($prize['probability'] ?? 0)) * 1000);
            if ($rand <= $cursor) {
                return $prize;
            }
        }

        return $prizes[array_key_last($prizes)];
    }

    protected function parseDateRange(string $dateRange): array
    {
        $parts = explode(' - ', $dateRange);
        if (count($parts) !== 2) {
            return ['', ''];
        }

        $start = trim($parts[0]);
        $end = trim($parts[1]);
        if ($start === '' || $end === '') {
            return ['', ''];
        }

        return [
            $start . ' 00:00:00',
            $end . ' 23:59:59',
        ];
    }

    protected function getDefaultConfig(): array
    {
        return [
            'is_enabled' => 0,
            'daily_limit' => 3,
            'prizes' => [
                [
                    'name' => '100点数',
                    'type' => 'points',
                    'value' => 100,
                    'probability' => 30,
                    'sort' => 1,
                ],
                [
                    'name' => '50点数',
                    'type' => 'points',
                    'value' => 50,
                    'probability' => 35,
                    'sort' => 2,
                ],
                [
                    'name' => '1元余额',
                    'type' => 'money',
                    'value' => 1,
                    'probability' => 20,
                    'sort' => 3,
                ],
                [
                    'name' => '10次打包额度',
                    'type' => 'pack_quota',
                    'value' => 10,
                    'probability' => 10,
                    'sort' => 4,
                ],
                [
                    'name' => '谢谢参与',
                    'type' => 'empty',
                    'value' => 0,
                    'probability' => 5,
                    'sort' => 5,
                ],
            ],
        ];
    }
}
