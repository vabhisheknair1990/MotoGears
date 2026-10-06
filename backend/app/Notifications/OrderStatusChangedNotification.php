<?php

namespace App\Notifications;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusChangedNotification extends Notification
{
    public function __construct(public Order $order, public OrderStatus $status) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Order '.$this->order->order_number.' is '.$this->status->label())
            ->line('Your order '.$this->order->order_number.' is now: '.$this->status->label().'.');
        if ($this->order->tracking_number) {
            $mail->line('Tracking number: '.$this->order->tracking_number.' ('.$this->order->carrier.')');
        }

        return $mail->action('Track order', rtrim(config('app.frontend_url'), '/').'/account/orders/'.$this->order->id);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Order '.$this->status->label(),
            'message' => 'Order '.$this->order->order_number.' is now '.$this->status->label().'.',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->status->value,
            'url' => '/account/orders/'.$this->order->id,
        ];
    }
}
