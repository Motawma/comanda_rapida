<?php
// api/cardapio_pedido.php — API PÚBLICA: recebe pedido do cardápio digital
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']); exit;
}

require_once __DIR__ . '/../conexao.php';

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$slug           = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($body['slug'] ?? '')));
$tipo           = in_array($body['tipo'] ?? '', ['mesa','delivery','retirada']) ? $body['tipo'] : 'mesa';
$mesa           = trim($body['mesa'] ?? '');
$clienteNome    = trim($body['cliente_nome'] ?? '');
$clienteWhats   = preg_replace('/\D/', '', $body['cliente_whatsapp'] ?? '');
$clienteEndr    = trim($body['cliente_endereco'] ?? '');
$formaPgto      = trim($body['forma_pagamento'] ?? '');
$obs            = trim($body['observacoes'] ?? '');
$clienteGoogleId= preg_replace('/[^0-9]/', '', $body['cliente_google_id'] ?? '');
$itens          = $body['itens'] ?? [];

// Validações básicas
if (!$slug) { echo json_encode(['success'=>false,'message'=>'Slug inválido.']); exit; }
if (!$clienteNome) { echo json_encode(['success'=>false,'message'=>'Informe seu nome.']); exit; }
if (empty($itens) || !is_array($itens)) { echo json_encode(['success'=>false,'message'=>'Pedido sem itens.']); exit; }
if ($tipo === 'delivery' && !$clienteEndr) { echo json_encode(['success'=>false,'message'=>'Informe o endereço de entrega.']); exit; }

try {
    $pdo = getPDO();

    // ── Busca empresa ────────────────────────────────────────────────────────
    $stmt = $pdo->prepare("SELECT id, nome, cardapio_ativo, cardapio_delivery, cardapio_mesa,
                                  cardapio_taxa_entrega, cardapio_whatsapp, cardapio_pix_chave
                           FROM empresas WHERE slug = ? AND ativo = 1 LIMIT 1");
    $stmt->execute([$slug]);
    $empresa = $stmt->fetch();

    if (!$empresa) { echo json_encode(['success'=>false,'message'=>'Estabelecimento não encontrado.']); exit; }
    if (!$empresa['cardapio_ativo']) { echo json_encode(['success'=>false,'message'=>'Cardápio indisponível no momento.']); exit; }
    if ($tipo === 'delivery' && !$empresa['cardapio_delivery']) { echo json_encode(['success'=>false,'message'=>'Entrega não disponível.']); exit; }

    $empresaId = (int)$empresa['id'];

    // ── Garante coluna origem em pedidos ─────────────────────────────────────
    try { $pdo->exec("ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS origem VARCHAR(50) DEFAULT NULL"); }
    catch (Throwable $e) {}

    // ── Valida e monta itens ─────────────────────────────────────────────────
    $itensMontados = [];
    $totalPedido   = 0;

    foreach ($itens as $item) {
        $prodId = (int)($item['produto_id'] ?? 0);
        $qtd    = max(1, (int)($item['quantidade'] ?? 1));
        if (!$prodId) continue;

        // Verifica se produto pertence à empresa e está ativo/disponível
        $sp = $pdo->prepare("SELECT id, nome, preco FROM produtos WHERE id = ? AND empresa_id = ? AND ativo = 1 LIMIT 1");
        $sp->execute([$prodId, $empresaId]);
        $prod = $sp->fetch();
        if (!$prod) continue;

        // Monta obs com escolhas
        $escolhasTexto = [];
        $adicionalTotal = 0;
        $escolhasData   = $item['escolhas'] ?? [];

        if (!empty($escolhasData) && is_array($escolhasData)) {
            foreach ($escolhasData as $titulo => $opcoes) {
                if (!is_array($opcoes)) $opcoes = [$opcoes];
                // Busca preços adicionais das opções escolhidas
                foreach ($opcoes as $nomeOpc) {
                    $so = $pdo->prepare("
                        SELECT o.preco_adicional FROM produto_escolha_opcoes o
                        JOIN produto_escolhas e ON e.id = o.escolha_id
                        WHERE e.produto_id = ? AND o.nome = ? LIMIT 1
                    ");
                    $so->execute([$prodId, $nomeOpc]);
                    $adicionalTotal += (float)($so->fetchColumn() ?: 0);
                }
                if (!empty($opcoes)) {
                    $escolhasTexto[] = $titulo . ': ' . implode(', ', $opcoes);
                }
            }
        }

        $obsItem = trim($item['obs'] ?? '');
        $obsCompleta = implode(' | ', array_filter(array_merge($escolhasTexto, $obsItem ? [$obsItem] : [])));

        $precoUnit = (float)$prod['preco'] + $adicionalTotal;
        $subtotal  = $precoUnit * $qtd;
        $totalPedido += $subtotal;

        $itensMontados[] = [
            'produto_id'  => $prodId,
            'nome'        => $prod['nome'],
            'qtd'         => $qtd,
            'preco_unit'  => $precoUnit,
            'subtotal'    => $subtotal,
            'obs'         => $obsCompleta,
        ];
    }

    if (empty($itensMontados)) {
        echo json_encode(['success'=>false,'message'=>'Nenhum item válido no pedido.']); exit;
    }

    // Taxa de entrega
    if ($tipo === 'delivery') {
        $totalPedido += (float)$empresa['cardapio_taxa_entrega'];
    }

    // ── Caixa aberto? (opcional — pedido entra mesmo sem caixa) ──────────────
    $sessaoId = null;
    try {
        $sc = $pdo->prepare("SELECT id FROM caixa_sessoes WHERE empresa_id = ? AND closed_at IS NULL ORDER BY id DESC LIMIT 1");
        $sc->execute([$empresaId]);
        $sessaoId = $sc->fetchColumn() ?: null;
    } catch (Throwable $e) {}

    // ── Mesa: delivery usa label especial ────────────────────────────────────
    $mesaFinal = $mesa ?: ($tipo === 'delivery' ? 'Delivery' : ($tipo === 'retirada' ? 'Retirada' : 'Cardápio'));
    $origem    = ($tipo === 'delivery') ? 'cardapio_delivery' : (($tipo === 'retirada') ? 'cardapio_retirada' : 'cardapio_mesa');

    // ── Garante coluna cliente_google_id ANTES da transação (DDL causa commit implícito) ──
    try { $pdo->exec("ALTER TABLE cardapio_pedidos ADD COLUMN cliente_google_id VARCHAR(255) DEFAULT NULL"); } catch (Throwable $e) {}

    $pdo->beginTransaction();

    // ── Para pedidos de mesa: tenta reaproveitar pedido aberto da mesma mesa ──
    $pedidoId = null;
    if ($tipo === 'mesa' && $mesa && $sessaoId) {
        try {
            $ep = $pdo->prepare("
                SELECT id FROM pedidos
                WHERE empresa_id = ? AND caixa_sessao_id = ? AND mesa = ?
                  AND status IN ('PENDENTE','EM_PREPARO')
                ORDER BY id DESC LIMIT 1
            ");
            $ep->execute([$empresaId, $sessaoId, $mesaFinal]);
            $pedidoId = $ep->fetchColumn() ?: null;
        } catch (Throwable $e) {}
    }

    if ($pedidoId) {
        // Adiciona ao pedido existente: atualiza total
        $upd = $pdo->prepare("UPDATE pedidos SET total = total + ? WHERE id = ?");
        $upd->execute([$totalPedido, $pedidoId]);
    } else {
        // Cria novo pedido
        if ($sessaoId) {
            $ip = $pdo->prepare("INSERT INTO pedidos (mesa, status, total, impresso, caixa_sessao_id, empresa_id, origem)
                                 VALUES (?, 'PENDENTE', ?, 0, ?, ?, ?)");
            $ip->execute([$mesaFinal, $totalPedido, $sessaoId, $empresaId, $origem]);
        } else {
            $ip = $pdo->prepare("INSERT INTO pedidos (mesa, status, total, impresso, empresa_id, origem)
                                 VALUES (?, 'PENDENTE', ?, 0, ?, ?)");
            $ip->execute([$mesaFinal, $totalPedido, $empresaId, $origem]);
        }
        $pedidoId = (int)$pdo->lastInsertId();
    }

    // ── Insere itens ──────────────────────────────────────────────────────────
    // Detecta colunas disponíveis
    $hasObs = false; $hasItemStatus = false;
    try { $pdo->query("SELECT obs FROM itens_pedido LIMIT 0"); $hasObs = true; } catch (Throwable $e) {}
    try { $pdo->query("SELECT item_status FROM itens_pedido LIMIT 0"); $hasItemStatus = true; } catch (Throwable $e) {}

    foreach ($itensMontados as $it) {
        if ($hasObs && $hasItemStatus) {
            $ii = $pdo->prepare("INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unit, subtotal, obs, item_status, empresa_id) VALUES (?,?,?,?,?,?,'PENDENTE',?)");
            $ii->execute([$pedidoId, $it['produto_id'], $it['qtd'], $it['preco_unit'], $it['subtotal'], $it['obs'], $empresaId]);
        } elseif ($hasObs) {
            $ii = $pdo->prepare("INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unit, subtotal, obs, empresa_id) VALUES (?,?,?,?,?,?,?)");
            $ii->execute([$pedidoId, $it['produto_id'], $it['qtd'], $it['preco_unit'], $it['subtotal'], $it['obs'], $empresaId]);
        } else {
            $ii = $pdo->prepare("INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unit, subtotal, empresa_id) VALUES (?,?,?,?,?,?)");
            $ii->execute([$pedidoId, $it['produto_id'], $it['qtd'], $it['preco_unit'], $it['subtotal'], $empresaId]);
        }
    }

    // ── Insere cardapio_pedidos ───────────────────────────────────────────────

    $ic = $pdo->prepare("INSERT INTO cardapio_pedidos
        (empresa_id, pedido_id, tipo, mesa, cliente_nome, cliente_whatsapp, cliente_endereco, forma_pagamento, observacoes, cliente_google_id, status)
        VALUES (?,?,?,?,?,?,?,?,?,?,'novo')");
    $ic->execute([
        $empresaId, $pedidoId, $tipo,
        $mesa ?: null,
        $clienteNome,
        $clienteWhats ?: null,
        $clienteEndr  ?: null,
        $formaPgto    ?: null,
        $obs          ?: null,
        $clienteGoogleId ?: null,
    ]);

    $pdo->commit();

    echo json_encode([
        'success'    => true,
        'pedido_id'  => $pedidoId,
        'total'      => $totalPedido,
        'message'    => 'Pedido recebido com sucesso!',
        'pix_chave'  => $empresa['cardapio_pix_chave'] ?: null,
        'whatsapp'   => preg_replace('/\D/', '', $empresa['cardapio_whatsapp'] ?? ''),
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Erro ao processar pedido. Tente novamente.']);
}
