<?php

namespace App\Domains\Sponsorships\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSponsorshipOrderRequest extends FormRequest
{
    /**
     * True if $url has no host (a relative path) or its host matches this app's own — never an
     * external origin. A plain `starts_with` check would let e.g. `https://suaranetijen.id.evil.com`
     * pass by string-prefix coincidence, so this compares the parsed host exactly.
     */
    public static function isSameOriginRedirect(?string $url): bool
    {
        if ($url === null || $url === '') {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if ($host === null || $host === false) {
            return true;
        }

        return strcasecmp($host, (string) parse_url(config('app.url'), PHP_URL_HOST)) === 0;
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * No account is required to sponsor an entity (docs/26) — a guest supplies an email
     * instead, and a real User is resolved/created from it in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $min = (int) config('sponsorship.min_amount', 1000);

        return [
            // Either an existing entity_id, or a URL match that came back with no candidates —
            // the latter case submits new_entity_* fields instead (docs/26: sponsoring a URL
            // with no existing match creates the entity, Disabled until payment confirms).
            'entity_id' => ['required_without:new_entity_name', 'nullable', 'integer', 'exists:entities,id'],
            'new_entity_name' => ['required_without:entity_id', 'nullable', 'string', 'max:255'],
            'new_entity_category_id' => ['required_without:entity_id', 'nullable', 'integer', 'exists:categories,id'],
            'new_entity_url' => ['required_without:entity_id', 'nullable', 'url', 'max:2048'],
            'new_entity_description' => ['nullable', 'string', 'max:500'],
            'website_url' => ['nullable', 'url', 'max:2048'],
            'amount' => ['required', 'integer', "min:{$min}"],
            'email' => [$this->user() ? 'nullable' : 'required', 'email', 'max:255'],
            // Sumopod redirects the user's browser here after checkout, so an arbitrary
            // external URL would be an open redirect through our own payment flow. Only
            // accept a return URL on this app's own origin.
            'redirect_url' => [
                'nullable', 'url', 'max:2048',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! self::isSameOriginRedirect($value)) {
                        $fail('URL redirect harus berada pada domain aplikasi ini.');
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entity_id.required_without' => 'Pilih entitas yang ingin disponsori.',
            'entity_id.exists' => 'Entitas yang dipilih tidak ditemukan.',
            'new_entity_name.required_without' => 'Nama entitas wajib diisi.',
            'new_entity_category_id.required_without' => 'Pilih kategori entitas.',
            'new_entity_category_id.exists' => 'Kategori tidak ditemukan.',
            'new_entity_url.required_without' => 'URL entitas wajib diisi.',
            'amount.required' => 'Nominal sponsor wajib diisi.',
            'amount.min' => 'Nominal sponsor minimal Rp1.000.',
            'email.required' => 'Masukkan email untuk melanjutkan tanpa akun.',
            'email.email' => 'Format email tidak valid.',
        ];
    }
}
