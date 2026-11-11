<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Knowledge;
use App\Models\Unanswered;

final class KnowledgeController extends Controller
{
    public function index(): void
    {
        $search = Request::query('q');
        $page = max(1, (int) Request::query('page', '1'));
        $old = Session::pullOld();

        $prefill = '';
        $fromId = (int) Request::query('from');
        if ($fromId > 0 && ($u = Unanswered::find($fromId))) {
            $prefill = (string) $u['question'];
        }

        $this->view('knowledge/index', [
            'title'   => 'دانشنامه',
            'page'    => 'knowledge',
            'list'    => Knowledge::paginate($search, $page),
            'search'  => $search,
            'scripts' => ['knowledge'],
            'old'     => $old + ['question' => $prefill, 'answer' => '', 'from' => $fromId ?: ''],
        ]);
    }

    public function store(): void
    {
        [$q, $a] = $this->validated('/knowledge');
        Knowledge::create($q, $a);

        Unanswered::resolveByQuestion($q);
        $from = (int) Request::str('from');
        if ($from > 0) {
            Unanswered::resolve($from);
            Session::flash('success', 'پاسخ اضافه شد و سوال از فهرست بی‌پاسخ‌ها حذف شد.');
            Response::redirect('/unanswered');
        }
        Session::flash('success', 'پرسش و پاسخ به دانشنامه اضافه شد.');
        Response::redirect('/knowledge');
    }

    public function edit(string $id): void
    {
        $row = Knowledge::find((int) $id);
        if (!$row) {
            Response::error('این مورد پیدا نشد.', 404);
        }
        $old = Session::pullOld();
        $this->view('knowledge/edit', [
            'title' => 'ویرایش پرسش و پاسخ',
            'page'  => 'knowledge',
            'row'   => $row,
            'scripts' => ['knowledge'],
            'old'   => $old + ['question' => $row['question'], 'answer' => $row['answer']],
        ]);
    }

    public function update(string $id): void
    {
        $id = (int) $id;
        if (!Knowledge::find($id)) {
            Response::error('این مورد پیدا نشد.', 404);
        }
        [$q, $a] = $this->validated("/knowledge/{$id}/edit", $id);
        Knowledge::update($id, $q, $a);
        Session::flash('success', 'تغییرات ذخیره شد.');
        Response::redirect('/knowledge');
    }

    public function delete(string $id): void
    {
        Knowledge::delete((int) $id);
        Session::flash('success', 'مورد از دانشنامه حذف شد.');
        Response::redirect('/knowledge');
    }

    /** @return array{0:string,1:string} */
    private function validated(string $back, int $exceptId = 0): array
    {
        $q = trim(Request::str('question'));
        $a = trim(Request::str('answer'));
        $error = null;

        if (mb_strlen($q) < 3) {
            $error = 'سوال را بنویسید (حداقل ۳ کاراکتر).';
        } elseif (mb_strlen($q) > Knowledge::QUESTION_MAX) {
            $error = 'سوال حداکثر ' . fa_num(Knowledge::QUESTION_MAX) . ' کاراکتر است.';
        } elseif (mb_strlen($a) < 1) {
            $error = 'پاسخ را بنویسید.';
        } elseif (mb_strlen($a) > Knowledge::ANSWER_MAX) {
            $error = 'پاسخ حداکثر ' . fa_num(Knowledge::ANSWER_MAX) . ' کاراکتر است.';
        } elseif (Knowledge::existsQuestion($q, $exceptId)) {
            $error = 'همین سوال قبلاً در دانشنامه ثبت شده است.';
        }

        if ($error !== null) {
            Session::flash('error', $error);
            Session::setOld(['question' => $q, 'answer' => $a, 'from' => Request::str('from')]);
            Response::redirect($back);
        }
        return [$q, $a];
    }
}
