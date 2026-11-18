<?php

declare(strict_types=1);

namespace Mom\Connectors;

/**
 * A connector type whose accounts are connected by filling in a form, such
 * as a server and a password. A page draws the form from the fields, the app
 * validates what was typed against them, and the type checks that it really
 * works before anything is saved.
 */
interface ConnectsByForm
{
    /**
     * A sentence or two above the form: what is being asked for and where
     * the person finds it.
     */
    public function formIntroduction(): string;

    /**
     * The form, in the order it is shown.
     *
     * @return list<Field>
     */
    public function fields(): array;

    /**
     * The field that says which account this is. Saving the same account
     * again changes that connection, so the field cannot be changed later.
     */
    public function accountField(): string;

    /**
     * Tries what was typed against the service, and says what to save.
     *
     * @param  array<string, mixed>  $values  by field name, already valid against the fields' rules
     *
     * @throws CouldNotConnect naming the field to look at
     */
    public function connect(array $values): ConnectedAccount;
}
