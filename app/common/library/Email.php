<?php

declare(strict_types=1);

namespace app\common\library;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use app\common\model\Setting;

/**
 * 邮件发送类
 */
class Email
{
    protected $config = [];
    
    public function __construct()
    {
        $setting = Setting::find(1);
        if ($setting) {
            $this->config = [
                'host' => $setting['smtp_host'] ?? '',
                'port' => $setting['smtp_port'] ?? 465,
                'username' => $setting['smtp_user'] ?? '',
                'password' => $setting['smtp_pass'] ?? '',
                'from' => $setting['smtp_from'] ?? '',
                'fromname' => $setting['smtp_fromname'] ?? '系统邮件',
            ];
        }
    }
    
    /**
     * 发送邮件
     * @param string $to 收件人
     * @param string $subject 主题
     * @param string $body 内容
     * @return bool
     */
    public function send(string $to, string $subject, string $body): bool
    {
        if (empty($this->config['host']) || empty($this->config['username'])) {
            return false;
        }
        
        try {
            $mail = new PHPMailer(true);
            
            // 服务器配置
            $mail->isSMTP();
            $mail->Host = $this->config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['username'];
            $mail->Password = $this->config['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = (int)$this->config['port'];
            $mail->CharSet = 'UTF-8';
            
            // 发件人
            $mail->setFrom($this->config['from'] ?: $this->config['username'], $this->config['fromname']);
            
            // 收件人
            $mail->addAddress($to);
            
            // 内容
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            return $mail->send();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * 发送验证码邮件
     * @param string $to 收件人
     * @param string $code 验证码
     * @return bool
     */
    public function sendCode(string $to, string $code): bool
    {
        $subject = '邮箱验证码';
        $body = "
            <div style='padding: 20px; background: #f5f5f5;'>
                <div style='max-width: 500px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 5px;'>
                    <h2 style='color: #333; margin-bottom: 20px;'>邮箱验证码</h2>
                    <p style='color: #666; line-height: 1.8;'>您的验证码是：</p>
                    <p style='font-size: 24px; font-weight: bold; color: #1890ff; letter-spacing: 5px;'>{$code}</p>
                    <p style='color: #999; font-size: 12px; margin-top: 20px;'>验证码有效期为5分钟，请勿泄露给他人。</p>
                </div>
            </div>
        ";
        
        return $this->send($to, $subject, $body);
    }
}
