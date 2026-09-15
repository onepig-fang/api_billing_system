<?php

declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * @mixin \think\Model
 */
class Recharge extends Model
{
    // 定义时间戳字段名
    protected $createTime = 'time';
    protected $updateTime = 'intime';

}
