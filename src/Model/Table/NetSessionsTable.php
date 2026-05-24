<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Net sessions ORM table (M6).
 *
 * A net session tracks a scheduled or live radio net managed by the owning
 * operator. Status lifecycle: scheduled → live → ended. Only the owner and
 * authorised co-loggers (NetSessionLoggers rows) may write check-ins.
 *
 * Schema: migration 20260522000001.
 *
 * Associations:
 *   - belongsTo Owners (Users, FK owner_id)
 *   - hasMany Qsos (FK net_session_id)
 *   - hasMany NetSessionLoggers (FK net_session_id)
 */
class NetSessionsTable extends Table
{
    /**
     * Configure table name, primary key, Timestamp behavior, and associations.
     *
     * @param array<string, mixed> $config Table config passed from the ORM locator.
     * @return void
     */
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

    /**
     * Validation: net_title required; net_organisation, frequency_mhz, band,
     * mode, notes optional; is_public boolean.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
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

    /**
     * Check whether a user has write access to the given net session.
     *
     * Returns true if the user is the session owner or appears in the
     * net_session_loggers table for the session.
     *
     * @param int $sessionId Net session primary key.
     * @param int $userId    User primary key to check.
     * @return bool
     */
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

    /**
     * Scheduled (not-yet-started) sessions owned by the given user, newest first.
     *
     * @param int $userId Owner's user primary key.
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findUpcomingForUser(int $userId): SelectQuery
    {
        return $this->find()
            ->where(['owner_id' => $userId, 'status' => 'scheduled'])
            ->orderBy(['created_at' => 'DESC']);
    }

    /**
     * Currently-live sessions owned by the given user, most recently started first.
     *
     * @param int $userId Owner's user primary key.
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findLiveForUser(int $userId): SelectQuery
    {
        return $this->find()
            ->where(['owner_id' => $userId, 'status' => 'live'])
            ->orderBy(['started_at' => 'DESC']);
    }

    /**
     * Ended sessions owned by the given user, most recently ended first.
     *
     * @param int $userId Owner's user primary key.
     * @param int $limit  Maximum number of rows to return (default 50).
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findRecentForUser(int $userId, int $limit = 50): SelectQuery
    {
        return $this->find()
            ->where(['owner_id' => $userId, 'status' => 'ended'])
            ->orderBy(['ended_at' => 'DESC'])
            ->limit($limit);
    }
}
