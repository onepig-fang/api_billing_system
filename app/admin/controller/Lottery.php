<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\AdminController;
use app\common\library\LotteryService;
use think\facade\Log;

class Lottery extends AdminController
{
    protected LotteryService $service;

    protected function initialize()
    {
        parent::initialize();
        $this->service = new LotteryService();
    }

    public function index()
    {
        $config = $this->service->getConfig();
        return view('lottery/index', ['config' => $config]);
    }

    public function records()
    {
        return view('lottery/records');
    }

    public function save()
    {
        if (!$this->request->isPost()) {
            return $this->jsonError('请求方式错误');
        }

        try {
            $isEnabled = (int) $this->request->post('is_enabled', 0);
            $dailyLimit = (int) $this->request->post('daily_limit', 3);

            $names = $this->request->post('prize_names', []);
            $types = $this->request->post('prize_types', []);
            $values = $this->request->post('prize_values', []);
            $probabilities = $this->request->post('prize_probabilities', []);
            $sorts = $this->request->post('prize_sorts', []);

            if (!is_array($names)) {
                $names = [];
            }
            if (!is_array($types)) {
                $types = [];
            }
            if (!is_array($values)) {
                $values = [];
            }
            if (!is_array($probabilities)) {
                $probabilities = [];
            }
            if (!is_array($sorts)) {
                $sorts = [];
            }

            $max = max(count($names), count($types), count($values), count($probabilities), count($sorts));
            $prizes = [];
            for ($i = 0; $i < $max; $i++) {
                $name = trim((string) ($names[$i] ?? ''));
                if ($name === '') {
                    continue;
                }

                $prizes[] = [
                    'name' => $name,
                    'type' => trim((string) ($types[$i] ?? 'empty')),
                    'value' => (float) ($values[$i] ?? 0),
                    'probability' => (float) ($probabilities[$i] ?? 0),
                    'sort' => (int) ($sorts[$i] ?? ($i + 1)),
                ];
            }

            $this->service->saveConfig([
                'is_enabled' => $isEnabled,
                'daily_limit' => $dailyLimit,
                'prizes' => $prizes,
            ]);

            return $this->jsonSuccess('保存成功');
        } catch (\Throwable $e) {
            Log::error('save lottery config failed', ['error' => $e->getMessage()]);
            return $this->jsonError('保存失败');
        }
    }

    public function recordList()
    {
        try {
            $page = $this->request->param('page/d', 1);
            if ($page <= 0) {
                $page = $this->request->param('current_page/d', 1);
            }

            $limit = $this->request->param('limit/d', 15);

            $filters = [
                'username' => $this->request->param('username', ''),
                'prize_type' => $this->request->param('prize_type', ''),
                'date_range' => $this->request->param('date_range', ''),
            ];

            $list = $this->service->getRecordList($filters, (int) $page, (int) $limit);

            return json([
                'code' => 0,
                'msg' => '获取成功',
                'count' => (int) $list->total(),
                'data' => $list->items(),
            ]);
        } catch (\Throwable $e) {
            Log::error('get lottery records failed', ['error' => $e->getMessage()]);
            return json([
                'code' => 1,
                'msg' => '获取失败',
                'count' => 0,
                'data' => [],
            ]);
        }
    }

    public function statistics()
    {
        try {
            $data = $this->service->getStatistics();
            return $this->jsonSuccess('获取成功', $data);
        } catch (\Throwable $e) {
            Log::error('get lottery statistics failed', ['error' => $e->getMessage()]);
            return $this->jsonError('获取失败');
        }
    }
}
