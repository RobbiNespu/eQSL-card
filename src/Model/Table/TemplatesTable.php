<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Card templates ORM table.
 *
 * A template defines the visual canvas (width × height) and a JSON layout
 * descriptor used by the renderer to position callsign, QSO data, and
 * background image elements. Templates may be:
 *   - private (is_public=false, default) — visible only to the owner.
 *   - public-pending (is_public=true, is_approved=false) — submitted for review.
 *   - public-approved (is_public=true, is_approved=true) — listed in the gallery.
 *   - system (is_system=true) — shipped with the installer; read-only in UI.
 *
 * Associations:
 *   - belongsTo Users
 *   - hasMany Cards
 */
class TemplatesTable extends Table
{
    /**
     * Configure table name, primary key, Timestamp behavior, and associations.
     *
     * @param array<string, mixed> $config Table config passed from the ORM locator.
     * @return void
     */
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

    /**
     * Validation: name + layout_json required; canvas_width/height must be
     * positive integers; description, thumbnail_path optional; is_public,
     * is_approved, is_system must be booleans.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('name')
            ->maxLength('name', 120)
            ->notEmptyString('name')
            ->scalar('description')
            ->allowEmptyString('description')
            ->integer('canvas_width')
            ->greaterThan('canvas_width', 0)
            ->integer('canvas_height')
            ->greaterThan('canvas_height', 0)
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
