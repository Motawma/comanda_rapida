<?php
// api/pedido_atualizar_cliente.php
// Salva fiado_nome e fiado_telefone no pedido (campos opcionais do fiado)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireAdminApi();

$input     = json_decode(file_get_contents('php://input'), true);
$pedidoId  = (int)($input['pedido_id'] ?? 0);
$nome      = trim((string)($input['fiado_nome']      ?? ''));
$telefone  = trim((string)($input['fiado_telefone']  ?? ''));
$empresaId = currentEmpresaId();

if ($pedidoId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'pedido_id inválido']);
    exit;
}

try {
    $pdo = getPDO();

    // Garante que as colunas existem (cria silenciosamente se não existirem)
    try {
        $pdo->exec("ALTER TABLE pedidos ADD COLUMN fiado_nome VARCHAR(100) NULL");
    } catch (Throwable $e) { /* já existe */ }
    try {
        $pdo->exec("ALTER TABLE pedidos ADD COLUMN fiado_telefone VARCHAR(20) NULL");
    } catch (Throwable $e) { /* já existe */ }

    // Verifica que o pedido pertence à empresa
    $chk = $pdo->prepare("SELECT id FROM pedidos WHERE id = ? AND empresa_id = ? LIMIT 1");
    $chk->execute([$pedidoId, $empresaId]);
    if (!$chk->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pedido não encontrado']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE pedidos SET fiado_nome = ?, fiado_telefone = ? WHERE id = ? AND empresa_id = ?");
    $stmt->execute([
        $nome     !== '' ? $nome     : null,
        $telefone !== '' ? $telefone : null,
        $pedidoId,
        $empresaId,
    ]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar dados do cliente']);
}
