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

            // Manually curated reference specs (docs/03, ADR-008 clarification) — only the
            // block matching the entity's actual category/type is persisted by the controller,
            // so all of these stay optional here regardless of which category is selected.
            'smartphone_spec' => ['nullable', 'array'],
            'smartphone_spec.chipset' => ['nullable', 'string', 'max:255'],
            'smartphone_spec.ram' => ['nullable', 'string', 'max:100'],
            'smartphone_spec.storage' => ['nullable', 'string', 'max:100'],
            'smartphone_spec.screen_size_inch' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'smartphone_spec.screen_type' => ['nullable', 'string', 'max:255'],
            'smartphone_spec.rear_camera' => ['nullable', 'string', 'max:255'],
            'smartphone_spec.front_camera' => ['nullable', 'string', 'max:255'],
            'smartphone_spec.battery_mah' => ['nullable', 'integer', 'min:0'],
            'smartphone_spec.fast_charging_watt' => ['nullable', 'integer', 'min:0'],
            'smartphone_spec.os' => ['nullable', 'string', 'max:100'],
            'smartphone_spec.network' => ['nullable', 'string', 'max:100'],
            'smartphone_spec.release_year' => ['nullable', 'integer', 'min:1990', 'max:2100'],

            'car_spec' => ['nullable', 'array'],
            'car_spec.body_type' => ['nullable', 'string', 'max:100'],
            'car_spec.engine_cc' => ['nullable', 'integer', 'min:0'],
            'car_spec.cylinder_count' => ['nullable', 'integer', 'min:0', 'max:16'],
            'car_spec.fuel_type' => ['nullable', 'string', 'max:100'],
            'car_spec.power_hp' => ['nullable', 'numeric', 'min:0'],
            'car_spec.torque_nm' => ['nullable', 'numeric', 'min:0'],
            'car_spec.transmission' => ['nullable', 'string', 'max:100'],
            'car_spec.drivetrain' => ['nullable', 'string', 'max:100'],
            'car_spec.fuel_tank_liter' => ['nullable', 'integer', 'min:0'],
            'car_spec.seating_capacity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'car_spec.dimensions_mm' => ['nullable', 'string', 'max:100'],
            'car_spec.release_year' => ['nullable', 'integer', 'min:1990', 'max:2100'],

            'motorcycle_spec' => ['nullable', 'array'],
            'motorcycle_spec.body_type' => ['nullable', 'string', 'max:100'],
            'motorcycle_spec.engine_cc' => ['nullable', 'integer', 'min:0'],
            'motorcycle_spec.cooling_system' => ['nullable', 'string', 'max:100'],
            'motorcycle_spec.fuel_type' => ['nullable', 'string', 'max:100'],
            'motorcycle_spec.power_hp' => ['nullable', 'numeric', 'min:0'],
            'motorcycle_spec.torque_nm' => ['nullable', 'numeric', 'min:0'],
            'motorcycle_spec.transmission' => ['nullable', 'string', 'max:100'],
            'motorcycle_spec.fuel_tank_liter' => ['nullable', 'numeric', 'min:0'],
            'motorcycle_spec.weight_kg' => ['nullable', 'integer', 'min:0'],
            'motorcycle_spec.braking_system' => ['nullable', 'string', 'max:255'],
            'motorcycle_spec.release_year' => ['nullable', 'integer', 'min:1990', 'max:2100'],

            'person_profile' => ['nullable', 'array'],
            'person_profile.birth_date' => ['nullable', 'date'],
            'person_profile.birth_place' => ['nullable', 'string', 'max:255'],
            'person_profile.occupation' => ['nullable', 'string', 'max:255'],
            'person_profile.affiliation' => ['nullable', 'string', 'max:255'],
            'person_profile.active_since_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'person_profile.official_website' => ['nullable', 'url', 'max:255'],
        ];
    }
}
