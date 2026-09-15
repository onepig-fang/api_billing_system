<?php

declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * @mixin \think\Model
 */
class Admin extends Model
{
    // 禁用自动时间戳（login_time 是手动设置的最后登录时间）
    protected $autoWriteTimestamp = false;


  public function getInfo($id)
    {
        try{
            $info = self::where('id', $id)->find();
            if ($info) {
                // 头像
                if ($info['qq']) {
                    $info['img'] = 'http://q1.qlogo.cn/g?b=qq&nk='.$info['qq'].'&s=100';
                }
                return $info;
            }
            return false;
        }catch (\Exception $e){
            return false;
        }
    }

    /**
     * 根据用户名/密码 进行登录判断
     * @param $user
     * @param $pwd
     * @return Admin|array|mixed|Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function checkLogin($user, $pwd)
    {
        $admin = Admin::where('username', '=', htmlspecialchars(trim($user)))->find();
        if (!$admin) {
            return null;
        }

        $password = trim((string)$pwd);
        $storedHash = (string)($admin->password ?? '');
        if (!secure_password_verify($password, $storedHash)) {
            return null;
        }

        if (secure_password_needs_rehash($storedHash)) {
            $admin->password = secure_password_hash($password);
            $admin->save();
        }

        return $admin;
    }

}
