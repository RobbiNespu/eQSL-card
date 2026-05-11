<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class CallsignDirectoryTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('callsign_directory');
        $this->setPrimaryKey('id');
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
            ->scalar('name')->maxLength('name', 255)->allowEmptyString('name')
            ->scalar('qth')->maxLength('qth', 255)->allowEmptyString('qth')
            ->scalar('country')->maxLength('country', 64)->allowEmptyString('country')
            ->scalar('grid_square')->maxLength('grid_square', 10)->allowEmptyString('grid_square')
            ->scalar('license_class')->maxLength('license_class', 40)->allowEmptyString('license_class')
            ->scalar('source_label')->maxLength('source_label', 80)->allowEmptyString('source_label')
            ->dateTime('imported_at')->notEmptyDateTime('imported_at');
        return $validator;
    }
}
