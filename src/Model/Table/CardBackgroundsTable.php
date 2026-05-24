<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Card background images. Renamed from `UploadsTable` in
 * 20260516000007_RenameUploadsToCardBackgrounds — the underlying
 * data is unchanged, only the name on the outside.
 */
class CardBackgroundsTable extends Table
{
    /**
     * Configure table name, primary key, Timestamp behavior (created_at only),
     * and associations. The Cards association uses the legacy FK column name
     * `upload_id` for backward compatibility with existing card rows.
     *
     * @param array<string, mixed> $config Table config passed from the ORM locator.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('card_backgrounds');
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
        // M4-T10 cleanup walks CardBackgrounds.notMatching('Cards', …) to
        // find rows referenced by no card. The reciprocal
        // Cards.belongsTo CardBackgrounds association still uses the
        // legacy `upload_id` FK column name; we keep that for backward
        // compat with existing card rows.
        $this->hasMany('Cards', ['foreignKey' => 'upload_id']);
    }

    /**
     * Validation: original_filename, storage_path, mime_type, sha256_hash are
     * required scalars; width_px, height_px, file_size_bytes must be positive
     * integers.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
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

    /**
     * Application rules: sha256_hash must be unique; exactly one of user_id or
     * guest_visit_id must be set (XOR ownership — a background cannot be owned
     * by both a user and a guest session simultaneously).
     *
     * @param \Cake\ORM\RulesChecker $rules Rules checker instance.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['sha256_hash']));
        $rules->add(function (EntityInterface $entity): bool {
            $hasUser = !empty($entity->get('user_id'));
            $hasGuest = !empty($entity->get('guest_visit_id'));

            return ($hasUser xor $hasGuest);
        }, 'ownerExclusive', [
            'errorField' => 'user_id',
            'message' => 'Card background must have either user_id OR guest_visit_id, not both.',
        ]);

        return $rules;
    }
}
