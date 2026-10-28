<?php

namespace App\Domains\Entities\Requests;

use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEntitySubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ! $this->user()->isBanned();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'entity_type' => ['required', Rule::in([
                EntityType::Brand->value,
                EntityType::Product->value,
                EntityType::Service->value,
            ])],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->whereIn('id', Category::query()->active()->excludePublicFigure()->pluck('id')),
            ],
            'parent_entity_id' => [
                'nullable',
                'integer',
                Rule::exists('entities', 'id')->where('type', EntityType::Brand->value),
            ],
            'website_url' => ['nullable', 'url:http,https', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_owner' => ['boolean'],
        ];
    }
}
