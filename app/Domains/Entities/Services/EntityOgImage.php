<?php

namespace App\Domains\Entities\Services;

use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Sentiment\Services\ScoreCalculator;
use GdImage;
use Illuminate\Support\Facades\File;

/**
 * Social share card for an entity page (docs/31 Fase 2). Only entities that clear the public
 * threshold get one; the card shows the same three numbers as the page and nothing else.
 */
class EntityOgImage
{
    private const WIDTH = 1200;

    private const HEIGHT = 630;

    /**
     * The snapshot the share card is drawn from: 365 days, else all time, and only when the
     * entity clears the public score threshold.
     */
    public function eligibleSnapshot(Entity $entity): ?SentimentSnapshot
    {
        $snapshots = SentimentSnapshot::query()
            ->where('entity_id', $entity->id)
            ->whereIn('period', [Period::OneYear->value, Period::All->value])
            ->get();

        $snapshot = $snapshots->firstWhere('period', Period::OneYear) ?? $snapshots->firstWhere('period', Period::All);

        if ($snapshot === null || $snapshot->score === null || ! ScoreCalculator::isPublicScoreEligible((int) $snapshot->opinion_count)) {
            return null;
        }

        return $snapshot;
    }

    /**
     * Changes whenever a number on the card changes, so the URL and the cached file both roll over.
     */
    public function version(SentimentSnapshot $snapshot): string
    {
        return substr(md5(implode('|', [
            $snapshot->score,
            $snapshot->opinion_count,
            $snapshot->positive_count,
            $snapshot->neutral_count,
            $snapshot->negative_count,
        ])), 0, 10);
    }

    /**
     * Path of the rendered card, rendering it first when this version has not been drawn yet.
     */
    public function path(Entity $entity, SentimentSnapshot $snapshot): string
    {
        $directory = storage_path('app/og');
        $path = "{$directory}/{$entity->slug}-{$this->version($snapshot)}.png";

        if (! is_file($path)) {
            File::ensureDirectoryExists($directory);

            foreach (glob("{$directory}/{$entity->slug}-*.png") ?: [] as $stale) {
                if (preg_match('/^'.preg_quote($entity->slug, '/').'-[0-9a-f]{10}\.png$/', basename($stale))) {
                    @unlink($stale);
                }
            }

            file_put_contents($path, $this->render($entity, $snapshot));
        }

        return $path;
    }

    public function render(Entity $entity, SentimentSnapshot $snapshot): string
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagealphablending($image, true);

        $green = $this->color($image, 8, 127, 91);
        $ink = $this->color($image, 24, 57, 45);
        $muted = $this->color($image, 85, 105, 90);
        $white = $this->color($image, 255, 255, 255);

        imagefilledrectangle($image, 0, 0, self::WIDTH, self::HEIGHT, $this->color($image, 244, 247, 241));
        imagefilledrectangle($image, 0, 0, 16, self::HEIGHT, $green);

        $regular = resource_path('fonts/DejaVuSans.ttf');
        $bold = resource_path('fonts/DejaVuSans-Bold.ttf');

        $this->text($image, 28, 72, 82, $green, $bold, 'SuaraNetijen');

        $this->text($image, 26, 72, 124, $muted, $regular, $entity->category->name);

        $nameLines = $this->wrap($entity->name, $bold, 64, 1056, 2);
        $y = 200;
        foreach ($nameLines as $line) {
            $this->text($image, 64, 72, $y, $ink, $bold, $line);
            $y += 80;
        }

        $score = (string) round((float) $snapshot->score);
        $this->text($image, 120, 72, 468, $green, $bold, $score);
        $scoreWidth = $this->width($score, $bold, 120);
        $this->text($image, 36, 72 + $scoreWidth + 12, 468, $muted, $regular, '/100');
        $this->text($image, 26, 72, 508, $ink, $bold, 'Sentimen Netijen');

        $count = number_format((int) $snapshot->opinion_count, 0, ',', '.');
        $this->text($image, 32, 600, 440, $ink, $bold, "{$count} opini netizen");

        $total = max(1, (int) $snapshot->positive_count + (int) $snapshot->neutral_count + (int) $snapshot->negative_count);
        $segments = [
            [(int) $snapshot->positive_count, $this->color($image, 8, 127, 91), 'positif'],
            [(int) $snapshot->neutral_count, $this->color($image, 156, 163, 175), 'netral'],
            [(int) $snapshot->negative_count, $this->color($image, 225, 29, 72), 'negatif'],
        ];

        $barX = 600;
        $barWidth = 528;
        $x = $barX;
        $labels = [];
        foreach ($segments as [$value, $color, $label]) {
            $segmentWidth = (int) round($barWidth * $value / $total);
            if ($segmentWidth > 0) {
                imagefilledrectangle($image, $x, 462, min($x + $segmentWidth, $barX + $barWidth) - 1, 490, $color);
            }
            $x += $segmentWidth;
            $labels[] = round($value / $total * 100).'% '.$label;
        }
        $this->text($image, 20, 600, 530, $muted, $regular, implode('   ', $labels));

        $this->text($image, 22, 72, 590, $muted, $regular, 'suaranetijen.id');
        imagefilledrectangle($image, 0, self::HEIGHT - 8, self::WIDTH, self::HEIGHT, $green);

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();

        return $png;
    }

    /**
     * @param  int<0, 255>  $r
     * @param  int<0, 255>  $g
     * @param  int<0, 255>  $b
     */
    private function color(GdImage $image, int $r, int $g, int $b): int
    {
        return (int) imagecolorallocate($image, $r, $g, $b);
    }

    private function text(GdImage $image, int $size, int $x, int $y, int $color, string $font, string $text): void
    {
        imagettftext($image, $size, 0, $x, $y, $color, $font, $text);
    }

    private function width(string $text, string $font, int $size): int
    {
        $box = imagettfbbox($size, 0, $font, $text);

        return $box === false ? 0 : (int) abs($box[2] - $box[0]);
    }

    /**
     * Greedy word wrap into at most $maxLines lines, ending the last line with an ellipsis when cut.
     *
     * @return array<int, string>
     */
    private function wrap(string $text, string $font, int $size, int $maxWidth, int $maxLines): array
    {
        $lines = [];
        $current = '';

        foreach (preg_split('/\s+/', trim($text)) ?: [] as $word) {
            $candidate = $current === '' ? $word : "{$current} {$word}";

            if ($current !== '' && $this->width($candidate, $font, $size) > $maxWidth) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        $lines[] = $current;

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $last = $lines[$maxLines - 1];

            while ($last !== '' && $this->width($last.'…', $font, $size) > $maxWidth) {
                $last = mb_substr($last, 0, -1);
            }

            $lines[$maxLines - 1] = $last.'…';
        }

        return $lines;
    }
}
