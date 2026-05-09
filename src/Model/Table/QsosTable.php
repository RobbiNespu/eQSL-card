<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class QsosTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('qsos');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created_at' => 'new',
                    'updated_at' => 'always',
                ],
            ],
        ]);

        $this->belongsTo('Users');
        $this->hasMany('Cards');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('call_worked')->maxLength('call_worked', 20)->notEmptyString('call_worked')
            ->dateTime('qso_datetime_utc')->notEmptyDateTime('qso_datetime_utc')
            ->numeric('frequency_mhz')->allowEmptyString('frequency_mhz')
            ->scalar('band')->maxLength('band', 8)->allowEmptyString('band')
            ->scalar('mode')->maxLength('mode', 20)->allowEmptyString('mode')
            ->scalar('rst_sent')->maxLength('rst_sent', 8)->allowEmptyString('rst_sent')
            ->scalar('rst_received')->maxLength('rst_received', 8)->allowEmptyString('rst_received')
            ->scalar('operator_name')->maxLength('operator_name', 120)->allowEmptyString('operator_name')
            ->scalar('operator_qth')->maxLength('operator_qth', 120)->allowEmptyString('operator_qth')
            ->scalar('grid_square')->maxLength('grid_square', 10)->allowEmptyString('grid_square')
            ->scalar('notes')->allowEmptyString('notes');
        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['user_id'], 'Users'));
        $rules->add($rules->isUnique(
            ['user_id', 'call_worked', 'qso_datetime_utc', 'band'],
            ['allowMultipleNulls' => true, 'message' => 'Duplicate QSO for this user, callsign, datetime, and band.']
        ));
        return $rules;
    }
}
