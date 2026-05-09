<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class AppSetting extends Entity
{
    protected array $_accessible = [
        'key' => true,
        'value' => true,
        'updated_at' => true,
    ];
}
