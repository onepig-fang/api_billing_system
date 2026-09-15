<?php

declare(strict_types=1);

namespace app\common\model;

use think\Model;


/**
 * @mixin \think\Model
 */
class Shops extends Model
{
    // 表名
    protected $name = "shop";
    // 禁用自动时间戳
    protected $autoWriteTimestamp = false;


}
