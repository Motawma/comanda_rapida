<?php
/**
 * api/item_status_atualizar.php — Atualiza o status de um item individual do pedido.
 *
 * POST JSON:
 *   { "item_id": 123, "status": "PRONTO" }
 *   ou para múltiplos itens de uma vez:
 *   { "item_ids": [123, 124], "status": "PRONTO" }
 *
 * Fluxo por item: PENDENTE → EM_PREPARO → PRONTO → ENTREGUE
 *
 * Após atualizar os itens, sincroniza o status do pedido automaticamente:
 *   - Se todos os itens estão ENTREGUE → pedido fica ENTREGUE
 *   - Se há mix, pedido fica no nível mais baixo entre os itens
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../funcoes.php';

$in = json_decode(file_get_contents('php://input'), true) ?: [];

// Suporte a item único ou múltiplos
$itemIds = [];
if (!empty($in['item_ids']) && is_array($in['item_ids'])) {
    $itemIds = array_map('intval', $in['item_ids']);
} elseif (!empty($in['item_id'])) {
    $itemIds = [(int)$in['item_id']];
}

$novoStatus = trim((string)($in['status'] ?? ''));

$allowed = ['PENDENTE', 'EM_PREPARO', 'PRONTO', 'ENTREGUE'];
if (empty($itemIds) || !in_array($novoStatus, $allowed)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'item_id(s) e status válido são obrigatórios']);
    exit;
}

// Transições permitidas por item
$transitions = [
    'PENDENTE'    => ['EM_PREPARO'],
    'EM_PREPARO'  => ['PRONTO', 'PENDENTE'],
    'PRONTO'      => ['ENTREGUE', 'EM_PREPARO'],
    'ENTREGUE'    => ['PRONTO'],  // permite desfazer entrega
];

// Transições extras para bebidas (podem pular etapas — "entregar antes")
$transitionsBebida = [
    'PENDENTE'   => ['EM_PREPARO', 'PRONTO', 'ENTREGUE'],
    'EM_PREPARO' => ['PRONTO', 'PENDENTE', 'ENTREGUE'],
    'PRONTO'     => ['ENTREGUE', 'EM_PREPARO'],
    'ENTREGUE'   => ['PRONTO'],
];

// Palavras-chave para detectar bebida — checadas na categoria E no nome do produto
$bebidaKeywords = [
    'BEBIDA','DRINK','SUCO','CERVEJA','REFRIGERANTE','AGUA','ÁGUA',
    'CAIPIRINHA','CAIPIROSKA','VINHO','CHOPP','CHOPE','LONG NECK','LONGNECK',
    'COCA','GUARANA','GUARANÁ','SPRITE','FANTA','MONSTER','RED BULL','REDBULL',
    'H2O','LIMONADA','VITAMINA','SHAKE','GIN','VODKA','WHISKY','WHISKEY',
    'HEINEKEN','BRAHMA','SKOL','ANTARCTICA','ITAIPAVA','EISENBAHN','ORIGINAL',
    'ENERGETICO','ENERGÉTICO','TONICA','TÔNICA','SODA',
];

try {
    $pdo = getPDO();
    $pdo->beginTransaction();

    $pedidoIds = [];
    $erros = [];

    foreach ($itemIds as $itemId) {
        // Buscar item atual com lock
        $stmt = $pdo->prepare("
            SELECT ip.id, ip.item_status, ip.pedido_id, p.nome, p.categoria
            FROM itens_pedido ip
            JOIN produtos p ON p.id = ip.produto_id
            WHERE ip.id = ?
            FOR UPDATE
        ");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            $erros[] = "Item #{$itemId} não encontrado";
            continue;
        }

        $statusAtual = $item['item_status'];

        // Se já está no status desejado, pula
        if ($statusAtual === $novoStatus) {
            $pedidoIds[] = (int)$item['pedido_id'];
            continue;
        }

        // Detectar se é bebida verificando categoria E nome do produto
        $normStr = function($s) {
            $s = mb_strtoupper(trim((string)$s), 'UTF-8');
            // Remove acentos sem depender de Normalizer
            $from = ['Á','À','Ã','Â','É','Ê','Í','Ó','Õ','Ô','Ú','Ü','Ç','á','à','ã','â','é','ê','í','ó','õ','ô','ú','ü','ç'];
            $to   = ['A','A','A','A','E','E','I','O','O','O','U','U','C','A','A','A','A','E','E','I','O','O','O','U','U','C'];
            return str_replace($from, $to, $s);
        };
        $catUpper  = $normStr($item['categoria'] ?? '');
        $nomeUpper = $normStr($item['nome'] ?? '');
        $isBebida  = false;
        foreach ($bebidaKeywords as $kw) {
            if (str_contains($catUpper, $kw) || str_contains($nomeUpper, $kw)) {
                $isBebida = true;
                break;
            }
        }

        // Verificar transição permitida (bebidas têm transições extras)
        $allowedNext = $isBebida
            ? ($transitionsBebida[$statusAtual] ?? [])
            : ($transitions[$statusAtual] ?? []);
        if (!in_array($novoStatus, $allowedNext)) {
            $erros[] = "Item #{$itemId} ({$item['nome']}): transição inválida {$statusAtual} → {$novoStatus}";
            continue;
        }

        // Atualizar status do item
        $upd = $pdo->prepare("UPDATE itens_pedido SET item_status = ? WHERE id = ?");
        $upd->execute([$novoStatus, $itemId]);

        $pedidoIds[] = (int)$item['pedido_id'];
    }

    $pdo->commit();

    // Sincronizar status de cada pedido afetado
    $pedidoIds = array_unique($pedidoIds);
    $pedidoStatusResults = [];
    foreach ($pedidoIds as $pid) {
        $newPedidoStatus = sincronizarStatusPedidoComItens($pid);
        $pedidoStatusResults[$pid] = $newPedidoStatus;
    }

    if (!empty($erros)) {
        echo json_encode([
            'ok'      => true,
            'partial' => true,
            'errors'  => $erros,
            'pedido_status' => $pedidoStatusResults,
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'ok'      => true,
            'pedido_status' => $pedidoStatusResults,
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Erro interno do servidor']);
}
