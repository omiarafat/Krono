<?php
declare(strict_types=1);
define('ROOT', dirname(__DIR__));
spl_autoload_register(function (string $c): void { $f = ROOT . '/app/' . str_replace('\\', '/', $c) . '.php'; if (is_file($f)) require $f; });

$GLOBALS['env'] = [];
foreach (@file(ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $l) {
    if ($l[0] === '#' || !str_contains($l, '=')) continue;
    [$k, $v] = explode('=', $l, 2); $GLOBALS['env'][trim($k)] = trim($v, " \t\"'");
}
function env(string $k, ?string $d = null): ?string { return $GLOBALS['env'][$k] ?? $d; }

date_default_timezone_set('UTC'); // storage and logic in UTC; display zone = APP_TIMEZONE / workspace setting
ini_set('display_errors', env('APP_ENV') === 'local' ? '1' : '0');
ini_set('log_errors', '1'); ini_set('error_log', ROOT . '/storage/logs/php-error.log'); error_reporting(E_ALL);

if (PHP_SAPI !== 'cli') {
    header('X-Frame-Options: DENY'); header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: same-origin');
    session_name('krono_sid');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']), 'path' => '/']);
    session_start();
}

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function base_path(): string { return rtrim((string)parse_url((string)env('APP_URL', ''), PHP_URL_PATH), '/'); }
function url(string $p = '/'): string { return rtrim((string)env('APP_URL', ''), '/') . $p; }
function asset(string $p): string { return url('/assets/' . $p); }
function redirect(string $p): never { header('Location: ' . url($p)); exit; }
function icon(string $n): string { static $i = null; $i ??= require ROOT . '/app/icons.php'; return '<svg class="ico" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($i[$n] ?? '') . '</svg>'; }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): bool { return isset($_POST['_csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['_csrf']); }
function old(string $k): string { return e((string)($_POST[$k] ?? '')); }
function flash(string $type, string $msg): void { $_SESSION['flash'] = [$type, $msg]; }
function flash_html(): string { if (empty($_SESSION['flash'])) return ''; [$t, $m] = $_SESSION['flash']; unset($_SESSION['flash']); return '<div class="mt-3.5 rounded-xl border px-3 py-2.5 text-xs leading-[1.45] ' . ($t === 'error' ? 'border-danger/35 bg-danger/8 text-danger' : 'border-line bg-soft text-brand') . '" role="status">' . e($m) . '</div>'; }
function errors_html(array $errs): string { return $errs ? '<div class="mt-3.5 rounded-xl border border-danger/35 bg-danger/8 px-3 py-2.5 text-xs leading-[1.45] text-danger" role="alert">' . e(implode(' ', $errs)) . '</div>' : ''; }
function render(string $view, array $d = [], string $layout = 'auth'): void {
    extract($d, EXTR_SKIP);
    ob_start(); require ROOT . "/app/Views/$view.php"; $content = ob_get_clean();
    require ROOT . "/app/Views/layouts/$layout.php";
}
