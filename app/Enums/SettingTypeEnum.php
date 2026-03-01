<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static PAYMENT()
 */
enum SettingTypeEnum: string
{
    use InvokableCases, Values, Names;
    case SYSTEM = 'system';
    case STORAGE = 'storage';
    case EMAIL = 'email';
    case PAYMENT = 'payment';
    case AUTHENTICATION = 'authentication';
    case NOTIFICATION = 'notification';
    case WEB = 'web';
    case APP = 'app';
    case DELIVERY_BOY = 'delivery_boy';
    case HOME_GENERAL_SETTINGS = 'home_general_settings';
}
