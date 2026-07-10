<?php

namespace FalconCms\Core\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use FalconCms\Core\Mail\Concerns\QueueableViaConfig;

class EmailVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, QueueableViaConfig;

    public string $verifyUrl;
    public string $userName;
    public string $siteName;
    public string $siteUrl;
    public int $expiresMinutes;

    public function __construct(string $verifyUrl, string $userName = '', int $expiresMinutes = 5)
    {
        $this->verifyUrl      = $verifyUrl;
        $this->userName       = $userName ?: 'there';
        $this->expiresMinutes = $expiresMinutes;
        $this->siteName       = get_cms_option('site_title') ?: config('app.name', 'Falcon CMS');
        $this->siteUrl        = config('app.url', url('/'));
        $this->configureQueue();
    }

    public function envelope(): Envelope
    {
        $from     = get_cms_option('mail_from_address') ?: config('mail.from.address', 'noreply@' . request()->getHost());
        $fromName = get_cms_option('mail_from_name') ?: $this->siteName;

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address($from, $fromName),
            subject: 'Verify your email address — ' . $this->siteName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'falcon-cms::emails.auth.verify_email',
        );
    }
}
