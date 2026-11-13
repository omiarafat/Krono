<?php
/** Small view helpers shared by pages: status / priority badges, avatars, friendly dates. One place, so every page looks the same. */
final class Ui {
    /** Badge tones (css .tag-<tone>). Only for Status / Priority (approved exception); the text label is always shown. */
    private const TONE = [
        'project' => ['upcoming' => 'violet', 'planning' => 'blue', 'in_progress' => 'green', 'on_hold' => 'red', 'completed' => 'lime', 'archived' => 'gray'],
        'task' => ['todo' => 'gray', 'in_progress' => 'green', 'review' => 'amber', 'on_hold' => 'red', 'completed' => 'lime'],
        'priority' => ['high' => 'red', 'medium' => 'amber', 'normal' => 'blue', 'low' => 'lime'],
    ];

    /** Tone name for a dropdown row (data-tag): the row and the closed select then show the same badge. */
    static function tone(string $kind, string $key): string { return self::TONE[$kind][$key] ?? 'gray'; }

    /** @param string $label text shown (defaults to the key's normal label) */
    static function tag(string $kind, string $key, ?string $label = null): string {
        $label ??= ($kind === 'project' ? (Project::STATUS[$key] ?? ucfirst($key)) : ($kind === 'task' ? (Task::STATUS[$key] ?? ucfirst($key)) : (Project::PRIORITY[$key] ?? ucfirst($key))));
        return '<span class="tag tag-' . self::tone($kind, $key) . '">' . e($label) . '</span>';
    }

    /** Round avatar with initials. $size = Tailwind size classes, e.g. "h-6 w-6". */
    static function avatar(string $name, string $size = 'h-6 w-6'): string {
        return '<b class="inline-flex ' . $size . ' shrink-0 items-center justify-center rounded-full bg-soft text-nano font-bold text-brand" title="' . e($name) . '">' . e(Project::initials($name)) . '</b>';
    }

    /** "Oct 22, 2026" for a Y-m-d date (no timezone shift: it is a plain calendar date). */
    static function date(?string $ymd): string { return $ymd ? date('M d, Y', strtotime($ymd . ' 12:00:00')) : '—'; }

    /** "just now", "2h ago", "yesterday", else "Oct 19, 2026". $utc is a UTC DATETIME string. */
    static function ago(string $utc): string {
        $d = time() - strtotime($utc . ' UTC');
        if ($d < 60) return 'just now';
        if ($d < 3600) return intdiv($d, 60) . 'm ago';
        if ($d < 86400) return intdiv($d, 3600) . 'h ago';
        if ($d < 172800) return 'yesterday';
        if ($d < 2592000) return intdiv($d, 86400) . 'd ago';
        return self::date(substr($utc, 0, 10));
    }

    /** "Today at 10:42 AM", "Yesterday at 4:18 PM", "Oct 19, 2026 · 3:25 PM" in the workspace timezone. */
    static function when(string $utc, DateTimeZone $tz): string {
        $t = (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone($tz); $today = (new DateTimeImmutable('now', $tz))->setTime(0, 0);
        $days = (int)$today->diff($t->setTime(0, 0))->format('%r%a');
        $clock = $t->format('g:i A');
        return $days === 0 ? "Today at $clock" : ($days === -1 ? "Yesterday at $clock" : $t->format('M d, Y') . ' · ' . $clock);
    }

    /** Due label for a task: Today / Tomorrow / "Oct 24" (year added when it is not this year). @return array{0:string,1:bool} [label, overdue] */
    static function due(?string $ymd, DateTimeZone $tz, bool $done = false): array {
        if (!$ymd) return ['—', false];
        $today = new DateTimeImmutable('today', $tz); $d = new DateTimeImmutable($ymd, $tz);
        $days = (int)$today->diff($d)->format('%r%a');
        $label = $days === 0 ? 'Today' : ($days === 1 ? 'Tomorrow' : $d->format($d->format('Y') === $today->format('Y') ? 'M d' : 'M d, Y'));
        return [$label, $days < 0 && !$done];
    }

    /** "Oct 19, 2026" for a UTC DATETIME, shown in the workspace timezone. */
    static function day(string $utc, DateTimeZone $tz): string { return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone($tz)->format('M d, Y'); }

    /** Minutes as "12h", "1h 30m", "45m" (estimates). */
    static function minutes(int $m): string { $h = intdiv($m, 60); $r = $m % 60; return $h && $r ? "{$h}h {$r}m" : ($h ? "{$h}h" : "{$r}m"); }

    /** "Today", "In 1 day", "3 days overdue" for a due date. @return array{0:string,1:bool} [text, overdue] */
    static function dueIn(?string $ymd, DateTimeZone $tz): array {
        if (!$ymd) return ['No due date', false];
        $days = (int)(new DateTimeImmutable('today', $tz))->diff(new DateTimeImmutable($ymd, $tz))->format('%r%a');
        if ($days === 0) return ['Due today', false];
        if ($days > 0) return ['In ' . $days . ' day' . ($days === 1 ? '' : 's'), false];
        return [-$days . ' day' . ($days === -1 ? '' : 's') . ' overdue', true];
    }
}
