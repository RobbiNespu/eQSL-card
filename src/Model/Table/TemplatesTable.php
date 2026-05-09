<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class TemplatesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('templates');
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
            ->scalar('name')
            ->maxLength('name', 120)
            ->notEmptyString('name')
            ->scalar('description')
            ->allowEmptyString('description')
            ->numeric('canvas_width')
            ->numeric('canvas_height')
            ->scalar('layout_json')
            ->notEmptyString('layout_json')
            ->scalar('thumbnail_path')
            ->maxLength('thumbnail_path', 255)
            ->allowEmptyString('thumbnail_path')
            ->boolean('is_public')
            ->boolean('is_approved')
            ->boolean('is_system');

        return $validator;
    }
}
