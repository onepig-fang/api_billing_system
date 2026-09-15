<?php

declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * @mixin \think\Model
 */
class News extends Model
{
    // 表内无自动时间戳字段，禁用自动写入（原有声明与表结构不符）
    protected $autoWriteTimestamp = false;
    protected $name = "new";

}
