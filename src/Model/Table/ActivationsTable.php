<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * M5 T12 — Activations ORM table.
 *
 * Schema lives in migration 20260516000008_CreateActivationsTable.
 *
 * Indexes used by the controller:
 *   - (user_id, ended_at) — `findActive()` for the banner on /qsos/quick
 *   - (user_id, started_at) — `findRecent()` for the activations list
 *
 * Validation is intentionally minimal: code + name required, grid_square
 * follows Maidenhead syntax when present (4 or 6 chars, AA00aa). All
 * other constraints live at the DB layer (NOT NULL, varchar limits).
 */
class ActivationsTable extends Table
{
    /**
     * Configure table name, primary key, Timestamp behavior (created_at only),
     * and associations to Users + Qsos.
     *
     * @param array<string, mixed> $config Table config passed from the ORM locator.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('activations');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created_at' => 'new',
                ],
            ],
        ]);

        $this->belongsTo('Users');
        // qsos.activation_id was added in T13; the inverse association
        // lets us COUNT(*) on Activations->find()->contain('Qsos').
        $this->hasMany('Qsos', ['foreignKey' => 'activation_id']);
    }

    /**
     * Validation: code + name required (max 60/120 chars); grid_square
     * is optional but must match Maidenhead format (4 or 6 chars) when present.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('code')
            ->maxLength('code', 60)
            ->notEmptyString('code', 'Activation reference (e.g. POTA-K-1234) is required.')
            ->scalar('name')
            ->maxLength('name', 120)
            ->notEmptyString('name', 'Activation name is required.')
            ->scalar('grid_square')
            ->maxLength('grid_square', 8)
            ->allowEmptyString('grid_square')
            ->add('grid_square', 'maidenhead', [
                'rule' => function ($value) {
                    if ($value === null || $value === '') return true;
                    return (bool)preg_match('/^[A-R]{2}[0-9]{2}([a-x]{2})?$/i', (string)$value);
                },
                'message' => 'Grid square must be Maidenhead format (4 or 6 chars, e.g. OJ02 or OJ02wx).',
            ])
            ->scalar('notes')
            ->allowEmptyString('notes');

        return $validator;
    }

    /**
     * Return the user's current active activation (ended_at IS NULL), or null.
     *
     * Used on every /qsos/quick page load to render the active-activation
     * banner and to auto-tag new QSOs at save time.
     *
     * @param int $userId Owner user primary key.
     * @return \App\Model\Entity\Activation|null
     */
    public function findActiveForUser(int $userId): ?\App\Model\Entity\Activation
    {
        /** @var \App\Model\Entity\Activation|null $row */
        $row = $this->find()
            ->where(['user_id' => $userId, 'ended_at IS' => null])
            ->orderBy(['started_at' => 'DESC'])
            ->first();
        return $row;
    }

    /**
     * Recent activations for a user (active + ended), newest first.
     *
     * @param int $userId Owner user primary key.
     * @param int $limit  Maximum rows to return (default 20).
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findRecentForUser(int $userId, int $limit = 20): SelectQuery
    {
        return $this->find()
            ->where(['user_id' => $userId])
            ->orderBy(['started_at' => 'DESC'])
            ->limit($limit);
    }
}
