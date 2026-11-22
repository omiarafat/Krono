<?php
/** PDF export of the Reports page: builds an English A4 HTML report and renders it with Dompdf (composer require dompdf/dompdf). */
final class ReportPdf {
    const DAY_ROWS = 1500;

    static function available(): bool {
        if (class_exists('Dompdf\\Dompdf', false)) return true;
        $a = ROOT . '/vendor/autoload.php'; if (is_file($a)) require_once $a;
        return class_exists('Dompdf\\Dompdf');
    }

    static function filename(array $ws, array $f): string {
        $slug = trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower((string)$ws['name'])), '-') ?: 'workspace';
        return "krono-report-$slug-{$f['from']}" . ($f['to'] !== $f['from'] ? "-to-{$f['to']}" : '') . '.pdf';
    }

    static function stream(string $html, string $name): void {
        $o = new \Dompdf\Options(); $o->set('isRemoteEnabled', false); $o->set('chroot', ROOT); $o->set('defaultFont', 'DejaVu Sans');
        $d = new \Dompdf\Dompdf($o); $d->loadHtml($html, 'UTF-8'); $d->setPaper('A4', 'portrait'); $d->render();
        $d->stream($name, ['Attachment' => true]);
        exit;
    }

    /** Status of every member-day in the range (future days skipped): [uid => [date => [status, tag, seconds]]] plus per-member counts. */
    static function statuses(array $ws, array $people, array $f, DateTimeZone $tz, array $r): array {
        $wid = (int)$ws['id']; $ids = $f['member'] ? [$f['member']] : array_map('intval', array_keys($people));
        $worked = []; foreach ($r['items'] as $x) $worked[(int)$x['user_id']][$x['day']] = ($worked[(int)$x['user_id']][$x['day']] ?? 0) + (int)$x['duration_seconds'];
        $att = []; foreach ($r['dayKeys'] as $k) { [$u, $d] = explode('|', $k, 2); $att[(int)$u][$d] = true; }
        $leave = DaysOff::leaveMap($wid, $ids, $f['from'], $f['to']);
        $today = (new DateTimeImmutable('now', $tz))->format('Y-m-d'); $end = min($f['to'], $today);
        $out = []; $count = [];
        foreach ($ids as $uid) {
            $off = DaysOff::listOf(DaysOff::effective($wid, $uid)); $c = ['present' => 0, 'absent' => 0, 'leave' => 0, 'off' => 0];
            for ($d = new DateTimeImmutable($f['from']); $d->format('Y-m-d') <= $end; $d = $d->modify('+1 day')) {
                $k = $d->format('Y-m-d'); $isOff = DaysOff::isOff($off, $d); $did = isset($att[$uid][$k]) || isset($worked[$uid][$k]);
                $lv = ($leave[$uid][$k]['status'] ?? '') === 'approved'; $tag = '';
                if ($did) { $st = 'Present'; if ($isOff) $tag = 'Worked on day off'; $c['present']++; }
                elseif ($lv) { $st = 'Leave'; $c['leave']++; }
                elseif ($isOff) { $st = 'Days off'; $c['off']++; }
                else { $st = 'Absent'; $c['absent']++; }
                $out[$uid][$k] = [$st, $tag, $worked[$uid][$k] ?? 0];
            }
            $count[$uid] = $c;
        }
        return [$out, $count];
    }

    static function html(array $ws, array $u, array $people, array $f, DateTimeZone $tz, array $r): string {
        $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $names = $people; $df = Account::df();
        $fd = fn(string $ymd) => (new DateTimeImmutable($ymd))->format($df);
        $logo = ''; $lf = ROOT . '/public/assets/brand/Krono_Logo_Light.svg';
        if (is_file($lf)) $logo = '<img src="data:image/svg+xml;base64,' . base64_encode((string)file_get_contents($lf)) . '" style="height:22px">';
        $filters = ['Period' => $fd($f['from']) . ($f['to'] !== $f['from'] ? ' – ' . $fd($f['to']) : '')];
        if ($f['member']) $filters['Team member'] = $names[$f['member']] ?? '';
        if ($f['project']) { foreach ($r['items'] as $x) if ((int)$x['project_id'] === $f['project']) { $filters['Project'] = $x['project_name']; break; } $filters['Project'] ??= 'Selected project'; }
        if ($f['task']) { foreach ($r['items'] as $x) if ((int)$x['task_id'] === $f['task']) { $filters['Task'] = $x['task_title']; break; } $filters['Task'] ??= 'Selected task'; }
        if ($f['status'] !== '') $filters['Status'] = Task::STATUS[$f['status']] ?? $f['status'];
        if ($f['priority'] !== '') $filters['Priority'] = Project::PRIORITY[$f['priority']] ?? $f['priority'];
        if ($f['location'] !== '') $filters['Work location'] = Report::LOCATIONS[$f['location']] ?? '';
        if ($f['q'] !== '') $filters['Description'] = '“' . $f['q'] . '”';
        $showStatus = $f['location'] === '';
        [$days, $count] = $showStatus ? self::statuses($ws, $people, $f, $tz, $r) : [[], []];

        // per-member summary
        $mem = []; foreach ($r['items'] as $x) { $m = &$mem[(int)$x['user_id']]; $m['n'] = ($m['n'] ?? 0) + 1; $m['s'] = ($m['s'] ?? 0) + (int)$x['duration_seconds']; unset($m); }
        $ids = $f['member'] ? [$f['member']] : array_keys($people);

        $h = '<!doctype html><html><head><meta charset="utf-8"><style>
@page{margin:34px 32px 44px}body{font-family:"DejaVu Sans",sans-serif;font-size:9px;color:#1c2b24}
h1{font-size:17px;margin:0 0 2px}h2{font-size:11px;margin:18px 0 6px;color:#0f6b4a}.muted{color:#6b7a73}
table{width:100%;border-collapse:collapse}th{background:#eef5f1;text-align:left;font-size:8px;text-transform:uppercase;color:#4a5b53;padding:5px 6px;border-bottom:1px solid #cfdcd5}
td{padding:5px 6px;border-bottom:1px solid #e6eee9;vertical-align:top}.r{text-align:right}.cards td{border:1px solid #cfdcd5;padding:8px 10px;width:25%}
.big{font-size:14px;font-weight:bold}.f td{border:0;padding:1px 0}.head td{border:0;padding:0}.tag{color:#a05a00}
.St-Present{color:#0f6b4a;font-weight:bold}.St-Absent{color:#b3261e;font-weight:bold}.St-Leave{color:#7a4fc4;font-weight:bold}.St-Days{color:#6b7a73;font-weight:bold}
</style></head><body>';
        $h .= '<table class="head"><tr><td>' . $logo . '</td><td class="r muted">Generated ' . $e((new DateTimeImmutable('now', $tz))->format($df . ' H:i')) . '<br>by ' . $e($u['name']) . '</td></tr></table>';
        $h .= '<h1 style="margin-top:14px">' . $e($ws['name']) . ' — Time report</h1>';
        $h .= '<table class="f">'; foreach ($filters as $k => $v) $h .= '<tr><td class="muted" style="width:90px">' . $e($k) . '</td><td>' . $e($v) . '</td></tr>'; $h .= '</table>';
        $h .= '<table class="cards" style="margin-top:12px"><tr>';
        foreach ([['Total work time', fmt_hm($r['seconds'])], ['Time entries', (string)$r['total']], ['Tasks worked', (string)$r['tasks']], ['Attendance days', (string)$r['attDays']]] as [$t, $v])
            $h .= '<td><div class="muted">' . $e($t) . '</div><div class="big">' . $e($v) . '</div></td>';
        $h .= '</tr></table>';
        if ($r['truncated']) $h .= '<p class="muted">This range has more than ' . number_format(Report::MAX_ROWS) . ' entries; only the newest are included.</p>';

        $h .= '<h2>Team summary</h2><table><tr><th>Member</th><th class="r">Entries</th><th class="r">Work time</th>' . ($showStatus ? '<th class="r">Present</th><th class="r">Absent</th><th class="r">Leave</th><th class="r">Days off</th>' : '') . '</tr>';
        foreach ($ids as $id) {
            $c = $count[$id] ?? null;
            $h .= '<tr><td>' . $e($names[$id] ?? 'Member') . '</td><td class="r">' . (int)($mem[$id]['n'] ?? 0) . '</td><td class="r">' . fmt_hm((int)($mem[$id]['s'] ?? 0)) . '</td>'
                . ($c ? '<td class="r">' . $c['present'] . '</td><td class="r">' . $c['absent'] . '</td><td class="r">' . $c['leave'] . '</td><td class="r">' . $c['off'] . '</td>' : '') . '</tr>';
        }
        $h .= '</table>';

        if ($r['projects']) {
            $h .= '<h2>Time by project</h2><table><tr><th>Project</th><th class="r">Work time</th></tr>';
            foreach ($r['projects'] as [$n, $s]) $h .= '<tr><td>' . $e($n) . '</td><td class="r">' . fmt_hm((int)$s) . '</td></tr>';
            $h .= '</table>';
        }

        if ($showStatus && $days) {
            $h .= '<h2>Daily attendance</h2><table><tr><th>Date</th><th>Member</th><th>Status</th><th class="r">Work time</th></tr>'; $n = 0; $cut = false;
            foreach ($ids as $id) foreach (array_reverse($days[$id] ?? [], true) as $d => [$st, $tag, $sec]) {
                if (++$n > self::DAY_ROWS) { $cut = true; break 2; }
                $h .= '<tr><td>' . $e($fd($d)) . ' <span class="muted">' . $e((new DateTimeImmutable($d))->format('D')) . '</span></td><td>' . $e($names[$id] ?? 'Member') . '</td><td><span class="St-' . strtok($st, ' ') . '">' . $e($st) . '</span>'
                    . ($tag ? ' <span class="tag">· ' . $e($tag) . '</span>' : '') . '</td><td class="r">' . ($sec ? fmt_hm($sec) : '—') . '</td></tr>';
            }
            $h .= '</table>'; if ($cut) $h .= '<p class="muted">Showing the first ' . self::DAY_ROWS . ' days.</p>';
        } elseif (!$showStatus) $h .= '<p class="muted">Daily attendance statuses are not shown when a work location filter is applied.</p>';

        $h .= '<h2>Time entries</h2>';
        if (!$r['items']) $h .= '<p class="muted">No saved time entries match these filters.</p>';
        else {
            $h .= '<table><tr><th>Date</th><th>Member</th><th>Project / Task</th><th>Work description</th><th class="r">Time</th></tr>';
            foreach ($r['items'] as $x)
                $h .= '<tr><td>' . $e($fd($x['day'])) . '</td><td>' . $e($x['member']) . '</td><td><b>' . $e($x['task_title']) . '</b><br><span class="muted">' . $e($x['project_name']) . '</span></td><td>'
                    . $e($x['work_description'] !== '' && $x['work_description'] !== null ? $x['work_description'] : '—') . '</td><td class="r">' . fmt_hm((int)$x['duration_seconds']) . '</td></tr>';
            $h .= '</table>';
        }
        return $h . '</body></html>';
    }
}
