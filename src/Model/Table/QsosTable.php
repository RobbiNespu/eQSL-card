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

    /**
     * The two recognized values of `qso_type`. 'contact' is a 1:1 station
     * QSO (the default); 'net' is a net check-in where the QSO row also
     * carries the NCS callsign, net title, and organisation. New types
     * should land on this constant first so the validator + form layer
     * pick them up in lockstep.
     */
    public const QSO_TYPES = ['contact', 'net'];

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
            ->scalar('notes')->allowEmptyString('notes')
            ->scalar('qso_type')->inList('qso_type', self::QSO_TYPES, 'Invalid QSO type.')
            ->scalar('ncs_callsign')->maxLength('ncs_callsign', 20)
            ->scalar('net_title')->maxLength('net_title', 120)
            ->scalar('net_organisation')->maxLength('net_organisation', 120);

        // Net mode requires NCS callsign + net title; organisation stays
        // optional because not every net is run under a named club/society.
        // The "when" callback fires only on rows where qso_type is 'net',
        // so contact QSOs remain unaffected. requirePresence makes the
        // validation kick in even when the field isn't supplied at all
        // (default Cake behaviour is to skip rules for absent fields).
        $netMode = static function ($context) {
            return ($context['data']['qso_type'] ?? 'contact') === 'net';
        };
        $validator
            ->requirePresence('ncs_callsign', $netMode)
            ->notEmptyString('ncs_callsign', 'NCS callsign is required for net check-ins.', $netMode)
            ->allowEmptyString('ncs_callsign', null, static fn($c) => !$netMode($c))
            ->requirePresence('net_title', $netMode)
            ->notEmptyString('net_title', 'Net title is required for net check-ins.', $netMode)
            ->allowEmptyString('net_title', null, static fn($c) => !$netMode($c))
            ->allowEmptyString('net_organisation');

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
