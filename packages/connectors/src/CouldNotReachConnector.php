<?php

declare(strict_types=1);

namespace Mom\Connectors;

use RuntimeException;
use Throwable;

/**
 * The outside service would not let us in through a connector. The message is
 * written for the team's admins, who see it in settings.
 */
final class CouldNotReachConnector extends RuntimeException
{
    /**
     * Whether trying again later may work without anyone doing anything.
     */
    public bool $isTemporary = false;

    /**
     * Whether the service turned away what we have stored, so that only
     * connecting the account again will help.
     */
    public bool $needsReconnect = false;

    /**
     * Who runs the service that could not be reached, when it is known,
     * such as "Microsoft".
     */
    public ?string $company = null;

    /**
     * What we have stored is gone or no longer accepted, so only connecting
     * the account again will fix it.
     */
    public static function needsReconnect(string $company): self
    {
        $exception = new self((string) __(':company no longer accepts the sign-in for this connector. Connect it again.', [
            'company' => $company,
        ]));
        $exception->company = $company;
        $exception->needsReconnect = true;

        return $exception;
    }

    /**
     * The service could not be reached or answered with an error that may pass.
     */
    public static function unavailable(string $company, ?Throwable $previous = null): self
    {
        $exception = new self((string) __(':company could not be reached. It will be tried again.', [
            'company' => $company,
        ]), previous: $previous);
        $exception->isTemporary = true;
        $exception->company = $company;

        return $exception;
    }

    /**
     * The service refused the request, for example because the connected
     * account may not do what was asked.
     */
    public static function refused(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, previous: $previous);
    }
}
