<?php

declare(strict_types=1);
namespace App\Services\Messages;

use App\Models\MessageRepository;
use App\Services\AllegroService;
use App\Services\EmpikService;
use App\Services\MediaMarktService;
use App\Services\WooCommerceService;
use InvalidArgumentException;

/**
 * Wątki z danych zaimportowanych zamówień (bez dodatkowych zapytań do API):
 *  note   – uwaga/wiadomość kupującego przy zakupie (każdy kanał),
 *  return – zwrot zgłoszony w ERLI (returns[] w zamówieniu: powód, pozycje, komentarz kupującego).
 * Odpowiedź tylko tam, gdzie kanał ma do tego API: Allegro (nowa wiadomość do kupującego),
 * Empik/MediaMarkt (wątek na zamówieniu OR43), WooCommerce (notatka dla klienta – sklep wysyła e-mail).
 */
final class OrderNotesSource implements MessageSource
{
    public const ERLI_RETURN_REASONS = [
        'resign' => 'Rezygnacja z zakupu', 'mistake' => 'Zakup przez pomyłkę', 'itemsQuality' => 'Przedmiot wadliwy lub uszkodzony',
        'itemsDescription' => 'Niezgodny z opisem', 'deliveryQuality' => 'Uszkodzony w transporcie', 'other' => 'Inny powód',
        'doesNotFit' => 'Nie pasuje', 'itemIsMissing' => 'Brakuje elementu', 'notDeliveredOnTime' => 'Niedostarczony na czas',
        'itemDamaged' => 'Przedmiot uszkodzony (paczka cała)', 'itemAndPackageDamaged' => 'Przedmiot i paczka uszkodzone',
    ];

    /** Kanały, w których na uwagę do zamówienia można odpowiedzieć przez API. */
    public const REPLY_PLATFORMS = ['allegro', 'empik', 'mediamarkt', 'woocommerce'];

    /** @var array<string,object> */
    private $services;

    public function __construct(array $services = []) { $this->services = $services; }

    public static function canReply(string $platform, string $kind): bool
    {
        return $kind === 'note' && in_array($platform, self::REPLY_PLATFORMS, true);
    }

    public function fetch(array $account, array $settings, array $state, MessageRepository $repo, callable $emit): array
    {
        $platform = (string) $account['platform'];
        $notes = !empty($settings['sync_notes']);
        $returns = $platform === 'erli' && !empty($settings['sync_returns']);
        if (!$notes && !$returns) { return $state; }
        $since = (string) ($state['orders_since'] ?? gmdate('Y-m-d H:i:s', time() - (int) $settings['history_days'] * 86400));
        $newest = $since;
        $rows = $repo->db()->fetchAll(
            'SELECT o.id,o.external_id,o.buyer_name,o.email,o.ordered_at,o.updated_at,o.details_json FROM om_orders o JOIN om_accounts a ON a.id=o.account_id
             WHERE a.platform=:p AND a.source_id=:s AND o.updated_at>=:since ORDER BY o.updated_at LIMIT 500',
            ['p' => $platform, 's' => (int) $account['connection_id'], 'since' => $since]
        );
        foreach ($rows as $order) {
            $newest = max($newest, (string) $order['updated_at']);
            $details = json_decode((string) $order['details_json'], true) ?: [];
            $raw = (array) ($details['raw'] ?? []);
            if ($notes) {
                $note = trim((string) ($details['buyer_note'] ?? ''));
                if ($note !== '') { $emit($this->noteThread($platform, $order, $raw, $note)); }
            }
            if ($returns && !empty($raw['returns'])) { $emit($this->returnThread($order, $raw)); }
        }
        $state['orders_since'] = $newest;
        return $state;
    }

    private function noteThread(string $platform, array $order, array $raw, string $note): array
    {
        $login = (string) ($raw['buyer']['login'] ?? '');
        return [
            'kind' => 'note', 'external_id' => 'order:'.$order['external_id'], 'subject' => 'Uwaga do zamówienia '.self::number($platform, $order, $raw),
            'customer_name' => (string) $order['buyer_name'], 'customer_login' => $login, 'order_external_id' => (string) $order['external_id'],
            'remote_status' => 'NOTE', 'needs_reply' => true, 'closed' => false, 'last_message_at' => (string) $order['ordered_at'],
            'meta' => ['buyer_login' => $login, 'email' => (string) $order['email'], 'read_only' => !self::canReply($platform, 'note')],
            'messages' => [['external_id' => 'note:'.sha1($note), 'author_role' => 'customer', 'author_name' => (string) ($order['buyer_name'] ?: $login), 'body' => $note, 'created_at' => (string) $order['ordered_at']]],
        ];
    }

    private function returnThread(array $order, array $raw): array
    {
        $messages = []; $reasons = []; $last = '';
        foreach ((array) $raw['returns'] as $index => $return) {
            if (!is_array($return)) { continue; }
            $reason = self::ERLI_RETURN_REASONS[$return['reason'] ?? ''] ?? (string) ($return['reason'] ?? 'nie podano');
            $reasons[] = $reason;
            $items = [];
            foreach ((array) ($return['items'] ?? []) as $item) {
                $line = (array) ($raw['items'][(int) ($item['index'] ?? -1)] ?? []);
                $items[] = trim((string) ($line['name'] ?? 'pozycja '.((int) ($item['index'] ?? 0) + 1))).' × '.(int) ($item['quantity'] ?? 1);
            }
            $comment = trim((string) ($return['comment'] ?? ''));
            $created = (string) ($return['created'] ?? $order['updated_at']);
            $last = max($last, MessageRepository::date($created));
            $messages[] = [
                'external_id' => 'return:'.$index.':'.$created, 'author_role' => 'customer', 'author_name' => (string) $order['buyer_name'],
                'body' => 'Zgłoszono zwrot. Powód: '.$reason.'.'.($items ? "\nPozycje: ".implode(', ', $items).'.' : '').($comment !== '' ? "\nKomentarz kupującego: ".$comment : '')
                    .(!empty($return['bankAccount']['number']) ? "\nKonto do zwrotu: ".$return['bankAccount']['number'].(!empty($return['bankAccount']['name']) ? ' ('.$return['bankAccount']['name'].')' : '') : ''),
                'created_at' => $created,
            ];
        }
        return [
            'kind' => 'return', 'external_id' => 'order:'.$order['external_id'], 'subject' => 'Zwrot: '.implode(', ', array_unique($reasons)),
            'customer_name' => (string) $order['buyer_name'], 'order_external_id' => (string) $order['external_id'], 'remote_status' => 'RETURN',
            'needs_reply' => true, 'closed' => false, 'last_message_at' => $last, 'meta' => ['read_only' => true, 'email' => (string) $order['email']], 'messages' => $messages,
        ];
    }

    private static function number(string $platform, array $order, array $raw): string
    {
        $number = (string) ($raw['number'] ?? $raw['reference'] ?? $raw['commercial_id'] ?? $order['external_id']);
        return mb_strlen($number, 'UTF-8') > 24 ? mb_substr($number, 0, 8, 'UTF-8').'…' : $number;
    }

    public function reply(array $account, array $thread, string $text, array $options, MessageRepository $repo): string
    {
        if (!self::canReply((string) $thread['platform'], (string) $thread['kind'])) {
            throw new InvalidArgumentException(($thread['kind'] === 'return' ? 'Zwroty ERLI obsłuż w panelu ERLI' : 'Ten kanał nie udostępnia wysyłania wiadomości przez API').' – oznacz wątek jako obsłużony po odpowiedzi innym kanałem.');
        }
        switch ($thread['platform']) {
            case 'allegro':
                $login = (string) ($thread['meta']['buyer_login'] ?? $thread['customer_login']);
                if ($login === '') { throw new InvalidArgumentException('Brak loginu kupującego Allegro w zamówieniu.'); }
                if (mb_strlen($text, 'UTF-8') > 2000) { throw new InvalidArgumentException('Allegro przyjmuje wiadomości do 2000 znaków.'); }
                $service = $this->services['allegro'] ?? new AllegroService(true);
                $response = $service->api($account, 'POST', '/messaging/messages', [], ['recipient' => ['login' => $login], 'text' => $text, 'order' => ['id' => (string) $thread['order_external_id']]]);
                return (string) ($response['id'] ?? '');
            case 'empik':
            case 'mediamarkt':
                $mirakl = new MiraklMessages($this->services[$thread['platform']] ?? ($thread['platform'] === 'empik' ? new EmpikService() : new MediaMarktService()));
                return $mirakl->replyOnOrder($account, $thread, $text, 'Uwaga do zamówienia', $repo);
            case 'woocommerce':
                $service = $this->services['woocommerce'] ?? new WooCommerceService();
                $response = $service->addOrderNote($account, (string) $thread['order_external_id'], $text, true);
                return (string) ($response['id'] ?? '');
        }
        throw new InvalidArgumentException('Brak API odpowiedzi dla tego kanału.');
    }
}
