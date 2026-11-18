<?php

declare(strict_types=1);

namespace Mom\Connectors;

/**
 * Something a person may tick before signing in, which changes what the
 * sign-in asks for.
 */
final readonly class SignInOption
{
    /**
     * @param  string  $key  what the choice is stored as
     * @param  string  $label  the choice, as a statement to tick
     * @param  ?string  $hint  a line beneath it
     * @param  ?string  $summary  the choice in a word or two, for a connected account's card; the label when left out
     */
    public function __construct(
        public string $key,
        public string $label,
        public ?string $hint = null,
        public ?string $summary = null,
    ) {}

    /**
     * @return array{key: string, label: string, hint: ?string}
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'hint' => $this->hint];
    }
}
