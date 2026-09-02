<?php

namespace App\Domains\Entities\Requests;

use App\Domains\Entities\Enums\AliasType;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Services\TextNormalizer;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreEntityAliasRequest extends FormRequest
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
        $entity = $this->route('entity');
        $entityId = $entity instanceof Entity ? $entity->id : (int) $entity;

        return [
            'alias' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($entityId): void {
                    $normalized = TextNormalizer::normalize((string) $value);
                    if ($normalized === '') {
                        $fail('The alias must contain valid alphanumeric characters.');

                        return;
                    }

                    $exists = DB::table('entity_aliases')
                        ->where('entity_id', $entityId)
                        ->where('normalized_alias', $normalized)
                        ->exists();

                    if ($exists) {
                        $fail('This alias (or an equivalent normalized form) already exists for this entity.');
                    }
                },
            ],
            'alias_type' => ['nullable', Rule::enum(AliasType::class)],
        ];
    }
}
