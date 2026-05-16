<?php
declare(strict_types=1);

namespace App\Model\Table;

use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class AppSettingsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('app_settings');
        // Composite/non-id primary key. The migration declares `key` as PK with no autoincrement id.
        $this->setPrimaryKey('key');
        $this->setDisplayField('key');
    }

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
     * Manually stamp updated_at since this table only has a single timestamp column.
     */
    public function beforeSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        $entity->set('updated_at', new DateTime());
    }
}
