<?php

declare(strict_types=1);

namespace App\Services;

class TemplateParser
{
    public function parse(array $template): array
    {
        $columns = isset($template['columns']) && is_array($template['columns']) ? $template['columns'] : array();

        $parsedColumns = array();
        foreach ($columns as $column) {
            $settings = isset($column['settings']) && is_array($column['settings']) ? $column['settings'] : array();
            $mappings = isset($column['mappings']) && is_array($column['mappings']) ? $column['mappings'] : array();

            $parsedColumns[] = array(
                'header_name' => isset($column['header_name']) ? (string) $column['header_name'] : '',
                'source_type' => isset($column['source_type']) ? (string) $column['source_type'] : 'field',
                'source_value' => isset($column['source_value']) ? (string) $column['source_value'] : '',
                'settings' => $this->normalizeSettings($settings),
                'mappings' => $this->normalizeMappings($mappings),
            );
        }

        return array(
            'id' => isset($template['id']) ? (int) $template['id'] : 0,
            'name' => isset($template['name']) ? (string) $template['name'] : '',
            'delimiter' => isset($template['delimiter']) ? (string) $template['delimiter'] : ';',
            'encoding' => isset($template['encoding']) ? (string) $template['encoding'] : 'UTF-8',
            'add_bom' => isset($template['add_bom']) ? (int) $template['add_bom'] : 1,
            'array_separator' => isset($template['array_separator']) ? (string) $template['array_separator'] : '|',
            'description_templates' => isset($template['description_templates']) && is_array($template['description_templates']) ? $template['description_templates'] : array(),
            'columns' => $parsedColumns,
        );
    }

    private function normalizeSettings(array $settings): array
    {
        $condition = isset($settings['condition']) && is_array($settings['condition']) ? $settings['condition'] : array();
        $conditions = isset($settings['conditions']) && is_array($settings['conditions']) ? $settings['conditions'] : array();
        $args = isset($settings['args']) && is_array($settings['args']) ? $settings['args'] : array();
        $imageLayout = isset($settings['image_layout']) && is_array($settings['image_layout']) ? $settings['image_layout'] : array();
        $imageOptions = isset($settings['image_options']) && is_array($settings['image_options']) ? $settings['image_options'] : array();

        return array(
            'function' => isset($settings['function']) ? (string) $settings['function'] : '',
            'args' => $args,
            'format' => isset($settings['format']) ? (string) $settings['format'] : '',
            'array_separator' => isset($settings['array_separator']) ? (string) $settings['array_separator'] : '',
            'image_layout' => $imageLayout,
            'image_options' => $imageOptions,
            'condition' => array(
                'field' => isset($condition['field']) ? (string) $condition['field'] : '',
                'operator' => isset($condition['operator']) ? (string) $condition['operator'] : 'eq',
                'value' => array_key_exists('value', $condition) ? $condition['value'] : '',
                'then' => array_key_exists('then', $condition) ? $condition['then'] : '',
                'else' => array_key_exists('else', $condition) ? $condition['else'] : '',
            ),
            'conditions' => $conditions,
            'condition_else' => array_key_exists('condition_else', $settings)
                ? (string) $settings['condition_else']
                : (array_key_exists('else', $condition) ? (string) $condition['else'] : ''),
        );
    }

    private function normalizeMappings(array $mappings): array
    {
        $out = array();

        foreach ($mappings as $mapping) {
            $from = isset($mapping['from_value']) ? (string) $mapping['from_value'] : (isset($mapping['from']) ? (string) $mapping['from'] : '');
            $to = isset($mapping['to_value']) ? (string) $mapping['to_value'] : (isset($mapping['to']) ? (string) $mapping['to'] : '');

            if ($from === '') {
                continue;
            }

            $out[$from] = $to;
        }

        return $out;
    }
}
