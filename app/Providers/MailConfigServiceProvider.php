<?php

namespace App\Providers;

use App\Enums\SettingTypeEnum;
use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Config;

class MailConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if (!file_exists(storage_path('installed'))) {
            return;
        }
        if (!Schema::hasTable('settings')) {
            return;
        }

        $emailSettings = Setting::find(SettingTypeEnum::EMAIL());
        if (!empty($emailSettings->value)) {
            $emailSettings = $emailSettings->value;
            Config::set('mail.mailers.smtp.host', $emailSettings['smtpHost']);
            Config::set('mail.mailers.smtp.port', $emailSettings['smtpPort']);
            Config::set('mail.mailers.smtp.username', $emailSettings['smtpEmail']);
            Config::set('mail.mailers.smtp.password', $emailSettings['smtpPassword'] ?? null);
            Config::set('mail.mailers.smtp.encryption', $emailSettings['smtpEncryption']);
            Config::set('mail.from.address', $emailSettings['smtpEmail']);
        }
//            Config::set('mail.from.name', $emailData['smtpHost']);

    }
}
