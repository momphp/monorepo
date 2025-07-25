<?php

declare(strict_types=1);

namespace Mom\Data;

abstract class AbstractInteger extends AbstractValue
{
    public static function fromInteger(int $value): static
    {
        return new static($value);
    }

    public static function fromNullableInteger(?int $value): static
    {
        return new static($value);
    }

    public static function forArrayValue(AbstractValue $value): ?int
    {
        if ($value instanceof AbstractInteger) {
            return $value->toNullableInteger();
        }

        return null;
    }

    public function toPrimitive(): ?int
    {
        return $this->toNullableInteger();
    }

    public function toNullableInteger(): ?int
    {
        $value = $this->toValue();

        if (is_int($value)) {
            return $value;
        }

        if ($value instanceof AbstractInteger) {
            return $value->toNullableInteger();
        }

        return null;
    }

    public function toInteger(): int
    {
        return $this->toNullableInteger() ?? 0;
    }
}
