<?php
// auth.php
// Simple session-based auth helpers for admin area

// Configure session cookie params
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$cookieParams = [
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
];
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params($cookieParams);
} else {
    // fallback for older PHP (<7.3) - set httponly only
    session_set_cookie_params(0, '/', '', $cookieParams['secure'], $cookieParams['httponly']);
}
if (session_status() === PHP_SESSION_NONE) session_start();

function isLoggedIn(): bool {
    return !empty($_SESSION['user']) && is_array($_SESSION['user']);
}

function currentUser(): ?array {
    return isLoggedIn() ? $_SESSION['user'] : null;
}

function loginAs(array $userRow): void {
    // store lightweight user info in session
    $_SESSION['user'] = [
        'id' => (int)$userRow['id'],
        'username' => $userRow['username'],
        'role' => $userRow['role']
    ];
    // regenerate session id on login
    if (function_exists('session_regenerate_id')) session_regenerate_id(true);
}

function logout(): void {
    // clear session user
    unset($_SESSION['user']);
    if (function_exists('session_regenerate_id')) session_regenerate_id(true);
    // destroy session entirely
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
    if (($user['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo '<h3>Acesso negado</h3><p>Você não tem permissão para acessar esta página.</p>';
        exit;
    }
}

// Exige login (qualquer role: admin ou staff). Usado em comanda e cozinha.
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ./index.php');
        exit;
    }
}

// Exige login como admin. Usado no caixa.
function requireAdminOrRedirect(): void {
    if (!isLoggedIn()) {
        header('Location: ./index.php');
        exit;
    }
    $user = currentUser();
    if (($user['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo '<!doctype html><html><head><meta charset="utf-8"><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh"><div class="text-center"><h3>🔒 Acesso restrito</h3><p class="text-muted">Apenas administradores podem acessar o caixa.</p><a href="index.php" class="btn btn-outline-secondary mt-2">Voltar</a></div></body></html>';
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
    if (($user['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Sem permissão']);
        exit;
    }
}
