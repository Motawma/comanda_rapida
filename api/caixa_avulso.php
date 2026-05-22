<?php
/**
 * api/caixa_avulso.php — Registra Reforço no caixa.
 * (Sangria usa api/sangria.php existente)
 *
 * POST JSON:
 *   { "tipo": "REFORCO", "valor": 50.00, "obs": "troco inicial" }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireAdminApi();

$in    = json_decode(file_get_contents('php://input'), true) ?: [];
$tipo  = strtoupper(trim((string)($in['tipo'] ?? '')));
$valor = (float)($in['valor'] ?? 0);
$obs   = trim((string)($in['obs'] ?? ''));

if (!in_array($tipo, ['SANGRIA', 'REFORCO'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tipo inválido.']);
    exit;
}

if ($valor <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valor deve ser maior que zero.']);
    exit;
}

try {
    $pdo = getPDO();

    // Busca sessão aberta (closed_at IS NULL)
    $stmt = $pdo->prepare("SELECT id FROM caixa_sessoes WHERE closed_at IS NULL ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $sessao = $stmt->fetch();

    if (!$sessao) {
        echo json_encode(['success' => false, 'message' => 'Nenhum caixa aberto. Abra o caixa antes de registrar avulsos.']);
        exit;
    }

    $sessaoId = (int)$sessao['id'];
    $user     = currentUser();
    $userId   = $user['id'] ?? null;

    // Cria tabela se não existir
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS caixa_movimentos (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            sessao_id  INT NOT NULL,
            user_id    INT NULL,
            tipo       ENUM('SANGRIA','REFORCO') NOT NULL,
            valor      DECIMAL(10,2) NOT NULL,
            obs        VARCHAR(500) NULL,
            criado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sessao (sessao_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $ins = $pdo->prepare("INSERT INTO caixa_movimentos (sessao_id, user_id, tipo, valor, obs) VALUES (?, ?, ?, ?, ?)");
    $ins->execute([$sessaoId, $userId, $tipo, $valor, $obs ?: null]);

    $label = $tipo === 'SANGRIA' ? 'Sangria' : 'Reforço';
    echo json_encode([
        'success' => true,
        'id'      => (int)$pdo->lastInsertId(),
        'tipo'    => $tipo,
        'valor'   => $valor,
        'message' => "$label de R$ " . number_format($valor, 2, ',', '.') . " registrado com sucesso.",
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
