<?php

namespace App\Services;

use App\Enums\GenerationStatus;
use App\Enums\MessageRole;
use App\Models\ColumnConversation;
use App\Models\Message;

class ColumnConversationService
{
    public function __construct(
        private ModelDefinitionService $modelDefinitionService,
    ) {}

    /**
     * Возвращает подтверждённую историю сообщений колонки,
     * ограниченную contextWindow модели.
     *
     * - Только сообщения данной колонки (изоляция)
     * - Только подтверждённые: user messages + assistant messages от completed generations
     * - partialOutput отменённых/ошибочных generation НЕ включается
     * - Автоматическое отсечение старых сообщений при превышении contextWindow
     *
     * @return Message[]
     */
    public function getConfirmedHistory(ColumnConversation $column): array
    {
        $messages = $this->fetchConfirmedMessages($column);

        return $this->trimToContextWindow($messages, $column->model_code);
    }

    private function fetchConfirmedMessages(ColumnConversation $column): array
    {
        return $column->messages()
            ->where(function ($query) {
                $query->where('role', MessageRole::USER->value)
                    ->orWhere(function ($q) {
                        $q->where('role', MessageRole::ASSISTANT->value)
                            ->whereHas('generation', fn ($g) => $g
                                ->where('status', GenerationStatus::COMPLETED->value)
                            );
                    });
            })
            ->orderBy('sequence')
            ->get()
            ->all();
    }

    /**
     * Отсекает самые старые сообщения, если суммарная длина
     * превышает contextWindow модели. Без ошибки пользователю.
     *
     * Стратегия: считаем символы, отсекаем с начала.
     * Точная токен-проверка будет добавлена при интеграции с OpenRouter.
     *
     * @param  Message[]  $messages
     * @return Message[]
     */
    private function trimToContextWindow(array $messages, string $modelCode): array
    {
        $model = $this->modelDefinitionService->findByCode($modelCode);

        if ($model === null) {
            return $messages;
        }

        $maxChars = $model->contextWindow * 2;
        $totalChars = array_sum(array_map(fn (Message $m) => mb_strlen($m->content), $messages));

        if ($totalChars <= $maxChars) {
            return $messages;
        }

        // Отсекаем старые сообщения с начала, пока не уложимся в лимит
        while (count($messages) > 0) {
            $totalChars -= mb_strlen($messages[0]->content);
            array_shift($messages);

            if ($totalChars <= $maxChars) {
                break;
            }
        }

        return $messages;
    }
}
