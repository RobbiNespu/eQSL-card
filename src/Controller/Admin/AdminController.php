<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;
use Cake\Http\Exception\ForbiddenException;

/**
 * Shared parent for all admin controllers. Enforces the
 * "must be authenticated admin" gate once instead of repeating it
 * in every subclass's beforeFilter().
 *
 * Subclasses can override beforeFilter() for additional controller-
 * specific gating, but MUST call parent::beforeFilter($event) first.
 */
abstract class AdminController extends AppController
{
    /** @var int Identity of the calling admin (set in beforeFilter). */
    protected int $actorId = 0;

    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
    }

    public function beforeFilter(\Cake\Event\EventInterface $event): void
    {
        parent::beforeFilter($event);

        $identity = $this->Authentication->getIdentity();
        if (!$identity) {
            return;
        }
        $user = $this->fetchTable('Users')->get($identity->getIdentifier());
        if ($user->role !== 'admin') {
            throw new ForbiddenException('Admin only.');
        }
        $this->actorId = $identity->getIdentifier();
    }
}
