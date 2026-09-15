<?php

declare(strict_types=1);

namespace app\common\library;

use think\facade\Session;

/**
 * 验证码类
 */
class Captcha
{
    protected $config = [
        'length' => 4,
        'fontSize' => 25,
        'width' => 150,
        'height' => 40,
        'expire' => 300,
    ];
    
    /**
     * 生成验证码
     */
    public function index()
    {
        $code = $this->generateCode();
        
        Session::set('captcha_code', strtolower($code));
        Session::set('captcha_time', time());
        
        return $this->createImage($code);
    }
    
    /**
     * 验证验证码
     * @param string $code 用户输入的验证码
     * @return bool
     */
    public function check(string $code): bool
    {
        $sessionCode = Session::get('captcha_code');
        $sessionTime = Session::get('captcha_time');
        
        if (empty($sessionCode) || empty($sessionTime)) {
            return false;
        }
        
        // 检查是否过期
        if (time() - $sessionTime > $this->config['expire']) {
            Session::delete('captcha_code');
            Session::delete('captcha_time');
            return false;
        }
        
        // 验证码比对
        $result = strtolower($code) === $sessionCode;
        
        // 验证后删除
        Session::delete('captcha_code');
        Session::delete('captcha_time');
        
        return $result;
    }
    
    /**
     * 生成随机验证码
     */
    protected function generateCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $code = '';
        for ($i = 0; $i < $this->config['length']; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }
    
    /**
     * 创建验证码图片
     */
    protected function createImage(string $code)
    {
        $width = $this->config['width'];
        $height = $this->config['height'];
        
        $image = imagecreatetruecolor($width, $height);
        
        // 背景色
        $bgColor = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $bgColor);
        
        // 添加干扰线
        for ($i = 0; $i < 5; $i++) {
            $lineColor = imagecolorallocate($image, random_int(100, 200), random_int(100, 200), random_int(100, 200));
            imageline($image, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), $lineColor);
        }
        
        // 添加干扰点
        for ($i = 0; $i < 50; $i++) {
            $pointColor = imagecolorallocate($image, random_int(100, 200), random_int(100, 200), random_int(100, 200));
            imagesetpixel($image, random_int(0, $width), random_int(0, $height), $pointColor);
        }
        
        // 写入验证码
        $fontFile = __DIR__ . '/arial.ttf';
        $codeLen = strlen($code);
        $x = ($width - $codeLen * $this->config['fontSize'] * 0.7) / 2;
        
        for ($i = 0; $i < $codeLen; $i++) {
            $textColor = imagecolorallocate($image, random_int(0, 100), random_int(0, 100), random_int(0, 100));
            
            if (file_exists($fontFile)) {
                imagettftext($image, $this->config['fontSize'], random_int(-15, 15), (int)$x, (int)($height * 0.75), $textColor, $fontFile, $code[$i]);
            } else {
                imagestring($image, 5, (int)$x, (int)(($height - 16) / 2), $code[$i], $textColor);
            }
            
            $x += $this->config['fontSize'] * 0.7;
        }
        
        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);
        
        return response($imageData, 200, ['Content-Type' => 'image/png']);
    }
}
