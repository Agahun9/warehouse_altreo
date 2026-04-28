<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\SmartyFactory;
use App\Models\TaskboardRepository;
use App\Models\UserRepository;
use RuntimeException;
use Throwable;

class TaskboardController extends Controller
{
    /** @var TaskboardRepository */
    private $taskboard;

    /** @var UserRepository */
    private $users;

    public function __construct()
    {
        $this->taskboard = new TaskboardRepository($this->db());
        $this->taskboard->ensureSchema();
        $this->users = new UserRepository($this->db());
        $this->users->ensureSchema();
    }

    public function index(): void
    {
        $currentUser = $this->requireModule('taskboard');
        $flashSuccess = $this->getFlash('success');
        $flashError = $this->getFlash('error');
        $this->releaseSessionLock();

        $boards = $this->taskboard->boards(true);
        $selectedBoardId = $this->resolveBoardId($boards, (int) $this->input('board_id', 0));
        $selectedTaskId = (int) $this->input('task_id', 0);
        $selectedBoard = $selectedBoardId > 0 ? $this->taskboard->boardById($selectedBoardId) : null;
        $statusDefinitions = $this->boardStatusDefinitions($selectedBoard);
        $activeStatusDefinitions = $this->activeStatusDefinitions($statusDefinitions);
        $tasks = $selectedBoardId > 0 ? $this->taskboard->tasksForBoard($selectedBoardId) : array();
        $partitionedTasks = $this->partitionTasksByArchiveState($tasks, $statusDefinitions);
        $tasksByStatus = $this->groupTasksByStatus($partitionedTasks['active'], $activeStatusDefinitions);
        $archivedTasks = $partitionedTasks['archived'];
        $selectedTask = null;

        if ($selectedTaskId > 0) {
            foreach ($tasks as $task) {
                if ((int) ($task['id'] ?? 0) === $selectedTaskId) {
                    $selectedTask = $task;
                    break;
                }
            }
        }

        $taskIds = array_map(static function (array $task): int {
            return (int) ($task['id'] ?? 0);
        }, $tasks);
        $subtasks = $this->groupByTaskId($this->taskboard->subtasksForTaskIds($taskIds));
        $notes = $this->groupByTaskId($this->taskboard->notesForTaskIds($taskIds));
        $attachments = $this->groupByTaskId($this->taskboard->attachmentsForTaskIds($taskIds));
        $activeUsers = $this->activeUsers();
        $boardProgress = $selectedBoardId > 0 ? $this->buildBoardProgress($selectedBoardId, $statusDefinitions) : $this->emptyBoardProgress($statusDefinitions);
        $boardTaskCount = count($tasks);

        $this->render('taskboard/index', array(
            'pageTitle' => 'Taskboard',
            'contentTitle' => 'Taskboard',
            'pageDescription' => 'Tablice zadan, deadliny, checklisty, notatki i szybkie przesuwanie pracy po etapach.',
            'breadcrumbCurrent' => 'Taskboard',
            'currentUser' => $currentUser,
            'flashSuccess' => $flashSuccess,
            'flashError' => $flashError,
            'boards' => $boards,
            'selectedBoardId' => $selectedBoardId,
            'selectedBoard' => $selectedBoard,
            'tasksByStatus' => $tasksByStatus,
            'archivedTasks' => $archivedTasks,
            'selectedTask' => $selectedTask,
            'subtasksByTaskId' => $subtasks,
            'notesByTaskId' => $notes,
            'attachmentsByTaskId' => $attachments,
            'activeUsers' => $activeUsers,
            'boardProgress' => $boardProgress,
            'boardTaskCount' => $boardTaskCount,
            'statusDefinitions' => $statusDefinitions,
            'activeStatusDefinitions' => $activeStatusDefinitions,
            'allStatusDefinitions' => $statusDefinitions,
            'priorityDefinitions' => $this->priorityDefinitions(),
            'canWriteTaskboard' => $this->moduleAccessLevel($currentUser, 'taskboard') === 'edit',
        ));
    }

    public function taskpanel(): void
    {
        $currentUser = $this->requireModule('taskboard');

        try {
            $taskId = (int) $this->input('task_id', 0);
            $boardId = (int) $this->input('board_id', 0);
            if ($taskId <= 0) {
                throw new RuntimeException('Brak zadania do pokazania.');
            }

            $task = $this->taskboard->taskById($taskId);
            if (!$task) {
                throw new RuntimeException('Nie znaleziono zadania.');
            }

            $resolvedBoardId = (int) ($task['board_id'] ?? 0);
            if ($boardId > 0 && $resolvedBoardId !== $boardId) {
                throw new RuntimeException('Zadanie nie nalezy do wskazanej tablicy.');
            }

            $board = $this->taskboard->boardById($resolvedBoardId);
            if (!$board) {
                throw new RuntimeException('Nie znaleziono tablicy.');
            }

            $statusDefinitions = $this->boardStatusDefinitions($board);
            $subtasksByTaskId = $this->groupByTaskId($this->taskboard->subtasksForTaskIds(array($taskId)));
            $notesByTaskId = $this->groupByTaskId($this->taskboard->notesForTaskIds(array($taskId)));
            $attachmentsByTaskId = $this->groupByTaskId($this->taskboard->attachmentsForTaskIds(array($taskId)));
            $html = $this->renderTaskDetailsHtml(array(
                'selectedBoard' => $board,
                'selectedTask' => $task,
                'subtasksByTaskId' => $subtasksByTaskId,
                'notesByTaskId' => $notesByTaskId,
                'attachmentsByTaskId' => $attachmentsByTaskId,
                'activeUsers' => $this->activeUsers(),
                'statusDefinitions' => $statusDefinitions,
                'priorityDefinitions' => $this->priorityDefinitions(),
                'canWriteTaskboard' => $this->moduleAccessLevel($currentUser, 'taskboard') === 'edit',
                'baseUrl' => './index.php',
            ));

            $this->jsonResponse(array(
                'ok' => true,
                'html' => $html,
                'task_id' => $taskId,
                'board_id' => $resolvedBoardId,
                'title' => (string) ($task['title'] ?? ''),
            ));
        } catch (Throwable $exception) {
            $this->jsonResponse(array('error' => $exception->getMessage()), 422);
        }
    }

    public function createboard(): void
    {
        $currentUser = $this->requireModuleWrite('taskboard');
        $this->assertPost();

        try {
            $name = trim((string) $this->input('name', ''));
            $description = trim((string) $this->input('description', ''));
            $accentColor = $this->normalizeAccentColor((string) $this->input('accent_color', '#0d6efd'));
            if ($name === '') {
                throw new RuntimeException('Nazwa tablicy jest wymagana.');
            }

            $boardId = $this->taskboard->createBoard(array(
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'accent_color' => $accentColor,
                'statuses_json' => json_encode($this->parseStatusesInput()),
                'status_span' => $this->normalizeStatusSpan((int) $this->input('status_span', 3)),
                'created_by' => (int) ($currentUser['id'] ?? 0),
            ));

            $this->setFlash('success', 'Tablica zostala utworzona.');
            $this->redirect($this->boardUrl($boardId));
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
            $this->redirect('./index.php?controller=taskboard&action=index');
        }
    }

    public function updateboard(): void
    {
        $this->requireModuleWrite('taskboard');
        $this->assertPost();

        $boardId = (int) $this->input('board_id', 0);
        try {
            $board = $this->taskboard->boardById($boardId);
            if (!$board) {
                throw new RuntimeException('Nie znaleziono tablicy.');
            }

            $name = trim((string) $this->input('name', ''));
            if ($name === '') {
                throw new RuntimeException('Nazwa tablicy jest wymagana.');
            }

            $statusDefinitions = $this->parseStatusesInput();

            $this->taskboard->updateBoard($boardId, array(
                'name' => $name,
                'description' => trim((string) $this->input('description', '')) ?: null,
                'accent_color' => $this->normalizeAccentColor((string) $this->input('accent_color', '#0d6efd')),
                'statuses_json' => json_encode($statusDefinitions),
                'status_span' => $this->normalizeStatusSpan((int) $this->input('status_span', 3)),
                'is_archived' => $this->input('is_archived', '0') === '1' ? 1 : 0,
            ));
            $this->setFlash('success', 'Tablica zostala zaktualizowana.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect($this->boardUrl($boardId));
    }

    public function createtask(): void
    {
        $currentUser = $this->requireModuleWrite('taskboard');
        $this->assertPost();

        $boardId = (int) $this->input('board_id', 0);
        try {
            $board = $this->taskboard->boardById($boardId);
            if (!$board) {
                throw new RuntimeException('Nie znaleziono tablicy dla zadania.');
            }

            $title = trim((string) $this->input('title', ''));
            if ($title === '') {
                throw new RuntimeException('Tytul zadania jest wymagany.');
            }

            $statusDefinitions = $this->boardStatusDefinitions($board);
            $status = $this->normalizeStatus((string) $this->input('status', 'todo'), $statusDefinitions);
            $taskId = $this->taskboard->createTask(array(
                'board_id' => $boardId,
                'title' => $title,
                'description' => trim((string) $this->input('description', '')) ?: null,
                'status' => $status,
                'priority' => $this->normalizePriority((string) $this->input('priority', 'medium')),
                'position' => $this->taskboard->nextTaskPosition($boardId, $status),
                'assigned_user_id' => $this->normalizeOptionalUserId($this->input('assigned_user_id', '')),
                'due_at' => $this->normalizeDateTimeInput((string) $this->input('due_at', '')),
                'created_by' => (int) ($currentUser['id'] ?? 0),
                'updated_by' => (int) ($currentUser['id'] ?? 0),
            ));

            $this->setFlash('success', 'Zadanie zostalo dodane.');
            $this->redirect($this->boardUrl($boardId, $taskId));
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
            $this->redirect($this->boardUrl($boardId));
        }
    }

    public function updatetask(): void
    {
        $currentUser = $this->requireModuleWrite('taskboard');
        $this->assertPost();

        $taskId = (int) $this->input('task_id', 0);
        $boardId = (int) $this->input('board_id', 0);
        try {
            $task = $this->taskboard->taskById($taskId);
            if (!$task) {
                throw new RuntimeException('Nie znaleziono zadania.');
            }

            $boardId = (int) ($task['board_id'] ?? $boardId);
            $title = trim((string) $this->input('title', ''));
            if ($title === '') {
                throw new RuntimeException('Tytul zadania jest wymagany.');
            }

            $board = $this->taskboard->boardById($boardId);
            $statusDefinitions = $this->boardStatusDefinitions($board);
            $status = $this->normalizeStatus((string) $this->input('status', (string) ($task['status'] ?? 'todo')), $statusDefinitions);
            $payload = array(
                'title' => $title,
                'description' => trim((string) $this->input('description', '')) ?: null,
                'status' => $status,
                'priority' => $this->normalizePriority((string) $this->input('priority', 'medium')),
                'assigned_user_id' => $this->normalizeOptionalUserId($this->input('assigned_user_id', '')),
                'due_at' => $this->normalizeDateTimeInput((string) $this->input('due_at', '')),
                'updated_by' => (int) ($currentUser['id'] ?? 0),
            );
            $payload = array_merge($payload, $this->taskStatusTransitionPayload($task, $status, $statusDefinitions));
            $this->taskboard->updateTask($taskId, $payload);

            $this->setFlash('success', 'Zadanie zostalo zapisane.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect($this->boardUrl($boardId, $taskId));
    }

    public function movetask(): void
    {
        $this->requireModuleWrite('taskboard');
        if (!$this->isPost()) {
            $this->jsonResponse(array('error' => 'Dozwolona jest tylko metoda POST.'), 405);
            return;
        }

        try {
            $taskId = (int) $this->input('task_id', 0);
            $boardId = (int) $this->input('board_id', 0);
            $orderedIds = $this->input('ordered_ids', array());
            if (!is_array($orderedIds)) {
                $orderedIds = array();
            }

            $task = $this->taskboard->taskById($taskId);
            if (!$task) {
                throw new RuntimeException('Nie znaleziono zadania do przesuniecia.');
            }

            $boardId = (int) ($task['board_id'] ?? $boardId);
            $board = $this->taskboard->boardById($boardId);
            $statusDefinitions = $this->boardStatusDefinitions($board);
            $status = $this->normalizeStatus((string) $this->input('status', 'todo'), $statusDefinitions);
            $orderedIds = array_values(array_unique(array_filter(array_map('intval', $orderedIds), static function (int $value): bool {
                return $value > 0;
            })));
            if (!in_array($taskId, $orderedIds, true)) {
                $orderedIds[] = $taskId;
            }

            $this->taskboard->reorderTaskColumn($boardId, $status, $orderedIds);
            $this->taskboard->updateTask($taskId, $this->taskStatusTransitionPayload($task, $status, $statusDefinitions));
            $this->jsonResponse(array('ok' => true));
        } catch (Throwable $exception) {
            $this->jsonResponse(array('error' => $exception->getMessage()), 422);
        }
    }

    public function marktaskdone(): void
    {
        $currentUser = $this->requireModuleWrite('taskboard');
        if (!$this->isPost()) {
            $this->jsonResponse(array('error' => 'Dozwolona jest tylko metoda POST.'), 405);
            return;
        }

        try {
            $taskId = (int) $this->input('task_id', 0);
            $task = $this->taskboard->taskById($taskId);
            if (!$task) {
                throw new RuntimeException('Nie znaleziono zadania.');
            }

            $boardId = (int) ($task['board_id'] ?? 0);
            $board = $this->taskboard->boardById($boardId);
            $statusDefinitions = $this->boardStatusDefinitions($board);
            $doneStatus = $this->resolveDoneStatusKey($statusDefinitions);

            $payload = array(
                'status' => $doneStatus,
                'position' => $this->taskboard->nextTaskPosition($boardId, $doneStatus),
                'updated_by' => (int) ($currentUser['id'] ?? 0),
            );
            $payload = array_merge($payload, $this->taskStatusTransitionPayload($task, $doneStatus, $statusDefinitions));
            $this->taskboard->updateTask($taskId, $payload);

            $payload = array(
                'ok' => true,
                'board_id' => $boardId,
                'task_id' => $taskId,
                'done_status' => $doneStatus,
            );

            if ($this->isAjaxRequest()) {
                $this->jsonResponse($payload);
                return;
            }

            $this->setFlash('success', 'Zadanie przeniesione do archiwum.');
            $this->redirect($this->boardUrl($boardId));
        } catch (Throwable $exception) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse(array('error' => $exception->getMessage()), 422);
                return;
            }

            $this->setFlash('error', $exception->getMessage());
            $this->redirect($this->boardUrl((int) $this->input('board_id', 0), (int) $this->input('task_id', 0)));
        }
    }

    public function restoretask(): void
    {
        $currentUser = $this->requireModuleWrite('taskboard');
        if (!$this->isPost()) {
            $this->jsonResponse(array('error' => 'Dozwolona jest tylko metoda POST.'), 405);
            return;
        }

        try {
            $taskId = (int) $this->input('task_id', 0);
            $task = $this->taskboard->taskById($taskId);
            if (!$task) {
                throw new RuntimeException('Nie znaleziono zadania.');
            }

            $boardId = (int) ($task['board_id'] ?? 0);
            $board = $this->taskboard->boardById($boardId);
            $statusDefinitions = $this->boardStatusDefinitions($board);
            $restoreStatus = $this->resolveRestoreStatusKey($task, $statusDefinitions);

            $this->taskboard->updateTask($taskId, array(
                'status' => $restoreStatus,
                'position' => $this->taskboard->nextTaskPosition($boardId, $restoreStatus),
                'completed_at' => null,
                'archived_from_status' => null,
                'archived_from_position' => null,
                'updated_by' => (int) ($currentUser['id'] ?? 0),
            ));

            if ($this->isAjaxRequest()) {
                $this->jsonResponse(array(
                    'ok' => true,
                    'board_id' => $boardId,
                    'task_id' => $taskId,
                    'status' => $restoreStatus,
                ));
                return;
            }

            $this->setFlash('success', 'Zadanie przywrocone z archiwum.');
            $this->redirect($this->boardUrl($boardId, $taskId));
        } catch (Throwable $exception) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse(array('error' => $exception->getMessage()), 422);
                return;
            }

            $this->setFlash('error', $exception->getMessage());
            $this->redirect($this->boardUrl((int) $this->input('board_id', 0), (int) $this->input('task_id', 0)));
        }
    }

    public function deleteboard(): void
    {
        $this->requireModuleWrite('taskboard');
        $this->assertPost();

        $boardId = (int) $this->input('board_id', 0);

        try {
            $board = $this->taskboard->boardById($boardId);
            if (!$board) {
                throw new RuntimeException('Nie znaleziono tablicy.');
            }

            foreach ($this->taskboard->attachmentsForBoard($boardId) as $attachment) {
                $absolutePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) ($attachment['file_path'] ?? ''));
                if (is_file($absolutePath)) {
                    @unlink($absolutePath);
                }
            }

            $this->taskboard->deleteBoard($boardId);
            $this->setFlash('success', 'Tablica zostala usunieta.');
            $this->redirect('./index.php?controller=taskboard&action=index');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
            $this->redirect($this->boardUrl($boardId));
        }
    }

    public function createsubtask(): void
    {
        $this->requireModuleWrite('taskboard');
        $this->assertPost();

        $taskId = (int) $this->input('task_id', 0);
        try {
            $task = $this->taskboard->taskById($taskId);
            if (!$task) {
                throw new RuntimeException('Nie znaleziono zadania.');
            }

            $label = trim((string) $this->input('label', ''));
            if ($label === '') {
                throw new RuntimeException('Tresc podzadania jest wymagana.');
            }

            $this->taskboard->createSubtask(array(
                'task_id' => $taskId,
                'label' => $label,
                'position' => $this->taskboard->nextSubtaskPosition($taskId),
            ));
            $this->setFlash('success', 'Podzadanie zostalo dodane.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect($this->boardUrl((int) $this->input('board_id', 0), $taskId));
    }

    public function togglesubtask(): void
    {
        $this->requireModuleWrite('taskboard');
        if (!$this->isPost()) {
            $this->jsonResponse(array('error' => 'Dozwolona jest tylko metoda POST.'), 405);
            return;
        }

        try {
            $subtaskId = (int) $this->input('subtask_id', 0);
            $subtask = $this->taskboard->subtaskById($subtaskId);
            if (!$subtask) {
                throw new RuntimeException('Nie znaleziono podzadania.');
            }

            $isDone = (int) ($subtask['is_done'] ?? 0) === 1 ? 0 : 1;
            $this->taskboard->updateSubtask($subtaskId, array(
                'is_done' => $isDone,
                'done_at' => $isDone === 1 ? date('Y-m-d H:i:s') : null,
            ));
            $this->jsonResponse(array('ok' => true, 'is_done' => $isDone === 1));
        } catch (Throwable $exception) {
            $this->jsonResponse(array('error' => $exception->getMessage()), 422);
        }
    }

    public function addnote(): void
    {
        $currentUser = $this->requireModuleWrite('taskboard');
        $this->assertPost();

        $taskId = (int) $this->input('task_id', 0);
        try {
            $task = $this->taskboard->taskById($taskId);
            if (!$task) {
                throw new RuntimeException('Nie znaleziono zadania.');
            }

            $note = trim((string) $this->input('note', ''));
            if ($note === '') {
                throw new RuntimeException('Notatka nie moze byc pusta.');
            }

            $this->taskboard->addNote(array(
                'task_id' => $taskId,
                'note' => $note,
                'created_by' => (int) ($currentUser['id'] ?? 0),
            ));
            $this->setFlash('success', 'Notatka zostala dodana.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect($this->boardUrl((int) $this->input('board_id', 0), $taskId));
    }

    public function uploadattachment(): void
    {
        $currentUser = $this->requireModuleWrite('taskboard');
        $this->assertPost();

        $taskId = (int) $this->input('task_id', 0);
        try {
            $task = $this->taskboard->taskById($taskId);
            if (!$task) {
                throw new RuntimeException('Nie znaleziono zadania.');
            }

            if (!isset($_FILES['attachment']) || !is_array($_FILES['attachment'])) {
                throw new RuntimeException('Brak pliku do zalaczenia.');
            }

            $stored = $this->storeAttachment($_FILES['attachment']);
            $this->taskboard->addAttachment(array(
                'task_id' => $taskId,
                'file_name' => $stored['file_name'],
                'file_path' => $stored['file_path'],
                'mime_type' => $stored['mime_type'],
                'file_size' => $stored['file_size'],
                'uploaded_by' => (int) ($currentUser['id'] ?? 0),
            ));
            if ($this->isAjaxRequest()) {
                $this->jsonResponse(array('ok' => true));
                return;
            }

            $this->setFlash('success', 'Zalacznik zostal dodany.');
        } catch (Throwable $exception) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse(array('error' => $exception->getMessage()), 422);
                return;
            }
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect($this->boardUrl((int) $this->input('board_id', 0), $taskId));
    }

    public function deleteattachment(): void
    {
        $this->requireModuleWrite('taskboard');
        $this->assertPost();

        $boardId = (int) $this->input('board_id', 0);
        $taskId = (int) $this->input('task_id', 0);
        $attachmentId = (int) $this->input('attachment_id', 0);

        try {
            $attachment = $this->taskboard->attachmentById($attachmentId);
            if (!$attachment) {
                throw new RuntimeException('Nie znaleziono zalacznika.');
            }

            $absolutePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) ($attachment['file_path'] ?? ''));
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }

            $this->taskboard->deleteAttachment($attachmentId);
            $this->setFlash('success', 'Zalacznik zostal usuniety.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }

        $this->redirect($this->boardUrl($boardId, $taskId));
    }

    private function activeUsers(): array
    {
        $rows = $this->users->allUsers();
        $active = array();
        foreach ($rows as $row) {
            if ((int) ($row['is_active'] ?? 0) !== 1 || (int) ($row['is_blocked'] ?? 0) === 1) {
                continue;
            }
            $active[] = $row;
        }

        return $active;
    }

    private function resolveBoardId(array $boards, int $requestedBoardId): int
    {
        foreach ($boards as $board) {
            if ((int) ($board['id'] ?? 0) === $requestedBoardId) {
                return $requestedBoardId;
            }
        }

        return isset($boards[0]['id']) ? (int) $boards[0]['id'] : 0;
    }

    private function groupTasksByStatus(array $tasks, array $statusDefinitions): array
    {
        $grouped = array();
        foreach ($statusDefinitions as $statusKey => $_statusMeta) {
            $grouped[$statusKey] = array();
        }

        foreach ($tasks as $task) {
            $status = (string) ($task['status'] ?? 'todo');
            if (!isset($grouped[$status])) {
                $firstStatusKey = array_key_first($grouped);
                $status = $firstStatusKey !== null ? (string) $firstStatusKey : 'todo';
            }
            $grouped[$status][] = $task;
        }

        return $grouped;
    }

    private function partitionTasksByArchiveState(array $tasks, array $statusDefinitions): array
    {
        $active = array();
        $archived = array();

        foreach ($tasks as $task) {
            $status = (string) ($task['status'] ?? '');
            if ($this->isDoneStatus($status, $statusDefinitions)) {
                $archived[] = $task;
                continue;
            }

            $active[] = $task;
        }

        return array(
            'active' => $active,
            'archived' => $archived,
        );
    }

    private function groupByTaskId(array $rows): array
    {
        $grouped = array();
        foreach ($rows as $row) {
            $taskId = (int) ($row['task_id'] ?? 0);
            if ($taskId <= 0) {
                continue;
            }
            if (!isset($grouped[$taskId])) {
                $grouped[$taskId] = array();
            }
            $grouped[$taskId][] = $row;
        }

        return $grouped;
    }

    private function statusDefinitions(): array
    {
        return array(
            'todo' => array('label' => 'Do zrobienia', 'accent' => '#64748b', 'span' => 3),
            'in_progress' => array('label' => 'W trakcie', 'accent' => '#0d6efd', 'span' => 3),
            'review' => array('label' => 'Do sprawdzenia', 'accent' => '#f59e0b', 'span' => 3),
            'done' => array('label' => 'Zrobione', 'accent' => '#10b981', 'span' => 3),
        );
    }

    private function priorityDefinitions(): array
    {
        return array(
            'low' => array('label' => 'Niski', 'accent' => '#94a3b8'),
            'medium' => array('label' => 'Sredni', 'accent' => '#0d6efd'),
            'high' => array('label' => 'Wysoki', 'accent' => '#f97316'),
            'urgent' => array('label' => 'Pilny', 'accent' => '#dc2626'),
        );
    }

    private function normalizeStatus(string $status, array $statusDefinitions): string
    {
        $status = strtolower(trim($status));
        if (isset($statusDefinitions[$status])) {
            return $status;
        }

        $firstStatusKey = array_key_first($statusDefinitions);
        return $firstStatusKey !== null ? (string) $firstStatusKey : 'todo';
    }

    private function normalizePriority(string $priority): string
    {
        $priority = strtolower(trim($priority));
        return isset($this->priorityDefinitions()[$priority]) ? $priority : 'medium';
    }

    private function normalizeOptionalUserId($value): ?int
    {
        $raw = trim((string) $value);
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }

        return (int) $raw;
    }

    private function normalizeDateTimeInput(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new RuntimeException('Niepoprawny termin zadania.');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function normalizeAccentColor(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $value) !== 1) {
            return '#0d6efd';
        }

        return strtolower($value);
    }

    private function normalizeStatusSpan(int $value): int
    {
        if ($value < 1) {
            return 1;
        }

        if ($value > 12) {
            return 12;
        }

        return $value;
    }

    private function boardStatusDefinitions($board): array
    {
        $default = $this->statusDefinitions();
        if (!is_array($board) || empty($board['statuses_json'])) {
            return $default;
        }

        $decoded = json_decode((string) $board['statuses_json'], true);
        if (!is_array($decoded)) {
            return $default;
        }

        $definitions = array();
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }

            $key = strtolower(trim((string) ($row['key'] ?? '')));
            $label = trim((string) ($row['label'] ?? ''));
            if ($key === '' || $label === '') {
                continue;
            }

            $definitions[$key] = array(
                'label' => $label,
                'accent' => $this->normalizeAccentColor((string) ($row['accent'] ?? '#0d6efd')),
                'span' => $this->normalizeStatusSpan((int) ($row['span'] ?? 3)),
            );
        }

        return $definitions !== array() ? $definitions : $default;
    }

    private function activeStatusDefinitions(array $statusDefinitions): array
    {
        $active = array();
        foreach ($statusDefinitions as $statusKey => $meta) {
            if ($this->isDoneStatus($statusKey, $statusDefinitions)) {
                continue;
            }
            $active[$statusKey] = $meta;
        }

        return $active !== array() ? $active : $statusDefinitions;
    }

    private function parseStatusesInput(): array
    {
        $labels = $this->input('status_labels', array());
        $accents = $this->input('status_accents', array());
        $spans = $this->input('status_spans', array());
        if (is_array($labels) && $labels !== array()) {
            $definitions = array();
            foreach ($labels as $index => $labelValue) {
                $label = trim((string) $labelValue);
                if ($label === '') {
                    continue;
                }

                $key = $this->normalizeStatusKey($label);
                if ($key === '' || isset($definitions[$key])) {
                    continue;
                }

                $definitions[$key] = array(
                    'key' => $key,
                    'label' => $label,
                    'accent' => $this->normalizeAccentColor((string) ($accents[$index] ?? '#0d6efd')),
                    'span' => $this->normalizeStatusSpan((int) ($spans[$index] ?? 3)),
                );

                if (count($definitions) >= 8) {
                    break;
                }
            }

            if ($definitions !== array()) {
                return array_values($definitions);
            }
        }

        $rawInput = (string) $this->input('statuses_text', '');
        $rawInput = trim($rawInput);
        if ($rawInput === '') {
            return array_values(array_map(static function (string $key, array $meta): array {
                        return array(
                            'key' => $key,
                            'label' => (string) $meta['label'],
                            'accent' => (string) $meta['accent'],
                            'span' => isset($meta['span']) ? (int) $meta['span'] : 3,
                        );
                    }, array_keys($this->statusDefinitions()), $this->statusDefinitions()));
        }

        $lines = preg_split('/\r\n|\r|\n/', $rawInput) ?: array();
        $definitions = array();
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            $label = (string) ($parts[0] ?? '');
            if ($label === '') {
                continue;
            }

            $key = $this->normalizeStatusKey($label);
            if ($key === '' || isset($definitions[$key])) {
                continue;
            }

            $definitions[$key] = array(
                'key' => $key,
                'label' => $label,
                'accent' => $this->normalizeAccentColor((string) ($parts[1] ?? '#0d6efd')),
                'span' => 3,
            );

            if (count($definitions) >= 8) {
                break;
            }
        }

        if ($definitions === array()) {
            throw new RuntimeException('Dodaj przynajmniej jeden status tablicy.');
        }

        return array_values($definitions);
    }

    private function normalizeStatusKey(string $label): string
    {
        $normalized = strtolower(trim($label));
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?: '';
        $normalized = trim($normalized, '_');
        if ($normalized === '') {
            $normalized = 'status_' . substr(bin2hex(random_bytes(3)), 0, 6);
        }

        return substr($normalized, 0, 32);
    }

    private function emptyBoardProgress(array $statusDefinitions): array
    {
        $progress = array();
        foreach ($statusDefinitions as $statusKey => $_meta) {
            $progress[$statusKey] = 0;
        }

        return $progress;
    }

    private function buildBoardProgress(int $boardId, array $statusDefinitions): array
    {
        $progress = $this->emptyBoardProgress($statusDefinitions);
        foreach ($this->taskboard->boardProgress($boardId) as $status => $count) {
            if (!isset($progress[$status])) {
                $progress[$status] = 0;
            }
            $progress[$status] = (int) $count;
        }

        return $progress;
    }

    private function resolveDoneStatusKey(array $statusDefinitions): string
    {
        foreach ($statusDefinitions as $statusKey => $_meta) {
            if ($this->isDoneStatus((string) $statusKey, $statusDefinitions)) {
                return (string) $statusKey;
            }
        }

        $lastStatusKey = array_key_last($statusDefinitions);
        return $lastStatusKey !== null ? (string) $lastStatusKey : 'done';
    }

    private function resolveRestoreStatusKey(array $task, array $statusDefinitions): string
    {
        $archivedFromStatus = (string) ($task['archived_from_status'] ?? '');
        if ($archivedFromStatus !== '' && isset($statusDefinitions[$archivedFromStatus]) && !$this->isDoneStatus($archivedFromStatus, $statusDefinitions)) {
            return $archivedFromStatus;
        }

        foreach ($statusDefinitions as $statusKey => $_meta) {
            if (!$this->isDoneStatus((string) $statusKey, $statusDefinitions)) {
                return (string) $statusKey;
            }
        }

        return 'todo';
    }

    private function taskStatusTransitionPayload(array $task, string $newStatus, array $statusDefinitions): array
    {
        $currentStatus = (string) ($task['status'] ?? '');
        $isCurrentDone = $this->isDoneStatus($currentStatus, $statusDefinitions);
        $isNewDone = $this->isDoneStatus($newStatus, $statusDefinitions);

        if ($isNewDone && !$isCurrentDone) {
            return array(
                'completed_at' => date('Y-m-d H:i:s'),
                'archived_from_status' => $currentStatus !== '' ? $currentStatus : null,
                'archived_from_position' => isset($task['position']) ? (int) $task['position'] : null,
            );
        }

        if (!$isNewDone) {
            return array(
                'completed_at' => null,
                'archived_from_status' => null,
                'archived_from_position' => null,
            );
        }

        return array(
            'completed_at' => (string) ($task['completed_at'] ?? '') !== '' ? $task['completed_at'] : date('Y-m-d H:i:s'),
        );
    }

    private function isDoneStatus(string $status, array $statusDefinitions): bool
    {
        $label = strtolower(trim((string) ($statusDefinitions[$status]['label'] ?? '')));
        $haystack = strtolower(trim($status . ' ' . $label));
        foreach (array('done', 'complete', 'completed', 'closed', 'finish', 'finished', 'zrobione', 'zakonczone', 'zamkniete') as $keyword) {
            if (strpos($haystack, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    private function renderTaskDetailsHtml(array $data): string
    {
        $smarty = SmartyFactory::create();
        foreach ($data as $key => $value) {
            $smarty->assign($key, $value);
        }

        return (string) $smarty->fetch('taskboard/task_details.tpl');
    }

    private function isAjaxRequest(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        return strpos($accept, 'application/json') !== false || $this->input('ajax', '') === '1';
    }

    private function storeAttachment(array $file): array
    {
        $tmpName = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Brak poprawnego pliku tymczasowego.');
        }

        $size = isset($file['size']) ? (int) $file['size'] : 0;
        if ($size <= 0) {
            throw new RuntimeException('Plik jest pusty.');
        }

        if ($size > 20 * 1024 * 1024) {
            throw new RuntimeException('Zalacznik moze miec maksymalnie 20 MB.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, $tmpName) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        $allowed = array(
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
        );

        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Dozwolone sa obrazy, PDF i TXT.');
        }

        $relativeDir = 'uploads/taskboard/' . date('Y/m');
        $absoluteDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('Nie udalo sie utworzyc katalogu na zalaczniki.');
        }

        $originalName = isset($file['name']) ? trim((string) $file['name']) : 'zalacznik';
        $safeOriginalName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $originalName) ?: 'zalacznik';
        $filename = 'task_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
        $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($tmpName, $absolutePath)) {
            throw new RuntimeException('Nie udalo sie zapisac zalacznika.');
        }

        return array(
            'file_name' => $safeOriginalName,
            'file_path' => $relativeDir . '/' . $filename,
            'mime_type' => $mime,
            'file_size' => $size,
        );
    }

    private function assertPost(): void
    {
        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=taskboard&action=index');
        }
    }

    private function boardUrl(int $boardId, int $taskId = 0): string
    {
        $params = array(
            'controller' => 'taskboard',
            'action' => 'index',
        );
        if ($boardId > 0) {
            $params['board_id'] = $boardId;
        }
        if ($taskId > 0) {
            $params['task_id'] = $taskId;
        }

        return './index.php?' . http_build_query($params);
    }

    private function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
    }
}
