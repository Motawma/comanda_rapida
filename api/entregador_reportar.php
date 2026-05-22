<?php
// api/entregador_reportar.php — registra problema de entrega no pedido (sem auth, protegido por slug)
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../conexao.php';

$input         = json_decode(file_get_contents('php://input'), true) ?? [];
$slug          = trim((string)($input['slug']          ?? ''));
$pedidoId      = (int)($input['pedido_id']    ?? 0);
$descricao     = trim((string)($input['descricao']     ?? ''));
$entregadorNome= trim((string)($input['entregador_nome'] ?? ''));

if (!$slug || !$pedidoId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Parâmetros inválidos.']);
    exit;
}

try {
    $pdo = getPDO();

    $se = $pdo->prepare("SELECT id FROM empresas WHERE slug = ? LIMIT 1");
    $se->execute([$slug]);
    $emp = $se->fetch();
    if (!$emp) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Empresa não encontrada.']);
        exit;
    }
    $empresaId = (int)$emp['id'];

    // Garante que o pedido pertence à empresa
    $sp = $pdo->prepare("SELECT id FROM pedidos WHERE id = ? AND empresa_id = ? LIMIT 1");
    $sp->execute([$pedidoId, $empresaId]);
    if (!$sp->fetch()) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Pedido não encontrado.']);
        exit;
    }

    // Adiciona coluna se não existir
    try { $pdo->exec("ALTER TABLE pedidos ADD COLUMN problema_entrega TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE pedidos ADD COLUMN problema_descricao TEXT NULL"); } catch (Throwable $e) {}

    $texto = $descricao;
    if ($entregadorNome) $texto = "Entregador: {$entregadorNome}\n" . $texto;

    $pdo->prepare("UPDATE pedidos SET problema_entrega = 1, problema_descricao = ? WHERE id = ? AND empresa_id = ?")
        ->execute([$texto, $pedidoId, $empresaId]);

    echo json_encode(['ok' => true]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
