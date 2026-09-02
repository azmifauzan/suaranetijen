<?php

namespace App\Domains\Sources\Contracts;

use App\Domains\Sources\Enums\SourceHealthState;
use Carbon\CarbonImmutable;

readonly class SourceHealth
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public SourceHealthState $status,
        public ?string $message = null,
        public ?int $responseTimeMs = null,
        public ?CarbonImmutable $checkedAt = null,
        public array $details = []
    ) {}

    public function isHealthy(): bool
    {
        return $this->status === SourceHealthState::Healthy;
    }

    public function isOperational(): bool
    {
        return $this->status->isOperational();
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function healthy(?string $message = null, ?int $responseTimeMs = null, array $details = []): self
    {
        return new self(
            status: SourceHealthState::Healthy,
            message: $message ?? 'Source preflight passed successfully.',
            responseTimeMs: $responseTimeMs,
            checkedAt: CarbonImmutable::now(),
            details: $details
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function degraded(string $message, ?int $responseTimeMs = null, array $details = []): self
    {
        return new self(
            status: SourceHealthState::Degraded,
            message: $message,
            responseTimeMs: $responseTimeMs,
            checkedAt: CarbonImmutable::now(),
            details: $details
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function blocked(string $message, ?int $responseTimeMs = null, array $details = []): self
    {
        return new self(
            status: SourceHealthState::Blocked,
            message: $message,
            responseTimeMs: $responseTimeMs,
            checkedAt: CarbonImmutable::now(),
            details: $details
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function policyDisabled(string $message, array $details = []): self
    {
        return new self(
            status: SourceHealthState::PolicyDisabled,
            message: $message,
            responseTimeMs: null,
            checkedAt: CarbonImmutable::now(),
            details: $details
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function parserBroken(string $message, array $details = []): self
    {
        return new self(
            status: SourceHealthState::ParserBroken,
            message: $message,
            responseTimeMs: null,
            checkedAt: CarbonImmutable::now(),
            details: $details
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function quotaLimited(string $message, array $details = []): self
    {
        return new self(
            status: SourceHealthState::QuotaLimited,
            message: $message,
            responseTimeMs: null,
            checkedAt: CarbonImmutable::now(),
            details: $details
        );
    }
}
