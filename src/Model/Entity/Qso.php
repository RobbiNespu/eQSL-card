<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Qso extends Entity
{
    protected array $_accessible = [
        'user_id' => true,
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
}
