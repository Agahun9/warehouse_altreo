<?php

declare(strict_types=1);
namespace App\Services\Messages;

use App\Models\MessageRepository;

/**
 * Źródło wiadomości jednego marketplace. Wątki przekazywane do $emit mają format
 * MessageRepository::ingest(): kind, external_id, subject, customer_name, customer_login,
 * order_external_id, remote_status, needs_reply, closed, last_message_at, due_at, meta,
 * messages (lista: external_id, author_role customer|seller|operator|system, author_name, body,
 * attachments, created_at) albo null, gdy wiadomości się nie zmieniły.
 */
interface MessageSource
{
    /**
     * Pobiera zmiany od ostatniej synchronizacji. Zwraca nowy stan połączenia; klucze specjalne:
     * `_errors` (lista błędów częściowych), `_open` ([kind => lista external_id otwartych po stronie
     * marketplace] – lokalne wątki spoza listy zostaną zamknięte).
     */
    public function fetch(array $account, array $settings, array $state, MessageRepository $repo, callable $emit): array;

    /** Wysyła odpowiedź w wątku; zwraca identyfikator wiadomości w marketplace (lub pusty). */
    public function reply(array $account, array $thread, string $text, array $options, MessageRepository $repo): string;
}
