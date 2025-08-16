<?php

declare(strict_types=1);

namespace Mom\Data;

use BackedEnum;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;
use stdClass;

abstract class AbstractData implements Arrayable
{
    private Model|Authenticatable|null $eloquentModel = null;

    private ?BackedEnum $morphAlias = null;

    private bool $existsInDatabase = false;

    public static function getFactory(): ?Factory
    {
        return null;
    }

    public static function fake(array $attributes = [], array $with = [], bool $persist = true): static
    {
        $factory = static::getFactory();

        if (null === $factory) {
            throw new RuntimeException('The getFactory method must be implemented.');
        }

        if (true === $persist) {
            $model = $factory->create($attributes);

            return self::fromEloquentModel($model->load($with))
                ->setIsExistsInDatabase(true)
                ->setEloquentModel($model);
        }

        $model = $factory->make($attributes);

        return self::fromEloquentModel($model)
            ->setEloquentModel($model);
    }

    public static function fakeCollection(
        array $attributes = [],
        int $count = 2,
        array $with = [],
        bool $persist = true,
    ): Collection {
        $factory = static::getFactory();

        if (null === $factory) {
            throw new RuntimeException('The getFactory method must be implemented.');
        }

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

                /** @var AbstractValue $name */
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

                /** @var AbstractValue $name */
                $name = $type->getName();

                return [$property->name => $name::fromDataArray($item)];
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

                /** @var AbstractValue $name */
                $name = $type->getName();

                return [$property->name => $name::fromEloquentModel($model)];
            })->toArray();

        /** @phpstan-ignore-next-line */
        return new static(...$properties)->setEloquentModel($model);
    }

    public static function fromData(AbstractData $data, string $method = 'fromData', mixed $options = null): static
    {
        $class = new ReflectionClass(static::class);

        $properties = collect($class->getProperties())
            ->mapWithKeys(function (ReflectionProperty $property) use ($data, $method, $options): array {
                /** @var ReflectionNamedType $type */
                $type = $property->getType();

                /** @var AbstractValue $name */
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

                /** @var AbstractDataValue $name */
                $name = $type->getName();

                if (false === method_exists($name, $method)) {
                    return [$property->name => $name::new()];
                }

                return [$property->name => $name::$method($data, $options)];
            })->toArray();

        /** @phpstan-ignore-next-line */
        return new static(...$properties);
    }

    public static function collect(Collection|array $items, string $method = 'fromData'): Collection
    {
        if (is_array($items)) {
            $items = collect($items);
        }

        return $items->map(function (mixed $item) use ($method) {
            if ($item instanceof Model) {
                return static::fromEloquentModel($item);
            }

            if (is_array($item)) {
                return static::fromArray($item);
            }

            if ($item instanceof AbstractData) {
                return static::fromData($item, $method);
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

        if ($item instanceof AbstractData) {
            return static::fromData($item);
        }

        return static::new();
    }

    public function forEloquentFactory(): array
    {
        $class = new ReflectionClass(static::class);

        return collect($class->getProperties())
            ->filter(function (ReflectionProperty $property): bool {
                $propertyName = $property->name;

                /** @var AbstractValue $propertyInstance */
                $propertyInstance = $this->{$propertyName};

                return $propertyInstance->isIncludeInEloquentFactory();
            })
            ->mapWithKeys(function (ReflectionProperty $property): array {
                $propertyName = $property->name;

                /** @var ReflectionNamedType $type */
                $type = $property->getType();

                /** @var AbstractValue $typeName */
                $typeName = $type->getName();

                return [$typeName::getName() => $typeName::forEloquentFactoryValue($this->{$propertyName})];
            })->toArray();
    }

    public function getPrimaryKey(): AbstractValue
    {
        throw new RuntimeException('The getPrimaryKey method must be implemented.');
    }

    public function getMorphAlias(): ?BackedEnum
    {
        return $this->morphAlias;
    }

    public function setMorphAlias(?BackedEnum $morphAlias): AbstractData
    {
        $this->morphAlias = $morphAlias;

        return $this;
    }

    public function getEloquentModel(): Model|Authenticatable|null
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $class = new ReflectionClass(static::class);

        $data = collect($class->getProperties())
            ->filter(function (ReflectionProperty $property): bool {
                $propertyName = $property->name;

                /** @var AbstractValue $propertyInstance */
                $propertyInstance = $this->{$propertyName};

                return $propertyInstance->isIncludeInToArray();
            })
            ->mapWithKeys(function (ReflectionProperty $property): array {
                $propertyName = $property->name;

                /** @var ReflectionNamedType $type */
                $type = $property->getType();

                /** @var AbstractValue $typeName */
                $typeName = $type->getName();

                return [$typeName::getName() => $typeName::forArrayValue($this->{$propertyName}, $this)];
            })->toArray();

        $data['class'] = static::class;

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function forDatabaseCreate(): array
    {
        $class = new ReflectionClass(static::class);

        return collect($class->getProperties())
            ->filter(function (ReflectionProperty $property): bool {
                $propertyName = $property->name;

                /** @var AbstractValue $propertyInstance */
                $propertyInstance = $this->{$propertyName};

                return $propertyInstance->isIncludeInForDatabaseCreate();
            })
            ->mapWithKeys(function (ReflectionProperty $property): array {
                $propertyName = $property->name;

                /** @var ReflectionNamedType $type */
                $type = $property->getType();

                /** @var AbstractValue $typeName */
                $typeName = $type->getName();

                return [$typeName::getName() => $typeName::forDatabaseCreateValue($this->{$propertyName}, $this)];
            })->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function forDatabaseUpdate(): array
    {
        $class = new ReflectionClass(static::class);

        return collect($class->getProperties())
            ->filter(function (ReflectionProperty $property): bool {
                $propertyName = $property->name;

                /** @var AbstractValue $propertyInstance */
                $propertyInstance = $this->{$propertyName};

                return $propertyInstance->isIncludeInForDatabaseUpdate();
            })
            ->mapWithKeys(function (ReflectionProperty $property): array {
                $propertyName = $property->name;

                /** @var ReflectionNamedType $type */
                $type = $property->getType();

                /** @var AbstractValue $typeName */
                $typeName = $type->getName();

                return [$typeName::getName() => $typeName::forDatabaseUpdateValue($this->{$propertyName}, $this)];
            })->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function toEncryptedArray(): array
    {
        $class = new ReflectionClass(static::class);

        $data = collect($class->getProperties())
            ->filter(function (ReflectionProperty $property): bool {
                $propertyName = $property->name;

                /** @var AbstractValue $propertyInstance */
                $propertyInstance = $this->{$propertyName};

                return $propertyInstance->isIncludeInToArray();
            })
            ->mapWithKeys(function (ReflectionProperty $property): array {
                $propertyName = $property->name;

                /** @var ReflectionNamedType $type */
                $type = $property->getType();

                /** @var AbstractValue $typeName */
                $typeName = $type->getName();

                return [$typeName::getName() => $typeName::forEncryptedArrayValue($this->{$propertyName}, $this)];
            })->toArray();

        $data['class'] = static::class;

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function forResource(Request $request): array
    {
        $class = new ReflectionClass(static::class);

        $data = collect($class->getProperties())
            ->filter(function (ReflectionProperty $property): bool {
                $propertyName = $property->name;

                /** @var AbstractValue $propertyInstance */
                $propertyInstance = $this->{$propertyName};

                return $propertyInstance->isIncludeInForResource();
            })
            ->mapWithKeys(function (ReflectionProperty $property): array {
                $propertyName = $property->name;

                /** @var ReflectionNamedType $type */
                $type = $property->getType();

                /** @var AbstractValue $typeName */
                $typeName = $type->getName();

                return [$typeName::getName() => $typeName::forResourceValue($this->{$propertyName}, $this)];
            })->toArray();

        $data['class'] = static::class;

        return $data;
    }

    public function toResource(): JsonResource
    {
        throw new RuntimeException('The toResource method must be implemented.');
    }
}
