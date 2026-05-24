<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Audit log entry entity.
 *
 * All fields are server-controlled and locked from mass assignment.
 * Rows are written exclusively through the AuditLogService or similar
 * service-layer helpers — never directly from a controller's patchEntity.
 */
class AuditLog extends Entity
{
    protected array $_accessible = [
        // All fields are server-set; nothing should be mass-assignable.
        'actor_user_id' => false,
        'actor_guest_visit_id' => false,
        'event' => false,
        'target_type' => false,
        'target_id' => false,
        'metadata_json' => false,
    ];
}
