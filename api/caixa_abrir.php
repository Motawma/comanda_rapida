<?php
// api/caixa_abrir.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireAdminApi();

$input = json_decode(file_get_contents('php://input'), true);
$opening_cash = isset($input['opening_cash']) ? (float)$input['opening_cash'] : 0.0;
$obs = isset($input['obs']) ? trim((string)$input['obs']) : null;

try {
    $pdo = getPDO();
    // Verifica se já existe sessão aberta
    $stmt = $pdo->prepare("SELECT id FROM caixa_sessoes WHERE closed_at IS NULL ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $r = $stmt->fetch();
    if ($r) {
        echo json_encode(['success' => false, 'message' => 'Já existe uma sessão aberta', 'sessao_id' => (int)$r['id']]);
        exit;
    }

    $user = currentUser();
    $userId = $user['id'] ?? null;

    $ins = $pdo->prepare("INSERT INTO caixa_sessoes (opening_cash, obs, user_id) VALUES (?, ?, ?)");
    $ins->execute([$opening_cash, $obs, $userId]);
    $sessaoId = (int)$pdo->lastInsertId();

    echo json_encode(['success' => true, 'sessao_id' => $sessaoId]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao abrir caixa']);
    exit;
}
