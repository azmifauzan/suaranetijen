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
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
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
            'entity_id' => ['required', 'integer', 'exists:entities,id'],
            'amount' => ['required', 'integer', "min:{$min}"],
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
            'entity_id.required' => 'Pilih entitas yang ingin disponsori.',
            'entity_id.exists' => 'Entitas yang dipilih tidak ditemukan.',
            'amount.required' => 'Nominal sponsor wajib diisi.',
            'amount.min' => 'Nominal sponsor minimal Rp1.000.',
        ];
    }
}
