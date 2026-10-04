<?php declare(strict_types=1);

namespace Tests\Support\Grid;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Reads a rendered page the way a user (and the datagrid's own JavaScript) sees its datagrids: grids, columns, sort links,
 * filter fields, rows, hide/show links, per-page choices.
 *
 * It is the ONLY place that knows the markup of ublaboo/datagrid 6 AND contributte/datagrid 7 (a grid is `div.datagrid.datagrid-<grid>`
 * in 6 and `div.datagrid[data-datagrid-name=<grid>]` in 7; both use `col-<column>` cells, `filter[<column>]` fields, `data-id` rows,
 * `do=<grid>-hideColumn` links ...). The grid tests talk to this class, so the datagrid/Bootstrap update means adapting THIS
 * file, not rewriting the tests: what they assert is behaviour. The same file lives in all three projects.
 */
final class DatagridPage
{
    private readonly Crawler $crawler;

    public function __construct(string $html)
    {
        $this->crawler = new Crawler($html);
    }

    /**
     * @return list<string>
     */
    public function gridNames() : array
    {
        $names = [];

        $this->crawler->filterXPath('//div[contains(concat(" ", normalize-space(@class), " "), " datagrid ")]')->each(
            static function (Crawler $grid) use (&$names) : void {
                if ($grid->attr('data-datagrid-name') !== null) { // contributte/datagrid 7
                    $names[] = (string) $grid->attr('data-datagrid-name');

                    return;
                }

                foreach (explode(' ', (string) $grid->attr('class')) as $class) { // ublaboo/datagrid 6
                    if (str_starts_with($class, 'datagrid-')) {
                        $names[] = substr($class, strlen('datagrid-'));
                    }
                }
            },
        );

        return array_values(array_unique($names));
    }

    public function filterFormId(string $grid) : string
    {
        return 'frm-' . $grid . '-filter';
    }

    /**
     * @return list<string> column keys in header order (the action column has none)
     */
    public function columns(string $grid) : array
    {
        $keys = [];

        $this->grid($grid)->filterXPath('.//thead//th[contains(@class, "col-")]')->each(
            function (Crawler $th) use (&$keys) : void {
                $key = $this->columnKey($th);

                if ($key !== null && $key !== 'action') {
                    $keys[] = $key;
                }
            },
        );

        return array_values(array_unique($keys));
    }

    /**
     * @return array<string, string> column key => URL of the (ascending) sort link
     */
    public function sortUrls(string $grid) : array
    {
        $urls = [];

        $this->grid($grid)->filterXPath('.//thead//a[starts-with(@id, "datagrid-sort-")]')->each(
            static function (Crawler $link) use (&$urls) : void {
                $urls[substr((string) $link->attr('id'), strlen('datagrid-sort-'))] = (string) $link->attr('href');
            },
        );

        return $urls;
    }

    /**
     * @return list<array{key: string, name: string, kind: string, options: list<string>}> kind: text | date | range | select | multiselect
     */
    public function filters(string $grid) : array
    {
        $filters = [];

        $this->grid($grid)->filterXPath('.//thead//*[(self::input or self::select) and starts-with(@name, "filter[")]')->each(
            static function (Crawler $field) use (&$filters) : void {
                $name = (string) $field->attr('name');
                preg_match('~^filter\[([^\]]+)\]~', $name, $m);

                $options = [];
                $kind = 'text';

                if (preg_match('~^filter\[[^\]]+\]\[(from|to)\]$~', $name) === 1) {
                    foreach ($filters as $known) {
                        if ($known['key'] === $m[1] && $known['kind'] === 'range') {
                            return; // the "to" half of a range already listed
                        }
                    }

                    $kind = 'range';
                } elseif ($field->nodeName() === 'select') {
                    $kind = $field->attr('multiple') !== null ? 'multiselect' : 'select';
                    $field->filter('option')->each(static function (Crawler $option) use (&$options) : void {
                        $value = (string) $option->attr('value');

                        if ($value !== '') {
                            $options[] = $value;
                        }
                    });
                } elseif ($field->attr('data-provide') === 'datepicker' || $field->attr('data-datagrid-datepicker') !== null || str_contains((string) $field->attr('class'), 'datepicker')) {
                    $kind = 'date';
                }

                $filters[] = ['key' => $m[1], 'name' => $name, 'kind' => $kind, 'options' => $options];
            },
        );

        return $filters;
    }

    /**
     * @return list<string> ids (data-id) of the rows shown, in order
     */
    public function rowIds(string $grid) : array
    {
        $ids = [];

        $this->grid($grid)->filterXPath('.//tbody/tr[@data-id]')->each(
            static function (Crawler $row) use (&$ids) : void {
                $ids[] = (string) $row->attr('data-id');
            },
        );

        return $ids;
    }

    /**
     * @return list<string> text of one column, row by row
     */
    public function cells(string $grid, string $column) : array
    {
        $cells = [];

        $this->grid($grid)->filterXPath('.//tbody/tr[@data-id]')->each(
            static function (Crawler $row) use (&$cells, $column) : void {
                $cell = $row->filterXPath('.//td[contains(concat(" ", normalize-space(@class), " "), " col-' . $column . ' ")]');
                $cells[] = $cell->count() === 0 ? '' : trim((string) preg_replace('~\s+~u', ' ', $cell->text()));
            },
        );

        return $cells;
    }

    /**
     * "( Položky: 1 - 2 z 2 )" -> 2; null when the grid shows no such line
     */
    public function totalItems(string $grid) : ?int
    {
        $info = $this->grid($grid)->filterXPath('.//*[contains(@class, "col-items")]');

        if ($info->count() === 0) {
            return null;
        }

        return preg_match('~z\s+(\d+)~u', $info->text(), $m) === 1 ? (int) $m[1] : null;
    }

    /**
     * @return array<string, string> column key => URL
     */
    public function hideColumnUrls(string $grid) : array
    {
        return $this->columnLinks($grid, 'hideColumn');
    }

    /**
     * @return array<string, string> column key => URL
     */
    public function showColumnUrls(string $grid) : array
    {
        return $this->columnLinks($grid, 'showColumn');
    }

    /**
     * @return list<string> the "per page" choices, e.g. 10, 20, 50, all
     */
    public function perPageOptions(string $grid) : array
    {
        $options = [];

        $this->grid($grid)->filterXPath('.//select[@name="perPage"]/option')->each(
            static function (Crawler $option) use (&$options) : void {
                $options[] = (string) $option->attr('value');
            },
        );

        return $options;
    }

    /**
     * URL that clears the filter of ONE column (the "x" next to a filtered column header). An AJAX answer redraws only parts of the
     * grid (no header), so with $pageUrl the URL is built from the signal's known shape: `<page>?<grid>-key=<column>&do=<grid>-resetColumnFilter`.
     */
    public function resetColumnFilterUrl(string $grid, string $column, ?string $pageUrl = null) : ?string
    {
        $link = $this->grid($grid)->filterXPath('.//a[@data-datagrid-reset-filter-by-column="' . $column . '"]');

        if ($link->count() > 0) {
            return (string) $link->attr('href');
        }

        if ($pageUrl === null) {
            return null;
        }

        return $pageUrl . (str_contains($pageUrl, '?') ? '&' : '?') . $grid . '-key=' . rawurlencode($column) . '&do=' . $grid . '-resetColumnFilter';
    }

    public function hasGrid(string $grid) : bool
    {
        return in_array($grid, $this->gridNames(), true);
    }

    public function hasSummaryRow(string $grid) : bool
    {
        return $this->grid($grid)->filterXPath('.//tr[contains(@class, "datagrid-row-columns-summary")]')->count() > 0;
    }

    /**
     * @return array<string, string> column key => text of the summary row
     */
    public function summary(string $grid) : array
    {
        $summary = [];

        $this->grid($grid)->filterXPath('.//tr[contains(@class, "datagrid-row-columns-summary")]/td')->each(
            function (Crawler $td) use (&$summary) : void {
                $key = $this->columnKey($td);

                if ($key !== null) {
                    $summary[$key] = trim((string) preg_replace('~\s+~u', ' ', $td->text()));
                }
            },
        );

        return $summary;
    }

    /**
     * @return list<string> texts of the actions of the first row (title attribute or text)
     */
    public function rowActionTitles(string $grid) : array
    {
        $titles = [];

        $this->grid($grid)->filterXPath('(.//tbody/tr[@data-id])[1]//td[contains(@class, "col-action")]//a')->each(
            static function (Crawler $a) use (&$titles) : void {
                $titles[] = trim((string) ($a->attr('title') ?? $a->text()));
            },
        );

        return $titles;
    }

    private function grid(string $name) : Crawler
    {
        $grid = $this->crawler->filterXPath('//div[@data-datagrid-name="' . $name . '"]');

        if ($grid->count() === 0) {
            $grid = $this->crawler->filterXPath('//div[contains(concat(" ", normalize-space(@class), " "), " datagrid-' . $name . ' ")]');
        }

        if ($grid->count() === 0) {
            throw new \LogicException('No datagrid "' . $name . '" on the page.');
        }

        return $grid;
    }

    private function columnKey(Crawler $cell) : ?string
    {
        foreach (explode(' ', (string) $cell->attr('class')) as $class) {
            if (str_starts_with($class, 'col-')) {
                return substr($class, 4);
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function columnLinks(string $grid, string $signal) : array
    {
        $urls = [];

        $this->grid($grid)->filterXPath('.//a[contains(@href, "do=' . $grid . '-' . $signal . '")]')->each(
            static function (Crawler $link) use (&$urls) : void {
                $href = (string) $link->attr('href');

                if (preg_match('~-column=([^&]+)~', $href, $m) === 1) {
                    $urls[urldecode($m[1])] = $href;
                }
            },
        );

        return $urls;
    }
}
