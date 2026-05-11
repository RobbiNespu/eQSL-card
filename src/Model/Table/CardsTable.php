<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\ORM\Query\SelectQuery;
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
            // PDFs are no longer pre-rendered — built on demand by the
            // download controller action — so new rows persist with null.
            // Legacy rows keep their path string; both paths are valid.
            ->allowEmptyString('pdf_path')
            ->scalar('share_slug')
            ->maxLength('share_slug', 43)
            ->allowEmptyString('share_slug')
            ->scalar('share_password_hash')
            ->maxLength('share_password_hash', 255)
            ->allowEmptyString('share_password_hash');

        return $validator;
    }

    /**
     * Custom finder: only cards that have NOT been soft-deleted.
     *
     * Soft-delete (M2-T9) sets `cards.deleted_at`. The schema-level row stays
     * for forensics and storage cleanup (deferred to M4 admin sweep tools), so
     * any user-facing list/detail surface MUST hide rows where `deleted_at` is
     * non-null. Wrapping the predicate in a finder keeps the list of "where
     * to filter" auditable in one place — every controller that reads a
     * user's card library should use `find('active')`.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Base query.
     * @param array $options Reserved for future use (unused today).
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findActive(SelectQuery $query, array $options = []): SelectQuery
    {
        return $query->where([$this->getAlias() . '.deleted_at IS' => null]);
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
