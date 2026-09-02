<?php

namespace App\Domains\Entities\Requests;

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEntityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'parent_id' => ['nullable', 'integer', 'exists:entities,id'],
            'type' => ['required', Rule::enum(EntityType::class)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:entities,slug'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(EntityStatus::class)],
            'searchable' => ['boolean'],
            'rankable' => ['boolean'],
        ];
    }
}
