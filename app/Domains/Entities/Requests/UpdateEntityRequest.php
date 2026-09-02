<?php

namespace App\Domains\Entities\Requests;

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Entity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEntityRequest extends FormRequest
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
        /** @var Entity $entity */
        $entity = $this->route('entity');

        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:entities,id',
                Rule::notIn([$entity->id]),
            ],
            'type' => ['required', Rule::enum(EntityType::class)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('entities', 'slug')->ignore($entity->id)],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(EntityStatus::class)],
            'searchable' => ['boolean'],
            'rankable' => ['boolean'],
        ];
    }
}
