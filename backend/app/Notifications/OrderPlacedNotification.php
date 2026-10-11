<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the customer's account, or straight to the email address for guest orders. */
class OrderPlacedNotification extends Notification
{
    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $o = $this->order;
        $name = $notifiable->name ?? $o->customer_name ?? 'there';
        $mail = (new MailMessage)
            ->subject('Order '.$o->order_number.' received')
            ->greeting('Thanks for your order, '.$name.'!')
            ->line('Order number: '.$o->order_number)
            ->line('Total: ₹'.number_format((float) $o->grand_total, 2));
        if ($o->is_guest) {
            $mail->line('Use the button below to see your order, pay, download the invoice or cancel. Keep this email — the link is private to you.')
                ->line('You can also find your order anytime under "Track order" with the order number and this email address.');
        }

        return $mail->action('View order', $o->customerUrl());
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Order placed',
            'message' => 'Your order '.$this->order->order_number.' has been placed.',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'url' => '/account/orders/'.$this->order->id,
        ];
    }
}
