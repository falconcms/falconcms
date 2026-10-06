<?php

namespace FalconShop\Mail;

use FalconCms\Core\Mail\Concerns\QueueableViaConfig;
use FalconCms\Core\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, QueueableViaConfig, SerializesModels;

    public $order;

    public $notificationType;

    public $customMessage;

    public $recipientType;

    public $refundAmount;

    public function __construct(Order $order, $notificationType = 'placed', $customMessage = null, $recipientType = 'customer', $refundAmount = null)
    {
        $this->order = $order;
        $this->notificationType = $notificationType; // 'placed', 'status_updated'
        $this->customMessage = $customMessage;
        $this->recipientType = $recipientType; // 'customer', 'admin'
        $this->refundAmount = $refundAmount;      // amount refunded in this action (for refund emails)
        $this->configureQueue();
    }

    public function envelope(): Envelope
    {
        $shopName = get_cms_option('site_name', get_shop_option('shop_store_name', 'Lazy Shop'));

        if ($this->notificationType === 'status_updated') {
            $tplKey = 'email_template_order_status_updated';
            $defaultSubject = 'Update on your order #{{order_number}} [{{new_status}}]';
        } elseif ($this->recipientType === 'admin') {
            $tplKey = 'email_template_order_placed_admin';
            $defaultSubject = '[New Order] #{{order_number}} — {{customer_name}}';
        } else {
            $tplKey = 'email_template_order_placed_customer';
            $defaultSubject = 'Order Confirmation - Order #{{order_number}}';
        }

        $tplData = json_decode(get_cms_option($tplKey, '{}'), true) ?: [];
        $subjectTpl = $tplData['subject'] ?? $defaultSubject;
        $subject = str_replace(
            ['{{order_number}}', '{{customer_name}}', '{{new_status}}', '{{site_name}}'],
            [$this->order->order_number, $this->order->first_name.' '.$this->order->last_name, ucfirst($this->order->status), $shopName],
            $subjectTpl
        );

        return new Envelope(
            from: new Address(
                get_shop_option('shop_email_from_address', 'store@'.request()->getHost()),
                get_shop_option('shop_email_from_name', config('app.name', 'Lazy Panda Shop'))
            ),
            subject: "[$shopName] ".$subject,
        );
    }

    public function content(): Content
    {
        // A site that published the CMS's views may have restyled the email at its old path;
        // that copy still wins over the plugin's own.
        $override = 'falcon-cms::emails.shop.order_notification';

        return new Content(
            view: view()->exists($override) ? $override : 'falcon-shop::emails.order_notification',
        );
    }
}
