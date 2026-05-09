<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class CardsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('cards');
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
        $this->belongsTo('GuestVisits');
        $this->belongsTo('Templates');
        $this->belongsTo('Uploads');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('qso_data_json')
            ->notEmptyString('qso_data_json')
            ->scalar('png_path')
            ->maxLength('png_path', 255)
            ->notEmptyString('png_path')
            ->scalar('pdf_path')
            ->maxLength('pdf_path', 255)
            ->notEmptyString('pdf_path')
            ->scalar('share_slug')
            ->maxLength('share_slug', 43)
            ->allowEmptyString('share_slug')
            ->scalar('share_password_hash')
            ->maxLength('share_password_hash', 255)
            ->allowEmptyString('share_password_hash');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['share_slug'], ['allowMultipleNulls' => true]));
        $rules->add(function (EntityInterface $entity): bool {
            $hasUser = !empty($entity->get('user_id'));
            $hasGuest = !empty($entity->get('guest_visit_id'));

            return ($hasUser xor $hasGuest);
        }, 'ownerExclusive', [
            'errorField' => 'user_id',
            'message' => 'Card must have either user_id OR guest_visit_id, not both.',
        ]);

        return $rules;
    }
}
