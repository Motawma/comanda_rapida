<?php
/**
 * api/kds_pedidos.php — API do KDS (Kitchen Display System)
 * Retorna pedidos do dia filtrados por empresa_id (multi-tenant)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../funcoes.php';
require_once __DIR__ . '/../auth.php';

try {
    $pdo = getPDO();
    $empresaId = currentEmpresaId();

    $date = trim((string)($_GET['date'] ?? ''));
    if ($date === '') $date = date('Y-m-d');

    // Detectar se coluna item_status existe
    $hasItemStatus = false;
    try {
        $pdo->query("SELECT item_status FROM itens_pedido LIMIT 0");
        $hasItemStatus = true;
    } catch (Throwable $e) {
        $hasItemStatus = false;
    }

    if ($hasItemStatus) {
        // ── MODO NOVO: baseado em item_status ──
        $stmt = $pdo->prepare("
            SELECT DISTINCT p.id, p.mesa, p.status, p.total, p.created_at, p.em_preparo_at, p.pronto_at
            FROM pedidos p
            INNER JOIN itens_pedido ip ON ip.pedido_id = p.id
            WHERE (DATE(p.created_at) = ? OR p.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR))
              AND p.empresa_id = ?
              AND p.status NOT IN ('PAGO','CANCELADO','FIADO')
              AND ip.item_status IN ('PENDENTE','EM_PREPARO','PRONTO')
            ORDER BY
              FIELD(
                (SELECT MIN(FIELD(ip2.item_status, 'PENDENTE','EM_PREPARO','PRONTO'))
                 FROM itens_pedido ip2 WHERE ip2.pedido_id = p.id AND ip2.item_status IN ('PENDENTE','EM_PREPARO','PRONTO')),
                1, 2, 3
              ),
              p.created_at ASC
        ");
        $stmt->execute([$date, $empresaId]);
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pedidoIds = array_column($pedidos, 'id');
        $itensMap = [];

        if (!empty($pedidoIds)) {
            $placeholders = implode(',', array_fill(0, count($pedidoIds), '?'));
            $stmtI = $pdo->prepare("
                SELECT ip.id AS item_id, ip.pedido_id, ip.quantidade, ip.item_status, ip.obs, p.nome, p.categoria
                FROM itens_pedido ip
                JOIN produtos p ON p.id = ip.produto_id
                WHERE ip.pedido_id IN ($placeholders)
                  AND ip.item_status IN ('PENDENTE','EM_PREPARO','PRONTO')
                ORDER BY ip.id ASC
            ");
            $stmtI->execute($pedidoIds);
            $allItens = $stmtI->fetchAll(PDO::FETCH_ASSOC);

            foreach ($allItens as $it) {
                $pid = (int)$it['pedido_id'];
                if (!isset($itensMap[$pid])) $itensMap[$pid] = [];
                $itensMap[$pid][] = [
                    'item_id'     => (int)$it['item_id'],
                    'nome'        => $it['nome'],
                    'quantidade'  => (int)$it['quantidade'],
                    'categoria'   => $it['categoria'] ?? '',
                    'item_status' => $it['item_status'],
                    'obs'         => $it['obs'] ?? null,
                ];
            }
        }

        $lista = [];
        foreach ($pedidos as $p) {
            $pid = (int)$p['id'];
            $itens = $itensMap[$pid] ?? [];
            if (empty($itens)) continue;

            $hierarchy = ['PENDENTE' => 0, 'EM_PREPARO' => 1, 'PRONTO' => 2];
            $minLevel = 999;
            foreach ($itens as $it) {
                $level = $hierarchy[$it['item_status']] ?? 999;
                if ($level < $minLevel) $minLevel = $level;
            }
            $statusMap = array_flip($hierarchy);
            $statusEfetivo = $statusMap[$minLevel] ?? 'PENDENTE';

            $stmtEntregues = $pdo->prepare("SELECT COUNT(*) FROM itens_pedido WHERE pedido_id = ? AND item_status = 'ENTREGUE'");
            $stmtEntregues->execute([$pid]);
            $totalEntregues = (int)$stmtEntregues->fetchColumn();

            $lista[] = [
                'id'              => $pid,
                'mesa'            => (string)$p['mesa'],
                'status'          => $statusEfetivo,
                'status_pedido'   => (string)$p['status'],
                'total'           => (float)$p['total'],
                'created_at'      => (string)$p['created_at'],
                'em_preparo_at'   => $p['em_preparo_at'],
                'pronto_at'       => $p['pronto_at'],
                'itens'           => $itens,
                'itens_entregues' => $totalEntregues,
            ];
        }

        $contadores = ['PENDENTE' => 0, 'EM_PREPARO' => 0, 'PRONTO' => 0];
        foreach ($lista as $p) {
            $contadores[$p['status']] = ($contadores[$p['status']] ?? 0) + 1;
        }

        echo json_encode([
            'success'          => true,
            'date'             => $date,
            'pedidos'          => $lista,
            'contadores'       => $contadores,
            'server_now'       => date('Y-m-d H:i:s'),
            'item_status_mode' => true,
        ], JSON_UNESCAPED_UNICODE);

    } else {
        // ── MODO LEGADO: sem item_status ──
        $stmt = $pdo->prepare("
            SELECT id, mesa, status, total, created_at, em_preparo_at, pronto_at
            FROM pedidos
            WHERE (DATE(created_at) = ? OR created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR))
              AND empresa_id = ?
              AND status IN ('PENDENTE','EM_PREPARO','PRONTO')
            ORDER BY
              FIELD(status, 'PENDENTE','EM_PREPARO','PRONTO'),
              created_at ASC
        ");
        $stmt->execute([$date, $empresaId]);
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pedidoIds = array_column($pedidos, 'id');
        $itensMap = [];

        if (!empty($pedidoIds)) {
            $placeholders = implode(',', array_fill(0, count($pedidoIds), '?'));
            $stmtI = $pdo->prepare("
                SELECT ip.pedido_id, ip.quantidade, p.nome, p.categoria
                FROM itens_pedido ip
                JOIN produtos p ON p.id = ip.produto_id
                WHERE ip.pedido_id IN ($placeholders)
                ORDER BY ip.id ASC
            ");
            $stmtI->execute($pedidoIds);
            $allItens = $stmtI->fetchAll(PDO::FETCH_ASSOC);

            foreach ($allItens as $it) {
                $pid = (int)$it['pedido_id'];
                if (!isset($itensMap[$pid])) $itensMap[$pid] = [];
                $itensMap[$pid][] = [
                    'nome'       => $it['nome'],
                    'quantidade' => (int)$it['quantidade'],
                    'categoria'  => $it['categoria'] ?? '',
                ];
            }
        }

        $lista = [];
        foreach ($pedidos as $p) {
            $pid = (int)$p['id'];
            $lista[] = [
                'id'            => $pid,
                'mesa'          => (string)$p['mesa'],
                'status'        => (string)$p['status'],
                'total'         => (float)$p['total'],
                'created_at'    => (string)$p['created_at'],
                'em_preparo_at' => $p['em_preparo_at'],
                'pronto_at'     => $p['pronto_at'],
                'itens'         => $itensMap[$pid] ?? [],
            ];
        }

        $contadores = ['PENDENTE' => 0, 'EM_PREPARO' => 0, 'PRONTO' => 0];
        foreach ($lista as $p) {
            $contadores[$p['status']] = ($contadores[$p['status']] ?? 0) + 1;
        }

        echo json_encode([
            'success'          => true,
            'date'             => $date,
            'pedidos'          => $lista,
            'contadores'       => $contadores,
            'server_now'       => date('Y-m-d H:i:s'),
            'item_status_mode' => false,
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}
