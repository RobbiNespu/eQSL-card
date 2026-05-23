<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * M6 — Net session. owner_id, status, slugs, started/ended are
 * server-controlled and locked out of mass assignment.
 *
 * @property int $id
 * @property int $owner_id
 * @property string $net_title
 * @property string|null $net_organisation
 * @property string|null $frequency_mhz
 * @property string|null $band
 * @property string|null $mode
 * @property string $status
 * @property string $public_slug
 * @property bool $is_public
 * @property string|null $logger_token
 * @property \Cake\I18n\DateTime|null $started_at
 * @property \Cake\I18n\DateTime|null $ended_at
 * @property string|null $notes
 * @property \Cake\I18n\DateTime $created_at
 * @property \Cake\I18n\DateTime $updated_at
 */
class NetSession extends Entity
{
    protected array $_accessible = [
        'net_title'        => true,
        'net_organisation' => true,
        'frequency_mhz'    => true,
        'band'             => true,
        'mode'             => true,
        'is_public'        => true,
        'notes'            => true,
    ];
}
