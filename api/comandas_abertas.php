<?php
/**
 * api/comandas_abertas.php — Lista comandas abertas (não PAGO/CANCELADO)
 * 
 * Retorna pedidos abertos da sessão de caixa atual, agrupados por mesa,
 * para que o garçom possa selecionar rapidamente a comanda do cliente.
 * 
 * GET params:
 *   q (opcional) — filtro por nº da mesa/comanda
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../funcoes.php';

try {
    $pdo = getPDO();
    $sessao = getOpenCaixaSessao($pdo);

    if (!$sessao) {
        echo json_encode([
            'success' => true,
            'comandas' => [],
            'message' => 'Caixa fechado'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sessaoId = (int)$sessao['id'];
    $filtro = trim((string)($_GET['q'] ?? ''));

    // Buscar pedidos abertos da sessão atual agrupados por mesa
    if ($filtro !== '') {
        $stmt = $pdo->prepare("
            SELECT p.id, p.mesa, p.status, p.total, p.created_at
            FROM pedidos p
            WHERE p.caixa_sessao_id = ?
              AND p.status NOT IN ('PAGO','CANCELADO')
              AND p.mesa LIKE ?
            ORDER BY p.mesa ASC, p.id DESC
        ");
        $stmt->execute([$sessaoId, "%{$filtro}%"]);
    } else {
        $stmt = $pdo->prepare("
            SELECT p.id, p.mesa, p.status, p.total, p.created_at
            FROM pedidos p
            WHERE p.caixa_sessao_id = ?
              AND p.status NOT IN ('PAGO','CANCELADO')
            ORDER BY p.mesa ASC, p.id DESC
        ");
        $stmt->execute([$sessaoId]);
    }

    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Agrupar por mesa (como agora é 1 pedido por mesa, cada mesa tem 1 pedido)
    $mesasMap = [];
    $pedidoIds = [];

    foreach ($pedidos as $p) {
        $mesa = (string)$p['mesa'];
        $pid = (int)$p['id'];
        $pedidoIds[] = $pid;

        if (!isset($mesasMap[$mesa])) {
            $mesasMap[$mesa] = [
                'mesa'       => $mesa,
                'pedido_id'  => $pid,
                'status'     => (string)$p['status'],
                'total'      => (float)$p['total'],
                'created_at' => (string)$p['created_at'],
                'itens'      => [],
                'qtd_itens'  => 0,
            ];
        }
    }

    // Buscar itens de todos os pedidos encontrados
    if (!empty($pedidoIds)) {
        $placeholders = implode(',', array_fill(0, count($pedidoIds), '?'));
        $stmtI = $pdo->prepare("
            SELECT ip.id AS item_id, ip.pedido_id, ip.quantidade, ip.subtotal, ip.item_status,
                   pr.nome, pr.categoria
            FROM itens_pedido ip
            JOIN produtos pr ON pr.id = ip.produto_id
            WHERE ip.pedido_id IN ($placeholders)
            ORDER BY ip.id ASC
        ");
        $stmtI->execute($pedidoIds);
        $allItens = $stmtI->fetchAll(PDO::FETCH_ASSOC);

        // Mapear itens por pedido_id -> mesa
        $pedidoMesaMap = [];
        foreach ($pedidos as $p) {
            $pedidoMesaMap[(int)$p['id']] = (string)$p['mesa'];
        }

        foreach ($allItens as $it) {
            $pid = (int)$it['pedido_id'];
            $mesa = $pedidoMesaMap[$pid] ?? null;
            if ($mesa === null || !isset($mesasMap[$mesa])) continue;

            $mesasMap[$mesa]['itens'][] = [
                'item_id'     => (int)$it['item_id'],
                'nome'        => $it['nome'],
                'quantidade'  => (int)$it['quantidade'],
                'subtotal'    => (float)$it['subtotal'],
                'categoria'   => $it['categoria'] ?? '',
                'item_status' => $it['item_status'] ?? '',
            ];
            $mesasMap[$mesa]['qtd_itens'] += (int)$it['quantidade'];
        }
    }

    // Converter para array indexado e ordenar por mesa
    $comandas = array_values($mesasMap);

    // Ordenar: mesas numéricas primeiro (crescente), depois alfanuméricas
    usort($comandas, function($a, $b) {
        $aNum = is_numeric($a['mesa']);
        $bNum = is_numeric($b['mesa']);
        if ($aNum && $bNum) return (int)$a['mesa'] - (int)$b['mesa'];
        if ($aNum) return -1;
        if ($bNum) return 1;
        return strcasecmp($a['mesa'], $b['mesa']);
    });

    echo json_encode([
        'success'  => true,
        'comandas' => $comandas,
        'total_abertas' => count($comandas),
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno'], JSON_UNESCAPED_UNICODE);
}
