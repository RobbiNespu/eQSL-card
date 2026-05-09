<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class UploadsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('uploads');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created_at' => 'new',
                ],
            ],
        ]);

        $this->belongsTo('Users');
        $this->belongsTo('GuestVisits');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('original_filename')
            ->maxLength('original_filename', 255)
            ->notEmptyString('original_filename')
            ->scalar('storage_path')
            ->maxLength('storage_path', 255)
            ->notEmptyString('storage_path')
            ->scalar('mime_type')
            ->maxLength('mime_type', 60)
            ->notEmptyString('mime_type')
            ->integer('width_px')
            ->greaterThan('width_px', 0)
            ->integer('height_px')
            ->greaterThan('height_px', 0)
            ->integer('file_size_bytes')
            ->greaterThan('file_size_bytes', 0)
            ->scalar('sha256_hash')
            ->maxLength('sha256_hash', 64)
            ->notEmptyString('sha256_hash');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['sha256_hash']));
        $rules->add(function (EntityInterface $entity): bool {
            $hasUser = !empty($entity->get('user_id'));
            $hasGuest = !empty($entity->get('guest_visit_id'));

            return ($hasUser xor $hasGuest);
        }, 'ownerExclusive', [
            'errorField' => 'user_id',
            'message' => 'Upload must have either user_id OR guest_visit_id, not both.',
        ]);

        return $rules;
    }
}
