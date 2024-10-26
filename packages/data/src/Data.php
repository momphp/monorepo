<?php

declare(strict_types=1);

namespace Mom\Data;

use BackedEnum;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;
use stdClass;

abstract class Data
{
    private ?Model $eloquentModel = null;

    private ?BackedEnum $morphAlias = null;

    private bool $existsInDatabase = false;

    abstract public function getPrimaryKey(): Value;

    abstract public static function getFactory(): Factory;

    public static function fake(array $attributes = [], array $with = [], bool $persist = true): static
    {
        $factory = static::getFactory();

        if (true === $persist) {
            $model = $factory->create($attributes);

            return self::fromEloquentModel($model->load($with))->setIsExistsInDatabase(true);
        }

        $model = $factory->make($attributes);

        return self::fromEloquentModel($model);
    }

    public static function fakeCollection(array $attributes = [], int $count = 2, array $with = [], bool $persist = true): Collection
    {
        $factory = static::getFactory();

        if (true === $persist) {
            $models = $factory->count($count)->create($attributes);

            return self::collect($models);
        }

        $models = $factory->count($count)->make($attributes);

        return self::collect($models);
    }

    public static function new(): static
    {
        $class = new ReflectionClass(static::class);

        $properties = collect($class->getProperties())
            ->mapWithKeys(function (ReflectionProperty $property): array {
                /** @var ReflectionNamedType $type */
                $type = $property->getType();

                /** @var Value $name */
                $name = $type->getName();

                return [$property->name => $name::new()];
            })->toArray();

        /** @phpstan-ignore-next-line */
        return new static(...$properties);
    }

    public static function fromArray(array $item): static
    {
        $class = new ReflectionClass(static::class);

        $properties = collect($class->getProperties())
            ->mapWithKeys(function (ReflectionProperty $property) use ($item): array {
                /** @var ReflectionNamedType $type */
                $type = $property->getType();

                /** @var Value $name */
                $name = $type->getName();

                return [$property->name => $name::fromArray($item)];
            })->toArray();

        /** @phpstan-ignore-next-line */
        return new static(...$properties);
    }

    public static function fromEloquentModel(?Model $model): static
    {
        if (null === $model) {
            return static::new();
        }

        $class = new ReflectionClass(static::class);

        $properties = collect($class->getProperties())
            ->mapWithKeys(function (ReflectionProperty $property) use ($model): array {
                /** @var ReflectionNamedType $type */
                $type = $property->getType();

                /** @var Value $name */
                $name = $type->getName();

                return [$property->name => $name::fromEloquentModel($model)];
            })->toArray();

        /** @phpstan-ignore-next-line */
        return (new static(...$properties))->setEloquentModel($model);
    }

    public static function fromData(Data $data, string $method = 'fromData', mixed $options = null): static
    {
        $class = new ReflectionClass(static::class);

        $properties = collect($class->getProperties())
            ->mapWithKeys(function (ReflectionProperty $property) use ($data, $method, $options): array {
                /** @var ReflectionNamedType $type */
                $type = $property->getType();

                /** @var Value $name */
                $name = $type->getName();

                if (false === method_exists($name, $method)) {
                    return [$property->name => $name::new()];
                }

                return [$property->name => $name::$method($data, $options)];
            })->toArray();

        /** @phpstan-ignore-next-line */
        return new static(...$properties);
    }

    public static function fromStandardClass(stdClass $data, mixed $options = null): static
    {
        $class = new ReflectionClass(static::class);

        $properties = collect($class->getProperties())
            ->mapWithKeys(function (ReflectionProperty $property) use ($data, $options): array {
                $method = 'fromStandardClass';

                /** @var ReflectionNamedType $type */
                $type = $property->getType();

                /** @var DataValue $name */
                $name = $type->getName();

                if (false === method_exists($name, $method)) {
                    return [$property->name => $name::new()];
                }

                return [$property->name => $name::$method($data, $options)];
            })->toArray();

        /** @phpstan-ignore-next-line */
        return new static(...$properties);
    }

    public static function collect(Collection|array $items): Collection
    {
        if (is_array($items)) {
            $items = collect($items);
        }

        return $items->map(function (mixed $item) {
            if ($item instanceof Model) {
                return static::fromEloquentModel($item);
            }

            if (is_array($item)) {
                return static::fromArray($item);
            }

            if ($item instanceof Data) {
                return static::fromData($item);
            }

            if ($item instanceof stdClass) {
                return static::fromStandardClass($item);
            }

            return $item;
        });
    }

    public static function from($item): static
    {
        if ($item instanceof Model) {
            return static::fromEloquentModel($item);
        }

        if (is_array($item)) {
            return static::fromArray($item);
        }

        if ($item instanceof Data) {
            return static::fromData($item);
        }

        return static::new();
    }

    public function getMorphAlias(): ?BackedEnum
    {
        return $this->morphAlias;
    }

    public function setMorphAlias(?BackedEnum $morphAlias): Data
    {
        $this->morphAlias = $morphAlias;

        return $this;
    }

    public function getEloquentModel(): ?Model
    {
        return $this->eloquentModel;
    }

    public function setEloquentModel(Model $eloquentModel): static
    {
        $this->eloquentModel = $eloquentModel;

        return $this;
    }

    public function isNull(): bool
    {
        return $this->getPrimaryKey()->isNull();
    }

    public function isNotNull(): bool
    {
        return false === $this->isNull();
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    public function setIsExistsInDatabase(bool $existsInDatabase): static
    {
        $this->existsInDatabase = $existsInDatabase;

        return $this;
    }

    public function isExistsInDatabase(): bool
    {
        return $this->existsInDatabase;
    }

    public function notExistsInDatabase(): bool
    {
        return false === $this->isExistsInDatabase();
    }

    public function toArray(): array
    {
        throw new RuntimeException('The toResource method must be implemented.');
    }

    public function forDatabaseCreate(): array
    {
        throw new RuntimeException('The toResource method must be implemented.');
    }

    public function forDatabaseUpdate(): array
    {
        throw new RuntimeException('The toResource method must be implemented.');
    }

    public function toEncryptedArray(): array
    {
        throw new RuntimeException('The toEncryptedArray method must be implemented.');
    }

    public function forResource(): array
    {
        throw new RuntimeException('The forResource method must be implemented.');
    }

    public function toResource(): JsonResource
    {
        throw new RuntimeException('The toResource method must be implemented.');
    }
}
