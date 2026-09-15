<?php

declare(strict_types=1);

namespace app\common\model;

use think\Model;


/**
 * @mixin \think\Model
 */
class Setting extends Model
{
    // 表内无自动时间戳字段，禁用自动写入（原有声明与表结构不符）
    protected $autoWriteTimestamp = false;

    // 字段类型转换，确保读取为期望类型
    protected $type = [
        'pack_enabled' => 'integer',
        'pack_cost' => 'integer',
    ];

}
