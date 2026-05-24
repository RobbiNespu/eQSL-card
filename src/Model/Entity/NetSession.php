<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Net session entity (M6).
 *
 * owner_id, status, public_slug, logger_token, started_at, and ended_at are
 * server-controlled and locked out of mass assignment. Only the descriptive
 * fields (net_title, net_organisation, frequency_mhz, band, mode, is_public,
 * notes) are writable from controller patchEntity calls.
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
