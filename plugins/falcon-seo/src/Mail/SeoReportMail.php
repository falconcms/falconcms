<?php

namespace FalconSeo\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The weekly SEO email: the average score, what dropped, broken links, what to fix first. */
class SeoReportMail extends Mailable
{
    public string $siteName;

    public function __construct(public array $data)
    {
        $this->siteName = (string) (get_cms_option('site_title') ?: config('app.name', 'FalconCMS'));
    }

    public function envelope(): Envelope
    {
        $from = get_cms_option('mail_from_address') ?: config('mail.from.address');
        $fromName = get_cms_option('mail_from_name') ?: $this->siteName;
        $headline = $this->data['brokenCount'] ? $this->data['brokenCount'].' broken link(s)' : 'SEO score '.($this->data['average'] ?? '–');

        return new Envelope(
            from: $from ? new Address($from, $fromName) : null,
            subject: "SEO this week: {$headline} — {$this->siteName}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'falcon-seo::emails.seo-report');
    }
}
