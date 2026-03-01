<?php

namespace App\Services;

use App\Enums\NotificationTypeEnum;
use App\Models\Notification;
use App\Models\User;
use App\Models\Store;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Exception;

class NotificationService
{
    /**
     * Create a new notification
     *
     * @param array $data
     * @return Notification
     * @throws Exception
     */
    public function createNotification(array $data): Notification
    {
        try {
            DB::beginTransaction();

            $notification = Notification::create([
                'user_id' => $data['user_id'] ?? null,
                'store_id' => $data['store_id'] ?? null,
                'order_id' => $data['order_id'] ?? null,
                'type' => $data['type'] ?? NotificationTypeEnum::GENERAL,
                'sent_to' => $data['sent_to'] ?? 'admin',
                'title' => $data['title'],
                'message' => $data['message'],
                'is_read' => $data['is_read'] ?? false,
                'metadata' => $data['metadata'] ?? null,
            ]);

            DB::commit();

            return $notification;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get notifications for a specific user
     *
     * @param int $userId
     * @param int $perPage
     * @return array
     */
    public function getUserNotifications(int $userId, int $perPage = 15): array
    {
        $notifications = Notification::where('user_id', $userId)
            ->with(['user', 'store', 'order'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return [
            'notifications' => $notifications->items(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ]
        ];
    }

    /**
     * Get notifications by sent_to type
     *
     * @param string $sentTo
     * @param int $perPage
     * @return array
     */
    public function getNotificationsBySentTo(string $sentTo, int $perPage = 15): array
    {
        $notifications = Notification::sentTo($sentTo)
            ->with(['user', 'store', 'order'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return [
            'notifications' => $notifications->items(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ]
        ];
    }

    /**
     * Get unread notifications count for a user
     *
     * @param int $userId
     * @return int
     */
    public function getUnreadCount(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->unread()
            ->count();
    }

    /**
     * Mark notification as read
     *
     * @param int $notificationId
     * @return bool
     * @throws Exception
     */
    public function markAsRead(int $notificationId): bool
    {
        try {
            $notification = Notification::findOrFail($notificationId);
            return $notification->markAsRead();
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Mark all notifications as read for a user
     *
     * @param int $userId
     * @return bool
     * @throws Exception
     */
    public function markAllAsRead(int $userId): bool
    {
        try {
            DB::beginTransaction();

            Notification::where('user_id', $userId)
                ->unread()
                ->update(['is_read' => true]);

            DB::commit();

            return true;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function markAllAsReadAdmin(): bool
    {
        try {
            DB::beginTransaction();

            Notification::where('sent_to', 'admin')
                ->unread()
                ->update(['is_read' => true]);

            DB::commit();

            return true;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete a notification
     *
     * @param int $notificationId
     * @return bool
     * @throws Exception
     */
    public function deleteNotification(int $notificationId): bool
    {
        try {
            $notification = Notification::findOrFail($notificationId);
            return $notification->delete();
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Get notifications by type
     *
     * @param NotificationTypeEnum $type
     * @param int $perPage
     * @return array
     */
    public function getNotificationsByType(NotificationTypeEnum $type, int $perPage = 15): array
    {
        $notifications = Notification::ofType($type)
            ->with(['user', 'store', 'order'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return [
            'notifications' => $notifications->items(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ]
        ];
    }

    /**
     * Send notification to multiple users
     *
     * @param array $userIds
     * @param array $notificationData
     * @return Collection
     * @throws Exception
     */
    public function sendBulkNotifications(array $userIds, array $notificationData): Collection
    {
        try {
            DB::beginTransaction();

            $notifications = collect();

            foreach ($userIds as $userId) {
                $data = array_merge($notificationData, ['user_id' => $userId]);
                $notification = $this->createNotification($data);
                $notifications->push($notification);
            }

            DB::commit();

            return $notifications;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
