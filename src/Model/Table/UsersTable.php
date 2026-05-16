<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class UsersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('users');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created_at' => 'new',
                    'updated_at' => 'always',
                ],
            ],
        ]);

        $this->hasMany('Cards');
        $this->hasMany('Templates');
        $this->hasMany('CardBackgrounds', ['foreignKey' => 'upload_id']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('name')->maxLength('name', 120)->notEmptyString('name')
            ->email('email')->notEmptyString('email')
            ->scalar('callsign')->maxLength('callsign', 20)->notEmptyString('callsign')
            ->inList('role', ['admin', 'user'])
            ->scalar('qth')->maxLength('qth', 120)->allowEmptyString('qth')
            ->scalar('grid_square')->maxLength('grid_square', 10)->allowEmptyString('grid_square')
            ->scalar('bio')->allowEmptyString('bio');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['email']));

        return $rules;
    }
}
