<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Application setting entity.
 *
 * Represents a single key/value configuration row. Both fields are
 * mass-assignable; the primary key is the `key` string column.
 */
class AppSetting extends Entity
{
    protected array $_accessible = [
        'key' => true,
        'value' => true,
    ];
}
