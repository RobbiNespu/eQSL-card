<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * M6 — Net sessions ORM. Schema: migration 20260522000001.
 */
class NetSessionsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('net_sessions');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp', [
            'events' => ['Model.beforeSave' => [
                'created_at' => 'new',
                'updated_at' => 'always',
            ]],
        ]);
        $this->belongsTo('Owners', ['className' => 'Users', 'foreignKey' => 'owner_id']);
        $this->hasMany('Qsos', ['foreignKey' => 'net_session_id']);
        $this->hasMany('NetSessionLoggers', ['foreignKey' => 'net_session_id']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('net_title')->maxLength('net_title', 120)
            ->notEmptyString('net_title', 'Net title is required.')
            ->scalar('net_organisation')->maxLength('net_organisation', 120)
            ->allowEmptyString('net_organisation')
            ->numeric('frequency_mhz')->allowEmptyString('frequency_mhz')
            ->scalar('band')->maxLength('band', 8)->allowEmptyString('band')
            ->scalar('mode')->maxLength('mode', 20)->allowEmptyString('mode')
            ->boolean('is_public')
            ->scalar('notes')->allowEmptyString('notes');
        return $validator;
    }

    /** Owner OR co-logger may write to the session. */
    public function isLogger(int $sessionId, int $userId): bool
    {
        $isOwner = $this->exists(['id' => $sessionId, 'owner_id' => $userId]);
        if ($isOwner) {
            return true;
        }
        return $this->NetSessionLoggers->exists([
            'net_session_id' => $sessionId,
            'user_id' => $userId,
        ]);
    }

    public function findUpcomingForUser(int $userId): SelectQuery
    {
        return $this->find()
            ->where(['owner_id' => $userId, 'status' => 'scheduled'])
            ->orderBy(['created_at' => 'DESC']);
    }

    public function findLiveForUser(int $userId): SelectQuery
    {
        return $this->find()
            ->where(['owner_id' => $userId, 'status' => 'live'])
            ->orderBy(['started_at' => 'DESC']);
    }

    public function findRecentForUser(int $userId, int $limit = 50): SelectQuery
    {
        return $this->find()
            ->where(['owner_id' => $userId, 'status' => 'ended'])
            ->orderBy(['ended_at' => 'DESC'])
            ->limit($limit);
    }
}
