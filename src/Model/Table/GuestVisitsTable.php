<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Guest visit sessions ORM table.
 *
 * Tracks unauthenticated users who generate QSL cards. Each guest session
 * is identified by a URL-safe Base64 token (session_token). The token is
 * stored in a cookie client-side; the row is created on first card generation
 * and its last_seen_at is bumped on every subsequent visit.
 *
 * ip_hash / user_agent_hash are stored as SHA-256 digests for lightweight
 * fraud detection; no raw PII is persisted.
 *
 * Associations:
 *   - hasMany Cards
 *   - hasMany CardBackgrounds (FK upload_id — legacy column name)
 */
class GuestVisitsTable extends Table
{
    /**
     * Configure table name, primary key, Timestamp behavior (created_at on
     * insert; last_seen_at on every save), and associations.
     *
     * @param array<string, mixed> $config Table config passed from the ORM locator.
     * @return void
     */
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
        $this->hasMany('CardBackgrounds', ['foreignKey' => 'upload_id']);
    }

    /**
     * Validation: session_token required on create (max 43 chars);
     * ip_hash and user_agent_hash are optional scalars.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
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

    /**
     * Application rules: session_token must be unique across all guest rows.
     *
     * @param \Cake\ORM\RulesChecker $rules Rules checker instance.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['session_token']));

        return $rules;
    }
}
