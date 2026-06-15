<?php

namespace FalconCms\Core\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagicLoginMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $magicUrl;
    public string $userName;
    public string $siteName;
    public string $siteUrl;

    public function __construct(string $magicUrl, string $userName = '')
    {
        $this->magicUrl  = $magicUrl;
        $this->userName  = $userName ?: 'there';
        $this->siteName  = get_cms_option('site_title') ?: config('app.name', 'Lazy CMS Builder');
        $this->siteUrl   = config('app.url', url('/'));
    }

    public function envelope(): Envelope
    {
        $from     = get_cms_option('mail_from_address') ?: config('mail.from.address', 'noreply@' . request()->getHost());
        $fromName = get_cms_option('mail_from_name') ?: $this->siteName;

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address($from, $fromName),
            subject: 'Your magic sign-in link — ' . $this->siteName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'falcon-cms::emails.auth.magic_login',
        );
    }
}
