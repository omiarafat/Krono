<?php
require dirname(__DIR__) . '/app/bootstrap.php';

$path = '/' . trim(substr((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), strlen(base_path())), '/');
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && !is_api_request() && !csrf_check()) { http_response_code(419); exit('Your session expired. Go back, refresh the page and try again.'); }

$A = AuthController::class;
$routes = [
    'GET /' => fn() => redirect(Auth::user() ? '/dashboard' : '/login'),
    'GET /login' => [$A, 'showLogin'], 'POST /login' => [$A, 'login'],
    'GET /register' => [$A, 'showRegister'], 'POST /register' => [$A, 'register'],
    'GET /verify' => [$A, 'showVerify'], 'POST /verify' => [$A, 'verify'], 'POST /verify/resend' => [$A, 'resend'],
    'POST /logout' => [$A, 'logout'], 'GET /dashboard' => [DashboardController::class, 'index'],
    'POST /open-app' => [$A, 'openApp'], 'GET /auth/handoff' => [$A, 'webHandoff'],
];
foreach (array_keys(PageController::SOON) as $soonPath) $routes["GET $soonPath"] = fn() => PageController::soon($soonPath);
// Desktop app API (JSON, Bearer token). Same account database as the website.
$P = ApiController::class;
$api = [
    'POST /api/auth/register' => 'register', 'POST /api/auth/verify' => 'verify', 'POST /api/auth/resend' => 'resend',
    'POST /api/auth/login' => 'login', 'POST /api/auth/handoff' => 'handoff', 'POST /api/auth/logout' => 'logout',
    'POST /api/auth/web-link' => 'webLink', 'GET /api/me' => 'me',
];
if (is_api_request()) { ApiController::run(fn() => call_user_func([$P, $api["$method $path"] ?? 'notFound'])); exit; }
$h = $routes["$method $path"] ?? null;
if (!$h) { http_response_code(404); render('errors/404', ['title' => 'Page not found']); exit; }
try { call_user_func($h); }
catch (Throwable $e) { error_log((string)$e); http_response_code(500); render('errors/500', ['title' => 'Something went wrong', 'detail' => env('APP_ENV') === 'local' ? $e->getMessage() : '']); }
