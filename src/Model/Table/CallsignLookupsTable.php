<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class CallsignLookupsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('callsign_lookups');
        $this->setPrimaryKey('id');
        // Timestamp behavior defaults assume `created`/`modified` field names;
        // our schema uses `created_at`/`updated_at` (matching the rest of the
        // app). Map both events explicitly.
        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created_at' => 'new',
                    'updated_at' => 'always',
                ],
            ],
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('callsign')->maxLength('callsign', 20)->notEmptyString('callsign')
            ->scalar('source')->maxLength('source', 20)->notEmptyString('source')
            ->scalar('name')->maxLength('name', 255)->allowEmptyString('name')
            ->scalar('qth')->maxLength('qth', 255)->allowEmptyString('qth')
            ->scalar('country')->maxLength('country', 64)->allowEmptyString('country')
            ->scalar('grid_square')->maxLength('grid_square', 10)->allowEmptyString('grid_square')
            ->scalar('license_class')->maxLength('license_class', 40)->allowEmptyString('license_class')
            ->scalar('source_url')->maxLength('source_url', 500)->allowEmptyString('source_url')
            ->dateTime('fetched_at')->notEmptyDateTime('fetched_at')
            ->dateTime('expires_at')->allowEmptyDateTime('expires_at');
        return $validator;
    }
}
