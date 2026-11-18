<?php

declare(strict_types=1);

namespace Mom\Connectors;

/**
 * One connected account, as a connector type sees it. The app stores
 * connected accounts however it likes and hands a type this much of one, so
 * a packaged type never depends on the app's own classes.
 */
interface Connection
{
    /**
     * What the app knows this connected account by. Stable, and unique
     * among the app's connected accounts.
     */
    public function connectionId(): string;

    /**
     * Which outside account this is, such as its address.
     */
    public function account(): string;

    /**
     * What was kept when the account was connected that is not secret.
     *
     * @return array<string, mixed>
     */
    public function settings(): array;

    /**
     * Whether an option was chosen when the account was signed in to.
     */
    public function hasOption(string $option): bool;

    /**
     * A token that lets the type act as the account right now, for an
     * account that was connected by signing in. The app refreshes and keeps
     * tokens; the type only asks for one.
     *
     * @throws CouldNotReachConnector
     */
    public function accessToken(): string;

    /**
     * What was kept encrypted when the account was connected by a form,
     * such as its passwords.
     *
     * @return array<string, mixed>
     */
    public function credentials(): array;
}
