<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Qso extends Entity
{
    protected array $_accessible = [
        'user_id' => false,
        // M5 T13 — locked from mass assignment. The controller assigns
        // activation_id server-side from the operator's current active
        // activation (T16); accepting it from request data would let a
        // malicious POST tag QSOs to another user's activation.
        'activation_id' => false,
        'call_worked' => true,
        'qso_datetime_utc' => true,
        'frequency_mhz' => true,
        'band' => true,
        'mode' => true,
        'rst_sent' => true,
        'rst_received' => true,
        'operator_name' => true,
        'operator_qth' => true,
        'grid_square' => true,
        'notes' => true,
        // Net check-in fields. Required when qso_type='net'; left null for
        // contact QSOs. Validation lives on QsosTable::validationDefault().
        'qso_type' => true,
        'ncs_callsign' => true,
        'net_title' => true,
        'net_organisation' => true,
        // Radioless QSO support: transport defaults to 'rf' (over the air),
        // 'echolink'/'zello'/etc. for internet-mediated contacts.
        'transport' => true,
        'transport_meta' => true,
    ];

    protected function _setNcsCallsign(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return strtoupper(trim($value));
    }

    /**
     * Normalise empty net fields to null so contact-mode rows don't leave
     * empty strings in the column. The add form posts empty strings for
     * these inputs in contact mode (the hidden fallback inputs preserve the
     * name=net_title/etc keys regardless of which toggle is active) so the
     * controller never has to branch on the type to clean up.
     */
    protected function _setNetTitle(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    protected function _setNetOrganisation(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    protected function _setTransportMeta(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    protected function _setCallWorked(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }
        return strtoupper(trim($value));
    }

    protected function _setGridSquare(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }
        // Maidenhead grid: first 4 chars uppercase, last 2 (subsquare) lowercase by convention
        $trimmed = trim($value);
        if (strlen($trimmed) >= 4) {
            return strtoupper(substr($trimmed, 0, 4)) . strtolower(substr($trimmed, 4));
        }
        return strtoupper($trimmed);
    }
}
