<?php

namespace App\Http\Requests\Addon;

use App\Enums\Attribute\AttributeTypesEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rule;

class StoreAddonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'type' => 'required|in:single,multiple',
            'is_required' => 'nullable|boolean',
            'min_select' => 'nullable|integer|min:0',
            'max_select' => 'nullable|integer|min:0|gte:min_select',
            'options' => 'required_if:type,multiple|array|min:1',
            'options.*.name' => 'required|string|max:255',
            'options.*.price' => 'required|numeric|min:0',
        ];
    }
}
