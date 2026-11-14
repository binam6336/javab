<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Correction;
use App\Models\Feedback;

/** مدیریت امتیازها/بازخوردها و اصلاح پاسخ‌ها */
final class FeedbackController extends Controller
{
    private const FILTERS = ['all', 'low', 'high', 'comment'];

    public function index(): void
    {
        $tab = Request::query('tab') === 'corrections' ? 'corrections' : 'ratings';
        $filter = in_array(Request::query('filter'), self::FILTERS, true) ? Request::query('filter') : 'all';
        $page = max(1, (int) Request::query('page', '1'));

        $this->view('feedback/index', [
            'title'       => 'بازخورد و اصلاح‌ها',
            'page'        => 'feedback',
            'tab'         => $tab,
            'filter'      => $filter,
            'summary'     => Feedback::summary(),
            'ratings'     => $tab === 'ratings' ? Feedback::paginate($filter, $page) : null,
            'corrections' => $tab === 'corrections' ? Correction::paginate($page) : null,
            'counts'      => ['corrections' => Correction::count()],
            'scripts'     => ['knowledge'],
        ]);
    }

    // ---------------- امتیازها ----------------

    public function toggleFeedback(string $id): void
    {
        Feedback::setActive((int) $id, Request::str('active') === '1');
        Session::flash('success', Request::str('active') === '1' ? 'این بازخورد دوباره در پاسخ‌ها اثر دارد.' : 'این بازخورد دیگر در پاسخ‌ها اثری ندارد.');
        Response::redirect($this->back('ratings'));
    }

    public function deleteFeedback(string $id): void
    {
        Feedback::delete((int) $id);
        Session::flash('success', 'بازخورد حذف شد.');
        Response::redirect($this->back('ratings'));
    }

    // ---------------- اصلاح‌ها ----------------

    public function updateCorrection(string $id): void
    {
        $id = (int) $id;
        if (!Correction::find($id)) {
            Response::error('این اصلاح پیدا نشد.', 404);
        }
        $question = trim(Request::str('question'));
        $answer = trim(Request::str('answer'));
        if (mb_strlen($question) < 2 || $answer === '') {
            Session::flash('error', 'سوال و پاسخ اصلاح‌شده را کامل بنویسید.');
        } elseif (mb_strlen($answer) > Correction::ANSWER_MAX) {
            Session::flash('error', 'پاسخ حداکثر ' . fa_num(Correction::ANSWER_MAX) . ' کاراکتر است.');
        } else {
            Correction::update($id, $question, $answer);
            Session::flash('success', 'اصلاح ذخیره شد.');
        }
        Response::redirect($this->back('corrections'));
    }

    public function toggleCorrection(string $id): void
    {
        Correction::setActive((int) $id, Request::str('active') === '1');
        Session::flash('success', Request::str('active') === '1' ? 'اصلاح فعال شد.' : 'اصلاح غیرفعال شد و دیگر در پاسخ‌ها اثری ندارد.');
        Response::redirect($this->back('corrections'));
    }

    public function deleteCorrection(string $id): void
    {
        Correction::delete((int) $id);
        Session::flash('success', 'اصلاح حذف شد.');
        Response::redirect($this->back('corrections'));
    }

    /** بازگشت به همان تب/فیلتر/صفحه */
    private function back(string $tab): string
    {
        $q = ['tab' => $tab];
        $filter = Request::str('filter');
        if ($tab === 'ratings' && in_array($filter, self::FILTERS, true) && $filter !== 'all') {
            $q['filter'] = $filter;
        }
        $page = (int) Request::str('page');
        if ($page > 1) {
            $q['page'] = $page;
        }
        return '/feedback?' . http_build_query($q);
    }
}
