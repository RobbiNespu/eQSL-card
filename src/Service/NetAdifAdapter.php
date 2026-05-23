<?php
declare(strict_types=1);

namespace App\Service;

/**
 * M6 T21 — Adapts a NetSession to the shape AdifExporter::export() expects
 * (code, name, grid_square, started_at, ended_at, notes) WITHOUT
 * modifying the exporter. `code` is blank (a net has no POTA/SOTA ref);
 * `name` carries the net title (+ organisation).
 */
final class NetAdifAdapter
{
    public string $code = '';
    public string $name;
    public ?string $grid_square = null;
    public mixed $started_at;
    public mixed $ended_at;
    public ?string $notes;

    public function __construct(\App\Model\Entity\NetSession $s)
    {
        $this->name = $s->net_title . ($s->net_organisation ? ' (' . $s->net_organisation . ')' : '');
        $this->started_at = $s->started_at;
        $this->ended_at = $s->ended_at;
        $this->notes = $s->notes;
    }
}
