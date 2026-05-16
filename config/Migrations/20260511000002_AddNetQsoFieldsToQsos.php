<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Net QSO support.
 *
 * A "net check-in" is a different beast from a 1:1 contact: the NCS (Net
 * Control Station) runs a scheduled on-air session under a named net
 * organisation, and participants check in. From the NCS's perspective the
 * card-issuing flow is "I ran this net, confirming check-in by <participant>"
 * so `call_worked` keeps its existing semantic (the other station; here,
 * the participant). The NCS, net title, and organisation are new fields
 * describing the net itself.
 *
 * `qso_type` defaults to 'contact' so the migration is a no-op for the
 * existing user-facing flows; new code reads it explicitly when switching
 * UI / templates between contact and net modes.
 */
final class AddNetQsoFieldsToQsos extends AbstractMigration
{
    public function change(): void
    {
        $this->table('qsos')
            ->addColumn('qso_type', 'string', [
                'limit' => 20,
                'null' => false,
                'default' => 'contact',
                'comment' => "'contact' | 'net'",
                'after' => 'call_worked',
            ])
            ->addColumn('ncs_callsign', 'string', [
                'limit' => 20,
                'null' => true,
                'default' => null,
                'after' => 'qso_type',
            ])
            ->addColumn('net_title', 'string', [
                'limit' => 120,
                'null' => true,
                'default' => null,
                'after' => 'ncs_callsign',
            ])
            ->addColumn('net_organisation', 'string', [
                'limit' => 120,
                'null' => true,
                'default' => null,
                'after' => 'net_title',
            ])
            ->update();
    }
}
