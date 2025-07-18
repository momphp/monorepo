<?php

declare(strict_types=1);

namespace Mom\Data;

use Illuminate\Support\Carbon;

abstract class AbstractDate extends AbstractValue
{
    public static function fromCarbon(Carbon $value): static
    {
        return new static($value);
    }

    public static function fromNullableCarbon(?Carbon $value): static
    {
        return new static($value);
    }

    public static function forArrayValue(AbstractValue $value): ?string
    {
        if ($value instanceof AbstractDate) {
            return $value->toNullableISOString();
        }

        return null;
    }

    public function toPrimitive(): ?Carbon
    {
        return $this->toNullableCarbon();
    }

    public function toNullableCarbon(): ?Carbon
    {
        $value = $this->toValue();

        if ($value instanceof Carbon) {
            return $value;
        }

        if (is_string($value)) {
            return Carbon::parse($value);
        }

        if ($value instanceof AbstractDate) {
            return $value->toNullableCarbon();
        }

        return null;
    }

    public function toCarbon(): Carbon
    {
        return $this->toNullableCarbon() ?? Carbon::now();
    }

    public function toNullableDateTimeString(): ?string
    {
        return $this->toNullableCarbon()?->toDateTimeString();
    }

    public function toDateTimeString(): string
    {
        return $this->toCarbon()->toDateTimeString();
    }

    public function toISOString(): string
    {
        return $this->toCarbon()->toISOString();
    }

    public function toNullableISOString(): ?string
    {
        return $this->toNullableCarbon()?->toISOString();
    }

    public function toNullableDateString(): ?string
    {
        return $this->toNullableCarbon()?->toDateString();
    }

    public function toDateString(): string
    {
        return $this->toCarbon()->toDateString();
    }
}
