<?php

namespace Acme\CmsDashboard\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Acme\CmsDashboard\Models\Order;

class OrderNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $notificationType;
    public $customMessage;

    public function __construct(Order $order, $notificationType = 'placed', $customMessage = null)
    {
        $this->order = $order;
        $this->notificationType = $notificationType; // 'placed', 'status_updated'
        $this->customMessage = $customMessage;
    }

    public function envelope(): Envelope
    {
        $shopName = get_cms_option('site_name', get_shop_option('shop_store_name', 'Lazy Shop'));
        $subject = $this->notificationType === 'placed' 
            ? "Order Confirmation - Order #{$this->order->order_number}"
            : "Update on your order #{$this->order->order_number} [" . strtoupper($this->order->status) . "]";

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address(
                get_shop_option('shop_email_from_address', 'store@' . request()->getHost()),
                get_shop_option('shop_email_from_name', config('app.name', 'Lazy Panda Shop'))
            ),
            subject: "[$shopName] " . $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'cms-dashboard::emails.shop.order_notification',
        );
    }
}
