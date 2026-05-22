<?php
// api/venda_balcao.php — Venda direta no balcão: cria pedido já como PAGO
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireAdminApi();

$input      = json_decode(file_get_contents('php://input'), true) ?: [];
$empresaId  = currentEmpresaId();
$itens      = $input['itens'] ?? [];
$metodo     = strtoupper(trim($input['metodo_pagamento'] ?? ''));
$obs        = trim($input['obs'] ?? '');

$metodosValidos = ['DINHEIRO', 'PIX', 'CREDITO', 'DEBITO', 'MISTO'];
if (empty($itens) || !is_array($itens)) {
    echo json_encode(['ok' => false, 'message' => 'Nenhum item informado']); exit;
}
if (!in_array($metodo, $metodosValidos)) {
    echo json_encode(['ok' => false, 'message' => 'Método de pagamento inválido']); exit;
}

$pdo = getPDO();

// Sessão de caixa aberta
$sc = $pdo->prepare("SELECT id FROM caixa_sessoes WHERE empresa_id = ? AND closed_at IS NULL ORDER BY id DESC LIMIT 1");
$sc->execute([$empresaId]);
$sessaoId = $sc->fetchColumn() ?: null;
if (!$sessaoId) {
    echo json_encode(['ok' => false, 'message' => 'Nenhuma sessão de caixa aberta']); exit;
}

// Garante colunas antes da transação
try { $pdo->exec("ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS metodo_pagamento VARCHAR(20) DEFAULT NULL"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE itens_pedido ADD COLUMN IF NOT EXISTS obs TEXT DEFAULT NULL"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE itens_pedido ADD COLUMN IF NOT EXISTS item_status VARCHAR(20) DEFAULT 'PENDENTE'"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS origem VARCHAR(50) DEFAULT NULL"); } catch (Throwable $e) {}

// Valida e monta itens
$itensMontados = [];
$total = 0.0;
$pdoCheck = $pdo;
foreach ($itens as $item) {
    $prodId = (int)($item['produto_id'] ?? 0);
    $qtd    = max(1, (int)($item['quantidade'] ?? 1));
    if (!$prodId) continue;
    $sp = $pdo->prepare("SELECT id, nome, preco FROM produtos WHERE id = ? AND empresa_id = ? AND ativo = 1 LIMIT 1");
    $sp->execute([$prodId, $empresaId]);
    $prod = $sp->fetch();
    if (!$prod) continue;
    $subtotal = (float)$prod['preco'] * $qtd;
    $total   += $subtotal;
    $itensMontados[] = [
        'produto_id' => $prodId,
        'nome'       => $prod['nome'],
        'qtd'        => $qtd,
        'preco_unit' => (float)$prod['preco'],
        'subtotal'   => $subtotal,
        'obs'        => trim($item['obs'] ?? ''),
    ];
}

if (empty($itensMontados)) {
    echo json_encode(['ok' => false, 'message' => 'Nenhum produto válido encontrado']); exit;
}

try {
    $pdo->beginTransaction();

    // Cria pedido já como PAGO
    $ip = $pdo->prepare("INSERT INTO pedidos (mesa, status, total, impresso, caixa_sessao_id, empresa_id, metodo_pagamento, origem)
                         VALUES ('Balcão', 'PAGO', ?, 0, ?, ?, ?, 'balcao')");
    $ip->execute([$total, $sessaoId, $empresaId, $metodo]);
    $pedidoId = (int)$pdo->lastInsertId();

    // Insere itens
    $ii = $pdo->prepare("INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unit, subtotal, obs, item_status, empresa_id)
                         VALUES (?, ?, ?, ?, ?, ?, 'ENTREGUE', ?)");
    foreach ($itensMontados as $it) {
        $ii->execute([$pedidoId, $it['produto_id'], $it['qtd'], $it['preco_unit'], $it['subtotal'], $it['obs'] ?: null, $empresaId]);
    }

    $pdo->commit();

    echo json_encode([
        'ok'        => true,
        'pedido_id' => $pedidoId,
        'total'     => $total,
        'message'   => 'Venda registrada com sucesso',
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Erro ao registrar venda: ' . $e->getMessage()]);
}
