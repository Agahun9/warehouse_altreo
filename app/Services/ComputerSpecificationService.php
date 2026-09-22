<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

/**
 * Specyfikacja komputerow magazynowych dla pozycji zamowienia (po SKU).
 * Uzywana przez notatki Sellasist i notatki zamowien SalesCenter.
 */
class ComputerSpecificationService
{
    /** @var Database */
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * @param string[] $skus SKU pozycji zamowienia w kolejnosci pozycji
     * @return array{html:string,plain_text:string,text:string}
     */
    public function build(array $skus): array
    {
        $htmlSections = array();
        $plainTextSections = array();
        $textSections = array();
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '') {
                continue;
            }

            $product = $this->findComputerProductBySku($sku);
            if (!is_array($product)) {
                continue;
            }

            $components = $this->computerComponents((string) ($product['id_components'] ?? ''));
            $htmlSections[] = $this->computerSpecificationSection($product, $components, $sku);
            $plainTextSections[] = $this->computerPlainTextSpecification($components);
            $textSections[] = $this->computerTextSpecification($product, $components, $sku);
        }

        if ($htmlSections === array()) {
            throw new RuntimeException('Nie znaleziono komputera magazynowego dla SKU z zamówienia.');
        }

        return array(
            'html' => '<div style="font-family:Arial,sans-serif;color:#1f2937">'
                . '<div style="font-size:18px;font-weight:700;margin-bottom:12px">Specyfikacja zamówienia</div>'
                . implode('<div style="height:12px"></div>', $htmlSections)
                . '</div>',
            'plain_text' => implode("\n", $plainTextSections),
            'text' => "Specyfikacja zamówienia\n\n" . implode("\n\n", $textSections),
        );
    }

    private function findComputerProductBySku(string $sku)
    {
        return $this->db->fetch(
            'SELECT id, id_components, sku, name, price FROM pr_products_altreo'
            . ' WHERE sku = :sku OR CONCAT("ALTREO_", id) = :derived_sku'
            . ' ORDER BY CASE WHEN sku = :exact_sku THEN 0 ELSE 1 END LIMIT 1',
            array(
                'sku' => $sku,
                'derived_sku' => $sku,
                'exact_sku' => $sku,
            )
        );
    }

    private function computerComponents(string $componentIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            'intval',
            preg_split('/\s*,\s*/', trim($componentIds)) ?: array()
        ))));
        if ($ids === array()) {
            return array();
        }

        $params = array();
        $placeholders = array();
        foreach ($ids as $index => $id) {
            $key = 'computer_spec_component_' . $index;
            $params[$key] = $id;
            $placeholders[] = ':' . $key;
        }

        $rows = $this->db->fetchAll(
            'SELECT id, category, name, name_title, name_spec FROM pr_components_altreo'
            . ' WHERE id IN (' . implode(', ', $placeholders) . ')',
            $params
        );
        $byId = array();
        foreach ($rows as $row) {
            if (is_array($row)) {
                $byId[(int) ($row['id'] ?? 0)] = $row;
            }
        }

        $components = array();
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $components[] = $byId[$id];
            }
        }

        usort($components, function (array $left, array $right): int {
            $leftRank = $this->computerComponentSortRank((string) ($left['category'] ?? ''));
            $rightRank = $this->computerComponentSortRank((string) ($right['category'] ?? ''));
            if ($leftRank !== $rightRank) {
                return $leftRank <=> $rightRank;
            }

            $categoryCompare = strnatcasecmp(
                trim((string) ($left['category'] ?? '')),
                trim((string) ($right['category'] ?? ''))
            );
            if ($categoryCompare !== 0) {
                return $categoryCompare;
            }

            $leftName = trim((string) ($left['name_spec'] ?? ($left['name'] ?? '')));
            $rightName = trim((string) ($right['name_spec'] ?? ($right['name'] ?? '')));
            $nameCompare = strnatcasecmp($leftName, $rightName);
            if ($nameCompare !== 0) {
                return $nameCompare;
            }

            return ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
        });

        return $components;
    }

    private function computerComponentSortRank(string $category): int
    {
        $category = mb_strtoupper(trim($category), 'UTF-8');
        $aliases = array(
            'PŁYTA GŁÓWNA' => 'MB',
            'PLYTA GLOWNA' => 'MB',
            'ZASILACZ' => 'PSU',
        );
        $category = $aliases[$category] ?? $category;

        $priority = array(
            'CPU' => 10,
            'GPU' => 20,
            'RAM' => 30,
            'SSD' => 40,
            'MB' => 50,
            'PSU' => 60,
        );

        return $priority[$category] ?? 100;
    }

    private function computerSpecificationSection(array $product, array $components, string $sku): string
    {
        $rows = '';
        foreach ($components as $component) {
            $category = trim((string) ($component['category'] ?? ''));
            $specification = trim((string) ($component['name_spec'] ?? ''));
            if ($specification === '') {
                $specification = trim((string) ($component['name'] ?? ''));
            }
            if ($specification === '') {
                $specification = trim((string) ($component['name_title'] ?? ''));
            }
            if ($specification === '') {
                continue;
            }

            $rows .= '<tr>'
                . '<td style="padding:7px 10px;border-bottom:1px solid #e5e7eb;font-weight:700;width:28%;background:#f8fafc">'
                . $this->escapeHtml($category !== '' ? $category : 'Komponent')
                . '</td>'
                . '<td style="padding:7px 10px;border-bottom:1px solid #e5e7eb">'
                . $this->escapeHtml($specification)
                . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td style="padding:8px 10px;color:#6b7280">Brak przypisanych komponentów.</td></tr>';
        }

        $price = number_format((float) ($product['price'] ?? 0), 2, ',', ' ');
        $name = trim((string) ($product['name'] ?? 'Komputer'));

        return '<div style="border:1px solid #cbd5e1;border-radius:8px;overflow:hidden">'
            . '<div style="padding:10px 12px;background:#1d4ed8;color:#ffffff">'
            . '<div style="font-size:15px;font-weight:700">' . $this->escapeHtml($name) . '</div>'
            . '<div style="font-size:12px;margin-top:3px">SKU: ' . $this->escapeHtml($sku) . '</div>'
            . '</div>'
            . '<table style="width:100%;border-collapse:collapse;font-size:13px"><tbody>' . $rows . '</tbody></table>'
            . '<div style="padding:11px 12px;background:#ecfdf5;color:#065f46;font-size:15px">'
            . '<strong>Sugerowana cena magazynowa: ' . $price . ' zł</strong>'
            . '</div>'
            . '</div>';
    }

    private function computerPlainTextSpecification(array $components): string
    {
        $values = array();
        foreach ($components as $component) {
            $specification = trim((string) ($component['name_spec'] ?? ''));
            if ($specification === '') {
                $specification = trim((string) ($component['name'] ?? ''));
            }
            if ($specification === '') {
                $specification = trim((string) ($component['name_title'] ?? ''));
            }
            if (mb_strtoupper($specification, 'UTF-8') === 'BRAK') {
                $specification = 'SAM KOMPUTER';
            }
            if ($specification !== '') {
                $values[] = $specification;
            }
        }

        if ($values === array()) {
            return 'Specyfikacja';
        }

        return "Specyfikacja\n" . implode(" /\n", $values) . ' /';
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Tekstowy odpowiednik sekcji HTML (nazwa, SKU, komponenty, cena) dla notatek bez HTML. */
    private function computerTextSpecification(array $product, array $components, string $sku): string
    {
        $lines = array(trim((string) ($product['name'] ?? 'Komputer')), 'SKU: ' . $sku);
        foreach ($components as $component) {
            $category = trim((string) ($component['category'] ?? ''));
            $specification = trim((string) ($component['name_spec'] ?? ''));
            if ($specification === '') {
                $specification = trim((string) ($component['name'] ?? ''));
            }
            if ($specification === '') {
                $specification = trim((string) ($component['name_title'] ?? ''));
            }
            if ($specification === '') {
                continue;
            }
            $lines[] = ($category !== '' ? $category : 'Komponent') . ': ' . $specification;
        }
        if (count($lines) === 2) {
            $lines[] = 'Brak przypisanych komponentów.';
        }
        $lines[] = 'Sugerowana cena magazynowa: ' . number_format((float) ($product['price'] ?? 0), 2, ',', ' ') . ' zł';

        return implode("\n", $lines);
    }
}
