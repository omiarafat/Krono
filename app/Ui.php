<?php
/** Small view helpers shared by pages: status / priority badges, avatars, friendly dates. One place, so every page looks the same. */
final class Ui {
    /** Badge colors: [background, dot]. Only used for Status / Priority (approved exception); the text label is always shown. */
    private const TAG = [
        'project' => [
            'upcoming' => ['bg-tag-violet/14', 'bg-tag-violet'], 'planning' => ['bg-tag-blue/14', 'bg-tag-blue'], 'in_progress' => ['bg-tag-green/14', 'bg-tag-green'],
            'on_hold' => ['bg-tag-red/14', 'bg-tag-red'], 'completed' => ['bg-tag-lime/14', 'bg-tag-lime'], 'archived' => ['bg-tag-gray/14', 'bg-tag-gray'],
        ],
        'task' => [
            'todo' => ['bg-tag-gray/14', 'bg-tag-gray'], 'in_progress' => ['bg-tag-green/14', 'bg-tag-green'], 'review' => ['bg-tag-amber/14', 'bg-tag-amber'],
            'on_hold' => ['bg-tag-red/14', 'bg-tag-red'], 'completed' => ['bg-tag-lime/14', 'bg-tag-lime'],
        ],
        'priority' => [
            'high' => ['bg-tag-red/14', 'bg-tag-red'], 'medium' => ['bg-tag-amber/14', 'bg-tag-amber'], 'normal' => ['bg-tag-blue/14', 'bg-tag-blue'], 'low' => ['bg-tag-lime/14', 'bg-tag-lime'],
        ],
    ];

    /** Dot color class, for the colored dot in dropdown rows (data-dot). */
    static function dot(string $kind, string $key): string { return self::TAG[$kind][$key][1] ?? 'bg-tag-gray'; }

    /** @param string $label text shown (defaults to the key's normal label) */
    static function tag(string $kind, string $key, ?string $label = null): string {
        [$bg, $dot] = self::TAG[$kind][$key] ?? ['bg-tag-gray/14', 'bg-tag-gray'];
        $label ??= ($kind === 'project' ? (Project::STATUS[$key] ?? ucfirst($key)) : ($kind === 'task' ? (Task::STATUS[$key] ?? ucfirst($key)) : (Project::PRIORITY[$key] ?? ucfirst($key))));
        return '<span class="tag ' . $bg . '"><i class="h-1.5 w-1.5 shrink-0 rounded-full ' . $dot . '"></i>' . e($label) . '</span>';
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
}
