<?php

declare(strict_types=1);

namespace App\Services;

class CsvExportService
{
    /** @var TemplateParser */
    private $parser;

    /** @var ValueResolver */
    private $resolver;

    public function __construct()
    {
        $this->parser = new TemplateParser();
        $this->resolver = new ValueResolver();
    }

    public function buildCsv(array $template, array $products, int $previewLimit = 0, array $exportOptions = array()): string
    {
        $tpl = $this->parser->parse($template);
        $delimiter = $tpl['delimiter'] !== '' ? $tpl['delimiter'] : ';';
        $encoding = strtoupper($tpl['encoding']);
        $addBom = (int) $tpl['add_bom'] === 1;
        $arraySeparator = $tpl['array_separator'] !== '' ? $tpl['array_separator'] : '|';

        $stream = fopen('php://temp', 'r+');
        $headers = array();
        foreach ($tpl['columns'] as $column) {
            $headers[] = $column['header_name'];
        }

        fputcsv($stream, $headers, $delimiter);

        $rows = $products;
        if ($previewLimit > 0) {
            $rows = array_slice($products, 0, $previewLimit);
        }

        foreach ($rows as $product) {
            $resolvedColumns = array();
            $rowCount = 1;

            foreach ($tpl['columns'] as $column) {
                $value = $this->resolveColumnValue($product, $column, $arraySeparator, $exportOptions);
                $items = $this->columnRowValues($product, $column, $value, $exportOptions);
                if (count($items) > $rowCount) {
                    $rowCount = count($items);
                }

                $resolvedColumns[] = array(
                    'items' => $items,
                    'repeat' => !$this->isMultiRowColumn($column),
                );
            }

            for ($rowIndex = 0; $rowIndex < $rowCount; $rowIndex++) {
                $line = array();
                foreach ($resolvedColumns as $resolvedColumn) {
                    if ($resolvedColumn['repeat']) {
                        $line[] = isset($resolvedColumn['items'][0]) ? $resolvedColumn['items'][0] : '';
                        continue;
                    }

                    $line[] = isset($resolvedColumn['items'][$rowIndex]) ? $resolvedColumn['items'][$rowIndex] : '';
                }

                fputcsv($stream, $line, $delimiter);
            }
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        if ($encoding === 'WINDOWS-1250') {
            $converted = @iconv('UTF-8', 'Windows-1250//TRANSLIT', $csv);
            if ($converted !== false) {
                $csv = $converted;
            }
        }

        if ($addBom && $encoding === 'UTF-8') {
            $csv = "\xEF\xBB\xBF" . $csv;
        }

        return $csv;
    }

    private function resolveColumnValue(array $product, array $column, string $arraySeparator, array $exportOptions = array()): string
    {
        $sourceType = strtolower(trim((string) ($column['source_type'] ?? 'field')));
        $sourceValue = (string) ($column['source_value'] ?? '');
        $settings = isset($column['settings']) && is_array($column['settings']) ? $column['settings'] : array();

        if ($sourceType !== 'computed' && in_array($sourceType, array('concat', 'upper', 'lower', 'trim'), true)) {
            if (!isset($settings['function']) || trim((string) $settings['function']) === '') {
                $settings['function'] = $sourceType;
            }

            $sourceType = 'computed';
        }

        if (isset($settings['array_separator']) && (string) $settings['array_separator'] !== '') {
            $arraySeparator = (string) $settings['array_separator'];
        }

        switch ($sourceType) {
            case 'static':
                $value = $this->resolver->resolveStatic($sourceValue);
                break;
            case 'computed':
                $value = $this->resolver->resolveComputed($product, $settings, $arraySeparator, $exportOptions);
                break;
            case 'field':
            default:
                $value = $this->resolver->resolveField($product, $sourceValue, $arraySeparator, $exportOptions);
                break;
        }

        $value = $this->applyConditions($product, $settings, $value, $arraySeparator, $exportOptions);
        $value = $this->applyMapping($column, $value);
        $value = $this->applyFormat($settings, $value);

        return is_scalar($value) ? (string) $value : '';
    }

    private function applyMapping(array $column, $value)
    {
        $map = isset($column['mappings']) && is_array($column['mappings']) ? $column['mappings'] : array();
        $key = (string) $value;

        if (isset($map[$key])) {
            return (string) $map[$key];
        }

        return $value;
    }

    private function applyConditions(array $product, array $settings, $value, string $arraySeparator, array $exportOptions = array())
    {
        $condition = isset($settings['condition']) && is_array($settings['condition']) ? $settings['condition'] : null;
        if (!$condition) {
            return $value;
        }

        $fieldPath = isset($condition['field']) ? (string) $condition['field'] : '';
        if (trim($fieldPath) === '') {
            return $value;
        }

        $operator = isset($condition['operator']) ? (string) $condition['operator'] : 'eq';
        $expected = isset($condition['value']) ? (string) $condition['value'] : '';

        $actualRaw = $this->resolver->resolveFieldValue($product, $fieldPath, $exportOptions);
        $actual = is_array($actualRaw)
            ? $this->resolver->resolveField($product, $fieldPath, $arraySeparator, $exportOptions)
            : $actualRaw;
        $actualString = is_scalar($actual) || $actual === null ? (string) $actual : '';
        $pass = false;

        switch ($operator) {
            case 'gt':
                $pass = (float) $actual > (float) $expected;
                break;
            case 'gte':
                $pass = (float) $actual >= (float) $expected;
                break;
            case 'lt':
                $pass = (float) $actual < (float) $expected;
                break;
            case 'lte':
                $pass = (float) $actual <= (float) $expected;
                break;
            case 'contains':
                $pass = stripos($actualString, $expected) !== false;
                break;
            case 'neq':
                $pass = $actual !== $expected;
                break;
            case 'eq':
            default:
                $pass = $actual === $expected;
                break;
        }

        if ($pass) {
            if (array_key_exists('then', $condition) && (string) $condition['then'] !== '') {
                return (string) $condition['then'];
            }

            return $value;
        }

        if (array_key_exists('else', $condition) && (string) $condition['else'] !== '') {
            return (string) $condition['else'];
        }

        return $value;
    }

    private function applyFormat(array $settings, $value)
    {
        $format = trim((string) ($settings['format'] ?? ''));
        if ($format === '') {
            return $value;
        }

        if ($format === 'upper') {
            return function_exists('mb_strtoupper') ? mb_strtoupper((string) $value, 'UTF-8') : strtoupper((string) $value);
        }

        if ($format === 'lower') {
            return function_exists('mb_strtolower') ? mb_strtolower((string) $value, 'UTF-8') : strtolower((string) $value);
        }

        if ($format === 'trim') {
            return trim((string) $value);
        }

        if (strpos($format, 'date:') === 0) {
            $phpFormat = substr($format, 5);
            $timestamp = strtotime((string) $value);

            return $timestamp !== false ? date($phpFormat !== '' ? $phpFormat : 'Y-m-d', $timestamp) : (string) $value;
        }

        if (strpos($format, 'number:') === 0) {
            $parts = explode(':', $format);
            $decimals = isset($parts[1]) ? (int) $parts[1] : 2;
            $decimalPoint = isset($parts[2]) && $parts[2] !== '' ? $parts[2] : ',';
            $thousandsSeparator = isset($parts[3]) ? $parts[3] : ' ';

            return number_format((float) $value, $decimals, $decimalPoint, $thousandsSeparator);
        }

        return $value;
    }

    private function columnRowValues(array $product, array $column, string $value, array $exportOptions): array
    {
        if (!$this->isMultiRowColumn($column)) {
            return array($value);
        }

        $sourceValue = strtolower(trim((string) ($column['source_value'] ?? '')));
        if (in_array($sourceValue, array('images', 'generated_images', 'product.generated_images'), true)) {
            $settings = isset($column['settings']) && is_array($column['settings']) ? $column['settings'] : array();
            return $this->resolver->generateImageExportRows($product, $exportOptions, $settings);
        }

        $items = preg_split('/\r\n|\r|\n/', $value);
        if (!is_array($items) || $items === array()) {
            return array('');
        }

        $items = array_values(array_filter(array_map('trim', $items), static function (string $item): bool {
            return $item !== '';
        }));

        return $items !== array() ? $items : array('');
    }

    private function isMultiRowColumn(array $column): bool
    {
        if (($column['source_type'] ?? 'field') !== 'field') {
            return false;
        }

        $sourceValue = strtolower(trim((string) ($column['source_value'] ?? '')));
        return in_array($sourceValue, array('images', 'generated_images', 'product.generated_images'), true);
    }
}
