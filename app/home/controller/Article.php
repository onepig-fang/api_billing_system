<?php

declare(strict_types=1);

namespace app\home\controller;

use app\HomeController;
use app\common\model\News;
use think\facade\View;

class Article extends HomeController
{
    public function index()
    {
        $list = [];
        try {
            $list = News::where('status', 1)->order('id', 'desc')->select();
        } catch (\Throwable $e) {
            $list = News::order('id', 'desc')->select();
        }

        View::assign('new', $list);
        return View::fetch('user/inside1/article/index');
    }

    public function details()
    {
        $id = (int) request()->param('id', 0);

        $info = null;
        if ($id > 0) {
            $info = News::find($id);
        }

        $prev = null;
        $next = null;
        if ($info) {
            $prev = News::where('id', '<', $info['id'])->order('id', 'desc')->find();
            $next = News::where('id', '>', $info['id'])->order('id', 'asc')->find();
        }

        View::assign([
            'info' => $info,
            'prev' => $prev,
            'next' => $next,
        ]);

        return View::fetch('user/inside1/article/details');
    }
}
