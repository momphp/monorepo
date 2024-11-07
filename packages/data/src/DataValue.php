<?php

declare(strict_types=1);

namespace Mom\Data;

use Closure;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class DataValue
{
    abstract public function __construct(mixed $value = null);

    abstract public function toNullableData(): mixed;

    abstract public function toData(): mixed;

    public static function new(): static
    {
        return new static();
    }

    public static function fromClosure(Closure $value): static
    {
        return new static($value);
    }

    public function isNull(): bool
    {
        return null === $this->toNullableData();
    }

    public function toArray(): array
    {
        $data = $this->toData();

        if ($data instanceof Data) {
            return $data->toArray();
        }

        return [];
    }

    public function toNullableArray(): ?array
    {
        $data = $this->toNullableData();

        if ($data instanceof Data) {
            return $data->toArray();
        }

        return null;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    public function toNullableJson(): ?string
    {
        $value = $this->toNullableArray();

        if (null === $value) {
            return null;
        }

        return json_encode($this->toArray());
    }

    public function toResource(): JsonResource
    {
        return $this->toData()->toResource();
    }

    public function toNullableResource(): ?JsonResource
    {
        return $this->toNullableData()?->toResource();
    }
}