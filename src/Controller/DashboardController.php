<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Logged-in user landing page at /dashboard.
 *
 * Shows a welcome panel with the user's callsign, quick-action buttons
 * (new QSO, import log, browse cards, manage templates) and the six most
 * recent cards + QSOs. Admin users also see a link into /admin.
 */
class DashboardController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
    }

    /**
     * Render the authenticated user's dashboard.
     *
     * Loads: the user entity, the 6 most-recent cards (with templates),
     * the 6 most-recent QSOs, aggregate stats (cards/QSOs/shares totals),
     * and the currently live net session (if any) for the dashboard banner.
     *
     * @return void
     */
    public function index(): void
    {
        $userId = $this->Authentication->getIdentity()->getIdentifier();
        $user = $this->fetchTable('Users')->get($userId);

        $cards = $this->fetchTable('Cards');
        $recentCards = $cards->find('active')
            ->where(['Cards.user_id' => $userId])
            ->contain(['Templates'])
            ->orderBy(['Cards.created_at' => 'DESC'])
            ->limit(6)
            ->all();

        $qsos = $this->fetchTable('Qsos');
        $recentQsos = $qsos->find()
            ->where(['Qsos.user_id' => $userId])
            ->orderBy(['Qsos.qso_datetime_utc' => 'DESC'])
            ->limit(6)
            ->all();

        $stats = [
            'cards_total' => $cards->find('active')->where(['user_id' => $userId])->count(),
            'qsos_total' => $qsos->find()->where(['user_id' => $userId])->count(),
            'shared_total' => $cards->find('active')->where([
                'user_id' => $userId,
                'share_slug IS NOT' => null,
                'share_revoked_at IS' => null,
            ])->count(),
        ];

        $liveNet = $this->fetchTable('NetSessions')->findLiveForUser($userId)->first();

        $this->set([
            'title' => 'Dashboard',
            'user' => $user,
            'recentCards' => $recentCards,
            'recentQsos' => $recentQsos,
            'stats' => $stats,
            'liveNet' => $liveNet,
        ]);
    }
}
