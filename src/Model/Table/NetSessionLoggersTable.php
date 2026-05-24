<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

/**
 * Net session co-loggers ORM table.
 *
 * Each row records that a user (user_id) has been granted logging rights on a
 * net session (net_session_id). The `added_via` column records how the logger
 * was added ('owner', 'token', 'invite', etc.) for audit purposes. Rows are
 * created by NetSessionsController when the session owner shares the logger
 * token, and deleted when the owner revokes access.
 *
 * Associations:
 *   - belongsTo NetSessions (net_session_id)
 *   - belongsTo Users (user_id)
 */
class NetSessionLoggersTable extends Table
{
    /**
     * Configure table name, primary key, Timestamp behavior (created_at only),
     * and associations to NetSessions + Users.
     *
     * @param array<string, mixed> $config Table config passed from the ORM locator.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('net_session_loggers');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp', [
            'events' => ['Model.beforeSave' => ['created_at' => 'new']],
        ]);
        $this->belongsTo('NetSessions', ['foreignKey' => 'net_session_id']);
        $this->belongsTo('Users', ['foreignKey' => 'user_id']);
    }
}
