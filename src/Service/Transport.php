<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Transport (medium) for a QSO contact.
 *
 * 'rf' is the traditional on-air radio QSO. The others are internet-mediated
 * platforms that ham operators use for digital nets, ragchews, or emergency
 * comms practice. Frequency / band are meaningless for non-rf transports —
 * the QSO form makes them optional in that case.
 */
final class Transport
{
    /** @var array<string,string> code → human label */
    public const TRANSPORTS = [
        'rf'         => 'RF (over the air)',
        'echolink'   => 'Echolink',
        'zello'      => 'Zello',
        'mumble'     => 'Mumble',
        'teamspeak'  => 'TeamSpeak',
        'discord'    => 'Discord',
        'other'      => 'Other / unspecified',
    ];

    /**
     * Option map suitable for `<select>`. Always includes the current value
     * even if it's an unknown legacy code, so admin views of historical rows
     * don't lose data.
     *
     * @return array<string,string>
     */
    public static function options(?string $current = null): array
    {
        $opts = self::TRANSPORTS;
        if ($current !== null && $current !== '' && !array_key_exists($current, $opts)) {
            $opts[$current] = $current . ' (unknown)';
        }
        return $opts;
    }

    /**
     * Human label for a transport code. Returns "RF (over the air)" when `$code` is null or empty.
     *
     * @param string|null $code Transport code (e.g. `echolink`, `rf`).
     * @return string Human-readable label.
     */
    public static function label(?string $code): string
    {
        if ($code === null || $code === '') {
            return self::TRANSPORTS['rf'];
        }
        return self::TRANSPORTS[$code] ?? $code;
    }

    /**
     * Whether the transport is internet-mediated (i.e. not RF).
     *
     * @param string|null $code Transport code.
     * @return bool True for any non-null, non-empty, non-rf code.
     */
    public static function isInternet(?string $code): bool
    {
        return $code !== null && $code !== '' && $code !== 'rf';
    }
}
