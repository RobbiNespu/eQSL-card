<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\TableRegistry;

/**
 * Writes events to the audit_logs table.
 *
 * Usage:
 *   $audit = new AuditLogger();
 *   $audit->log('card.generated', actorUserId: 7, target: ['type' => 'Cards', 'id' => 42]);
 *   $audit->log('template.approved', actorUserId: 1, target: ['type' => 'Templates', 'id' => 99],
 *               metadata: ['reviewer' => 'admin@x.com']);
 */
final class AuditLogger
{
    /**
     * @param array{type?: string, id?: int}|null $target
     * @param array<string, mixed>|null $metadata
     */
    public function log(
        string $event,
        ?int $actorUserId = null,
        ?int $actorGuestVisitId = null,
        ?array $target = null,
        ?array $metadata = null,
    ): int {
        $table = TableRegistry::getTableLocator()->get('AuditLogs');
        $entity = $table->newEmptyEntity();
        $entity->set('event', $event, ['guard' => false]);
        if ($actorUserId !== null) {
            $entity->set('actor_user_id', $actorUserId, ['guard' => false]);
        }
        if ($actorGuestVisitId !== null) {
            $entity->set('actor_guest_visit_id', $actorGuestVisitId, ['guard' => false]);
        }
        if ($target !== null) {
            if (isset($target['type'])) {
                $entity->set('target_type', $target['type'], ['guard' => false]);
            }
            if (isset($target['id'])) {
                $entity->set('target_id', (int)$target['id'], ['guard' => false]);
            }
        }
        if ($metadata !== null) {
            $entity->set('metadata_json', json_encode($metadata, JSON_UNESCAPED_SLASHES), ['guard' => false]);
        }
        $table->saveOrFail($entity);
        return $entity->id;
    }
}
