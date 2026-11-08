<?php
declare(strict_types=1);

namespace App\Helpers;

/** Pagination simple. */
final class Pager
{
    public int $page;
    public int $perPage;
    public int $total;
    public int $pages;

    public function __construct(int $total, ?int $perPage = null)
    {
        $this->perPage = $perPage ?? (int)config('per_page', 20);
        $this->total   = $total;
        $this->pages   = max(1, (int)ceil($total / $this->perPage));
        $this->page    = min($this->pages, max(1, (int)($_GET['page'] ?? 1)));
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /** SQL LIMIT/OFFSET (entiers castés : pas d'injection possible). */
    public function limitSql(): string
    {
        return ' LIMIT ' . (int)$this->perPage . ' OFFSET ' . (int)$this->offset();
    }

    public function render(): string
    {
        if ($this->pages <= 1) {
            return '';
        }
        $query = $_GET;
        $link = static function (int $p, string $text, string $cls = '') use ($query): string {
            $query['page'] = $p;
            return '<a class="page-link ' . $cls . '" href="?' . e(http_build_query($query)) . '">' . $text . '</a>';
        };
        $out = '<nav class="pager" aria-label="Pagination">';
        if ($this->page > 1) {
            $out .= $link($this->page - 1, '‹ Précédent');
        }
        $start = max(1, $this->page - 2);
        $end   = min($this->pages, $this->page + 2);
        for ($i = $start; $i <= $end; $i++) {
            $out .= $link($i, (string)$i, $i === $this->page ? 'active' : '');
        }
        if ($this->page < $this->pages) {
            $out .= $link($this->page + 1, 'Suivant ›');
        }
        $out .= '<span class="pager-info">' . $this->total . ' résultat' . ($this->total > 1 ? 's' : '') . '</span></nav>';
        return $out;
    }
}
