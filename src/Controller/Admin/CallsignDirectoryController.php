<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;
use App\Service\CallsignLookup\DirectoryCsvImporter;

/**
 * Admin UI for the callsign directory.
 *
 * GET  /admin/callsign-lookups/provider/local       paginated grid of imported rows
 * POST /admin/callsign-lookups/provider/local       upload a CSV; show import summary
 * POST /admin/callsign-lookups/provider/local/clear nuke the whole directory (audit-logged)
 */
class CallsignDirectoryController extends AppController
{
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
            throw new \Cake\Http\Exception\ForbiddenException('Admin only.');
        }
    }

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

        try {
            $summary = (new DirectoryCsvImporter())->import($tmp, $sourceLabel !== '' ? $sourceLabel : null);
        } catch (\Throwable $e) {
            @unlink($tmp);
            $this->Flash->error('Import failed: ' . $e->getMessage());
            return $this->redirect('/admin/callsign-lookups/provider/local');
        }
        @unlink($tmp);

        try {
            (new \App\Service\AuditLogger())->log(
                event: 'callsign_directory.imported',
                actorUserId: $this->Authentication->getIdentity()->getIdentifier(),
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
     * The `callsign_lookups` cache is left alone — clearing it is a
     * separate operation on /admin/cleanup.
     */
    public function clear()
    {
        $this->request->allowMethod('post');
        $directory = $this->fetchTable('CallsignDirectory');
        $count = $directory->deleteAll([]);

        try {
            (new \App\Service\AuditLogger())->log(
                event: 'callsign_directory.cleared',
                actorUserId: $this->Authentication->getIdentity()->getIdentifier(),
                metadata: ['rows_deleted' => $count],
            );
        } catch (\Throwable $e) {
            error_log('audit: ' . $e->getMessage());
        }

        $this->Flash->success("Cleared {$count} directory rows.");
        return $this->redirect('/admin/callsign-lookups/provider/local');
    }
}
