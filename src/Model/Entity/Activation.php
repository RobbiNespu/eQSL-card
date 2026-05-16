<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * M5 T12 — Activation row.
 *
 * Represents one portable session (POTA / SOTA / IOTA / field day /
 * kampung activation). Groups its QSOs via `qsos.activation_id`.
 *
 * @property int $id
 * @property int $user_id
 * @property string $code
 * @property string $name
 * @property string|null $grid_square
 * @property \Cake\I18n\DateTime $started_at
 * @property \Cake\I18n\DateTime|null $ended_at
 * @property string|null $notes
 * @property \Cake\I18n\DateTime $created_at
 *
 * @property \App\Model\Entity\User|null $user
 * @property \App\Model\Entity\Qso[] $qsos
 */
class Activation extends Entity
{
    /**
     * Mass-assignment whitelist. `user_id`, `started_at`, `ended_at`,
     * `created_at` are server-controlled and locked to prevent the
     * controller from accidentally accepting them from the request.
     */
    protected array $_accessible = [
        'code'        => true,
        'name'        => true,
        'grid_square' => true,
        'notes'       => true,
    ];

    /**
     * Convenience accessor: is this activation currently active?
     * Used by the active-activation banner and the auto-tag-on-save
     * logic in QsosController::quick().
     */
    public function isActive(): bool
    {
        return $this->ended_at === null;
    }
}
