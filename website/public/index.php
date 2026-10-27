<?php
require dirname(__DIR__) . '/app/bootstrap.php';

$path = '/' . trim(substr((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), strlen(base_path())), '/');
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && !csrf_check()) { http_response_code(419); exit('Your session expired. Go back, refresh the page and try again.'); }

$A = AuthController::class;
$routes = [
    'GET /' => fn() => redirect(Auth::user() ? '/dashboard' : '/login'),
    'GET /login' => [$A, 'showLogin'], 'POST /login' => [$A, 'login'],
    'GET /register' => [$A, 'showRegister'], 'POST /register' => [$A, 'register'],
    'GET /verify' => [$A, 'showVerify'], 'POST /verify' => [$A, 'verify'], 'POST /verify/resend' => [$A, 'resend'],
    'POST /logout' => [$A, 'logout'], 'GET /dashboard' => [$A, 'dashboard'],
];
$h = $routes["$method $path"] ?? null;
if (!$h) { http_response_code(404); render('errors/404', ['title' => 'Page not found']); exit; }
try { call_user_func($h); }
catch (Throwable $e) { error_log((string)$e); http_response_code(500); render('errors/500', ['title' => 'Something went wrong', 'detail' => env('APP_ENV') === 'local' ? $e->getMessage() : '']); }
