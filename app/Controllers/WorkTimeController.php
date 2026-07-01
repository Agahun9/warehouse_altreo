<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\UserRepository;
use App\Models\WorkTimeRepository;
use RuntimeException;
use Throwable;

class WorkTimeController extends Controller
{
    /** @var WorkTimeRepository */
    private $workTime;

    public function __construct()
    {
        $this->workTime = new WorkTimeRepository($this->db());
        $this->workTime->ensureSchema();
    }

    public function index(): void
    {
        $user = $this->requireAuth();
        $isManager = (int) ($user['id'] ?? 0) === 1;
        $month = $this->validMonth((string) $this->input('month', date('Y-m')));
        $selectedUserId = (int) ($user['id'] ?? 0);
        if ($isManager) {
            $requestedUserId = (int) $this->input('user_id', 0);
            $selectedUserId = $requestedUserId > 0 ? $requestedUserId : 0;
        }

        $users = array();
        if ($isManager) {
            $userRepository = new UserRepository($this->db());
            $userRepository->ensureSchema();
            $users = $userRepository->allUsers();
        }

        $scopeUserId = $isManager && $selectedUserId === 0 ? null : $selectedUserId;
        $entries = $this->workTime->entriesForMonth($month, $scopeUserId);
        $summaries = $this->workTime->monthlySummaries($month, $scopeUserId);
        $totalHours = 0.0;
        foreach ($summaries as $summary) {
            $totalHours += (float) ($summary['total_hours'] ?? 0);
        }

        $this->render('worktime/index', array(
            'pageTitle' => 'Czas pracy',
            'contentTitle' => 'Ewidencja czasu pracy',
            'pageDescription' => 'Godziny pracy, podsumowania miesieczne i historia zmian.',
            'currentUser' => $user,
            'isWorkTimeManager' => $isManager,
            'selectedMonth' => $month,
            'selectedUserId' => $selectedUserId,
            'users' => $users,
            'entries' => $entries,
            'summaries' => $summaries,
            'totalHours' => $totalHours,
            'auditLogs' => $this->workTime->auditLogs($isManager ? $scopeUserId : (int) $user['id'], 200),
        ));
    }

    public function save(): void
    {
        $user = $this->requireAuth();
        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=worktime&action=index');
        }

        $actorId = (int) ($user['id'] ?? 0);
        $isManager = $actorId === 1;
        $entryId = (int) $this->input('id', 0);
        $targetUserId = $isManager ? (int) $this->input('user_id', $actorId) : $actorId;
        $month = $this->validMonth((string) $this->input('return_month', date('Y-m')));

        try {
            if ($targetUserId <= 0) {
                throw new RuntimeException('Wybierz pracownika.');
            }
            if ($isManager) {
                $userRepository = new UserRepository($this->db());
                $userRepository->ensureSchema();
                if (!$userRepository->findById($targetUserId)) {
                    throw new RuntimeException('Wybrany pracownik nie istnieje.');
                }
            }
            $workDate = trim((string) $this->input('work_date', ''));
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $workDate);
            if (!$date || $date->format('Y-m-d') !== $workDate) {
                throw new RuntimeException('Podaj prawidlowa date pracy.');
            }
            $startTime = $this->validTime((string) $this->input('start_time', ''));
            $endTime = $this->validTime((string) $this->input('end_time', ''));
            $hours = $this->calculateHours($workDate, $startTime, $endTime);
            $note = trim((string) $this->input('note', ''));
            if (mb_strlen($note) > 500) {
                throw new RuntimeException('Notatka moze miec maksymalnie 500 znakow.');
            }
            $data = array(
                'user_id' => $targetUserId,
                'work_date' => $workDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'hours' => number_format($hours, 2, '.', ''),
                'note' => $note,
            );

            if ($entryId > 0) {
                $existing = $this->workTime->find($entryId);
                if (!is_array($existing) || (!$isManager && (int) $existing['user_id'] !== $actorId)) {
                    throw new RuntimeException('Nie masz uprawnien do edycji tego wpisu.');
                }
                if (!$isManager) {
                    $data['user_id'] = $actorId;
                }
                $this->workTime->updateEntry($entryId, $data, $actorId);
                $this->setFlash('success', 'Wpis czasu pracy zostal zmieniony. Zmiana trafila do dziennika.');
            } else {
                $this->workTime->create($data, $actorId);
                $this->setFlash('success', 'Czas pracy zostal zapisany: ' . number_format($hours, 2, ',', ' ') . ' h.');
            }
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $params = array('controller' => 'worktime', 'action' => 'index', 'month' => $month);
        if ($isManager) {
            $params['user_id'] = (int) $this->input('return_user_id', $targetUserId);
        }
        $this->redirect('./index.php?' . http_build_query($params));
    }

    public function delete(): void
    {
        $user = $this->requireAuth();
        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=worktime&action=index');
        }

        $actorId = (int) ($user['id'] ?? 0);
        $month = $this->validMonth((string) $this->input('return_month', date('Y-m')));
        $returnUserId = (int) $this->input('return_user_id', 0);

        try {
            if ($actorId !== 1) {
                throw new RuntimeException('Tylko administrator moze usuwac wpisy czasu pracy.');
            }
            $entryId = (int) $this->input('id', 0);
            if ($entryId <= 0) {
                throw new RuntimeException('Nie wybrano wpisu do usuniecia.');
            }
            $this->workTime->deleteEntry($entryId, $actorId);
            $this->setFlash('success', 'Wpis zostal usuniety i zapisany w dzienniku zmian.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $params = array('controller' => 'worktime', 'action' => 'index', 'month' => $month, 'user_id' => $returnUserId);
        $this->redirect('./index.php?' . http_build_query($params));
    }

    private function validMonth(string $month): string
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1 ? $month : date('Y-m');
    }

    private function validTime(string $time): string
    {
        $time = trim($time);
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time) !== 1) {
            throw new RuntimeException('Podaj godzine w formacie HH:MM.');
        }

        return $time . ':00';
    }

    private function calculateHours(string $workDate, string $startTime, string $endTime): float
    {
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $workDate . ' ' . $startTime);
        $end = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $workDate . ' ' . $endTime);
        if (!$start || !$end) {
            throw new RuntimeException('Nie udalo sie odczytac godzin pracy.');
        }
        if ($end <= $start) {
            throw new RuntimeException('Godzina wyjscia musi byc pozniejsza niz godzina przyjscia.');
        }

        $hours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
        if ($hours <= 0 || $hours > 24) {
            throw new RuntimeException('Czas pracy musi byc wiekszy od 0 i nie moze przekraczac 24 godzin.');
        }

        return round($hours, 2);
    }
}
