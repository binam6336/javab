<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\ChatMessage;
use App\Models\Correction;
use App\Models\Feedback;
use App\Models\Knowledge;
use App\Models\Unanswered;
use App\Services\AnswerService;
use App\Services\RateLimiter;

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
        $assistantIds = [];
        foreach ($rows as $r) {
            if ($r['role'] === 'assistant') {
                $assistantIds[] = (int) $r['id'];
            }
        }
        $feedback = Feedback::forMessages($assistantIds);
        $corrections = Correction::forMessages($assistantIds);

        $messages = array_map(static function ($r) use ($feedback, $corrections) {
            if ($r['role'] !== 'assistant') {
                return ['role' => 'user', 'content' => $r['content'], 'status' => 'ok'];
            }
            $id = (int) $r['id'];
            $fb = $feedback[$id] ?? null;
            $cr = $corrections[$id] ?? null;
            return [
                'role'       => 'assistant',
                'message_id' => $id,
                'content'    => $r['content'],
                'status'     => $r['status'],
                'confidence' => $r['confidence'] !== null ? (int) $r['confidence'] : null,
                'sources'    => AnswerService::sourcesFromKeys(array_filter(explode(',', (string) $r['sources']))),
                'via'        => $r['via'],
                'rating'     => $fb && $fb['rating'] !== null ? (int) $fb['rating'] : null,
                'comment'    => $fb ? (string) $fb['comment'] : '',
                'corrected'  => $cr ? (string) $cr['corrected_answer'] : null,
            ];
        }, $rows);
        Response::json(['ok' => true, 'messages' => $messages]);
    }

    /** امتیاز (۱ تا ۵) و/یا بازخورد روی یک پاسخ */
    public function rate(string $id): void
    {
        $msg = $this->assistantMessage((int) $id);
        $ratingIn = Request::input('rating');
        $rating = ($ratingIn === null || $ratingIn === '') ? null : (int) $ratingIn;
        $comment = trim((string) preg_replace("/[ \t]+/u", ' ', Request::str('comment')));

        if ($rating !== null && ($rating < 1 || $rating > 5)) {
            Response::json(['ok' => false, 'message' => 'امتیاز باید بین ۱ تا ۵ ستاره باشد.'], 422);
        }
        if (mb_strlen($comment) > Feedback::COMMENT_MAX) {
            Response::json(['ok' => false, 'message' => 'بازخورد حداکثر ' . fa_num(Feedback::COMMENT_MAX) . ' کاراکتر است.'], 422);
        }
        $old = Feedback::forMessage((int) $msg['id']);
        // اگر فقط یکی از دو مقدار فرستاده شد، دیگری حفظ می‌شود
        if (Request::input('rating') === null && $old) {
            $rating = $old['rating'] !== null ? (int) $old['rating'] : null;
        }
        if (Request::input('comment') === null && $old) {
            $comment = (string) $old['comment'];
        }
        if ($rating === null && $comment === '') {
            Response::json(['ok' => false, 'message' => 'یک امتیاز انتخاب کنید یا بازخورد بنویسید.'], 422);
        }

        Feedback::save((int) $msg['id'], (string) $msg['conversation_id'], ChatMessage::questionFor($msg), (string) $msg['content'], $rating, $comment !== '' ? $comment : null);
        Response::json(['ok' => true, 'message' => 'بازخورد شما ثبت شد؛ از آن در پاسخ‌های بعدی استفاده می‌شود.', 'rating' => $rating, 'comment' => $comment]);
    }

    /** ویرایش پاسخ: پاسخ درست ذخیره می‌شود و برای سوال‌های مشابه بعدی ملاک قرار می‌گیرد */
    public function correct(string $id): void
    {
        $msg = $this->assistantMessage((int) $id);
        $answer = trim(Request::str('answer'));
        if (mb_strlen($answer) < 1) {
            Response::json(['ok' => false, 'message' => 'متن پاسخ اصلاح‌شده را بنویسید.'], 422);
        }
        if (mb_strlen($answer) > Correction::ANSWER_MAX) {
            Response::json(['ok' => false, 'message' => 'پاسخ حداکثر ' . fa_num(Correction::ANSWER_MAX) . ' کاراکتر است.'], 422);
        }
        $question = ChatMessage::questionFor($msg);
        if ($question === '') {
            Response::json(['ok' => false, 'message' => 'سوال مربوط به این پاسخ پیدا نشد.'], 422);
        }
        Correction::saveForMessage((int) $msg['id'], (string) $msg['conversation_id'], $question, (string) $msg['content'], $answer);
        Unanswered::resolveByQuestion($question); // سوال حالا پاسخ دارد
        Response::json(['ok' => true, 'message' => 'پاسخ اصلاح شد؛ از این به بعد برای این سوال و سوال‌های مشابه همین پاسخ ملاک است.', 'corrected' => $answer]);
    }

    /** @return array<string,mixed> */
    private function assistantMessage(int $id): array
    {
        $limit = RateLimiter::attempt('feedback', Request::ip(), [[30, 60], [300, 3600]]);
        if (!$limit['ok']) {
            Response::json(['ok' => false, 'message' => 'درخواست‌ها زیاد است؛ کمی بعد دوباره امتحان کنید.'], 429);
        }
        $msg = ChatMessage::find($id);
        if (!$msg || $msg['role'] !== 'assistant' || !in_array($msg['status'], ['answered', 'no_answer'], true)) {
            Response::json(['ok' => false, 'message' => 'این پاسخ پیدا نشد.'], 404);
        }
        return $msg;
    }
}
