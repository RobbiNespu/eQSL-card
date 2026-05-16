<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Single source of truth for the docs portal's information architecture.
 *
 * Drives:
 *   - HelpController's route validation (only allow-listed pairs render).
 *   - The sidebar element (renders the tree).
 *   - Per-article previous/next links.
 *
 * Pages are added by editing TREE here — no DB, no admin UI.
 */
final class HelpCatalog
{
    /**
     * Nested: category-slug → ['label' => str, 'pages' => [slug => title]].
     * Order matters — used for prev/next.
     */
    public const TREE = [
        'getting-started' => [
            'label' => 'Getting started',
            'pages' => [
                'welcome'        => 'Welcome to eQSL Card',
                'create-account' => 'Create an account',
                'first-card'     => 'Your first eQSL card',
            ],
        ],
        'logging' => [
            'label' => 'Logging QSOs',
            'pages' => [
                'add-qso'      => 'Log a contact',
                'logbook'      => 'Browse your logbook',
                'import'       => 'Import an ADIF / CSV log',
                'net-checkins' => 'Logging net check-ins',
                'autocomplete' => 'Callsign auto-complete',
            ],
        ],
        'cards' => [
            'label' => 'Cards & sharing',
            'pages' => [
                'render'      => 'Generate an eQSL card',
                'bulk-render' => 'Bulk-render many cards',
                'share'       => 'Share a card publicly',
                'download'    => 'Download as image or PDF',
            ],
        ],
        'templates' => [
            'label' => 'Templates',
            'pages' => [
                'overview'      => 'How templates work',
                'designer'      => 'Design your own template',
                'submit-public' => 'Submit a template to the gallery',
            ],
        ],
        'admin' => [
            'label' => 'Admin guide',
            'pages' => [
                'install'      => 'First-time install + setup',
                'settings'     => 'Site settings',
                'users'        => 'User management',
                'cleanup'      => 'Storage cleanup',
                'callsign-dir' => 'Callsign directory CSV upload',
                'audit'        => 'Audit log',
                'migrations'   => 'Running migrations',
            ],
        ],
        'mobile' => [
            'label' => 'Mobile & portable ops',
            'pages' => [
                'navigation' => 'Bottom-tab navigation',
            ],
        ],
        'reference' => [
            'label' => 'Reference',
            'pages' => [
                'glossary'        => 'Glossary',
                'troubleshooting' => 'Troubleshooting',
                'about'           => 'About + credits',
            ],
        ],
    ];

    public static function exists(string $category, string $slug): bool
    {
        return isset(self::TREE[$category]['pages'][$slug]);
    }

    public static function pageLabel(string $category, string $slug): string
    {
        return self::TREE[$category]['pages'][$slug] ?? '';
    }

    public static function categoryLabel(string $category): string
    {
        return self::TREE[$category]['label'] ?? '';
    }

    /**
     * @return array{prev: ?array, next: ?array}
     */
    public static function neighbours(string $category, string $slug): array
    {
        $flat = [];
        foreach (self::TREE as $cat => $data) {
            foreach (array_keys($data['pages']) as $s) {
                $flat[] = ['category' => $cat, 'slug' => $s];
            }
        }
        $idx = null;
        foreach ($flat as $i => $entry) {
            if ($entry['category'] === $category && $entry['slug'] === $slug) {
                $idx = $i;
                break;
            }
        }
        if ($idx === null) {
            return ['prev' => null, 'next' => null];
        }
        return [
            'prev' => $flat[$idx - 1] ?? null,
            'next' => $flat[$idx + 1] ?? null,
        ];
    }

    /**
     * @return \Generator<array{0:string,1:string,2:string}>
     */
    public static function allPages(): \Generator
    {
        foreach (self::TREE as $category => $data) {
            foreach ($data['pages'] as $slug => $label) {
                yield [$category, $slug, $label];
            }
        }
    }
}
