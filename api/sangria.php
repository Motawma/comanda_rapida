<?php
// api/sangria.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireAdminApi();

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$valor  = isset($input['valor'])  ? (float)$input['valor']          : 0;
$motivo = isset($input['motivo']) ? trim((string)$input['motivo'])   : '';

if ($valor <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valor inválido.']);
    exit;
}
if ($motivo === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Motivo é obrigatório.']);
    exit;
}

try {
    $pdo       = getPDO();
    $empresaId = currentEmpresaId();
    $user      = currentUser();
    $userId    = $user['id']       ?? null;
    $userName  = $user['username'] ?? 'desconhecido';

    // Verifica sessão aberta
    $stmt = $pdo->prepare("SELECT id FROM caixa_sessoes WHERE closed_at IS NULL AND empresa_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$empresaId]);
    $sessao = $stmt->fetch();
    if (!$sessao) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nenhuma sessão de caixa aberta.']);
        exit;
    }
    $sessaoId = (int)$sessao['id'];

    // Insere sangria
    $ins = $pdo->prepare("
        INSERT INTO sangrias (caixa_sessao_id, empresa_id, user_id, user_nome, valor, motivo, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $ins->execute([$sessaoId, $empresaId, $userId, $userName, $valor, $motivo]);

    echo json_encode([
        'success'   => true,
        'message'   => 'Sangria registrada com sucesso.',
        'valor'     => $valor,
        'motivo'    => $motivo,
        'operador'  => $userName,
        'horario'   => date('d/m/Y H:i:s'),
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao registrar sangria: ' . $e->getMessage()]);
}
