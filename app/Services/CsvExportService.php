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
        $exportOptions['csv_description_templates'] = isset($tpl['description_templates']) && is_array($tpl['description_templates'])
            ? $tpl['description_templates']
            : array();
        $exportOptions['csv_generated_images_settings'] = $this->generatedImagesColumnSettings($tpl['columns']);
        $exportOptions['csv_description_images_settings'] = $this->descriptionImagesColumnSettings(
            $tpl['columns'],
            $exportOptions['csv_generated_images_settings']
        );

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

        $queueItems = isset($exportOptions['image_queue_items']) && is_array($exportOptions['image_queue_items'])
            ? array_values($exportOptions['image_queue_items'])
            : array();
        $queueItemIndex = 0;

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
                    'column' => $column,
                    'items' => $items,
                    'queue_item' => $this->isQueueItemColumn($column),
                );
            }

            for ($columnIndex = 0; $columnIndex < count($resolvedColumns); $columnIndex++) {
                $column = $resolvedColumns[$columnIndex]['column'];

                if ($this->isMultiRowColumn($column)) {
                    continue;
                }

                $items = array();
                for ($rowIndex = 0; $rowIndex < $rowCount; $rowIndex++) {
                    $rowExportOptions = $exportOptions;
                    $rowExportOptions['csv_row_index'] = $rowIndex;
                    $queueItem = isset($queueItems[$queueItemIndex + $rowIndex]) ? $queueItems[$queueItemIndex + $rowIndex] : '';
                    if ($queueItem !== '') {
                        $rowExportOptions['image_queue_item'] = $queueItem;
                    }

                    $items[] = $this->resolveColumnValue($product, $column, $arraySeparator, $rowExportOptions);
                }

                $resolvedColumns[$columnIndex]['items'] = $items;
            }

            for ($rowIndex = 0; $rowIndex < $rowCount; $rowIndex++) {
                $line = array();
                foreach ($resolvedColumns as $resolvedColumn) {
                    if (!empty($resolvedColumn['queue_item'])) {
                        $line[] = isset($queueItems[$queueItemIndex]) ? $queueItems[$queueItemIndex] : '';
                        continue;
                    }

                    $line[] = isset($resolvedColumn['items'][$rowIndex]) ? $resolvedColumn['items'][$rowIndex] : '';
                }

                fputcsv($stream, $line, $delimiter);
                $queueItemIndex++;
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

    private function generatedImagesColumnSettings(array $columns): array
    {
        $firstMatch = array();

        foreach ($columns as $column) {
            if (!is_array($column)) {
                continue;
            }

            $sourceType = strtolower(trim((string) ($column['source_type'] ?? 'field')));
            $sourceValue = strtolower(trim((string) ($column['source_value'] ?? '')));
            if ($sourceType !== 'field') {
                continue;
            }

            if ($sourceValue !== 'images' && preg_match('/^(?:product\.)?(?:generated_images|images_export)(?:\[\d+\])?$/', $sourceValue) !== 1) {
                continue;
            }

            $settings = isset($column['settings']) && is_array($column['settings']) ? $column['settings'] : array();
            if ($firstMatch === array()) {
                $firstMatch = $settings;
            }

            if ($this->hasCustomizedGeneratedImagesSettings($settings)) {
                // Image settings are shared by every generated_images column.
                // The editor tells the user to configure them in the first such
                // column, so later (possibly stale) settings must not override it.
                return $settings;
            }
        }

        return $firstMatch;
    }

    private function descriptionImagesColumnSettings(array $columns, array $fallback): array
    {
        foreach ($columns as $column) {
            if (!is_array($column)) {
                continue;
            }

            $sourceType = strtolower(trim((string) ($column['source_type'] ?? 'field')));
            $sourceValue = strtolower(trim((string) ($column['source_value'] ?? '')));
            if ($sourceType !== 'field' || !in_array($sourceValue, array('images', 'generated_images', 'product.generated_images'), true)) {
                continue;
            }

            return isset($column['settings']) && is_array($column['settings'])
                ? $column['settings']
                : array();
        }

        return $fallback;
    }

    private function hasCustomizedGeneratedImagesSettings(array $settings): bool
    {
        $defaultLayout = array(
            array('type' => 'thumbnail', 'value' => ''),
            array('type' => 'mockup', 'value' => ''),
            array('type' => 'image', 'value' => ''),
        );
        $layout = isset($settings['image_layout']) && is_array($settings['image_layout']) ? $settings['image_layout'] : array();
        if ($layout !== array() && $layout !== $defaultLayout) {
            return true;
        }

        $imageOptions = isset($settings['image_options']) && is_array($settings['image_options']) ? $settings['image_options'] : array();
        $defaults = array(
            'image_collection_code' => '',
            'price_to_csv' => '',
            'image_count' => 0,
            'thumbnail_count' => 0,
            'mockup_count' => 0,
            'image_base_directory' => 'T:\\wygnerowane_do_EU',
            'thumbnail_macro' => '{{base_directory}}\\{{contours}}\\{{collection_code}}\\miniatura_t_{{index}}.png',
            'mockup_macro' => '{{base_directory}}\\{{contours}}\\mockup_{{index}}.jpg',
            'image_macro' => '{{base_directory}}\\{{contours}}\\{{collection_code}}\\{{index}}.jpg',
        );

        foreach ($defaults as $key => $defaultValue) {
            if (!array_key_exists($key, $imageOptions)) {
                continue;
            }

            $value = $imageOptions[$key];
            if (in_array($key, array('image_count', 'thumbnail_count', 'mockup_count'), true)) {
                if ((int) $value !== (int) $defaultValue) {
                    return true;
                }
                continue;
            }

            if ((string) $value !== (string) $defaultValue) {
                return true;
            }
        }

        return false;
    }

    private function resolveColumnValue(array $product, array $column, string $arraySeparator, array $exportOptions = array()): string
    {
        $sourceType = strtolower(trim((string) ($column['source_type'] ?? 'field')));
        $sourceValue = (string) ($column['source_value'] ?? '');
        $settings = isset($column['settings']) && is_array($column['settings']) ? $column['settings'] : array();

        if ($sourceType !== 'computed' && in_array($sourceType, array('concat', 'upper', 'lower', 'trim', 'ucfirst'), true)) {
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

        $value = $this->applyConditions($product, $settings, $value, $arraySeparator, $exportOptions, $sourceType === 'field' ? $sourceValue : '');
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

    private function applyConditions(array $product, array $settings, $value, string $arraySeparator, array $exportOptions = array(), string $sourceFieldPath = '')
    {
        $legacyCondition = isset($settings['condition']) && is_array($settings['condition']) ? $settings['condition'] : array();
        $conditions = isset($settings['conditions']) && is_array($settings['conditions'])
            ? array_values($settings['conditions'])
            : array();
        if ($conditions === array() && $legacyCondition !== array()) {
            $conditions[] = $legacyCondition;
        }
        if ($conditions === array()) {
            return $value;
        }

        foreach ($conditions as $condition) {
            if (!is_array($condition)) {
                continue;
            }
            $fieldPath = trim($sourceFieldPath) !== ''
                ? $sourceFieldPath
                : (isset($condition['field']) ? (string) $condition['field'] : '');
            if (trim($fieldPath) === '') {
                continue;
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
                case 'gt': $pass = (float) $actual > (float) $expected; break;
                case 'gte': $pass = (float) $actual >= (float) $expected; break;
                case 'lt': $pass = (float) $actual < (float) $expected; break;
                case 'lte': $pass = (float) $actual <= (float) $expected; break;
                case 'contains':
                    $pass = function_exists('mb_stripos')
                        ? mb_stripos($actualString, $expected, 0, 'UTF-8') !== false
                        : stripos($actualString, $expected) !== false;
                    break;
                case 'neq': $pass = $actual !== $expected; break;
                case 'eq':
                default: $pass = $actual === $expected; break;
            }

            if ($pass) {
                if (array_key_exists('then', $condition) && (string) $condition['then'] !== '') {
                    return (string) $condition['then'];
                }
                return $value;
            }
        }

        $fallback = (string) ($settings['condition_else'] ?? ($legacyCondition['else'] ?? ''));
        if ($fallback !== '') {
            return $fallback;
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

        if (in_array($format, array('ucfirst', 'capitalize'), true)) {
            $value = (string) $value;
            if ($value === '') {
                return '';
            }
            if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
                return mb_strtoupper(mb_substr($value, 0, 1, 'UTF-8'), 'UTF-8')
                    . mb_substr($value, 1, null, 'UTF-8');
            }
            return ucfirst($value);
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

        if (strpos($format, 'length:') === 0) {
            $maxLength = (int) substr($format, 7);
            $value = (string) $value;
            if ($maxLength <= 0) {
                return $value;
            }

            return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength, 'UTF-8') : substr($value, 0, $maxLength);
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

    private function isQueueItemColumn(array $column): bool
    {
        if (($column['source_type'] ?? 'field') !== 'field') {
            return false;
        }

        $sourceValue = strtolower(trim((string) ($column['source_value'] ?? '')));

        return in_array($sourceValue, array('queue_item', 'image_queue_item', 'product.queue_item', 'product.image_queue_item'), true);
    }
}
