<?php

namespace App\Listeners\Order;

use App\Enums\NotificationTypeEnum;
use App\Events\Order\OrderPlaced;
use Illuminate\Contracts\Queue\ShouldQueue;

class NewOrderNotification
//    implements ShouldQueue
{

    /**
     * Handle the event.
     */
    public function handle(OrderPlaced $event): void
    {
        $customer = $event->order->user;
        if ($customer) {
            $this->sendNotification(user: $customer, event: $event, sendTo: "customer");
        }
        foreach ($event->order['sellerOrders'] ?? [] as $sellerOrder) {
            $seller = $sellerOrder->seller->user;
            $this->sendNotification(user: $seller, event: $event, sendTo: "seller");
        }
    }

    public function sendNotification($user, $event, $sendTo): void
    {
        $event->firebaseNotification = $this->firebaseNotification(event: $event, sendTo: $sendTo);
        $user->notify(new \App\Notifications\NewOrderNotification($event));
    }

    public function firebaseNotification($event, $sendTo): array
    {
        if ($sendTo === "seller") {
            return [
                'title' => 'New Order Received 🎉',
                'body'  => 'You have received a new order (Order #' . $event->order->id . '). Please review and confirm it at your earliest convenience.',
                'image' => $event->order->items->first()->product->main_image ?? null,
                'data'  => [
                    'order_slug' => $event->order->slug,
                    'order_id'   => $event->order->id,
                    'status'     => $event->order->status,
                    'type'       => NotificationTypeEnum::ORDER(),
                ],
            ];
        }
        return [
            'title' => 'Order Placed Successfully 🎉',
            'body'  => 'Thank you for your order! Your order #' . $event->order->id . ' has been placed successfully. We’ll notify you once it’s confirmed by the seller.',
            'image' => $event->order->items->first()->product->main_image ?? null,
            'data'  => [
                'order_slug' => $event->order->slug,
                'order_id'   => $event->order->id,
                'status'     => $event->order->status,
                'type'       => NotificationTypeEnum::ORDER(),
            ],
        ];
    }
}
