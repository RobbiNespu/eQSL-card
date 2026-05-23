<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class NetSessionLoggersTable extends Table
{
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
