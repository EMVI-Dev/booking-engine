<?php

namespace App\Services\CustomDomains;

/**
 * One rule for reading the website address an operator types.
 */
final class DomainName
{
    /**
     * Second-level suffixes where the registrable name has three labels (yourname.co.id).
     *
     * @var list<string>
     */
    private const TWO_LABEL_SUFFIXES = [
        'co.id', 'or.id', 'web.id', 'my.id', 'biz.id', 'ac.id', 'sch.id', 'net.id', 'ponpes.id',
        'co.uk', 'org.uk', 'com.au', 'net.au', 'com.sg', 'co.nz', 'com.my', 'co.jp', 'com.br',
    ];

    /**
     * Lower-case host without scheme, path, port or trailing dot; null when it is not a
     * public domain name (IP addresses, single labels and junk are refused).
     */
    public static function normalize(?string $input): ?string
    {
        $value = strtolower(trim((string) $input));
        $value = (string) preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $value);
        $value = (string) preg_replace('#[/?\#].*$#', '', $value);
        $value = (string) preg_replace('#:\d+$#', '', $value);
        $value = rtrim($value, '.');

        if ($value === '' || strlen($value) > 253 || filter_var($value, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        $labels = explode('.', $value);

        if (count($labels) < 2) {
            return null;
        }

        foreach ($labels as $label) {
            if (preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)$/', $label) !== 1) {
                return null;
            }
        }

        return preg_match('/^[a-z]{2,63}$/', end($labels)) === 1 ? $value : null;
    }

    /**
     * Whether the host is the registrable name itself (yourname.com), which needs an A record.
     */
    public static function isApex(string $host): bool
    {
        return self::subdomainPart($host) === '';
    }

    /**
     * The part before the registrable name ("tours" for tours.yourname.co.id), '' for the apex.
     */
    public static function subdomainPart(string $host): string
    {
        $labels = explode('.', $host);
        $suffixLabels = in_array(implode('.', array_slice($labels, -2)), self::TWO_LABEL_SUFFIXES, true) ? 3 : 2;

        return implode('.', array_slice($labels, 0, max(0, count($labels) - $suffixLabels)));
    }

    /**
     * Record name as most DNS panels expect it: '@' for the apex, otherwise the subdomain part.
     */
    public static function recordName(string $host): string
    {
        $sub = self::subdomainPart($host);

        return $sub === '' ? '@' : $sub;
    }
}
