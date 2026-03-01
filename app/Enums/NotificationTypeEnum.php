<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * NotificationTypeEnum Enum
 *
 * This enum defines the notification types available in the application.
 * It uses traits for invokable cases, values, and names for better usability.
 * @method static GENERAL()
 * @method static ORDER()
 * @method static PAYMENT()
 * @method static DELIVERY()
 * @method static PROMOTION()
 * @method static SYSTEM()
 * @method static PRODUCT()
 */
enum NotificationTypeEnum: string
{
    use InvokableCases, Values, Names;

    case GENERAL = 'general';
    case ORDER = 'order';
    case PAYMENT = 'payment';
    case DELIVERY = 'delivery';
    case PROMOTION = 'promotion';
    case SYSTEM = 'system';
    case PRODUCT = 'product';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function fromString(string $type): self
    {
        return match ($type) {
            'general' => self::GENERAL,
            'order' => self::ORDER,
            'payment' => self::PAYMENT,
            'delivery' => self::DELIVERY,
            'promotion' => self::PROMOTION,
            'system' => self::SYSTEM,
            'product', 'product_created', 'product_updated', 'product_status_updated' => self::PRODUCT,
            default => self::GENERAL,
        };
    }
}
