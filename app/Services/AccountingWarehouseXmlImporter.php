<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use SimpleXMLElement;

class AccountingWarehouseXmlImporter
{
    /** @var AccountingWarehouseClassifier */
    private $classifier;

    public function __construct(?AccountingWarehouseClassifier $classifier = null)
    {
        $this->classifier = $classifier ?? new AccountingWarehouseClassifier();
    }

    public function parseInvoiceXml(string $xmlContent, string $filename = ''): array
    {
        $xmlContent = trim($xmlContent);
        if ($xmlContent === '') {
            throw new RuntimeException('Plik XML jest pusty.');
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);
        if (!$xml instanceof SimpleXMLElement) {
            throw new RuntimeException('Nie udalo sie odczytac pliku XML faktury.');
        }

        $namespaces = $xml->getDocNamespaces(true);
        $defaultNamespace = isset($namespaces['']) ? (string) $namespaces[''] : '';
        if ($defaultNamespace !== '') {
            $xml->registerXPathNamespace('fa', $defaultNamespace);
        }

        $issueDate = $this->resolveHeaderValue($xml, array('//fa:Fa/fa:P_1'), array(array('Fa', 'P_1'), array('P_1')));
        $saleDate = $this->resolveHeaderValue($xml, array('//fa:Fa/fa:P_6'), array(array('Fa', 'P_6'), array('P_6')));
        $invoiceType = $this->resolveHeaderValue($xml, array('//fa:Fa/fa:RodzajFaktury'), array(array('Fa', 'RodzajFaktury'), array('RodzajFaktury')));
        $documentNumber = $this->resolveHeaderValue($xml, array('//fa:Fa/fa:P_2'), array(array('Fa', 'P_2'), array('P_2')));
        $totalNet = $this->decimalValue($xml, '//fa:Fa/fa:P_13_1') ?: $this->toDecimal($this->firstValueByPath($xml, array('Fa', 'P_13_1')));
        $totalGross = $this->decimalValue($xml, '//fa:Fa/fa:P_15') ?: $this->toDecimal($this->firstValueByPath($xml, array('Fa', 'P_15')));
        $isCorrection = $this->isCorrectionDocument($invoiceType, $documentNumber) || $this->hasNegativeInvoiceValue($xml, $totalNet, $totalGross);

        $header = array(
            'source_type' => 'xml',
            'document_kind' => $isCorrection ? 'adjustment' : 'receipt',
            'document_number' => $documentNumber,
            'supplier_name' => $this->resolveHeaderValue($xml, array('//fa:Podmiot1/fa:DaneIdentyfikacyjne/fa:Nazwa'), array(array('Podmiot1', 'DaneIdentyfikacyjne', 'Nazwa'))),
            'supplier_tax_id' => $this->resolveHeaderValue($xml, array('//fa:Podmiot1/fa:DaneIdentyfikacyjne/fa:NIP'), array(array('Podmiot1', 'DaneIdentyfikacyjne', 'NIP'))),
            'issue_date' => $issueDate,
            'sale_date' => $saleDate !== '' ? $saleDate : $issueDate,
            'receipt_date' => $saleDate !== '' ? $saleDate : $issueDate,
            'currency' => $this->resolveCurrency($xml, $documentNumber),
            'total_net' => $totalNet,
            'total_gross' => $totalGross,
            'notes' => '',
            'invoice_type' => $invoiceType,
            'xml_filename' => $filename,
            'xml_hash' => hash('sha256', $xmlContent),
            'xml_payload_base64' => base64_encode($xmlContent),
        );

        $lineNodes = $xml->xpath('//fa:Fa/fa:FaWiersz');
        if (!is_array($lineNodes) || $lineNodes === array()) {
            $lineNodes = $xml->xpath('//*[local-name()="FaWiersz"]');
        }
        if (!is_array($lineNodes) || $lineNodes === array()) {
            throw new RuntimeException($this->formatInvoiceXmlError('Faktura XML nie zawiera pozycji.', $filename, $documentNumber));
        }

        $lines = array();
        foreach ($lineNodes as $lineNode) {
            $originalName = $this->nodeValue($lineNode, $defaultNamespace, 'P_7');
            $quantity = $this->nodeDecimalValue($lineNode, $defaultNamespace, 'P_8B');
            $unit = $this->nodeValue($lineNode, $defaultNamespace, 'P_8A');
            $unit = $unit !== '' ? $unit : 'szt.';
            $lineGross = $this->nodeDecimalValue($lineNode, $defaultNamespace, 'P_11A');
            $lineNet = $this->nodeDecimalValue($lineNode, $defaultNamespace, 'P_11');
            $vatRate = $this->nodeDecimalValue($lineNode, $defaultNamespace, 'P_12');

            if ($quantity == 0.0) {
                $quantity = $this->nodeDecimalValue($lineNode, $defaultNamespace, 'P_8B');
            }

            if ($isCorrection && $lineNet < 0 && $quantity > 0) {
                $quantity *= -1;
            }
            if ($isCorrection && $lineGross < 0 && $quantity > 0) {
                $quantity *= -1;
            }

            $unitGross = $quantity !== 0.0 ? round($lineGross / $quantity, 2) : 0.0;
            $unitNet = $quantity !== 0.0 ? round($lineNet / $quantity, 2) : 0.0;
            $multiplier = 1 + ($vatRate / 100);

            if ($unitNet == 0.0 && $unitGross != 0.0) {
                $unitNet = $multiplier !== 0.0 ? round($unitGross / $multiplier, 2) : $unitGross;
            }
            if ($unitGross == 0.0 && $unitNet != 0.0) {
                $unitGross = round($unitNet * $multiplier, 2);
            }
            if ($lineNet == 0.0 && $unitNet != 0.0 && $quantity != 0.0) {
                $lineNet = round($unitNet * $quantity, 2);
            }
            if ($lineGross == 0.0 && $unitGross != 0.0 && $quantity != 0.0) {
                $lineGross = round($unitGross * $quantity, 2);
            }

            $classification = $this->classifier->classify($originalName);

            $lines[] = array(
                'original_name' => $originalName,
                'canonical_name' => (string) ($classification['canonical_name'] ?? 'pozostale'),
                'quantity' => $quantity,
                'unit' => $unit,
                'unit_net' => $unitNet,
                'unit_gross' => $unitGross,
                'line_net' => $lineNet,
                'line_gross' => $lineGross,
                'vat_rate' => $vatRate,
                'classification_confidence' => (string) ($classification['confidence'] ?? 'low'),
                'classification_rule' => (string) ($classification['rule'] ?? ''),
            );
        }

        return array(
            'header' => $header,
            'lines' => $lines,
        );
    }

    private function stringValue(SimpleXMLElement $xml, string $xpath): string
    {
        $nodes = $xml->xpath($xpath);
        if (!is_array($nodes) || !isset($nodes[0])) {
            return '';
        }

        return trim((string) $nodes[0]);
    }

    private function resolveHeaderValue(SimpleXMLElement $xml, array $xpaths, array $paths): string
    {
        foreach ($xpaths as $xpath) {
            $value = $this->stringValue($xml, $xpath);
            if ($value !== '') {
                return $value;
            }
        }

        foreach ($paths as $path) {
            $value = $this->firstValueByPath($xml, $path);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function decimalValue(SimpleXMLElement $xml, string $xpath): float
    {
        return $this->toDecimal($this->stringValue($xml, $xpath));
    }

    private function nodeDecimalValue(SimpleXMLElement $lineNode, string $namespace, string $field): float
    {
        return $this->toDecimal($this->nodeValue($lineNode, $namespace, $field));
    }

    private function nodeValue(SimpleXMLElement $lineNode, string $namespace, string $field): string
    {
        $directValue = trim((string) ($lineNode->children($namespace)->{$field} ?? ''));
        if ($directValue !== '') {
            return $directValue;
        }

        $nodes = $lineNode->xpath('./*[local-name()="' . $field . '"]');
        if (is_array($nodes) && isset($nodes[0])) {
            return trim((string) $nodes[0]);
        }

        return '';
    }

    private function firstValueByLocalName(SimpleXMLElement $xml, string $field): string
    {
        $nodes = $xml->xpath('//*[local-name()="' . $field . '"]');
        if (!is_array($nodes) || !isset($nodes[0])) {
            return '';
        }

        return trim((string) $nodes[0]);
    }

    private function firstValueByPath(SimpleXMLElement $xml, array $path): string
    {
        $xpath = '//*';
        foreach ($path as $segment) {
            $xpath .= '/*[local-name()="' . $segment . '"]';
        }

        $nodes = $xml->xpath($xpath);
        if (!is_array($nodes) || !isset($nodes[0])) {
            return '';
        }

        return trim((string) $nodes[0]);
    }

    private function toDecimal(string $value): float
    {
        $value = trim(str_replace(',', '.', $value));
        if ($value === '') {
            return 0.0;
        }

        return (float) $value;
    }

    private function isCorrectionDocument(string $invoiceType, string $documentNumber): bool
    {
        $rawDocumentNumber = strtoupper(trim($documentNumber));
        $invoiceType = $this->classifier->normalize($invoiceType);
        $documentNumber = $this->classifier->normalize($documentNumber);

        if ($invoiceType !== '' && ($invoiceType === 'kor' || strpos($invoiceType, 'kor') !== false || strpos($invoiceType, 'korekta') !== false || strpos($invoiceType, 'korygujaca') !== false)) {
            return true;
        }

        if ($rawDocumentNumber !== '' && preg_match('/(?:^|[\/\-\s])K(?:$|[\/\-\s])/', $rawDocumentNumber) === 1) {
            return true;
        }

        return $documentNumber !== '' && (strpos($documentNumber, 'korekta') !== false || strpos($documentNumber, 'kor') !== false);
    }

    private function hasNegativeInvoiceValue(SimpleXMLElement $xml, float $totalNet, float $totalGross): bool
    {
        if ($totalNet < 0 || $totalGross < 0) {
            return true;
        }

        $nodes = $xml->xpath('//*[not(*)]');
        if (!is_array($nodes)) {
            return false;
        }

        foreach ($nodes as $node) {
            $value = trim((string) $node);
            if ($value === '') {
                continue;
            }

            $normalizedValue = str_replace(array(' ', "\xc2\xa0"), '', str_replace(array(',', '−'), array('.', '-'), $value));
            if (preg_match('/^-?\d+(?:\.\d+)?$/', $normalizedValue) === 1 && (float) $normalizedValue < 0) {
                return true;
            }
        }

        return false;
    }

    private function resolveCurrency(SimpleXMLElement $xml, string $documentNumber): string
    {
        $currency = strtoupper(trim($this->resolveHeaderValue($xml, array('//fa:Fa/fa:KodWaluty'), array(array('Fa', 'KodWaluty'), array('KodWaluty')))));
        if ($currency !== '') {
            return $currency;
        }

        if (preg_match('/(?:^|\/)([A-Z]{3})(?:\/|$)/', strtoupper($documentNumber), $matches) === 1) {
            return $matches[1];
        }

        return 'PLN';
    }

    private function formatInvoiceXmlError(string $message, string $filename = '', string $documentNumber = ''): string
    {
        $details = array();
        $filename = trim($filename);
        $documentNumber = trim($documentNumber);

        if ($filename !== '') {
            $details[] = 'plik: ' . $filename;
        }
        if ($documentNumber !== '') {
            $details[] = 'numer faktury: ' . $documentNumber;
        }

        if ($details === array()) {
            return $message;
        }

        return $message . ' (' . implode(', ', $details) . ')';
    }
}
