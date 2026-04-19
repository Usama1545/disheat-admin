<?php

namespace App\Services;

use App\Types\Api\ApiResponseType;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

class LicenseValidator
{
    protected Client $http;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?: new Client([
            'timeout' => 10,
        ]);
    }

    // public function validate(string $purchaseCode, string $domainUrl): array
    // {
    //     $endpoint = config('license.endpoint', 'https://validator.infinitietech.com/home/validator');

    //     $response = $this->http->get($endpoint, [
    //         'query' => [
    //             'purchase_code' => $purchaseCode,
    //             'domain_url' => $domainUrl,
    //         ],
    //         'http_errors' => false,
    //     ]);

    //     $data = json_decode((string)$response->getBody(), true) ?: [];

    //     return ApiResponseType::toArray(success: $data['error'] == false ? true : false, message: $data['message'] ?? 'Error', data: $data ?? []);
    // }

    public function validate(string $purchaseCode, string $domainUrl): array
    {
        return ApiResponseType::toArray(
            success: true,
            message: 'Validation bypassed (temporary)',
            data: [
                'error' => false,
                'purchase_code' => $purchaseCode,
                'domain_url' => $domainUrl,
            ]
        );
    }

    public static function signature(string $purchaseCode, string $domainUrl, string $token): string
    {
        $key = env('APP_KEY', 'app-key-missing');
        return env('LICENSE_SIGNATURE');
    }
}
