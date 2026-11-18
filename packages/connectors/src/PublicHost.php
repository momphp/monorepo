<?php

declare(strict_types=1);

namespace Mom\Connectors;

/**
 * Whether a server someone typed in may be connected to. This app connects
 * to whatever host a connector names, so unless the installation allows it,
 * hosts on this machine or a private network are refused: otherwise a
 * connector could be pointed at services that only this server can reach.
 */
final class PublicHost
{
    public static function allows(string $host): bool
    {
        $host = mb_trim($host, " \n\r\t\v\0[]");

        if ('' === $host) {
            return false;
        }

        if (true === config('connectors.allow_private_hosts', false)) {
            return true;
        }

        $addresses = false !== filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::resolve($host);

        if ([] === $addresses) {
            return false;
        }

        foreach ($addresses as $address) {
            if (false === filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Every address a name leads to; one private address among them is
     * enough to refuse it.
     *
     * @return list<string>
     */
    private static function resolve(string $host): array
    {
        $addresses = [];

        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }
}
