<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class AuditLogsTable extends Table
{
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

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('event')->maxLength('event', 80)->notEmptyString('event')
            ->scalar('target_type')->maxLength('target_type', 40)->allowEmptyString('target_type')
            ->integer('target_id')->allowEmptyString('target_id')
            ->scalar('metadata_json')->allowEmptyString('metadata_json');
        return $validator;
    }

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
