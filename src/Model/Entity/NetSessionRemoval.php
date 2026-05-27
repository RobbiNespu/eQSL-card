<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * M7 A4 — net-session check-in tombstone. Written when a check-in is
 * deleted; read by the delta feed to populate `removed[]` for live
 * watchers.
 *
 * @property int $id
 * @property int $net_session_id
 * @property int $qso_id
 * @property \Cake\I18n\DateTime $removed_at
 */
class NetSessionRemoval extends Entity
{
    protected array $_accessible = [
        'net_session_id' => true,
        'qso_id'         => true,
        'removed_at'     => true,
    ];
}
