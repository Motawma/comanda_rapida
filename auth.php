<?php
// auth.php - com suporte multi-tenant (empresa_id)

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$cookieParams = [
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
];
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params($cookieParams);
} else {
    session_set_cookie_params(0, '/', '', $cookieParams['secure'], $cookieParams['httponly']);
}
if (session_status() === PHP_SESSION_NONE) session_start();

function isLoggedIn(): bool {
    return !empty($_SESSION['user']) && is_array($_SESSION['user']);
}

function currentUser(): ?array {
    return isLoggedIn() ? $_SESSION['user'] : null;
}

function currentEmpresaId(): int {
    $user = currentUser();
    return (int)($user['empresa_id'] ?? 0);
}

function isMaster(): bool {
    $user = currentUser();
    return ($user['role'] ?? '') === 'master';
}

function loginAs(array $userRow): void {
    $_SESSION['user'] = [
        'id'         => (int)$userRow['id'],
        'username'   => $userRow['username'],
        'role'       => $userRow['role'],
        'empresa_id' => (int)($userRow['empresa_id'] ?? 0),
    ];
    if (function_exists('session_regenerate_id')) session_regenerate_id(true);
}

function logout(): void {
    unset($_SESSION['user']);
    if (function_exists('session_regenerate_id')) session_regenerate_id(true);
    if (session_status() !== PHP_SESSION_NONE) {
        $_SESSION = [];
        try { session_destroy(); } catch (Throwable $e) {}
    }
}

function requireAdminPage(): void {
    if (!isLoggedIn()) {
        header('Location: ./admin_login.php');
        exit;
    }
    $user = currentUser();
    if (!in_array($user['role'] ?? '', ['admin', 'master'])) {
        http_response_code(403);
        echo '<h3>Acesso negado</h3>';
        exit;
    }
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ./index.php');
        exit;
    }
}

function requireCaixaOrAdmin(): void {
    if (!isLoggedIn()) {
        header('Location: ./index.php');
        exit;
    }
    $user = currentUser();
    if (!in_array($user['role'] ?? '', ['admin', 'master', 'caixa'])) {
        http_response_code(403);
        echo '<!doctype html><html><head><meta charset="utf-8">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        </head><body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">
        <div class="text-center"><h3>🔒 Acesso restrito</h3>
        <p class="text-muted">Você não tem permissão para acessar o caixa.</p>
        <a href="index.php" class="btn btn-outline-secondary mt-2">Voltar</a>
        </div></body></html>';
        exit;
    }
}

function requireAdminOrRedirect(): void {
    if (!isLoggedIn()) {
        header('Location: ./index.php');
        exit;
    }
    $user = currentUser();
    if (!in_array($user['role'] ?? '', ['admin', 'master'])) {
        http_response_code(403);
        echo '<!doctype html><html><head><meta charset="utf-8">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        </head><body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">
        <div class="text-center"><h3>🔒 Acesso restrito</h3>
        <p class="text-muted">Apenas administradores podem acessar o caixa.</p>
        <a href="index.php" class="btn btn-outline-secondary mt-2">Voltar</a>
        </div></body></html>';
        exit;
    }
}

function requireAdminApi(): void {
    header('Content-Type: application/json; charset=utf-8');
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Não autenticado']);
        exit;
    }
    $user = currentUser();
    if (!in_array($user['role'] ?? '', ['admin', 'master'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Sem permissão']);
        exit;
    }
}

function requireCaixaApi(): void {
    header('Content-Type: application/json; charset=utf-8');
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Não autenticado']);
        exit;
    }
    $user = currentUser();
    if (!in_array($user['role'] ?? '', ['admin', 'master', 'caixa'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Sem permissão']);
        exit;
    }
}

function requireMasterApi(): void {
    header('Content-Type: application/json; charset=utf-8');
    if (!isLoggedIn() || !isMaster()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acesso restrito ao master']);
        exit;
    }
}
