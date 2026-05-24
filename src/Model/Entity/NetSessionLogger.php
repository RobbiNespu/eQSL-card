<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Net session co-logger entity.
 *
 * Records that a user has been granted logging rights for a specific net
 * session. `added_via` tracks how the permission was granted (e.g. 'token',
 * 'owner').
 *
 * @property int $id
 * @property int $net_session_id
 * @property int $user_id
 * @property string $added_via
 * @property \Cake\I18n\DateTime $created_at
 */
class NetSessionLogger extends Entity
{
    protected array $_accessible = [
        'net_session_id' => true,
        'user_id'        => true,
        'added_via'      => true,
    ];
}
