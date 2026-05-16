<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class GuestVisitsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('guest_visits');
        $this->setPrimaryKey('id');
        // Custom columns: created_at on insert, last_seen_at always.
        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created_at' => 'new',
                    'last_seen_at' => 'always',
                ],
            ],
        ]);

        $this->hasMany('Cards');
        $this->hasMany('Uploads');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('session_token')
            ->maxLength('session_token', 43)
            ->requirePresence('session_token', 'create')
            ->notEmptyString('session_token')
            ->scalar('ip_hash')
            ->maxLength('ip_hash', 64)
            ->allowEmptyString('ip_hash')
            ->scalar('user_agent_hash')
            ->maxLength('user_agent_hash', 64)
            ->allowEmptyString('user_agent_hash');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['session_token']));

        return $rules;
    }
}
