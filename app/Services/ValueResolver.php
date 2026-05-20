<?php

declare(strict_types=1);

namespace App\Services;

class ValueResolver
{
    /** @var ComputedFunctions */
    private $computed;

    /** @var AllegroService */
    private $allegro;

    /** @var array */
    private $allegroDefinitionCache = array();

    /** @var array */
    private $allegroCategoryDefinitionsCache = array();

    /** @var EmpikService */
    private $empik;

    /** @var array */
    private $empikDefinitionCache = array();

    /** @var array */
    private $empikCategoryDefinitionsCache = array();

    public function __construct()
    {
        $this->computed = new ComputedFunctions();
        $this->allegro = new AllegroService();
        $this->empik = new EmpikService();
    }

    public function resolveField(array $product, string $path, string $arraySeparator = '|', array $exportOptions = array())
    {
        $value = $this->resolveFieldValue($product, $path, $exportOptions);

        if (is_array($value)) {
            return $this->flattenArray($value, $arraySeparator);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return $value === null ? '' : (string) $value;
    }

    public function resolveFieldValue(array $product, string $path, array $exportOptions = array())
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        $normalized = $this->normalizePath($path);

        if (in_array($normalized, array('allegro_parameters', 'allegro_parameters_text'), true)) {
            return $this->formatAllegroParameters($product);
        }

        if (in_array($normalized, array('allegro_parameters_eu', 'allegro_parameters_raw_eu'), true)) {
            return $this->formatAllegroParametersEu($product);
        }

        if (strpos($normalized, 'allegro_parameter.') === 0) {
            return $this->singleAllegroParameterValue($product, substr($normalized, 18));
        }

        if (in_array($normalized, array('empik_parameters', 'empik_parameters_text'), true)) {
            return $this->formatEmpikParameters($product);
        }

        if (strpos($normalized, 'empik_parameter.') === 0) {
            return $this->singleEmpikParameterValue($product, substr($normalized, 16));
        }

        if (in_array($normalized, array('collection_name', 'export_collection', 'csv_collection'), true)) {
            return isset($exportOptions['collection_name']) ? (string) $exportOptions['collection_name'] : '';
        }

        if (in_array($normalized, array('price_to_csv', 'export_price'), true)) {
            return isset($exportOptions['price_to_csv']) ? (string) $exportOptions['price_to_csv'] : '';
        }

        if (in_array($normalized, array('generated_title', 'title_generated', 'csv_title'), true)) {
            return $this->generateTitleExportValue($product, $exportOptions);
        }

        if (in_array($normalized, array('generated_images', 'images_export'), true)) {
            return $this->generateImageExportLines($product, $exportOptions);
        }

        if (strpos($normalized, 'csv_description.') === 0) {
            return $this->renderCsvDescriptionTemplate($product, substr($normalized, 16), $exportOptions);
        }

        return $this->walkPath($product, $normalized);
    }

    public function resolveStatic(string $value): string
    {
        return $value;
    }

    public function resolveComputed(array $product, array $settings, string $arraySeparator = '|', array $exportOptions = array())
    {
        $functionName = isset($settings['function']) ? (string) $settings['function'] : '';
        $args = isset($settings['args']) && is_array($settings['args']) ? $settings['args'] : array();

        $context = array(
            'product' => $product,
            'array_separator' => $arraySeparator,
            'field_resolver' => array($this, 'resolveField'),
            'field_value_resolver' => array($this, 'resolveFieldValue'),
            'export_options' => $exportOptions,
        );

        return $this->computed->apply($functionName, $args, $context);
    }

    private function normalizePath(string $path): string
    {
        $aliases = array(
            'id' => 'id',
            'sku' => 'sku',
            'name' => 'product_name',
            'title' => 'product_name',
            'description' => 'description',
            'price' => 'price_gross',
            'price_net' => 'price_net',
            'price_gross' => 'price_gross',
            'category' => 'category_name',
            'category_name' => 'category_name',
            'category_slug' => 'category_slug',
            'category_id_allegro' => 'allegro_category_id',
            'category_id_empik' => 'empik_category_id',
            'product.name' => 'product_name',
            'product.title' => 'product_name',
            'product.category.name' => 'category_name',
            'product.category.slug' => 'category_slug',
            'product.category.id' => 'category_id',
            'product.category_id_allegro' => 'category_allegro_id',
            'product.category_id_empik' => 'category_empik_id',
            'product.image' => 'img',
            'product.image.url' => 'img',
            'product.price' => 'price_gross',
            'product.net_price' => 'price_net',
            'product.gross_price' => 'price_gross',
            'products.name' => 'product_name',
            'products.title' => 'product_name',
            'products.description' => 'description',
            'products.sku' => 'sku',
            'products.price' => 'price_gross',
            'products.price_net' => 'price_net',
            'products.price_gross' => 'price_gross',
            'products.quantity' => 'quantity',
            'products.created_at' => 'created_at',
            'products.updated_at' => 'updated_at',
            'images' => 'generated_images',
            'generated_title' => 'generated_title',
            'csv_title' => 'generated_title',
            'csv_description' => 'csv_description',
            'allegro_parameters' => 'allegro_parameters',
            'allegro_parameters_eu' => 'allegro_parameters_eu',
            'empik_parameters' => 'empik_parameters',
            'categories.name' => 'category_name',
            'categories.slug' => 'category_slug',
            'categories.allegro_id' => 'allegro_category_id',
            'collection_name' => 'collection_name',
            'price_to_csv' => 'price_to_csv',
   
        );

        if (isset($aliases[$path])) {
            return $aliases[$path];
        }

        if (strpos($path, 'product.') === 0) {
            return substr($path, 8);
        }

        if (strpos($path, 'products.') === 0) {
            return substr($path, 9);
        }

        return $path;
    }

    private function walkPath($source, string $path)
    {
        $current = $source;
        $segments = explode('.', $path);

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            if (preg_match('/^([A-Za-z0-9_]+)\[(\d+)\]$/', $segment, $matches) === 1) {
                $key = $matches[1];
                $index = (int) $matches[2];

                if (!is_array($current) || !isset($current[$key]) || !is_array($current[$key]) || !isset($current[$key][$index])) {
                    return null;
                }

                $current = $current[$key][$index];
                continue;
            }

            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    private function flattenArray(array $value, string $separator): string
    {
        $out = array();
        foreach ($value as $item) {
            if (is_array($item)) {
                if (isset($item['url'])) {
                    $out[] = (string) $item['url'];
                } else {
                    $out[] = json_encode($item);
                }
            } else {
                $out[] = (string) $item;
            }
        }

        return implode($separator, array_filter($out, function ($item) {
            return $item !== '';
        }));
    }

    private function formatAllegroParameters(array $product): string
    {
        $raw = isset($product['allegro_parameters_raw']) && is_array($product['allegro_parameters_raw']) ? $product['allegro_parameters_raw'] : array();
        if ($raw === array()) {
            return '';
        }

        $categoryAllegroId = isset($product['category_allegro_id']) ? (string) $product['category_allegro_id'] : '';
        $nameMap = $this->allegroParameterNameMap($categoryAllegroId);
        $lines = array();

        foreach ($raw as $parameterId => $value) {
            $label = isset($nameMap[$parameterId]) ? $nameMap[$parameterId] : (string) $parameterId;
            $formattedValue = $this->formatAllegroParameterValue($value, $this->allegroParameterDefinition($categoryAllegroId, (string) $parameterId));
            if ($formattedValue === '') {
                continue;
            }

            $lines[] = $label . ': ' . $formattedValue;
        }

        return implode("\n", $lines);
    }

    private function singleAllegroParameterValue(array $product, string $parameterId): string
    {
        $raw = isset($product['allegro_parameters_raw']) && is_array($product['allegro_parameters_raw']) ? $product['allegro_parameters_raw'] : array();
        if ($parameterId === '' || !array_key_exists($parameterId, $raw)) {
            return '';
        }

        $categoryAllegroId = isset($product['category_allegro_id']) ? (string) $product['category_allegro_id'] : '';
        return $this->formatAllegroParameterValue($raw[$parameterId], $this->allegroParameterDefinition($categoryAllegroId, $parameterId));
    }

    private function formatAllegroParametersEu(array $product): string
    {
        $raw = isset($product['allegro_parameters_raw']) && is_array($product['allegro_parameters_raw']) ? $product['allegro_parameters_raw'] : array();
        if ($raw === array()) {
            return '';
        }

        $lines = array();
        foreach ($raw as $parameterId => $value) {
            $parameterId = trim((string) $parameterId);
            if ($parameterId === '') {
                continue;
            }

            $normalizedValue = $this->normalizeAllegroEuParameterValue($value);
            if ($normalizedValue === '') {
                continue;
            }

            $lines[] = $parameterId . '|' . $this->allegroEuParameterType($parameterId, $value) . '|' . $normalizedValue . '|';
        }

        return implode("\n", $lines);
    }

    private function normalizeAllegroEuParameterValue($value): string
    {
        if (is_array($value)) {
            $parts = array();
            foreach ($value as $item) {
                $item = trim((string) $item);
                if ($item !== '') {
                    $parts[] = $item;
                }
            }

            return implode('[rn]', $parts);
        }

        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private function allegroEuParameterType(string $parameterId, $value): string
    {
        if (is_array($value)) {
            return '3';
        }

        if (is_bool($value)) {
            return '1';
        }

        $normalized = trim((string) $value);
        if ($normalized === '0' || $normalized === '1') {
            return '1';
        }

        if ($normalized !== '' && strpos($normalized, $parameterId . '_') === 0) {
            return '3';
        }

        return '0';
    }

    private function allegroParameterNameMap(string $categoryAllegroId): array
    {
        if ($categoryAllegroId === '') {
            return array();
        }

        if (isset($this->allegroDefinitionCache[$categoryAllegroId])) {
            return $this->allegroDefinitionCache[$categoryAllegroId];
        }

        $map = array();

        foreach ($this->allegroCategoryDefinitions($categoryAllegroId) as $definition) {
            if (!is_array($definition) || empty($definition['id'])) {
                continue;
            }

            $map[(string) $definition['id']] = isset($definition['name']) ? (string) $definition['name'] : (string) $definition['id'];
        }

        $this->allegroDefinitionCache[$categoryAllegroId] = $map;
        return $map;
    }

    private function formatEmpikParameters(array $product): string
    {
        $raw = isset($product['empik_parameters_raw']) && is_array($product['empik_parameters_raw']) ? $product['empik_parameters_raw'] : array();
        if ($raw === array()) {
            return '';
        }

        $categoryEmpikId = isset($product['category_empik_id']) ? (string) $product['category_empik_id'] : '';
        $nameMap = $this->empikParameterNameMap($categoryEmpikId);
        $lines = array();

        foreach ($raw as $parameterId => $value) {
            $label = isset($nameMap[$parameterId]) ? $nameMap[$parameterId] : (string) $parameterId;
            $formattedValue = $this->formatEmpikParameterValue($value);
            if ($formattedValue === '') {
                continue;
            }

            $lines[] = $label . ': ' . $formattedValue;
        }

        return implode("\n", $lines);
    }

    private function singleEmpikParameterValue(array $product, string $parameterId): string
    {
        $raw = isset($product['empik_parameters_raw']) && is_array($product['empik_parameters_raw']) ? $product['empik_parameters_raw'] : array();
        if ($parameterId === '' || !array_key_exists($parameterId, $raw)) {
            return '';
        }

        return $this->formatEmpikParameterValue($raw[$parameterId]);
    }

    private function formatEmpikParameterValue($value): string
    {
        if (is_array($value)) {
            $parts = array();
            foreach ($value as $item) {
                $item = trim((string) $item);
                if ($item !== '') {
                    $parts[] = $item;
                }
            }

            return implode('|', $parts);
        }

        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private function empikParameterNameMap(string $categoryEmpikId): array
    {
        if ($categoryEmpikId === '') {
            return array();
        }

        if (isset($this->empikDefinitionCache[$categoryEmpikId])) {
            return $this->empikDefinitionCache[$categoryEmpikId];
        }

        $map = array();

        foreach ($this->empikCategoryDefinitions($categoryEmpikId) as $definition) {
            if (!is_array($definition) || empty($definition['id'])) {
                continue;
            }

            $map[(string) $definition['id']] = isset($definition['name']) ? (string) $definition['name'] : (string) $definition['id'];
        }

        $this->empikDefinitionCache[$categoryEmpikId] = $map;
        return $map;
    }

    private function empikCategoryDefinitions(string $categoryEmpikId): array
    {
        if ($categoryEmpikId === '') {
            return array();
        }

        if (isset($this->empikCategoryDefinitionsCache[$categoryEmpikId])) {
            return $this->empikCategoryDefinitionsCache[$categoryEmpikId];
        }

        try {
            $definitions = $this->empik->categoryAttributes($categoryEmpikId);
        } catch (\Throwable $exception) {
            $definitions = array();
        }

        $this->empikCategoryDefinitionsCache[$categoryEmpikId] = is_array($definitions) ? $definitions : array();
        return $this->empikCategoryDefinitionsCache[$categoryEmpikId];
    }

    private function allegroParameterDefinition(string $categoryAllegroId, string $parameterId): array
    {
        if ($categoryAllegroId === '' || $parameterId === '') {
            return array();
        }

        $definitions = $this->allegroCategoryDefinitions($categoryAllegroId);

        foreach ($definitions as $definition) {
            if (!is_array($definition) || empty($definition['id'])) {
                continue;
            }

            if ((string) $definition['id'] === $parameterId) {
                return $definition;
            }
        }

        return array();
    }

    private function formatAllegroParameterValue($value, array $definition = array()): string
    {
        if (is_array($value)) {
            $parts = array();
            foreach ($value as $item) {
                $formatted = $this->formatAllegroParameterValue($item, $definition);
                if ($formatted !== '') {
                    $parts[] = $formatted;
                }
            }

            return implode(', ', $parts);
        }

        if (is_bool($value)) {
            return $value ? 'Tak' : 'Nie';
        }

        if ($value === null) {
            return '';
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '';
        }

        $dictionary = isset($definition['dictionary']) && is_array($definition['dictionary']) ? $definition['dictionary'] : array();
        foreach ($dictionary as $option) {
            if (!is_array($option) || !isset($option['id'])) {
                continue;
            }

            if ((string) $option['id'] === $normalized) {
                return isset($option['value']) ? trim((string) $option['value']) : $normalized;
            }
        }

        return $normalized;
    }

    private function allegroCategoryDefinitions(string $categoryAllegroId): array
    {
        if ($categoryAllegroId === '') {
            return array();
        }

        if (isset($this->allegroCategoryDefinitionsCache[$categoryAllegroId])) {
            return $this->allegroCategoryDefinitionsCache[$categoryAllegroId];
        }

        try {
            $definitions = $this->allegro->categoryParameters($categoryAllegroId);
        } catch (\Throwable $exception) {
            $definitions = array();
        }

        $this->allegroCategoryDefinitionsCache[$categoryAllegroId] = is_array($definitions) ? $definitions : array();
        return $this->allegroCategoryDefinitionsCache[$categoryAllegroId];
    }

    private function generateTitleExportValue(array $product, array $exportOptions): string
    {
        $pattern = trim((string) ($exportOptions['title_template_pattern'] ?? ''));
        if ($pattern === '') {
            $pattern = 'Etui na Telefon {{dedicated_model}} {{dedicated_brand}} wzory {{collection}}';
        }

        $legacyReplacements = array(
            '{{dedicated_model}}' => $this->allegroParameterValueByNames($product, array('dedykowany model', 'model dedykowany', 'model')),
            '{{dedicated_brand}}' => $this->allegroParameterValueByNames($product, array('dedykowana marka', 'marka dedykowana', 'marka', 'producent')),
            '{{collection}}' => trim((string) ($exportOptions['collection_name'] ?? ($exportOptions['image_collection_name'] ?? ''))),
        );

        $pattern = strtr($pattern, $legacyReplacements);
        $resolved = preg_replace_callback('/\{\{?\s*(field|option)\s*:\s*([^}]+)\}?\}/i', function (array $matches) use ($product, $exportOptions) {
            $type = strtolower(trim((string) $matches[1]));
            $value = trim((string) $matches[2]);

            if ($type === 'field') {
                return $this->resolveTitleFieldExpression($product, $value, $exportOptions);
            }

            return isset($exportOptions[$value]) ? (string) $exportOptions[$value] : '';
        }, $pattern);

        return $this->cleanGeneratedTitle((string) $resolved);
    }

    private function resolveTitleFieldExpression(array $product, string $expression, array $exportOptions): string
    {
        $expression = trim($expression);
        if ($expression === '') {
            return '';
        }

        $format = '';
        $formatPos = strrpos($expression, '=');
        if ($formatPos !== false) {
            $format = trim(substr($expression, $formatPos + 1));
            $expression = trim(substr($expression, 0, $formatPos));
        }

        $fieldPath = $expression;
        $operationPos = strpos($expression, '+');
        $replacements = array();

        if ($operationPos !== false) {
            $fieldPath = trim(substr($expression, 0, $operationPos));
            $operation = trim(substr($expression, $operationPos + 1));
            if ($operation !== '') {
                foreach (explode('+', $operation) as $rule) {
                    $rule = trim((string) $rule);
                    if ($rule === '') {
                        continue;
                    }

                    $replaceParts = explode('-', $rule, 2);
                    $find = trim((string) ($replaceParts[0] ?? ''));
                    if ($find === '') {
                        continue;
                    }

                    $replacements[] = array(
                        'find' => $find,
                        'replace' => trim((string) ($replaceParts[1] ?? '')),
                    );
                }
            }
        }

        $resolved = $this->resolveField($product, $fieldPath, '|', $exportOptions);

        foreach ($replacements as $replacement) {
            $resolved = str_replace($replacement['find'], $replacement['replace'], $resolved);
        }

        return $this->applyTitleExpressionFormat($resolved, $format);
    }

    private function applyTitleExpressionFormat(string $value, string $format): string
    {
        $format = trim($format);
        if ($format === '') {
            return $value;
        }

        if ($format === 'upper') {
            return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
        }

        if ($format === 'lower') {
            return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        }

        if ($format === 'trim') {
            return trim($value);
        }

        if (strpos($format, 'date:') === 0) {
            $phpFormat = substr($format, 5);
            $timestamp = strtotime($value);

            return $timestamp !== false ? date($phpFormat !== '' ? $phpFormat : 'Y-m-d', $timestamp) : $value;
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

    private function allegroParameterValueByNames(array $product, array $candidateNames): string
    {
        $categoryAllegroId = isset($product['category_allegro_id']) ? (string) $product['category_allegro_id'] : '';
        $raw = isset($product['allegro_parameters_raw']) && is_array($product['allegro_parameters_raw']) ? $product['allegro_parameters_raw'] : array();
        if ($categoryAllegroId === '' || $raw === array()) {
            return '';
        }

        $normalizedCandidates = array();
        foreach ($candidateNames as $candidateName) {
            $normalized = $this->normalizeLabel((string) $candidateName);
            if ($normalized !== '') {
                $normalizedCandidates[$normalized] = true;
            }
        }

        foreach ($this->allegroCategoryDefinitions($categoryAllegroId) as $definition) {
            if (!is_array($definition) || empty($definition['id']) || !isset($definition['name'])) {
                continue;
            }

            $normalizedName = $this->normalizeLabel((string) $definition['name']);
            if ($normalizedName === '' || !isset($normalizedCandidates[$normalizedName])) {
                continue;
            }

            $parameterId = (string) $definition['id'];
            if (!array_key_exists($parameterId, $raw)) {
                continue;
            }

            return $this->formatAllegroParameterValue($raw[$parameterId], $definition);
        }

        return '';
    }

    private function cleanGeneratedTitle(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value));
        return trim((string) $value);
    }

    private function normalizeLabel(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($transliterated !== false) {
            $value = $transliterated;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        return trim((string) $value);
    }

    public function generateImageExportRows(array $product, array $exportOptions, array $settings = array()): array
    {
        $imageOptions = isset($settings['image_options']) && is_array($settings['image_options']) ? $settings['image_options'] : array();
        $productName = isset($product['product_name']) ? (string) $product['product_name'] : '';
        $collectionCode = strtoupper(trim((string) ($exportOptions['image_collection_code'] ?? ($imageOptions['image_collection_code'] ?? ''))));
        $collectionName = trim((string) ($exportOptions['collection_name'] ?? ''));
        $imageCount = max(0, (int) ($exportOptions['image_count'] ?? ($imageOptions['image_count'] ?? 0)));
        $thumbnailCount = max(0, (int) ($exportOptions['thumbnail_count'] ?? ($imageOptions['thumbnail_count'] ?? 0)));
        $mockupCount = max(0, (int) ($exportOptions['mockup_count'] ?? ($exportOptions['grid_count'] ?? ($imageOptions['mockup_count'] ?? 0))));
        $baseDirectory = trim((string) ($exportOptions['image_base_directory'] ?? ($imageOptions['image_base_directory'] ?? 'T:\\wygnerowane_do_EU')));
        $thumbnailMacro = trim((string) ($imageOptions['thumbnail_macro'] ?? ($exportOptions['thumbnail_macro'] ?? '{{base_directory}}\\{{contours}}\\{{collection_code}}\\miniatura_t_{{index}}.png')));
        $mockupMacro = trim((string) ($imageOptions['mockup_macro'] ?? ($exportOptions['mockup_macro'] ?? '{{base_directory}}\\{{contours}}\\mockup_{{index}}.jpg')));
        $imageMacro = trim((string) ($imageOptions['image_macro'] ?? ($exportOptions['image_macro'] ?? '{{base_directory}}\\{{contours}}\\{{collection_code}}\\{{index}}.jpg')));
        $thumbnailLines = array();
        $mockupLines = array();
        $imageLines = array();

        for ($i = 1; $i <= $thumbnailCount; $i++) {
            $path = $this->renderImageExportMacro($thumbnailMacro, $product, $exportOptions, $baseDirectory, $collectionCode, $collectionName, $productName, $i);
            if ($path !== '') {
                $thumbnailLines[] = $path;
            }
        }

        for ($i = 1; $i <= $mockupCount; $i++) {
            $path = $this->renderImageExportMacro($mockupMacro, $product, $exportOptions, $baseDirectory, $collectionCode, $collectionName, $productName, $i);
            if ($path !== '') {
                $mockupLines[] = $path;
            }
        }

        for ($i = 1; $i <= $imageCount; $i++) {
            $path = $this->renderImageExportMacro($imageMacro, $product, $exportOptions, $baseDirectory, $collectionCode, $collectionName, $productName, $i);
            if ($path !== '') {
                $imageLines[] = $path;
            }
        }

        $sharedLines = array_merge($mockupLines, $imageLines);
        $rows = array();
        $layout = isset($settings['image_layout']) && is_array($settings['image_layout']) ? $settings['image_layout'] : array();

        if ($layout === array()) {
            $layout = array(
                array('type' => 'thumbnail', 'value' => ''),
                array('type' => 'mockup', 'value' => ''),
                array('type' => 'image', 'value' => ''),
            );
        }

        if ($thumbnailLines !== array()) {
            foreach ($thumbnailLines as $thumbnailLine) {
                $rowLines = array();
                foreach ($layout as $layoutItem) {
                    $type = trim((string) ($layoutItem['type'] ?? ''));
                    if ($type === 'thumbnail') {
                        $rowLines[] = $thumbnailLine;
                        continue;
                    }

                    if ($type === 'mockup') {
                        $rowLines = array_merge($rowLines, $mockupLines);
                        continue;
                    }

                    if ($type === 'image') {
                        $rowLines = array_merge($rowLines, $imageLines);
                        continue;
                    }

                    if ($type === 'static') {
                        $staticValue = trim((string) ($layoutItem['value'] ?? ''));
                        if ($staticValue !== '') {
                            $rowLines[] = $staticValue;
                        }
                    }
                }

                $rowLines = array_values(array_filter($rowLines, static function (string $line): bool {
                    return trim($line) !== '';
                }));
                $rows[] = implode("\n", $rowLines);
            }

            return $rows;
        }

        if ($layout !== array()) {
            $rowLines = array();
            foreach ($layout as $layoutItem) {
                $type = trim((string) ($layoutItem['type'] ?? ''));
                if ($type === 'mockup') {
                    $rowLines = array_merge($rowLines, $mockupLines);
                    continue;
                }

                if ($type === 'image') {
                    $rowLines = array_merge($rowLines, $imageLines);
                    continue;
                }

                if ($type === 'static') {
                    $staticValue = trim((string) ($layoutItem['value'] ?? ''));
                    if ($staticValue !== '') {
                        $rowLines[] = $staticValue;
                    }
                }
            }

            $rowLines = array_values(array_filter($rowLines, static function (string $line): bool {
                return trim($line) !== '';
            }));
            if ($rowLines !== array()) {
                return array(implode("\n", $rowLines));
            }
        }

        if ($sharedLines !== array()) {
            return array(implode("\n", $sharedLines));
        }

        return array('');
    }

    public function generateImageExportAssetPaths(array $product, array $exportOptions, array $settings = array()): array
    {
        $imageOptions = isset($settings['image_options']) && is_array($settings['image_options']) ? $settings['image_options'] : array();
        $productName = isset($product['product_name']) ? (string) $product['product_name'] : '';
        $collectionCode = strtoupper(trim((string) ($exportOptions['image_collection_code'] ?? ($imageOptions['image_collection_code'] ?? ''))));
        $collectionName = trim((string) ($exportOptions['collection_name'] ?? ''));
        $imageCount = max(0, (int) ($exportOptions['image_count'] ?? ($imageOptions['image_count'] ?? 0)));
        $baseDirectory = trim((string) ($exportOptions['image_base_directory'] ?? ($imageOptions['image_base_directory'] ?? 'T:\\wygnerowane_do_EU')));
        $imageMacro = trim((string) ($imageOptions['image_macro'] ?? ($exportOptions['image_macro'] ?? '{{base_directory}}\\{{contours}}\\{{collection_code}}\\{{index}}.jpg')));

        $imageLines = array();
        for ($i = 1; $i <= $imageCount; $i++) {
            $path = $this->renderImageExportMacro($imageMacro, $product, $exportOptions, $baseDirectory, $collectionCode, $collectionName, $productName, $i);
            if ($path !== '') {
                $imageLines[] = $path;
            }
        }

        return $imageLines;
    }

    public function generateImageExportFlattenedLines(array $product, array $exportOptions, array $settings = array()): array
    {
        $rows = $this->generateImageExportRows($product, $exportOptions, $settings);
        $lines = array();

        foreach ($rows as $row) {
            if (!is_scalar($row)) {
                continue;
            }

            $items = preg_split('/\r\n|\r|\n/', (string) $row);
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                $item = trim((string) $item);
                if ($item === '') {
                    continue;
                }

                $lines[] = $item;
            }
        }

        return $lines;
    }

    private function generateImageExportLines(array $product, array $exportOptions): string
    {
        return implode("\n", $this->generateImageExportRows($product, $exportOptions));
    }

    private function renderCsvDescriptionTemplate(array $product, string $templateKey, array $exportOptions): string
    {
        $templateKey = trim($templateKey);
        if ($templateKey === '') {
            return '';
        }

        $templates = isset($exportOptions['csv_description_templates']) && is_array($exportOptions['csv_description_templates'])
            ? $exportOptions['csv_description_templates']
            : array();

        foreach ($templates as $template) {
            if (!is_array($template) || trim((string) ($template['key'] ?? '')) !== $templateKey) {
                continue;
            }

            return $this->buildCsvDescriptionHtml($product, $template, $exportOptions);
        }

        return '';
    }

    private function buildCsvDescriptionHtml(array $product, array $template, array $exportOptions): string
    {
        $sections = isset($template['sections']) && is_array($template['sections']) ? $template['sections'] : array();
        $html = array();

        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }

            $layout = trim((string) ($section['layout'] ?? 'text'));
            $sectionHtml = $this->buildCsvDescriptionSectionHtml($product, $section, $layout, $exportOptions);
            if ($sectionHtml !== '') {
                $html[] = $sectionHtml;
            }
        }

        if ($html === array()) {
            return '';
        }

        $style = '<style>'
            . '@media only screen and (max-width: 640px) {'
            . '.csv-desc-two-col,.csv-desc-two-col tbody,.csv-desc-two-col tr,.csv-desc-two-col td{display:block!important;width:100%!important;}'
            . '.csv-desc-two-col td{padding-left:0!important;padding-right:0!important;padding-bottom:12px!important;}'
            . '}'
            . '</style>';

        return $style . implode("\n", $html);
    }

    private function buildCsvDescriptionSectionHtml(array $product, array $section, string $layout, array $exportOptions): string
    {
        $leftImage = $this->csvDescriptionImageHtml($product, $section['left_image'] ?? array(), $exportOptions);
        $rightImage = $this->csvDescriptionImageHtml($product, $section['right_image'] ?? array(), $exportOptions);
        $text = $this->csvDescriptionTextHtml($product, (string) ($section['text'] ?? ''), $exportOptions);
        $leftText = $this->csvDescriptionTextHtml($product, (string) ($section['left_text'] ?? ''), $exportOptions);
        $rightText = $this->csvDescriptionTextHtml($product, (string) ($section['right_text'] ?? ''), $exportOptions);

        switch ($layout) {
            case 'image_image':
                return $this->csvDescriptionTwoColumnSection($leftImage, $rightImage);
            case 'image_text':
                return $this->csvDescriptionTwoColumnSection($leftImage, $rightText);
            case 'text_image':
                return $this->csvDescriptionTwoColumnSection($leftText, $rightImage);
            case 'text_text':
                return $this->csvDescriptionTwoColumnSection($leftText, $rightText);
            case 'text':
            default:
                return $this->csvDescriptionSingleColumnSection($text);
        }
    }

    private function csvDescriptionSingleColumnSection(string $content): string
    {
        $content = trim($content);
        if ($content === '') {
            return '';
        }

        return '<table role="presentation" style="width:100%;border-collapse:collapse;margin:0 0 16px 0;"><tr><td style="vertical-align:top;">'
            . $content
            . '</td></tr></table>';
    }

    private function csvDescriptionTwoColumnSection(string $leftContent, string $rightContent): string
    {
        $leftContent = trim($leftContent);
        $rightContent = trim($rightContent);
        if ($leftContent === '' && $rightContent === '') {
            return '';
        }

        return '<table role="presentation" class="csv-desc-two-col" style="width:100%;border-collapse:collapse;margin:0 0 16px 0;"><tr>'
            . '<td style="width:50%;vertical-align:top;padding-right:12px;">' . $leftContent . '</td>'
            . '<td style="width:50%;vertical-align:top;padding-left:12px;">' . $rightContent . '</td>'
            . '</tr></table>';
    }

    private function csvDescriptionTextHtml(array $product, string $template, array $exportOptions): string
    {
        $template = trim($template);
        if ($template === '') {
            return '';
        }

        $rendered = preg_replace_callback('/\{\{\s*(field|option):\s*([^}]+)\}\}/i', function (array $matches) use ($product, $exportOptions): string {
            $kind = strtolower(trim((string) ($matches[1] ?? 'field')));
            $token = trim((string) ($matches[2] ?? ''));
            if ($token === '') {
                return '';
            }

            if ($kind === 'option') {
                return (string) $this->resolveField($product, $token, '|', $exportOptions);
            }

            return (string) $this->resolveField($product, $token, '|', $exportOptions);
        }, $template);

        $escaped = htmlspecialchars((string) $rendered, ENT_QUOTES, 'UTF-8');
        return nl2br($escaped);
    }

    private function csvDescriptionImageHtml(array $product, $slot, array $exportOptions): string
    {
        $slot = is_array($slot) ? $slot : array();
        $source = trim((string) ($slot['source'] ?? ''));
        $index = max(1, (int) ($slot['index'] ?? 1));
        if ($source === '') {
            return '';
        }

        $imageUrl = '';
        if ($source === 'IMG_EU') {
            $settings = isset($exportOptions['csv_generated_images_settings']) && is_array($exportOptions['csv_generated_images_settings'])
                ? $exportOptions['csv_generated_images_settings']
                : array();
            $images = $this->generateImageExportFlattenedLines($product, $exportOptions, $settings);
            if (isset($images[$index - 1])) {
                $imageUrl = trim((string) $images[$index - 1]);
            }
        }

        if ($imageUrl === '') {
            return '';
        }

        return '<img src="' . htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') . '" alt="" style="display:block;max-width:100%;height:auto;">';
    }

    private function renderImageExportMacro(
        string $macro,
        array $product,
        array $exportOptions,
        string $baseDirectory,
        string $collectionCode,
        string $collectionName,
        string $productName,
        int $index
    ): string {
        $macro = trim($macro);
        if ($macro === '') {
            return '';
        }

        $contours = trim((string) ($product['contours'] ?? ''));
        $sku = trim((string) ($product['sku'] ?? ''));
        $oldSku = trim((string) (($product['custom_fields']['old_sku'] ?? '') ?: ($product['old_sku'] ?? '')));
        $price = trim((string) ($exportOptions['price_to_csv'] ?? ''));
        $slug = $this->slugForImagePath($productName);

        $rendered = strtr($macro, array(
            '{{base_directory}}' => rtrim($baseDirectory, '\\/'),
            '{{contours}}' => $contours,
            '{{collection_code}}' => $collectionCode,
            '{{collection_name}}' => $collectionName,
            '{{product_name}}' => $productName,
            '{{product_slug}}' => $slug,
            '{{sku}}' => $sku,
            '{{old_sku}}' => $oldSku,
            '{{price}}' => $price,
            '{{index}}' => (string) $index,
            '{{index0}}' => (string) max(0, $index - 1),
        ));

        $rendered = str_replace('/', '\\', $rendered);
        $rendered = preg_replace_callback('/\\\\+/', static function (): string {
            return '\\';
        }, $rendered);
        return trim((string) $rendered);
    }

    private function slugForImagePath(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($transliterated !== false) {
            $value = $transliterated;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);
        return trim((string) $value, '_');
    }

    private function slugForCollectionToken(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($transliterated !== false) {
            $value = $transliterated;
        }

        $value = strtoupper($value);
        return preg_replace('/[^A-Z0-9]+/', '', $value);
    }
}
