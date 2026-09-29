<?php

declare(strict_types=1);

namespace Darvis\Mailtrap\Support;

/**
 * The DNS lookups behind the local address check.
 *
 * getmxrr() and gethostbyname() return the same answer for "no such record"
 * and "the resolver timed out", so a bad DNS minute would block real addresses.
 * dns_get_record() returns an empty list for the first and false for the second,
 * which lets the check tell a missing record from a lookup that failed.
 *
 * Resolved from the container, so a test can swap it for a fake.
 *
 * @internal
 */
class DnsLookup
{
    /**
     * The MX records of a domain.
     *
     * @return array<int, array{priority: int, target: string}>|null An empty list when the domain has no MX record, null when the lookup failed.
     */
    public function mxRecords(string $domain): ?array
    {
        $records = @dns_get_record($domain, DNS_MX);

        if ($records === false) {
            return null;
        }

        return array_map(fn (array $record): array => [
            'priority' => (int) ($record['pri'] ?? 0),
            'target' => rtrim((string) ($record['target'] ?? ''), '.'),
        ], $records);
    }

    /**
     * Whether a host has an IPv4 or IPv6 address.
     *
     * @return bool|null Null when neither lookup found an address and at least one of them failed.
     */
    public function resolves(string $host): ?bool
    {
        $failed = false;

        foreach ([DNS_A, DNS_AAAA] as $type) {
            $records = @dns_get_record($host, $type);

            if ($records === false) {
                $failed = true;
            } elseif ($records !== []) {
                return true;
            }
        }

        return $failed ? null : false;
    }
}
