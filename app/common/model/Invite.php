<?php

declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * @mixin \think\Model
 */
class Invite extends Model
{
    // 表内无自动时间戳字段，禁用自动写入（原有 $time/$updateTime 声明无效且与表结构不符）
    protected $autoWriteTimestamp = false;

}
