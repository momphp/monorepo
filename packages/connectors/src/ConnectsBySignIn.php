<?php

declare(strict_types=1);

namespace Mom\Connectors;

/**
 * A connector type whose accounts are connected by signing in at the
 * service (OAuth 2). The app sends the person there, keeps the refresh token
 * the service gives back, and exchanges it for access tokens when needed.
 */
interface ConnectsBySignIn
{
    /**
     * The Socialite driver to sign in with. Its keys are read from
     * config("services.{driver}.client_id") and ".client_secret".
     */
    public function signInDriver(): string;

    /**
     * What the person may choose before signing in, such as access to
     * shared mailboxes.
     *
     * @return list<SignInOption>
     */
    public function signInOptions(): array;

    /**
     * What the person is asked to agree to, given what they chose.
     *
     * @param  list<string>  $options  the keys of the chosen options
     * @return list<string>
     */
    public function scopes(array $options): array;

    /**
     * What the sign-in page is told besides the permissions, such as to let
     * the person choose the account.
     *
     * @return array<string, string>
     */
    public function signInParameters(): array;

    /**
     * Where a stored refresh token is exchanged for an access token.
     */
    public function tokenUrl(): string;
}
