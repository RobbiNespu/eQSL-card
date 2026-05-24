<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\TableRegistry;

/**
 * Read/write app_settings table with in-memory cache (M4-T17/T18).
 *
 * Values are JSON-encoded on the way in and JSON-decoded on the way out, so
 * scalars, arrays, and assoc structures all round-trip cleanly. The cache is
 * a static (per-process) array — `set()` invalidates it; `clear()` lets tests
 * (or any consumer that wrote outside this service) force a reload on next
 * `get()`/`getAll()`.
 *
 * Known keys (string keys, JSON-encoded values):
 *   site_name           string
 *   max_upload_mb       int  (post-encode upload cap, MB)
 *   share_base_url      string  (used in og:url for /qsl/{slug} pages)
 *   smtp_host, smtp_port, smtp_user, smtp_pass, smtp_from
 */
final class AppSettings
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * Retrieve a single setting value by key.
     *
     * @param string $key     Setting key (e.g. `site_name`, `smtp_host`).
     * @param mixed  $default Value to return when the key is not set.
     * @return mixed JSON-decoded value, or `$default`.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->load();

        return self::$cache[$key] ?? $default;
    }

    /**
     * Return all settings as a key → decoded-value map.
     *
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        $this->load();

        return self::$cache ?? [];
    }

    /**
     * Persist a single setting to the database and invalidate the in-memory cache.
     *
     * @param string $key   Setting key.
     * @param mixed  $value Value to store (JSON-encoded on the way in).
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $table = TableRegistry::getTableLocator()->get('AppSettings');
        $existing = $table->find()->where(['key' => $key])->first();
        if ($existing) {
            $existing->set('value', json_encode($value, JSON_UNESCAPED_SLASHES), ['guard' => false]);
            $table->saveOrFail($existing);
        } else {
            $entity = $table->newEmptyEntity();
            $entity->set('key', $key, ['guard' => false]);
            $entity->set('value', json_encode($value, JSON_UNESCAPED_SLASHES), ['guard' => false]);
            $table->saveOrFail($entity);
        }
        // Invalidate cache so the next read sees the fresh value.
        self::$cache = null;
    }

    /**
     * Persist multiple settings in one call. Thin loop over {@see self::set()}.
     *
     * @param array<string, mixed> $kv Key → value pairs to persist.
     * @return void
     */
    public function setMany(array $kv): void
    {
        foreach ($kv as $k => $v) {
            $this->set($k, $v);
        }
    }

    /**
     * Discard the in-memory cache so the next read reloads from the database.
     *
     * Useful in tests or after an out-of-band write to `app_settings`.
     *
     * @return void
     */
    public function clear(): void
    {
        self::$cache = null;
    }

    /**
     * Populate the static cache from the database (no-op when already loaded).
     *
     * @return void
     */
    private function load(): void
    {
        if (self::$cache !== null) {
            return;
        }
        $table = TableRegistry::getTableLocator()->get('AppSettings');
        $rows = $table->find()->all();
        $cache = [];
        foreach ($rows as $row) {
            $cache[$row->key] = json_decode((string)$row->value, true);
        }
        self::$cache = $cache;
    }
}
