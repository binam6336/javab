<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Rule;

/** API قوانین ثابت (پاپ‌آپ صفحه‌ی دانشنامه) — خروجی همیشه JSON */
final class RuleController extends Controller
{
    public function index(): void
    {
        Response::json(['ok' => true, 'rules' => array_map([Rule::class, 'out'], Rule::all())]);
    }

    public function store(): void
    {
        if (Rule::count() >= Rule::MAX_COUNT) {
            Response::json(['ok' => false, 'message' => 'حداکثر ' . fa_num(Rule::MAX_COUNT) . ' قانون می‌توان ثبت کرد.'], 422);
        }
        $body = $this->body();
        $id = Rule::create($body);
        Response::json(['ok' => true, 'message' => 'قانون اضافه شد.', 'rule' => Rule::out((array) Rule::find($id))]);
    }

    public function update(string $id): void
    {
        $row = Rule::find((int) $id);
        if (!$row) {
            Response::json(['ok' => false, 'message' => 'این قانون پیدا نشد.'], 404);
        }
        $body = Request::input('body') === null ? (string) $row['body'] : $this->body();
        $activeIn = Request::input('active');
        $active = $activeIn === null ? (int) $row['is_active'] === 1 : filter_var($activeIn, FILTER_VALIDATE_BOOLEAN);
        Rule::update((int) $id, $body, $active);
        Response::json(['ok' => true, 'message' => 'قانون ذخیره شد.', 'rule' => Rule::out((array) Rule::find((int) $id))]);
    }

    public function delete(string $id): void
    {
        Rule::delete((int) $id);
        Response::json(['ok' => true, 'message' => 'قانون حذف شد.']);
    }

    private function body(): string
    {
        $body = trim((string) preg_replace("/[ \t]+/u", ' ', Request::str('body')));
        if (mb_strlen($body) < 3) {
            Response::json(['ok' => false, 'message' => 'متن قانون را بنویسید (حداقل ۳ کاراکتر).'], 422);
        }
        if (mb_strlen($body) > Rule::BODY_MAX) {
            Response::json(['ok' => false, 'message' => 'متن قانون حداکثر ' . fa_num(Rule::BODY_MAX) . ' کاراکتر است.'], 422);
        }
        return $body;
    }
}
