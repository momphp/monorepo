<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Mom\Connectors\Field;

it('describes a field for the page that draws it', function (): void {
    $field = Field::select('encryption', 'Encryption', ['ssl' => 'SSL/TLS', 'none' => 'None'])
        ->required()
        ->hint('Ask your provider.')
        ->default('ssl')
        ->group('Incoming mail')
        ->span(2);

    expect($field->toArray())->toBe([
        'name' => 'encryption',
        'label' => 'Encryption',
        'kind' => 'select',
        'required' => true,
        'placeholder' => null,
        'hint' => 'Ask your provider.',
        'default' => 'ssl',
        'options' => [['value' => 'ssl', 'label' => 'SSL/TLS'], ['value' => 'none', 'label' => 'None']],
        'group' => 'Incoming mail',
        'span' => 2,
    ]);
});

it('gives a new field for each addition and leaves the first alone', function (): void {
    $plain = Field::text('host', 'Server');
    $required = $plain->required()->placeholder('imap.acme.test');

    expect($plain->isRequired)->toBeFalse()
        ->and($plain->placeholder)->toBeNull()
        ->and($required->isRequired)->toBeTrue()
        ->and($required->placeholder)->toBe('imap.acme.test');
});

it('keeps a field within the columns of its group', function (int $asked, int $given): void {
    expect(Field::text('host', 'Server')->span($asked)->span)->toBe($given);
})->with([[0, 1], [3, 3], [9, 6]]);

it('validates what is typed by the kind of field and its own rules', function (Field $field, mixed $value, bool $passes): void {
    expect(Validator::make(['field' => $value], ['field' => $field->validationRules()])->passes())->toBe($passes);
})->with([
    'a required text left empty' => [fn () => Field::text('field', 'Name')->required(), '', false],
    'an optional text left empty' => [fn () => Field::text('field', 'Name'), null, true],
    'an email that is one' => [fn () => Field::email('field', 'Address'), 'kim@acme.test', true],
    'an email that is not' => [fn () => Field::email('field', 'Address'), 'kim', false],
    'a number that is one' => [fn () => Field::number('field', 'Port'), 993, true],
    'a number that is not' => [fn () => Field::number('field', 'Port'), 'many', false],
    'a number outside its own rule' => [fn () => Field::number('field', 'Port')->rules(['between:1,65535']), 70000, false],
    'a choice that is offered' => [fn () => Field::select('field', 'Encryption', ['ssl' => 'SSL']), 'ssl', true],
    'a choice that is not offered' => [fn () => Field::select('field', 'Encryption', ['ssl' => 'SSL']), 'rot13', false],
]);

it('treats only a password as a secret', function (): void {
    expect(Field::password('password', 'Password')->isSecret())->toBeTrue()
        ->and(Field::text('username', 'Username')->isSecret())->toBeFalse();
});
