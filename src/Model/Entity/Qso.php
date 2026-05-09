<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Qso extends Entity
{
    protected array $_accessible = [
        'user_id' => false,
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
    ];

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
