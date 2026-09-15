<?php

declare(strict_types=1);

namespace app\common\model;

use think\Model;


/**
 * @mixin \think\Model
 */
class ShopTimes extends Model
{
    // 表名
    protected $name = "shop_time";
    // 禁用自动时间戳
    protected $autoWriteTimestamp = false;


}
