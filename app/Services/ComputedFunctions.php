<?php

declare(strict_types=1);

namespace App\Services;

class ComputedFunctions
{
    public function apply(string $functionName, array $args, array $context)
    {
        switch (strtolower($functionName)) {
            case 'concat':
                return $this->concat($args, $context);
            case 'upper':
                return $this->upper($args, $context);
            case 'lower':
                return $this->lower($args, $context);
            case 'ucfirst':
            case 'capitalize':
                return $this->ucfirst($args, $context);
            case 'substring':
                return $this->substring($args, $context);
            case 'replace':
                return $this->replace($args, $context);
            case 'number_format':
                return $this->numberFormat($args, $context);
            case 'custom':
                return $this->custom($args, $context);
            default:
                return '';
        }
    }

    private function concat(array $args, array $context): string
    {
        $separator = isset($args['separator']) ? (string) $args['separator'] : '';
        $parts = isset($args['parts']) && is_array($args['parts']) ? $args['parts'] : array();

        $resolved = array();
        foreach ($parts as $part) {
            $resolved[] = $this->resolveToken($part, $context);
        }

        return implode($separator, array_filter($resolved, function ($item) {
            return $item !== '';
        }));
    }

    private function upper(array $args, array $context): string
    {
        $value = $this->resolveToken(isset($args['value']) ? $args['value'] : '', $context);
        return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
    }

    private function lower(array $args, array $context): string
    {
        $value = $this->resolveToken(isset($args['value']) ? $args['value'] : '', $context);
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private function ucfirst(array $args, array $context): string
    {
        $value = $this->resolveToken(isset($args['value']) ? $args['value'] : '', $context);
        if ($value === '') {
            return '';
        }
        if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
            return mb_strtoupper(mb_substr($value, 0, 1, 'UTF-8'), 'UTF-8')
                . mb_substr($value, 1, null, 'UTF-8');
        }
        return ucfirst($value);
    }

    private function substring(array $args, array $context): string
    {
        $value = $this->resolveToken(isset($args['value']) ? $args['value'] : '', $context);
        $start = isset($args['start']) ? (int) $args['start'] : 0;
        $length = isset($args['length']) ? (int) $args['length'] : null;

        if ($length === null || $length <= 0) {
            return function_exists('mb_substr') ? mb_substr($value, $start, null, 'UTF-8') : substr($value, $start);
        }

        return function_exists('mb_substr') ? mb_substr($value, $start, $length, 'UTF-8') : substr($value, $start, $length);
    }

    private function replace(array $args, array $context): string
    {
        $value = $this->resolveToken(isset($args['value']) ? $args['value'] : '', $context);
        $search = isset($args['search']) ? (string) $args['search'] : '';
        $replace = isset($args['replace']) ? (string) $args['replace'] : '';

        return str_replace($search, $replace, $value);
    }

    private function numberFormat(array $args, array $context): string
    {
        $value = (float) $this->resolveToken(isset($args['value']) ? $args['value'] : '', $context);
        $decimals = isset($args['decimals']) ? (int) $args['decimals'] : 2;
        $decimalPoint = isset($args['decimal_point']) ? (string) $args['decimal_point'] : ',';
        $thousandsSeparator = isset($args['thousands_separator']) ? (string) $args['thousands_separator'] : ' ';

        return number_format($value, $decimals, $decimalPoint, $thousandsSeparator);
    }

    private function custom(array $args, array $context)
    {
        $callback = isset($args['callback']) ? (string) $args['callback'] : '';
        $callable = $this->resolveCallback($callback);

        if ($callable === null) {
            return '';
        }

        return (string) call_user_func($callable, $context, $args);
    }

    private function resolveToken($token, array $context): string
    {
        if (!is_string($token)) {
            return (string) $token;
        }

        if (strncmp($token, 'field:', 6) === 0) {
            $path = substr($token, 6);
            if (isset($context['field_resolver']) && is_callable($context['field_resolver'])) {
                return (string) call_user_func(
                    $context['field_resolver'],
                    $context['product'],
                    $path,
                    $context['array_separator'],
                    isset($context['export_options']) && is_array($context['export_options']) ? $context['export_options'] : array()
                );
            }

            return isset($context[$path]) ? (string) $context[$path] : '';
        }

        return $token;
    }

    private function resolveCallback(string $callback)
    {
        $callback = trim($callback);
        if ($callback === '') {
            return null;
        }

        if (strpos($callback, '::') !== false) {
            $parts = explode('::', $callback, 2);
            if (count($parts) === 2 && is_callable(array($parts[0], $parts[1]))) {
                return array($parts[0], $parts[1]);
            }
        }

        if (is_callable($callback)) {
            return $callback;
        }

        return null;
    }
}
