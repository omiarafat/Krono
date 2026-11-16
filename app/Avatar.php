<?php
/**
 * Profile photos. The uploaded picture is never stored as sent: GD decodes it, crops it square, shrinks it and saves a fresh JPEG
 * (this drops hidden metadata and anything that is not a real image). Files live in storage/avatars (not public) and are served by avatar().
 */
final class Avatar {
    const MAX_BYTES = 2097152, SIZE = 256, TYPES = ['image/jpeg' => 1, 'image/png' => 1, 'image/webp' => 1];

    private static function dir(): string { $d = ROOT . '/storage/avatars'; if (!is_dir($d)) @mkdir($d, 0775, true); return $d; }

    /** @return string '' on success, else a message the person can read */
    static function save(int $uid, array $file): string {
        if (!function_exists('imagecreatefromstring')) return 'Photo upload is not available on this server (PHP GD is off).';
        $code = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($code === UPLOAD_ERR_NO_FILE) return 'Choose a photo first.';
        if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE || (int)($file['size'] ?? 0) > self::MAX_BYTES) return 'That photo is larger than 2 MB. Choose a smaller one.';
        if ($code !== UPLOAD_ERR_OK || (!is_uploaded_file((string)$file['tmp_name']) && !defined('KRONO_TEST'))) return 'The photo could not be uploaded. Try again.';
        $tmp = (string)$file['tmp_name']; $info = @getimagesize($tmp);
        if (!$info || !isset(self::TYPES[$info['mime'] ?? ''])) return 'Use a JPG, PNG or WebP photo.';
        if ($info[0] * $info[1] > 40000000) return 'That photo is too large to process. Choose a smaller one.';
        $src = @imagecreatefromstring((string)file_get_contents($tmp));
        if (!$src) return 'That file is not a readable photo.';
        $w = imagesx($src); $h = imagesy($src); $side = min($w, $h);
        $dst = imagecreatetruecolor(self::SIZE, self::SIZE); imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), self::SIZE, self::SIZE, $side, $side);
        $name = $uid . '-' . bin2hex(random_bytes(6)) . '.jpg'; $path = self::dir() . '/' . $name;
        if (!imagejpeg($dst, $path, 86)) return 'The photo could not be saved. Try again.';
        $old = (string)Db::q('SELECT avatar FROM users WHERE id = ?', [$uid])->fetchColumn();
        Db::q('UPDATE users SET avatar = ? WHERE id = ?', [$name, $uid]);
        self::drop($old);
        return '';
    }

    static function remove(int $uid): void {
        $old = (string)Db::q('SELECT avatar FROM users WHERE id = ?', [$uid])->fetchColumn();
        Db::q('UPDATE users SET avatar = NULL WHERE id = ?', [$uid]); self::drop($old);
    }
    private static function drop(string $name): void { if ($name !== '' && preg_match('/^\d+-[0-9a-f]{12}\.jpg$/', $name)) @unlink(self::dir() . '/' . $name); }

    /** Web address of a person's photo, or '' when they have none. The ?v= part makes browsers fetch a new photo at once. */
    static function url(int $uid, ?string $file): string { return $file ? url('/avatar?u=' . $uid . '&v=' . substr(md5($file), 0, 8)) : ''; }

    /** Circle with the photo, or with the first letter of the name. $size = Tailwind size classes, $text = letter size class. */
    static function html(int $uid, string $name, ?string $file, string $size = 'h-8.5 w-8.5', string $text = 'text-xs'): string {
        $u = self::url($uid, $file);
        if ($u !== '') return '<img class="' . $size . ' shrink-0 rounded-full object-cover" src="' . e($u) . '" alt="' . e($name) . '" width="64" height="64" loading="lazy">';
        $ch = mb_strtoupper(mb_substr(trim($name) !== '' ? trim($name) : '?', 0, 1));
        return '<b class="inline-flex ' . $size . ' shrink-0 items-center justify-center rounded-full bg-soft ' . $text . ' font-extrabold text-brand" aria-hidden="true">' . e($ch) . '</b>';
    }

    /** GET /avatar?u=ID : only people signed in to the same workspace (or the owner of the photo) may see it. */
    static function serve(): void {
        $me = Auth::user(); $uid = (int)($_GET['u'] ?? 0);
        if (!$me || $uid < 1) { http_response_code(404); exit; }
        if ($uid !== (int)$me['id'] && !Db::q('SELECT 1 FROM workspace_members a JOIN workspace_members b ON b.workspace_id = a.workspace_id WHERE a.user_id = ? AND b.user_id = ? LIMIT 1', [(int)$me['id'], $uid])->fetchColumn()) { http_response_code(404); exit; }
        $f = (string)Db::q('SELECT avatar FROM users WHERE id = ?', [$uid])->fetchColumn(); $p = self::dir() . '/' . $f;
        if ($f === '' || !preg_match('/^\d+-[0-9a-f]{12}\.jpg$/', $f) || !is_file($p)) { http_response_code(404); exit; }
        header('Content-Type: image/jpeg'); header('Content-Length: ' . filesize($p)); header('Cache-Control: private, max-age=86400'); header('X-Content-Type-Options: nosniff');
        readfile($p); exit;
    }
}
