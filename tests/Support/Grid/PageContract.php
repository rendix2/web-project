<?php declare(strict_types=1);

namespace Tests\Support\Grid;

use Symfony\Component\DomCrawler\Crawler;

/**
 * What a page promises to the user, without its markup: title, headings, breadcrumb, navigation, the fields of its forms, its buttons
 * and, per datagrid, the columns, filters, per-page choices and the actions of the first row. No CSS classes, no data cells - so an
 * update of Bootstrap / the datagrid / the templates that keeps the page's meaning keeps this identical, and one that changes a
 * label or loses a control shows exactly what (tests/Functional/PageContractCest). Taken over from alfred.
 */
final class PageContract
{
    private readonly Crawler $crawler;

    public function __construct(
        private readonly string $html,
    )
    {
        $this->crawler = new Crawler($html);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray() : array
    {
        $datagrid = new DatagridPage($this->html);
        $grids = [];

        foreach ($datagrid->gridNames() as $grid) {
            $filters = [];

            foreach ($datagrid->filters($grid) as $filter) {
                $filters[] = $filter['key'] . ':' . $filter['kind']; // not the options: they are data
            }

            $grids[$grid] = [
                'columns' => $datagrid->columns($grid),
                'headers' => $this->headers($grid),
                'sortable' => array_keys($datagrid->sortUrls($grid)),
                'filters' => $filters,
                'perPage' => $datagrid->perPageOptions($grid),
                'rowActions' => $datagrid->rowActionTitles($grid),
                'hasSummary' => $datagrid->hasSummaryRow($grid),
            ];
        }

        ksort($grids);
        $title = $this->crawler->filterXPath('//title');

        return [
            'title' => $this->normalize($title->count() > 0 ? $title->text() : ''),
            'headings' => $this->texts($this->crawler->filterXPath('//h1|//h2|//h3|//h4|//h5|//h6')),
            'breadcrumb' => $this->texts($this->crawler->filterXPath('//*[contains(@class, "breadcrumb")]//li')),
            'navigation' => $this->texts($this->crawler->filterXPath('//nav[contains(concat(" ", normalize-space(@class), " "), " navbar ")]//a[normalize-space(.) != ""]')),
            'forms' => $this->forms(),
            'buttons' => $this->texts($this->crawler->filterXPath('//button[not(ancestor::*[contains(@class, "datagrid")])][normalize-space(.) != ""]|//a[contains(concat(" ", normalize-space(@class), " "), " btn ")][not(ancestor::*[contains(@class, "datagrid")])]')),
            'grids' => $grids,
        ];
    }

    /**
     * @return list<string>
     */
    private function texts(Crawler $nodes) : array
    {
        $texts = $nodes->each(fn (Crawler $node) : string => $this->normalize($node->text()));

        return array_values(array_filter($texts, static fn (string $text) : bool => $text !== ''));
    }

    private function normalize(string $text) : string
    {
        return trim((string) preg_replace('~\s+~u', ' ', $text));
    }

    /**
     * @return list<string>
     */
    private function headers(string $grid) : array
    {
        $root = $this->crawler->filterXPath('//div[@data-datagrid-name="' . $grid . '"]');

        if ($root->count() === 0) {
            $root = $this->crawler->filterXPath('//div[contains(concat(" ", normalize-space(@class), " "), " datagrid-' . $grid . ' ")]');
        }

        return $this->texts($root->filterXPath('.//thead//th[contains(@class, "col-") and .//a[starts-with(@id, "datagrid-sort-")] or contains(@class, "col-action")]'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function forms() : array
    {
        $forms = [];

        $this->crawler->filterXPath('//form[not(ancestor::*[contains(@class, "datagrid")])][not(contains(@id, "-filter"))]')->each(
            function (Crawler $form) use (&$forms) : void {
                $fields = [];

                $form->filterXPath('.//input[@name and not(@type="hidden")]|.//select[@name]|.//textarea[@name]')->each(
                    static function (Crawler $field) use (&$fields) : void {
                        $name = (string) $field->attr('name');

                        if (str_starts_with($name, '_') || str_starts_with($name, 'perPage')) {
                            return;
                        }

                        $fields[] = [
                            'name' => $name,
                            'tag' => $field->nodeName(),
                            'type' => $field->attr('type') ?? '',
                            'required' => $field->attr('required') !== null,
                        ];
                    },
                );

                $forms[] = [
                    'id' => (string) $form->attr('id'),
                    'fields' => $fields,
                    'labels' => $this->texts($form->filter('label')),
                    'submit' => $form->filterXPath('.//input[@type="submit"]|.//button[@type="submit"]')->each(
                        static fn (Crawler $button) : string => trim($button->attr('value') ?? $button->text()),
                    ),
                ];
            },
        );

        return $forms;
    }
}
