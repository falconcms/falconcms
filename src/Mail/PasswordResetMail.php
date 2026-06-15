<?php

namespace FalconCms\Core\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $resetUrl;
    public string $userName;
    public string $siteName;
    public string $siteUrl;

    public function __construct(string $resetUrl, string $userName = '')
    {
        $this->resetUrl  = $resetUrl;
        $this->userName  = $userName ?: 'there';
        $this->siteName  = get_cms_option('site_title') ?: config('app.name', 'Lazy CMS Builder');
        $this->siteUrl   = config('app.url', url('/'));
    }

    public function envelope(): Envelope
    {
        $from    = get_cms_option('mail_from_address') ?: config('mail.from.address', 'noreply@' . request()->getHost());
        $fromName = get_cms_option('mail_from_name') ?: $this->siteName;

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address($from, $fromName),
            subject: 'Reset your password — ' . $this->siteName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'falcon-cms::emails.auth.password_reset',
        );
    }
}
