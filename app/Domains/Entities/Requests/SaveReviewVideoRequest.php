<?php

namespace App\Domains\Entities\Requests;

use App\Domains\Entities\Models\EntityReviewVideo;
use App\Domains\Entities\Services\ReviewVideoLookup;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Add or edit a review video. On add the link is required; on edit it is only
 * read for manual videos (an automatic video keeps the id the search found).
 */
class SaveReviewVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'url' => [$this->existingVideo() === null ? 'required' : 'nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:250'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $existing = $this->existingVideo();
            $url = trim((string) $this->input('url'));

            if ($validator->errors()->isNotEmpty() || $url === '' || $existing?->source === EntityReviewVideo::SOURCE_AUTO) {
                return;
            }

            $lookup = app(ReviewVideoLookup::class);
            $id = $lookup->parseId($url);

            if ($id === null) {
                $validator->errors()->add('url', 'Not a YouTube video link.');

                return;
            }

            $title = $lookup->embeddableTitle($id);

            if ($title === null) {
                $validator->errors()->add('url', 'YouTube cannot find or embed this video.');

                return;
            }

            $this->merge(['youtube_id' => $id, 'lookup_title' => $title]);
        }];
    }

    private function existingVideo(): ?EntityReviewVideo
    {
        $video = $this->route('video');

        return $video instanceof EntityReviewVideo ? $video : null;
    }
}
