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
    'GET /projects' => [ProjectController::class, 'index'], 'POST /projects/save' => [ProjectController::class, 'save'], 'POST /projects/archive' => [ProjectController::class, 'archive'], 'GET /project' => [ProjectController::class, 'show'], 'GET /task' => [TaskController::class, 'show'], 'POST /tasks/details' => [TaskController::class, 'saveDetails'],
    'GET /tasks' => [TaskController::class, 'index'], 'POST /tasks/save' => [TaskController::class, 'save'], 'POST /tasks/toggle' => [TaskController::class, 'toggle'], 'POST /tasks/delete' => [TaskController::class, 'delete'],
    'GET /time-tracking' => [TimeLogController::class, 'index'], 'GET /teams' => [TeamController::class, 'index'], 'POST /teams/invite' => [TeamController::class, 'invite'], 'POST /teams/role' => [TeamController::class, 'role'], 'POST /teams/manager' => [TeamController::class, 'manager'], 'POST /teams/status' => [TeamController::class, 'status'], 'POST /teams/invitation' => [TeamController::class, 'invitation'],
    'GET /invite' => [TeamController::class, 'showInvite'], 'POST /invite/accept' => [TeamController::class, 'accept'],
    'GET /attendance' => [AttendanceController::class, 'index'], 'GET /attendance/details' => [AttendanceController::class, 'details'],
    'GET /settings' => [SettingsController::class, 'root'], 'GET /settings/profile' => [SettingsController::class, 'profile'], 'GET /settings/security' => [SettingsController::class, 'security'], 'GET /settings/sessions' => [SettingsController::class, 'sessions'],
    'POST /settings/profile' => [SettingsController::class, 'saveProfile'], 'POST /settings/email/verify' => [SettingsController::class, 'verifyEmail'], 'POST /settings/email/resend' => [SettingsController::class, 'resendEmail'], 'POST /settings/email/cancel' => [SettingsController::class, 'cancelEmail'],
    'GET /settings/appearance' => [SettingsController::class, 'appearance'], 'POST /settings/appearance' => [SettingsController::class, 'saveAppearance'],
    'POST /settings/avatar' => [SettingsController::class, 'uploadPhoto'], 'POST /settings/avatar/remove' => [SettingsController::class, 'removePhoto'], 'GET /avatar' => [Avatar::class, 'serve'],
    'GET /settings/workspace' => fn() => redirect('/settings/workspace/general'), 'GET /settings/workspace/general' => [SettingsController::class, 'general'], 'POST /settings/workspace/general' => [SettingsController::class, 'saveGeneral'],
    'GET /settings/workspace/roles' => [SettingsController::class, 'roles'], 'GET /settings/workspace/access' => [SettingsController::class, 'access'],
    'GET /settings/workspace/email' => [SettingsController::class, 'email'], 'POST /settings/workspace/email' => [SettingsController::class, 'saveEmail'], 'POST /settings/workspace/email/test' => [SettingsController::class, 'testEmail'],
    'GET /settings/workspace/audit' => [SettingsController::class, 'audit'], 'GET /settings/workspace/audit/export' => [SettingsController::class, 'auditCsv'],
    'POST /settings/password' => [SettingsController::class, 'password'], 'POST /settings/sessions/revoke' => [SettingsController::class, 'revoke'], 'POST /settings/sessions/revoke-others' => [SettingsController::class, 'revokeOthers'],
    'POST /open-app' => [$A, 'openApp'], 'GET /auth/handoff' => [$A, 'webHandoff'],
];
foreach (array_keys(PageController::SOON) as $soonPath) if (!isset($routes["GET $soonPath"])) $routes["GET $soonPath"] = fn() => PageController::soon($soonPath);
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
