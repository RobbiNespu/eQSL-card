<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;
use App\Service\CallsignLookup\DirectoryCsvImporter;
use App\Service\OperationLog;

/**
 * Admin UI for the callsign directory.
 *
 * GET  /admin/callsign-lookups/provider/local       paginated grid of imported rows
 * POST /admin/callsign-lookups/provider/local       upload a CSV; show import summary
 * POST /admin/callsign-lookups/provider/local/clear nuke the whole directory (audit-logged)
 */
class CallsignDirectoryController extends AppController
{
    /** Load the Authentication component required by all Admin controllers. */
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Authentication.Authentication');
    }

    /**
     * Gate access to admin-only actions.
     *
     * Anonymous requests are handled by AuthenticationComponent (redirects to
     * /login). Only authenticated-but-not-admin users need the explicit 403.
     *
     * @param \Cake\Event\EventInterface $event The before-filter event.
     * @return void
     * @throws \Cake\Http\Exception\ForbiddenException When the authenticated user is not an admin.
     */
    public function beforeFilter(\Cake\Event\EventInterface $event): void
    {
        parent::beforeFilter($event);
        $identity = $this->Authentication->getIdentity();
        if (!$identity) {
            return;
        }
        $user = $this->fetchTable('Users')->get($identity->getIdentifier());
        if ($user->role !== 'admin') {
            throw new \Cake\Http\Exception\ForbiddenException('Admin only.');
        }
    }

    /**
     * Paginated, searchable grid of all imported callsign directory rows.
     *
     * Supports `?q=` full-callsign prefix search (upper-cased) and shows the
     * total row count so the operator can gauge completeness of the imported CSV.
     *
     * @return void
     */
    public function index(): void
    {
        $directory = $this->fetchTable('CallsignDirectory');
        $query = $directory->find();
        $search = trim((string)$this->request->getQuery('q', ''));
        if ($search !== '') {
            $query->where(['callsign LIKE' => '%' . strtoupper($search) . '%']);
        }
        $query->orderBy(['callsign' => 'ASC']);
        $rows = $this->paginate($query, ['limit' => 50]);

        $this->set([
            'rows' => $rows,
            'search' => $search,
            'total' => $directory->find()->count(),
            'title' => 'Admin · Callsign directory',
        ]);
    }

    /**
     * POST /admin/callsign-lookups/provider/local — upload a CSV and import it.
     *
     * Accepts a `csv` file + optional `source_label` ("MCMC 2026-Q1"). The
     * importer fails-soft on per-row issues and returns a summary; on
     * top-level parse errors (no callsign column) we flash a clear message
     * pointing the operator at the recognised header aliases.
     *
     * @return \Cake\Http\Response
     */
    public function upload()
    {
        $this->request->allowMethod('post');
        $file = $this->request->getUploadedFile('csv');
        if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
            $this->Flash->error('Please pick a CSV file to upload.');
            return $this->redirect('/admin/callsign-lookups/provider/local');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'eqsl_csv_');
        $file->moveTo($tmp);
        $sourceLabel = trim((string)$this->request->getData('source_label', ''));
        $actorId = $this->Authentication->getIdentity()->getIdentifier();

        try {
            $summary = (new DirectoryCsvImporter())->import($tmp, $sourceLabel !== '' ? $sourceLabel : null);
        } catch (\Throwable $e) {
            @unlink($tmp);
            OperationLog::failure('admin.callsign_directory.import', $e, [
                'actor_user_id' => $actorId,
                'source_label' => $sourceLabel ?: null,
            ]);
            $this->Flash->error('Import failed: ' . $e->getMessage());
            return $this->redirect('/admin/callsign-lookups/provider/local');
        }
        @unlink($tmp);

        try {
            (new \App\Service\AuditLogger())->log(
                event: 'callsign_directory.imported',
                actorUserId: $actorId,
                metadata: [
                    'imported' => $summary['imported'],
                    'updated' => $summary['updated'],
                    'skipped' => $summary['skipped'],
                    'source_label' => $sourceLabel ?: null,
                ],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }

        OperationLog::event('admin.callsign_directory.imported', [
            'actor_user_id' => $actorId,
            'imported'      => $summary['imported'],
            'updated'       => $summary['updated'],
            'skipped'       => $summary['skipped'],
            'source_label'  => $sourceLabel ?: null,
        ]);

        $msg = sprintf(
            'Imported %d new, updated %d existing, skipped %d.',
            $summary['imported'], $summary['updated'], $summary['skipped']
        );
        if (!empty($summary['errors'])) {
            $msg .= ' First error: ' . $summary['errors'][0];
        }
        $this->Flash->success($msg);
        return $this->redirect('/admin/callsign-lookups/provider/local');
    }

    /**
     * POST /admin/callsign-lookups/provider/local/clear — wipe the whole directory.
     *
     * The `callsign_lookups` cache is left alone — clearing it is a
     * separate operation on /admin/cleanup.
     *
     * @return \Cake\Http\Response
     */
    public function clear()
    {
        $this->request->allowMethod('post');
        $actorId = $this->Authentication->getIdentity()->getIdentifier();
        $directory = $this->fetchTable('CallsignDirectory');
        $count = $directory->deleteAll([]);

        try {
            (new \App\Service\AuditLogger())->log(
                event: 'callsign_directory.cleared',
                actorUserId: $actorId,
                metadata: ['rows_deleted' => $count],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }

        OperationLog::event('admin.callsign_directory.cleared', [
            'actor_user_id' => $actorId,
            'rows_deleted'  => $count,
        ]);

        $this->Flash->success("Cleared {$count} directory rows.");
        return $this->redirect('/admin/callsign-lookups/provider/local');
    }
}
