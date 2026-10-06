<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlacedNotification extends Notification
{
    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Order '.$this->order->order_number.' received')
            ->greeting('Thanks for your order, '.$notifiable->name.'!')
            ->line('Order number: '.$this->order->order_number)
            ->line('Total: ₹'.number_format((float) $this->order->grand_total, 2))
            ->action('View order', rtrim(config('app.frontend_url'), '/').'/account/orders/'.$this->order->id);
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
