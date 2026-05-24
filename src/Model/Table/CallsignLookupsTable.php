<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Per-callsign lookup cache table.
 *
 * Each row is a single callsign record fetched from an external source
 * (QRZ.com, HamDB, etc.) and cached locally. The `fetched_at` column
 * records when the record was retrieved; `expires_at` (nullable) marks
 * when a refresh should be forced. The `source` + `callsign` pair is
 * expected to be unique at the application layer; see CallsignLookupService.
 */
class CallsignLookupsTable extends Table
{
    /**
     * Configure table name, primary key, and Timestamp behavior with
     * explicit column name mappings (created_at / updated_at).
     *
     * @param array<string, mixed> $config Table config passed from the ORM locator.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('callsign_lookups');
        $this->setPrimaryKey('id');
        // Timestamp behavior defaults assume `created`/`modified` field names;
        // our schema uses `created_at`/`updated_at` (matching the rest of the
        // app). Map both events explicitly.
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
     * Validation: callsign + source required; optional name, qth, country,
     * grid_square, license_class, source_url; fetched_at required, expires_at optional.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('callsign')->maxLength('callsign', 20)->notEmptyString('callsign')
            ->scalar('source')->maxLength('source', 20)->notEmptyString('source')
            ->scalar('name')->maxLength('name', 255)->allowEmptyString('name')
            ->scalar('qth')->maxLength('qth', 255)->allowEmptyString('qth')
            ->scalar('country')->maxLength('country', 64)->allowEmptyString('country')
            ->scalar('grid_square')->maxLength('grid_square', 10)->allowEmptyString('grid_square')
            ->scalar('license_class')->maxLength('license_class', 40)->allowEmptyString('license_class')
            ->scalar('source_url')->maxLength('source_url', 500)->allowEmptyString('source_url')
            ->dateTime('fetched_at')->notEmptyDateTime('fetched_at')
            ->dateTime('expires_at')->allowEmptyDateTime('expires_at');
        return $validator;
    }
}
