<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\ChatMessage;
use App\Models\Knowledge;
use App\Services\AnswerService;

final class ChatController extends Controller
{
    public function index(): void
    {
        $this->view('chat/index', [
            'title'   => 'گفتگو',
            'page'    => 'chat',
            'samples' => Knowledge::samples(4),
            'kbCount' => Knowledge::count(),
            'scripts' => ['chat'],
        ]);
    }

    public function ask(): void
    {
        $conversation = Request::str('conversation');
        if (!preg_match('/^[a-f0-9]{32}$/', $conversation)) {
            Response::json(['ok' => false, 'status' => 'invalid', 'message' => 'شناسه‌ی گفتگو نامعتبر است.'], 400);
        }
        $result = AnswerService::ask($conversation, Request::str('question'), Request::ip());
        $http = $result['status'] === 'invalid' ? 422 : ($result['status'] === 'rate_limited' ? 429 : 200);
        if ($result['retry_after'] > 0) {
            header('Retry-After: ' . $result['retry_after']);
        }
        Response::json($result, $http);
    }

    public function history(string $conversation): void
    {
        $rows = ChatMessage::conversation($conversation);
        $ids = [];
        foreach ($rows as $r) {
            foreach (array_filter(explode(',', (string) $r['sources'])) as $id) {
                $ids[] = (int) $id;
            }
        }
        $kb = Knowledge::findMany($ids);
        $messages = array_map(static function ($r) use ($kb) {
            $sources = [];
            foreach (array_filter(explode(',', (string) $r['sources'])) as $id) {
                if (isset($kb[(int) $id])) {
                    $sources[] = ['id' => (int) $id, 'question' => (string) $kb[(int) $id]['question']];
                }
            }
            return [
                'role'       => $r['role'],
                'content'    => $r['content'],
                'status'     => $r['role'] === 'assistant' ? $r['status'] : 'ok',
                'confidence' => $r['confidence'] !== null ? (int) $r['confidence'] : null,
                'sources'    => $sources,
                'via'        => $r['via'],
            ];
        }, $rows);
        Response::json(['ok' => true, 'messages' => $messages]);
    }
}
