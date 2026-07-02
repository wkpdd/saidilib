<?php

namespace App\Services\Delivery;

class ShipmentResult
{
    public function __construct(
        public bool $success,
        public ?string $tracking = null,
        public ?string $message = null,
        public array $payload = [],
        public ?string $labelUrl = null,
    ) {}

    public static function ok(?string $tracking, array $payload = [], ?string $message = null, ?string $labelUrl = null): self
    {
        return new self(true, $tracking, $message, $payload, $labelUrl);
    }

    public static function fail(string $message, array $payload = []): self
    {
        return new self(false, null, $message, $payload);
    }
}
