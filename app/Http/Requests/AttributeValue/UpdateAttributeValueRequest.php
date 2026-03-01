<?php

namespace App\Http\Requests\AttributeValue;

use App\Enums\Attribute\AttributeTypesEnum;
use App\Models\GlobalProductAttribute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateAttributeValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'attribute_id' => [
                'required',
                Rule::exists('global_product_attributes', 'id')
                    ->withoutTrashed(),
            ],
            'values' => 'nullable|array',
            'values.*' => 'nullable|string|max:255',
            'swatche_value' => 'nullable|array',
        ];

        $attribute = GlobalProductAttribute::find($this->attribute_id);
        if ($attribute && $attribute->swatche_type === 'text') {
            $rules['swatche_value'] = 'required|array';
            $rules['swatche_value.*'] = 'required';
        } else {
            $rules['swatche_value.*'] = 'nullable';
        }

        return $rules;
    }

}
