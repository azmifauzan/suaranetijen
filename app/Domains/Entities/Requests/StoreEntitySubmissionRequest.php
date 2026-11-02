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
            // The sponsor path skips admin review and pays instead; the sponsor form needs the site.
            'sponsor' => ['boolean'],
            'website_url' => [Rule::requiredIf(fn (): bool => $this->boolean('sponsor')), 'nullable', 'url:http,https', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_owner' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'website_url.required' => 'Isi website resmi untuk jalur sponsor.',
        ];
    }
}
