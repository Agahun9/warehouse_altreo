<?php

declare(strict_types=1);
namespace App\Services\Messages;

use App\Models\MessageRepository;
use App\Services\PrestaShopService;
use InvalidArgumentException;

/**
 * PrestaShop – Obsługa klienta (formularz kontaktowy, wiadomości do zamówień) przez webservice:
 * customer_threads (status open/closed/pending1/pending2) i customer_messages (id_employee>0 = odpowiedź sklepu).
 * Klucz webservice potrzebuje uprawnień GET do customer_threads, customer_messages, customers i POST do customer_messages.
 * Uwaga: zapis przez API nie wysyła e-maila – klient widzi odpowiedź w historii wiadomości konta/zamówienia.
 */
final class PrestaShopMessages implements MessageSource
{
    public const STATUSES = ['open' => 'Otwarty', 'closed' => 'Zamknięty', 'pending1' => 'Oczekujący 1', 'pending2' => 'Oczekujący 2'];
    /** Webservice PrestaShop zwraca daty w strefie sklepu (dla polskich sklepów – czas polski). */
    private const ZONE = 'Europe/Warsaw';

    /** @var PrestaShopService */
    private $api;
    /** @var array<string,string> */
    private $customers = [];

    public function __construct(?PrestaShopService $api = null) { $this->api = $api ?? new PrestaShopService(); }

    public function fetch(array $account, array $settings, array $state, MessageRepository $repo, callable $emit): array
    {
        $state['_errors'] = [];
        if (empty($settings['sync_messages'])) { return $state; }
        $connectionId = (int) $account['connection_id'];
        $sinceUtc = (string) ($state['threads_since'] ?? gmdate('Y-m-d H:i:s', time() - (int) $settings['history_days'] * 86400));
        $sinceLocal = (new \DateTimeImmutable($sinceUtc, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone(self::ZONE))->format('Y-m-d H:i:s');
        $newest = $sinceUtc;
        for ($offset = 0; $offset < 500; $offset += 100) {
            $threads = self::rows($this->api->webserviceGet($account, 'customer_threads', [
                'display' => 'full', 'filter[date_upd]' => '['.$sinceLocal.',2999-12-31 23:59:59]', 'date' => '1', 'sort' => '[date_upd_ASC]', 'limit' => $offset.',100',
            ]), 'customer_threads');
            foreach ($threads as $thread) {
                $id = (string) ($thread['id'] ?? '');
                if ($id === '') { continue; }
                $updated = MessageRepository::date($thread['date_upd'] ?? '', self::ZONE);
                $newest = max($newest, $updated);
                $local = $repo->findByExternal($connectionId, 'message', $id);
                if ($local && (string) $local['remote_status'] === (string) ($thread['status'] ?? '') && (string) $local['last_message_at'] >= $updated) { continue; }
                $emit($this->mapThread($account, $thread));
            }
            if (count($threads) < 100) { break; }
        }
        $state['threads_since'] = $newest;
        return $state;
    }

    private static function rows(array $response, string $key): array
    {
        $rows = $response[$key] ?? [];
        return array_values(array_filter(is_array($rows) ? $rows : [], 'is_array'));
    }

    public function mapThread(array $account, array $thread): array
    {
        $id = (string) $thread['id'];
        $raw = self::rows($this->api->webserviceGet($account, 'customer_messages', ['display' => 'full', 'filter[id_customer_thread]' => '['.$id.']', 'sort' => '[date_add_ASC]']), 'customer_messages');
        $customer = $this->customerName($account, (int) ($thread['id_customer'] ?? 0)) ?: (string) ($thread['email'] ?? '');
        $messages = [];
        $lastPublicRole = '';
        foreach ($raw as $message) {
            $private = !empty($message['private']) && (string) $message['private'] !== '0';
            $role = $private ? 'system' : ((int) ($message['id_employee'] ?? 0) > 0 ? 'seller' : 'customer');
            if (!$private) { $lastPublicRole = $role; }
            $messages[] = [
                'external_id' => (string) ($message['id'] ?? ''), 'author_role' => $role,
                'author_name' => $private ? 'Notatka prywatna sklepu' : ($role === 'seller' ? 'Sklep' : $customer),
                'body' => MoreleMessages::text((string) ($message['message'] ?? '')),
                'attachments' => trim((string) ($message['file_name'] ?? '')) !== '' ? [['name' => (string) $message['file_name']]] : [],
                'created_at' => MessageRepository::date($message['date_add'] ?? '', self::ZONE),
            ];
        }
        $status = (string) ($thread['status'] ?? 'open');
        $orderId = (int) ($thread['id_order'] ?? 0);
        return [
            'kind' => 'message', 'external_id' => $id,
            'subject' => $orderId > 0 ? 'Wiadomość do zamówienia #'.$orderId : 'Wiadomość z formularza kontaktowego',
            'customer_name' => $customer, 'customer_login' => (string) ($thread['email'] ?? ''), 'order_external_id' => $orderId > 0 ? (string) $orderId : '',
            'remote_status' => $status, 'needs_reply' => $status !== 'closed' && $lastPublicRole === 'customer', 'closed' => $status === 'closed',
            'last_message_at' => MessageRepository::date($thread['date_upd'] ?? '', self::ZONE),
            'meta' => ['email' => (string) ($thread['email'] ?? ''), 'contact_id' => (int) ($thread['id_contact'] ?? 0), 'product_id' => (int) ($thread['id_product'] ?? 0), 'ps_status' => self::STATUSES[$status] ?? $status],
            'messages' => $messages,
        ];
    }

    private function customerName(array $account, int $customerId): string
    {
        if ($customerId < 1) { return ''; }
        $key = $account['connection_id'].'|'.$customerId;
        if (!array_key_exists($key, $this->customers)) {
            try {
                $customer = (array) ($this->api->webserviceGet($account, 'customers/'.$customerId)['customer'] ?? []);
                $this->customers[$key] = trim((string) ($customer['firstname'] ?? '').' '.(string) ($customer['lastname'] ?? ''));
            } catch (\Throwable $e) { $this->customers[$key] = ''; }
        }
        return $this->customers[$key];
    }

    public function reply(array $account, array $thread, string $text, array $options, MessageRepository $repo): string
    {
        if (!ctype_digit((string) $thread['external_id'])) { throw new InvalidArgumentException('Nieprawidłowy wątek PrestaShop.'); }
        $employee = (int) ($repo->platformSettings('prestashop')['employee_id'] ?? 1);
        $cdata = static function (string $value): string { return '<![CDATA['.str_replace(']]>', ']]]]><![CDATA[>', $value).']]>'; };
        $xml = '<?xml version="1.0" encoding="UTF-8"?><prestashop xmlns:xlink="http://www.w3.org/1999/xlink"><customer_message>'
            .'<id_employee>'.$employee.'</id_employee><id_customer_thread>'.(int) $thread['external_id'].'</id_customer_thread>'
            .'<message>'.$cdata($text).'</message><private>0</private><read>1</read></customer_message></prestashop>';
        $response = $this->api->webserviceCreate($account, 'customer_messages', $xml);
        return (string) ($response['customer_message']['id'] ?? '');
    }
}
