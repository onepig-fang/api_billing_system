<?php

declare(strict_types=1);

// +----------------------------------------------------------------------
// | 数卡网 [基于ThinkPHP8开发]
// +----------------------------------------------------------------------
// | Copyright (c) 2020-2030 http://www.shukawl.cn
// +----------------------------------------------------------------------
// | www.shukawl.cn High Speed Development Framework
// +----------------------------------------------------------------------
// | QQ：541628358     邮箱：541628358@qq.com
// +----------------------------------------------------------------------

namespace admin;

use think\Facade;

/**
 * 随机字符串
 * 
 */

class Random extends Facade
{
    protected static function getFacadeClass(): string
    {
        return 'admin\RandomClass';
    }
}

class RandomClass
{

    /**
     * @var object 对象实例
     */
    protected static $instance = null;

    /**
     * 类构造函数
     * class constructor.
     */
    public function __construct()
    {}

    /**
     * 初始化
     * @access public
     * @param array $options 参数
     * @return self
     */

    public static function instance(array $options = [])
    {
        if (is_null(self::$instance)) {
            self::$instance = new static($options);
        }

        // 返回实例
        return self::$instance;
    }

    /**
     * 生成大小写字母
     *
     * @param integer $length
     * @return string
     */
    public function alpha(int $length = 6): string
    {
        return self::Generate('alpha', $length);
    }

    /**
     * 生成纯数字
     * @param integer $length
     * @return string
     */
    public function number(int $length = 6): string
    {
        return self::Generate('number', $length);
    }

    /**
     * 生成小写字母
     * @param integer $length
     * @return string
     */
    public function lower(int $length = 6): string
    {
        return self::Generate('lower', $length);
    }

    /**
     * 生成大写字母
     * @param integer $length
     * @return string
     */
    public function upper(int $length = 6): string
    {
        return self::Generate('upper', $length);
    }

    /**
     * 下划线随机
     *
     * @param integer $length
     * @return string
     */
    public function alphaDash(int $length = 6): string
    {
        return self::Generate('alphaDash', $length);
    }

    /**
     * 生成数字+字母
     * @param integer $length
     * @return string
     */
    public function alphaNum(int $length = 6): string
    {
        return self::Generate('alphaNum', $length);
    }

    /**
     * 生成随机字符
     * @param string $type
     * @param integer $length
     * @return string
     */
    public function Generate(string $type = 'alpha', int $length = 6): string
    {
        $config = [
            'number'    => '1234567890',
            'lower'     => 'abcdefghijklmnopqrstuvwxyz',
            'upper'     => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'alpha'     => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'alphaDash' => '_abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'alphaNum'  => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
        ];

        $letter = str_shuffle($config[$type]);
        return substr($letter, 0, $length);
    }

    /**
     * 生成订单ID
     *
     * @param boolean $other
     * @return string
     */
    public function orderId(bool $other = false): string
    {
        if (!$other) {
            return date('Ymd') . str_pad((string)mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        } else {
            return date('Ymd') . substr(implode('', array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
        }
    }
    
}
