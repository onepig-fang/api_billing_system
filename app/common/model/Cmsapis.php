<?php

declare(strict_types=1);

namespace app\common\model;

use think\Model;


/**
 * @mixin \think\Model
 */
class Cmsapis extends Model
{
    // 表名
    protected $name = "cmsapi";
    // 主键
    protected $pk = 'cmsapi_id';
    // 禁用自动时间戳
    protected $autoWriteTimestamp = false;


}
