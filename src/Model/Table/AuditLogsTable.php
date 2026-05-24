<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Audit log ORM table.
 *
 * Append-only record of significant user actions (card generation, share
 * create/revoke, template publish, admin mutations, etc.). All columns are
 * server-set and locked in the entity's _accessible array. The event string
 * must be non-empty (enforced by both validationDefault and a custom rule
 * in buildRules).
 *
 * Associations:
 *   - belongsTo Users (actor_user_id)        — logged-in user who triggered the event.
 *   - belongsTo GuestVisits (actor_guest_visit_id) — guest session when no user is auth'd.
 */
class AuditLogsTable extends Table
{
    /**
     * Configure table name, primary key, Timestamp behavior (created_at only),
     * and actor associations.
     *
     * @param array<string, mixed> $config Table config passed from the ORM locator.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('audit_logs');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp', [
            'events' => ['Model.beforeSave' => ['created_at' => 'new']],
        ]);
        $this->belongsTo('Users', ['foreignKey' => 'actor_user_id']);
        $this->belongsTo('GuestVisits', ['foreignKey' => 'actor_guest_visit_id']);
    }

    /**
     * Validation: event required (max 80 chars); target_type/id and
     * metadata_json are optional.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('event')->maxLength('event', 80)->notEmptyString('event')
            ->scalar('target_type')->maxLength('target_type', 40)->allowEmptyString('target_type')
            ->integer('target_id')->allowEmptyString('target_id')
            ->scalar('metadata_json')->allowEmptyString('metadata_json');
        return $validator;
    }

    /**
     * Application rules: redundant not-empty guard on event as a belt-and-braces
     * check in case the entity was patched after validation.
     *
     * @param \Cake\ORM\RulesChecker $rules Rules checker instance.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add(function ($entity) {
            $event = $entity->get('event');
            return is_string($event) && $event !== '';
        }, 'eventNotEmpty', [
            'errorField' => 'event',
            'message' => 'event must not be empty',
        ]);
        return $rules;
    }
}
