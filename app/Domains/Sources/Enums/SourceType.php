<?php

namespace App\Domains\Sources\Enums;

enum SourceType: string
{
    case Forum = 'forum';
    case Social = 'social';
    case VideoComments = 'video_comments';
    case Rss = 'rss';
    case Mock = 'mock';

    /**
     * Human-readable label for the source type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Forum => 'Komunitas & Forum Diskusi',
            self::Social => 'Media Sosial & Firehose',
            self::VideoComments => 'Komentar Video',
            self::Rss => 'Umpan RSS Web',
            self::Mock => 'Mock Adapter Pengujian',
        };
    }
}
