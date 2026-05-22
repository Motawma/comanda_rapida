<?php
// api/resetar_senha.php — Valida token e salva nova senha
// Endpoint público — não exige autenticação nem CSRF
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input    = json_decode(file_get_contents('php://input'), true) ?: [];
$token    = trim($input['token']    ?? '');
$password = trim($input['password'] ?? '');

if (!$token) {
    echo json_encode(['success' => false, 'message' => 'Token não informado.']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'A senha deve ter no mínimo 6 caracteres.']);
    exit;
}

try {
    $pdo = getPDO();

    // Busca token válido (não usado e não expirado)
    $stmt = $pdo->prepare("SELECT id, user_id, email FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    if (!$reset) {
        echo json_encode(['success' => false, 'message' => 'Link expirado ou inválido. Solicite um novo.']);
        exit;
    }

    // Atualiza senha do usuário
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
        ->execute([$hash, $reset['user_id']]);

    // Marca este token como usado
    $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")
        ->execute([$reset['id']]);

    // Invalida todos os outros tokens do mesmo email
    $pdo->prepare("UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0")
        ->execute([$reset['email']]);

    echo json_encode(['success' => true, 'message' => 'Senha alterada com sucesso!']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
}
