<?php
// api/admin_delete_user.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireAdminApi();

$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? (int)$input['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'id inválido']);
    exit;
}

try {
    $pdo = getPDO();

    // verifica existência
    $stmt = $pdo->prepare("SELECT id, role, active FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        exit;
    }

    $current = currentUser();
    $currentId = $current['id'] ?? 0;
    if ($currentId && $currentId === (int)$user['id']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Não é possível excluir o próprio usuário']);
        exit;
    }

    // se for admin, verificar se é o último admin ativo
    if ($user['role'] === 'admin') {
        $cstmt = $pdo->prepare("SELECT COUNT(*) AS c FROM users WHERE role = 'admin' AND active = 1");
        $cstmt->execute();
        $cr = $cstmt->fetch();
        $count = (int)($cr['c'] ?? 0);
        if ($count <= 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Não é possível excluir o último admin ativo']);
            exit;
        }
    }

    // tudo ok: deletar
    $d = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $d->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Usuário excluído']);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir usuário']);
    exit;
}
