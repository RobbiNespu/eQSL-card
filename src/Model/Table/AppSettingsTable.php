<?php
declare(strict_types=1);

namespace App\Model\Table;

use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Application settings ORM table.
 *
 * Stores key/value pairs that drive runtime behaviour (rate-limit bypass
 * toggle, mailer settings, etc.). The primary key is the string `key`
 * column — there is no auto-increment `id`. updated_at is stamped manually
 * in beforeSave() because the Timestamp behavior only works with integer PKs
 * when detecting insert vs. update.
 */
class AppSettingsTable extends Table
{
    /**
     * Configure table name, composite PK on `key`, and display field.
     *
     * @param array<string, mixed> $config Table config passed from the ORM locator.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('app_settings');
        // Composite/non-id primary key. The migration declares `key` as PK with no autoincrement id.
        $this->setPrimaryKey('key');
        $this->setDisplayField('key');
    }

    /**
     * Validation: key required (max 80 chars); value is optional scalar.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('key')
            ->maxLength('key', 80)
            ->notEmptyString('key')
            ->scalar('value')
            ->allowEmptyString('value');

        return $validator;
    }

    /**
     * Stamp updated_at on every save. The Timestamp behavior is not used here
     * because the table's PK is `key` (not an integer id), which confuses the
     * behavior's insert-vs-update detection. We stamp the column directly.
     *
     * @param \Cake\Event\EventInterface                     $event   ORM save event.
     * @param \Cake\Datasource\EntityInterface               $entity  Entity being saved.
     * @param \ArrayObject<string, mixed>                    $options Save options.
     * @return void
     */
    public function beforeSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        $entity->set('updated_at', new DateTime());
    }
}
