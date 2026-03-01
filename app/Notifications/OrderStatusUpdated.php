<?php

namespace App\Notifications;

use App\Broadcasting\FirebaseChannel;
use App\Enums\NotificationTypeEnum;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class OrderStatusUpdated extends Notification
{

    protected $event;

    /**
     * Create a new notification instance.
     */
    public function __construct($event)
    {
        $this->event = $event;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [
//            'mail',
            FirebaseChannel::class];
    }

    /**
     * Get the firebase representation of the notification.
     */
    public function toFirebase($notifiable)
    {
        return $this->event->firebaseNotification ?? [
            'title' => 'Order Item ' . $this->event->orderItem->title . ' Update',
            'body' => 'Your order Item is now ' . ucfirst($this->event->orderItem->status) . '.',
            'image' => $this->event->orderItem->product->main_image ?? null,
            'data' => [
                'order_slug' => $this->event->orderItem->order->slug,
                'order_id' => $this->event->orderItem->order_id,
                'status' => $this->event->orderItem->status,
                'type' => NotificationTypeEnum::DELIVERY(),
            ],
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): ?MailMessage
    {
        try {
            $orderItem = $this->event->orderItem;
            $sellerOrderItem = $this->event->sellerOrderItem;
            $oldStatus = $this->event->oldStatus;
            $newStatus = $this->event->newStatus;

            return (new MailMessage)
                ->subject('Order Status Updated - ' . now())
                ->greeting('Hello ' . $notifiable->name . '!')
                ->line('Your order status has been updated.')
                ->line('Order ID: ' . $sellerOrderItem->sellerOrder->id)
                ->line('Product: ' . $orderItem->title)
                ->line('Previous Status: ' . $oldStatus)
                ->line('New Status: ' . $newStatus)
                ->action('View Order', url('seller/orders/' . $sellerOrderItem->sellerOrder->id))
                ->line('Thank you for using our application!');
        } catch (\Throwable $e) {
            // Log error but don’t stop the process
            Log::error('Mail notification failed: ' . $e->getMessage(), [
                'notifiable_id' => $notifiable->id ?? null,
                'notification' => static::class,
            ]);

            // return null or a fake MailMessage to avoid exception bubbling
            return null;
        }
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'order_item_id' => $this->event->orderItem->id,
            'seller_order_item_id' => $this->event->sellerOrderItem->id,
            'old_status' => $this->event->oldStatus,
            'new_status' => $this->event->newStatus,
            'seller_id' => $this->event->sellerOrderItem->sellerOrder->seller_id,
        ];
    }
}
