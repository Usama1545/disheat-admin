<?php

namespace App\Types\Settings;

use App\Interfaces\SettingInterface;
use App\Traits\SettingTrait;

class AppSettingType implements SettingInterface
{
    use SettingTrait;
    public string $appstoreLink = '';
    public string $playstoreLink = '';
    public string $appScheme = '';
    public string $appDomainName = '';
    protected static function getValidationRules(): array
    {
        return [
            'appstoreLink' => 'required|url',
            'playstoreLink' => 'required|url',
            'appScheme' => 'required',
            'appDomainName' => 'required',
        ];
    }
}
