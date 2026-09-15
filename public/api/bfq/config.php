<?php

include_once './class/Https.php';
// 从Cookie中获取用户key
if (isset($_COOKIE['user_key'])) {
    $userKey =$_COOKIE['user_key'];
} else {
    // 如果Cookie中没有key，使用默认值
    $userKey = 'default';
}
$u = 'http://'.$_SERVER["HTTP_HOST"] .'/ajax/users?key='.$userKey;

$result = Https::geturl($u);

$tokenKey = getenv('BFQ_TOKEN_KEY') ?: hash('sha256', 'token|' . $userKey);

 $config =  array (
   'name' =>$result['client_name'],
    //'https://res11.bignox.com/player/www/101ae5b183a03384c8005e2a827bc4fc/GDCHDGCCHrh8FpZ.mp4',
    'video' => 'https://wallpaperm.cmcm.com/live/preview_video/eb0a79367fc50c4b97a51dff3b8879e9_preview.mp4', //无地址引导页背景视频地址。

    'aes_key' => getenv('BFQ_AES_KEY') ?: substr(hash('sha256', 'aes-key|' . $tokenKey), 0, 16),//aeskey16位需修改setting.js底部一致。

    'aes_iv'  => getenv('BFQ_AES_IV') ?: substr(hash('sha256', 'aes-iv|' . $tokenKey), 0, 16),//aesiv16位需修改setting.js底部一致。
    
   'interface' =>  'http://'.$_SERVER["HTTP_HOST"].'/api.php/?key='.$userKey.'&url=',//JSON接口
    
    'Standby' => $result['byjx'],//备用json接口多个请使用,号隔开。
    
    'bofangqi' => 'artplayer', //播放器 dplayer artplayer 两种
    
    'fanhuileixing' => '1', //播放器返回模式，参数1为网页播放器，参数2为json。(api.php?url=地址访问)
    
    'fangdaoleixing' => '',//返回模式为2时，留空不需带key直接访问，0密钥错误时输出404，1带自定义输出url。
    
    'token_ua' => '',//返回模式为2时，限制指定UA来源访问，留空不开启
    
    'token_key' => $tokenKey,//返回模式为2时，防盗类型为0或者1时，访问api.php?key=12345678&url=地
    
   'token_url' => $result['referer'],//返回模式为2时，防盗类型为1时，限制UA开启时，输出的自定义链接
    
    'theme' => '#165DFF',//播放器进度条颜色设置。
    
    'background' => $result['img'],//播放器背景图片。
    
    'loading' => 'artplayer/img/load.gif',//播放器加载图片。
    
    'zantingguanggaoqidong' => $result['ggkg'],//暂停播放时的广告启动开关，1为启动，0为关闭。
    
    'zantingguanggaourl' => $result['dmfirst'],//广告图片地址。
    
    'zantingguanggaolianjie' => $result['you_href'],//广告链接地址。
    
    'danmuqidong' => $result['dmkupass'],//播放器弹幕启动开关，参数1为开启，参数0为关闭。
    
    'dmapi' => $result['dm'],//弹幕库地址,可用远程弹幕库，，例如http://www.baidu.com/dmku/。https://dmku.m3u8.pw/
    
    'sendtime' => 3,//发送弹幕的间隔时间限制，单位为秒。
    
    'pbgjz' => '操ABCDEFGHIJKLMNOPQRSTUVWSYZabcdefghijklmnopqrstuvwsyz',//弹幕敏感关键字限制。
);
