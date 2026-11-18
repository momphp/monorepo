<?php

declare(strict_types=1);

namespace Mom\Connectors;

use Illuminate\Validation\Rule;

/**
 * One field of the form a connector type is connected with. Start with the
 * kind of field and add to it:
 *
 *     Field::text('imap_host', 'Server')->required()->group('Incoming mail')->span(3)
 *
 * Each call gives a new field and leaves the one it was called on alone.
 */
final class Field
{
    /**
     * How many columns a group of fields is laid out on.
     */
    public const int COLUMNS = 6;

    /**
     * @param  array<string, string>  $options  a select's choices, by value
     * @param  list<mixed>  $rules  validation rules on top of the ones the kind of field brings
     * @param  array<string, string>  $messages  what to say when a rule fails, by rule name
     */
    private function __construct(
        public private(set) string $name,
        public private(set) string $label,
        public private(set) string $kind,
        public private(set) bool $isRequired = false,
        public private(set) ?string $placeholder = null,
        public private(set) ?string $hint = null,
        public private(set) int|string|null $default = null,
        public private(set) array $options = [],
        public private(set) ?string $group = null,
        public private(set) int $span = self::COLUMNS,
        public private(set) array $rules = [],
        public private(set) array $messages = [],
    ) {}

    public static function text(string $name, string $label): self
    {
        return new self($name, $label, 'text');
    }

    public static function email(string $name, string $label): self
    {
        return new self($name, $label, 'email');
    }

    /**
     * A secret. What is typed is never sent back to the page, so it is typed
     * again whenever the connector is changed.
     */
    public static function password(string $name, string $label): self
    {
        return new self($name, $label, 'password');
    }

    public static function number(string $name, string $label): self
    {
        return new self($name, $label, 'number');
    }

    /**
     * @param  array<string, string>  $options  the choices, by value
     */
    public static function select(string $name, string $label, array $options): self
    {
        return new self($name, $label, 'select', options: $options);
    }

    public function required(): self
    {
        $field = clone $this;
        $field->isRequired = true;

        return $field;
    }

    public function placeholder(string $placeholder): self
    {
        $field = clone $this;
        $field->placeholder = $placeholder;

        return $field;
    }

    public function hint(string $hint): self
    {
        $field = clone $this;
        $field->hint = $hint;

        return $field;
    }

    /**
     * What the field starts as on an empty form.
     */
    public function default(int|string $default): self
    {
        $field = clone $this;
        $field->default = $default;

        return $field;
    }

    /**
     * The titled box the field is shown in, with the others of the same group.
     */
    public function group(string $group): self
    {
        $field = clone $this;
        $field->group = $group;

        return $field;
    }

    /**
     * How many of the six columns the field takes; the fields of a group
     * flow onto rows.
     */
    public function span(int $span): self
    {
        $field = clone $this;
        $field->span = max(1, min(self::COLUMNS, $span));

        return $field;
    }

    /**
     * @param  list<mixed>  $rules
     */
    public function rules(array $rules): self
    {
        $field = clone $this;
        $field->rules = $rules;

        return $field;
    }

    /**
     * What to say when one of the field's rules fails, such as "regex".
     */
    public function message(string $rule, string $message): self
    {
        $field = clone $this;
        $field->messages = [...$this->messages, $rule => $message];

        return $field;
    }

    /**
     * Whether what is typed must never be sent back to the page.
     */
    public function isSecret(): bool
    {
        return 'password' === $this->kind;
    }

    /**
     * The validation rules for what is typed into the field.
     *
     * @return list<mixed>
     */
    public function validationRules(): array
    {
        return [
            $this->isRequired ? 'required' : 'nullable',
            ...match ($this->kind) {
                'email' => ['string', 'email', 'max:255'],
                'number' => ['integer'],
                'select' => [Rule::in(array_keys($this->options))],
                'password' => ['string', 'max:1000'],
                default => ['string', 'max:255'],
            },
            ...$this->rules,
        ];
    }

    /**
     * The field for the page that draws it.
     *
     * @return array{name: string, label: string, kind: string, required: bool, placeholder: ?string, hint: ?string, default: int|string|null, options: list<array{value: string, label: string}>, group: ?string, span: int}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'kind' => $this->kind,
            'required' => $this->isRequired,
            'placeholder' => $this->placeholder,
            'hint' => $this->hint,
            'default' => $this->default,
            'options' => array_map(
                fn (string $value, string $label): array => ['value' => $value, 'label' => $label],
                array_keys($this->options),
                array_values($this->options),
            ),
            'group' => $this->group,
            'span' => $this->span,
        ];
    }
}
