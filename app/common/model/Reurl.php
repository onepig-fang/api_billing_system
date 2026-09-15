<?php

declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * @mixin \think\Model
 */
class Reurl extends Model
{
    // 禁用自动时间戳，使用表中的 intime 字段
    protected $autoWriteTimestamp = false;


}
