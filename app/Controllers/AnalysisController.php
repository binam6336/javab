<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\KbIssue;
use App\Models\Knowledge;
use App\Services\KbAnalyzer;

/** تحلیل دانشنامه‌ها: آمار استفاده، هشدارهای محتوایی و استثناها */
final class AnalysisController extends Controller
{
    public function index(): void
    {
        $tab = Request::query('tab') === 'exceptions' ? 'exceptions' : 'active';
        $last = KbIssue::lastRun();
        $exceptions = KbIssue::exceptions();
        $items = self::itemsWithIssues($last ? (string) $last['run'] : '');

        // استثناها شامل همه‌ی موارد مستثنا هستند، حتی اگر در آخرین تحلیل هشداری نداشته باشند
        $missing = array_diff(array_keys($exceptions), array_keys($items));
        foreach (Knowledge::findMany($missing) as $id => $row) {
            $items[$id] = ['row' => $row, 'issues' => [], 'edited' => false];
        }

        $active = [];
        $excepted = [];
        foreach ($items as $id => $item) {
            if (isset($exceptions[$id])) {
                $item['excepted_at'] = $exceptions[$id];
                $excepted[$id] = $item;
            } elseif ($item['issues']) {
                $active[$id] = $item;
            }
        }
        uasort($active, static function ($a, $b) {
            return [count($b['issues']), (int) $a['row']['id']] <=> [count($a['issues']), (int) $b['row']['id']];
        });
        uksort($excepted, static function ($a, $b) use ($exceptions) {
            return strcmp($exceptions[$b], $exceptions[$a]);
        });

        $this->view('analysis/index', [
            'title'    => 'تحلیل دانشنامه‌ها',
            'page'     => 'analysis',
            'scripts'  => ['analysis'],
            'usage'    => KbAnalyzer::usage(),
            'last'     => $last,
            'stale'    => $last && ($last['signature'] ?? '') !== KbAnalyzer::signature(),
            'tab'      => $tab,
            'active'   => $active,
            'excepted' => $excepted,
        ]);
    }

    public function start(): void
    {
        Response::json(KbAnalyzer::start());
    }

    public function step(): void
    {
        Response::json(KbAnalyzer::step());
    }

    public function except(string $id): void
    {
        $id = (int) $id;
        if (!Knowledge::find($id)) {
            Response::error('این مورد پیدا نشد.', 404);
        }
        KbIssue::addException($id);
        Session::flash('success', 'هشدار این دانشنامه به لیست استثناها منتقل شد.');
        Response::redirect('/analysis');
    }

    public function unexcept(string $id): void
    {
        KbIssue::removeException((int) $id);
        Session::flash('success', 'دانشنامه از استثنا خارج شد و دوباره در تحلیل‌ها بررسی می‌شود.');
        Response::redirect('/analysis?tab=exceptions');
    }

    /**
     * هشدارهای آخرین تحلیل، گروه‌بندی‌شده بر اساس دانشنامه.
     * تناقض و هم‌پوشانی برای هر دو طرف نمایش داده می‌شود.
     * @return array<int,array{row:array<string,mixed>,issues:array<int,array<string,mixed>>,edited:bool}>
     */
    private static function itemsWithIssues(string $run): array
    {
        $raw = KbIssue::forRun($run);
        if (!$raw) {
            return [];
        }
        $ids = [];
        foreach ($raw as $i) {
            $ids[] = (int) $i['knowledge_id'];
            foreach (array_filter(explode(',', (string) $i['related'])) as $r) {
                $ids[] = (int) $r;
            }
        }
        $rows = Knowledge::findMany($ids);
        $crc = [];
        foreach ($rows as $id => $row) {
            $crc[$id] = KbAnalyzer::contentCrc((string) $row['question'], (string) $row['answer']);
        }

        $out = [];
        $add = static function (int $id, array $issue) use (&$out, $rows) {
            if (!isset($out[$id])) {
                $out[$id] = ['row' => $rows[$id], 'issues' => [], 'edited' => false];
            }
            $out[$id]['issues'][] = $issue;
        };

        foreach ($raw as $i) {
            $id = (int) $i['knowledge_id'];
            if (!isset($rows[$id])) {
                continue;
            }
            $related = array_values(array_filter(array_map('intval', explode(',', (string) $i['related'])), static function ($r) use ($rows) {
                return isset($rows[$r]);
            }));
            $pair = in_array($i['type'], ['contradiction', 'overlap'], true);
            if ($pair && !$related) {
                continue; // مورد مرتبط حذف شده است
            }
            $issue = [
                'type'            => (string) $i['type'],
                'reason'          => (string) $i['reason'],
                'excerpt'         => (string) $i['excerpt'],
                'related'         => $related,
                'related_excerpt' => (string) ($i['related_excerpt'] ?? ''),
            ];
            $add($id, $issue);
            if ((int) $i['content_crc'] !== $crc[$id]) {
                $out[$id]['edited'] = true;
            }
            if ($pair) {
                // نمایش همان مشکل از سمت مورد مرتبط
                $other = $related[0];
                $otherChanged = (int) $i['related_crc'] !== 0 && (int) $i['related_crc'] !== $crc[$other];
                $add($other, [
                    'type'            => $issue['type'],
                    'reason'          => $issue['reason'],
                    'excerpt'         => $issue['related_excerpt'],
                    'related'         => array_merge([$id], array_slice($related, 1)),
                    'related_excerpt' => $issue['excerpt'],
                ]);
                if ($otherChanged) {
                    $out[$other]['edited'] = true;
                }
            }
        }
        foreach ($out as $id => &$item) {
            $item['related_rows'] = [];
            foreach ($item['issues'] as $iss) {
                foreach ($iss['related'] as $r) {
                    $item['related_rows'][$r] = $rows[$r];
                }
            }
        }
        unset($item);
        return $out;
    }
}
