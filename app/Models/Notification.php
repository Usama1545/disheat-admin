<?php

namespace App\Models;

use App\Enums\NotificationTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\DatabaseNotification as BaseDatabaseNotification;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * @method static create(array $data)
 * @method static find($id)
 * @method static where(string $column, mixed $value)
 */
/**
 * Backward-compatible wrapper around Laravel's DatabaseNotification.
 *
 * This keeps the existing App\Models\Notification API mostly intact while
 * persisting notifications in the default `notifications` table using the
 * built-in Database channel columns: id, type, notifiable_type, notifiable_id,
 * data (json), read_at, timestamps.
 */
class Notification extends BaseDatabaseNotification
{
    use HasFactory;

    protected $table = 'notifications';

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'read_at' => 'datetime',
        'data' => 'array',
    ];

    /**
     * Get the user that owns the notification.
     */
    public function user(): BelongsTo
    {
        // Legacy helper: maps to notifiable when it's a User
        return $this->belongsTo(User::class, 'notifiable_id')
            ->where('notifiable_type', User::class);
    }

    /**
     * Get the store associated with the notification.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * Get the order associated with the notification.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Scope to get unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope to get read notifications.
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope to filter by notification type.
     */
    public function scopeOfType($query, NotificationTypeEnum $type)
    {
        // Stored as string in `type` column
        return $query->where('type', $type->value ?? (string)$type);
    }

    /**
     * Scope to filter by sent_to.
     */
    public function scopeSentTo($query, string $sentTo)
    {
        // `sent_to` is stored inside JSON `data`
        return $query->where('data->sent_to', $sentTo);
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(): bool
    {
        return $this->forceFill(['read_at' => now()])->save();
    }

    /**
     * Mark notification as unread.
     */
    public function markAsUnread(): bool
    {
        return $this->forceFill(['read_at' => null])->save();
    }

    // -----------------------
    // Legacy attribute helpers
    // -----------------------

    public function getIsReadAttribute(): bool
    {
        return !is_null($this->read_at);
    }

    public function getTitleAttribute(): ?string
    {
        return Arr::get($this->data ?? [], 'title');
    }

    public function getMessageAttribute(): ?string
    {
        return Arr::get($this->data ?? [], 'message');
    }

    public function getMetadataAttribute(): ?array
    {
        return Arr::get($this->data ?? [], 'metadata');
    }

    public function getSentToAttribute(): ?string
    {
        return Arr::get($this->data ?? [], 'sent_to');
    }

    public function getStoreIdAttribute(): ?int
    {
        return Arr::get($this->data ?? [], 'store_id');
    }

    public function getOrderIdAttribute(): ?int
    {
        return Arr::get($this->data ?? [], 'order_id');
    }

    public function getUserIdAttribute(): ?int
    {
        // Prefer explicit user_id in data; fallback to notifiable_id when type is User
        return Arr::get($this->data ?? [], 'user_id', $this->notifiable_type === User::class ? (int) $this->notifiable_id : null);
    }
}
