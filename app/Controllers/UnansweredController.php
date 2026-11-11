<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Models\Unanswered;

final class UnansweredController extends Controller
{
    public function index(): void
    {
        $this->view('unanswered/index', [
            'title' => 'سوال‌های بی‌پاسخ',
            'page'  => 'unanswered',
            'rows'  => Unanswered::open(),
            'scripts' => ['knowledge'],
        ]);
    }

    public function dismiss(string $id): void
    {
        Unanswered::resolve((int) $id);
        Session::flash('success', 'سوال از فهرست حذف شد.');
        Response::redirect('/unanswered');
    }
}
