<?php
// api/admin_list_users.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireAdminApi();

try {
    $pdo = getPDO();
    $stmt = $pdo->query("SELECT id, username, role, active, created_at FROM users ORDER BY created_at DESC");
    $rows = $stmt->fetchAll();
    echo json_encode(['success' => true, 'users' => $rows]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao listar usuários']);
    exit;
}
