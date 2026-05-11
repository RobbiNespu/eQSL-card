<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Radioless QSO support.
 *
 * Operators do "QSOs" over internet-mediated channels too — Echolink, Zello,
 * Mumble, TeamSpeak, Discord. These have no on-air frequency or band but the
 * rest of a QSO row (callsign, datetime, RST, notes) is still meaningful and
 * the user wants an eQSL card for them. Model this as a per-row `transport`
 * field that defaults to 'rf' so all existing rows keep their semantics,
 * plus a free-text `transport_meta` slot for "node 12345" / server name / etc.
 */
final class AddTransportToQsos extends AbstractMigration
{
    public function change(): void
    {
        $this->table('qsos')
            ->addColumn('transport', 'string', [
                'limit' => 20,
                'null' => false,
                'default' => 'rf',
                'comment' => "'rf' | 'echolink' | 'zello' | 'mumble' | 'teamspeak' | 'discord' | 'other'",
                'after' => 'mode',
            ])
            ->addColumn('transport_meta', 'string', [
                'limit' => 120,
                'null' => true,
                'default' => null,
                'comment' => 'Optional free-text: node number, server, channel, etc.',
                'after' => 'transport',
            ])
            ->update();
    }
}
