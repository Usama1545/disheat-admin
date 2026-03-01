<?php

namespace App\Http\Resources\Setting;

use App\Traits\PanelAware;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationSettingResource extends JsonResource
{
    use PanelAware;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'variable' => $this->variable,
            'value' => [
                'firebaseProjectId' => $this->value['firebaseProjectId'] ?? '',
                'vapIdKey' => $this->value['vapIdKey'] ?? '',
            ]
        ];

        // Only admin panel can access serviceAccountFile
        if ($this->getPanel() === 'admin') {
            $data['value']['serviceAccountFile'] = $this->value['serviceAccountFile'] ?? '';
        }

        return $data;
    }
}
