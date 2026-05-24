<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Bulk callsign directory table.
 *
 * Holds pre-imported operator records sourced from national databases or
 * uploaded CSV/ADIF files. Used by the callsign lookup service as a fast
 * local cache layer before falling back to external APIs. Records carry a
 * `source_label` (e.g. "MCMC 2024") and an `imported_at` timestamp so
 * stale imports can be identified and purged.
 */
class CallsignDirectoryTable extends Table
{
    /**
     * Configure table name, primary key, and Timestamp behavior
     * (created_at on insert, updated_at on every save).
     *
     * @param array<string, mixed> $config Table config passed from the ORM locator.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('callsign_directory');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created_at' => 'new',
                    'updated_at' => 'always',
                ],
            ],
        ]);
    }

    /**
     * Validation: callsign required; name, qth, country, grid_square,
     * license_class, source_label are optional scalars; imported_at required.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('callsign')->maxLength('callsign', 20)->notEmptyString('callsign')
            ->scalar('name')->maxLength('name', 255)->allowEmptyString('name')
            ->scalar('qth')->maxLength('qth', 255)->allowEmptyString('qth')
            ->scalar('country')->maxLength('country', 64)->allowEmptyString('country')
            ->scalar('grid_square')->maxLength('grid_square', 10)->allowEmptyString('grid_square')
            ->scalar('license_class')->maxLength('license_class', 40)->allowEmptyString('license_class')
            ->scalar('source_label')->maxLength('source_label', 80)->allowEmptyString('source_label')
            ->dateTime('imported_at')->notEmptyDateTime('imported_at');
        return $validator;
    }
}
