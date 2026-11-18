<?php

declare(strict_types=1);

namespace Mom\Connectors\Mail;

/**
 * An email's Message-ID as it is compared and stored: without the angle
 * brackets mail headers put around it.
 */
final class MessageId
{
    public static function normalize(mixed $id): ?string
    {
        if ( ! is_string($id)) {
            return null;
        }

        $id = mb_trim($id, " \t\n\r\0\x0B<>");

        return '' === $id ? null : $id;
    }

    /**
     * Every id in a References header, which lists them separated by space.
     *
     * @return list<string>
     */
    public static function all(?string $header): array
    {
        $ids = [];

        foreach (preg_split('/[\s,]+/', $header ?? '') ?: [] as $id) {
            $id = self::normalize($id);

            if (null !== $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
