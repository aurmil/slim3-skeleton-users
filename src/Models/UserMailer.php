<?php
declare(strict_types = 1);

namespace App\Models;

class UserMailer
{
    /**
     * @var Swift_Mailer
     */
    protected $mailer;

    /**
     * @var array|string
     */
    protected $from;

    /**
     * @var string
     */
    protected $templatesPath;

    /**
     * @var string
     */
    protected $siteName;

    public function __construct(\Swift_Mailer $mailer, string $templatesPath)
    {
        $this->mailer = $mailer;
        $this->templatesPath = rtrim($templatesPath, '/') . '/';
    }

    /**
     * @param array|string $from
     *
     * @throws \InvalidArgumentException
     */
    public function setFrom($from)
    {
        if (!is_array($from) && !is_string($from)) {
            throw new \InvalidArgumentException('Parameter must be a string or an array');
        }

        $this->from = $from;
    }

    public function setSiteName(string $siteName)
    {
        $this->siteName = $siteName;
    }

    public function sendActivateAccountEmail(
        string $to,
        string $activateAccountUrl
    ): int {
        $body = str_replace([
                '[subject]',
                '[site_name]',
                '[activate_account_url]',
            ], [
                'Activate your account',
                $this->siteName,
                $activateAccountUrl,
            ],
            file_get_contents($this->templatesPath . 'activate-account.html')
        );

        $mail = new \Swift_Message();
        $mail->setSubject('Activate your account')
            ->setBody($body, 'text/html')
            ->setFrom($this->from)
            ->setTo($to);

        return $this->mailer->send($mail);
    }

    public function sendResetPasswordEmail(
        string $to,
        string $resetPasswordUrl
    ): int {
        $body = str_replace([
                '[subject]',
                '[site_name]',
                '[reset_password_url]',
            ], [
                'Reset your password',
                $this->siteName,
                $resetPasswordUrl,
            ],
            file_get_contents($this->templatesPath . 'reset-password.html')
        );

        $mail = new \Swift_Message();
        $mail->setSubject('Reset your password')
            ->setBody($body, 'text/html')
            ->setFrom($this->from)
            ->setTo($to);

        return $this->mailer->send($mail);
    }
}
