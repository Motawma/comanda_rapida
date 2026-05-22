<?php
// funcoes.php
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/auth.php';

function getOpenCaixaSessao($pdo = null) {
    if ($pdo === null) $pdo = getPDO();
    $empresaId = currentEmpresaId();
    $stmt = $pdo->prepare("SELECT * FROM caixa_sessoes WHERE closed_at IS NULL AND empresa_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$empresaId]);
    $r = $stmt->fetch();
    return $r ?: null;
}

// Cria pedido e itens; retorna ID do pedido (int) ou array ['error'=>true,'message'=>...]
function criarPedidoNoBanco(string $mesa, array $itens) {
    $pdo = getPDO();
    $empresaId = currentEmpresaId();

    try {
        $pdo->beginTransaction();

        // Verifica sessão de caixa aberta
        $sessao = getOpenCaixaSessao($pdo);
        if (!$sessao) {
            $pdo->rollBack();
            return ['error' => true, 'message' => 'Caixa fechado. Abra o caixa para iniciar.'];
        }
        $sessaoId = (int)$sessao['id'];

        // Tenta encontrar pedido aberto para a mesa na sessão atual
        $find = $pdo->prepare("SELECT * FROM pedidos WHERE mesa = ? AND caixa_sessao_id = ? AND empresa_id = ? AND status NOT IN ('PAGO','CANCELADO','FIADO') ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $find->execute([$mesa, $sessaoId, $empresaId]);
        $open = $find->fetch();

        // Detectar se coluna item_status existe
        $hasItemStatus = false;
        try {
            $chk = $pdo->query("SELECT item_status FROM itens_pedido LIMIT 0");
            $hasItemStatus = true;
        } catch (Throwable $e) {
            $hasItemStatus = false;
        }

        // Detectar se coluna obs existe
        $hasObs = false;
        try {
            $pdo->query("SELECT obs FROM itens_pedido LIMIT 0");
            $hasObs = true;
        } catch (Throwable $e) {
            $hasObs = false;
        }

        if ($hasItemStatus && $hasObs) {
            $insertItem = $pdo->prepare("INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unit, subtotal, item_status, obs, empresa_id) VALUES (?, ?, ?, ?, ?, 'PENDENTE', ?, ?)");
        } elseif ($hasItemStatus) {
            $insertItem = $pdo->prepare("INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unit, subtotal, item_status, empresa_id) VALUES (?, ?, ?, ?, ?, 'PENDENTE', ?)");
        } elseif ($hasObs) {
            $insertItem = $pdo->prepare("INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unit, subtotal, obs, empresa_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        } else {
            $insertItem = $pdo->prepare("INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unit, subtotal, empresa_id) VALUES (?, ?, ?, ?, ?, ?)");
        }
        $getPreco = $pdo->prepare("SELECT preco FROM produtos WHERE id = ? AND ativo = 1 AND empresa_id = ?");

        if ($open) {
            $pedidoId = (int)$open['id'];

            if (empty($open['caixa_sessao_id'])) {
                $u = $pdo->prepare("UPDATE pedidos SET caixa_sessao_id = ? WHERE id = ? AND empresa_id = ?");
                $u->execute([$sessaoId, $pedidoId, $empresaId]);
            }

            foreach ($itens as $item) {
                $produtoId = (int)($item['produto_id'] ?? 0);
                $quantidade = max(1, (int)($item['quantidade'] ?? 1));
                $obs = isset($item['obs']) && $item['obs'] !== '' ? trim((string)$item['obs']) : null;
                if ($produtoId <= 0) throw new Exception("Produto inválido.");
                $getPreco->execute([$produtoId, $empresaId]);
                $prod = $getPreco->fetch();
                if (!$prod) throw new Exception("Produto ID {$produtoId} não encontrado ou inativo.");
                $preco = (float)$prod['preco'];
                $subtotal = round($preco * $quantidade, 2);
                if ($hasItemStatus && $hasObs) {
                    $insertItem->execute([$pedidoId, $produtoId, $quantidade, $preco, $subtotal, $obs, $empresaId]);
                } elseif ($hasItemStatus) {
                    $insertItem->execute([$pedidoId, $produtoId, $quantidade, $preco, $subtotal, $empresaId]);
                } elseif ($hasObs) {
                    $insertItem->execute([$pedidoId, $produtoId, $quantidade, $preco, $subtotal, $obs, $empresaId]);
                } else {
                    $insertItem->execute([$pedidoId, $produtoId, $quantidade, $preco, $subtotal, $empresaId]);
                }
            }

            $total = calcularTotal($pdo, $pedidoId);
            $u = $pdo->prepare("UPDATE pedidos SET total = ? WHERE id = ? AND empresa_id = ?");
            $u->execute([$total, $pedidoId, $empresaId]);
            $pdo->commit();
            return ['pedido_id' => $pedidoId, 'merged' => true];
        }

        // Cria novo pedido
        $stmt = $pdo->prepare("INSERT INTO pedidos (mesa, status, total, impresso, caixa_sessao_id, empresa_id) VALUES (?, 'PENDENTE', 0.00, 0, ?, ?)");
        $stmt->execute([$mesa, $sessaoId, $empresaId]);
        $pedidoId = (int)$pdo->lastInsertId();

        foreach ($itens as $item) {
            $produtoId = (int)($item['produto_id'] ?? 0);
            $quantidade = max(1, (int)($item['quantidade'] ?? 1));
            $obs = isset($item['obs']) && $item['obs'] !== '' ? trim((string)$item['obs']) : null;
            if ($produtoId <= 0) throw new Exception("Produto inválido.");
            $getPreco->execute([$produtoId, $empresaId]);
            $prod = $getPreco->fetch();
            if (!$prod) throw new Exception("Produto ID {$produtoId} não encontrado ou inativo.");
            $preco = (float)$prod['preco'];
            $subtotal = round($preco * $quantidade, 2);
            if ($hasItemStatus && $hasObs) {
                $insertItem->execute([$pedidoId, $produtoId, $quantidade, $preco, $subtotal, $obs, $empresaId]);
            } elseif ($hasItemStatus) {
                $insertItem->execute([$pedidoId, $produtoId, $quantidade, $preco, $subtotal, $empresaId]);
            } elseif ($hasObs) {
                $insertItem->execute([$pedidoId, $produtoId, $quantidade, $preco, $subtotal, $obs, $empresaId]);
            } else {
                $insertItem->execute([$pedidoId, $produtoId, $quantidade, $preco, $subtotal, $empresaId]);
            }
        }

        $total = calcularTotal($pdo, $pedidoId);
        $u = $pdo->prepare("UPDATE pedidos SET total = ? WHERE id = ? AND empresa_id = ?");
        $u->execute([$total, $pedidoId, $empresaId]);
        $pdo->commit();
        return ['pedido_id' => $pedidoId, 'merged' => false];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['error' => true, 'message' => $e->getMessage()];
    }
}

function calcularTotal($pdoOrId, ?int $pedidoIdOptional = null): float {
    if ($pdoOrId instanceof PDO) {
        $pdo = $pdoOrId;
        $pedidoId = $pedidoIdOptional;
    } else {
        $pdo = getPDO();
        $pedidoId = (int)$pdoOrId;
    }
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(subtotal),0) AS total FROM itens_pedido WHERE pedido_id = ?");
    $stmt->execute([$pedidoId]);
    $r = $stmt->fetch();
    return (float)($r['total'] ?? 0.0);
}

function marcarImpresso(int $pedidoId): bool {
    $pdo = getPDO();
    $empresaId = currentEmpresaId();
    $stmt = $pdo->prepare("UPDATE pedidos SET impresso = 1 WHERE id = ? AND empresa_id = ?");
    return (bool)$stmt->execute([$pedidoId, $empresaId]);
}

function getProdutoList(): array {
    $pdo = getPDO();
    $empresaId = currentEmpresaId();
    $stmt = $pdo->prepare("SELECT id, nome, preco, categoria FROM produtos WHERE ativo = 1 AND empresa_id = ? ORDER BY categoria, nome");
    $stmt->execute([$empresaId]);
    return $stmt->fetchAll();
}

function getPedido(int $pedidoId): ?array {
    $pdo = getPDO();
    $empresaId = currentEmpresaId();
    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$pedidoId, $empresaId]);
    $r = $stmt->fetch();
    return $r ?: null;
}

function getItensPedido(int $pedidoId): array {
    $pdo = getPDO();
    try {
        $stmt = $pdo->prepare("
            SELECT ip.*, p.nome, p.categoria
            FROM itens_pedido ip
            LEFT JOIN produtos p ON p.id = ip.produto_id
            WHERE ip.pedido_id = ?
            ORDER BY p.categoria, ip.id ASC
        ");
        $stmt->execute([$pedidoId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        $stmt = $pdo->prepare("
            SELECT ip.id, ip.pedido_id, ip.produto_id, ip.quantidade, ip.preco_unit, ip.subtotal, p.nome, p.categoria
            FROM itens_pedido ip
            LEFT JOIN produtos p ON p.id = ip.produto_id
            WHERE ip.pedido_id = ?
            ORDER BY p.categoria, ip.id ASC
        ");
        $stmt->execute([$pedidoId]);
        return $stmt->fetchAll();
    }
}

function listarPedidosPorDia(string $date, ?string $status = null): array {
    $pdo = getPDO();
    $empresaId = currentEmpresaId();
    if ($status) {
        $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE DATE(created_at) = ? AND status = ? AND empresa_id = ? ORDER BY created_at DESC");
        $stmt->execute([$date, $status, $empresaId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE DATE(created_at) = ? AND empresa_id = ? ORDER BY created_at DESC");
        $stmt->execute([$date, $empresaId]);
    }
    return $stmt->fetchAll();
}

function contarPedidosPorDia(string $date): array {
    $pdo = getPDO();
    $empresaId = currentEmpresaId();
    $stmt = $pdo->prepare("SELECT status, COUNT(*) AS c FROM pedidos WHERE DATE(created_at) = ? AND empresa_id = ? GROUP BY status");
    $stmt->execute([$date, $empresaId]);
    $rows = $stmt->fetchAll();
    $statuses = ['PENDENTE'=>0,'EM_PREPARO'=>0,'PRONTO'=>0,'ENTREGUE'=>0,'FIADO'=>0,'PAGO'=>0,'CANCELADO'=>0];
    foreach ($rows as $r) {
        $statuses[$r['status']] = (int)$r['c'];
    }
    return $statuses;
}

function totalVendidoDia(string $date): float {
    $pdo = getPDO();
    $empresaId = currentEmpresaId();
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS total FROM pedidos WHERE DATE(created_at) = ? AND status = 'PAGO' AND empresa_id = ?");
    $stmt->execute([$date, $empresaId]);
    $r = $stmt->fetch();
    return (float)($r['total'] ?? 0.0);
}

function atualizarStatusPedido($pdoOrPedidoId, $pedidoIdOrStatus = null, $statusOptional = null): bool {
    if ($pdoOrPedidoId instanceof PDO) {
        $pdo = $pdoOrPedidoId;
        $pedidoId = (int)$pedidoIdOrStatus;
        $status = (string)$statusOptional;
    } else {
        $pdo = getPDO();
        $pedidoId = (int)$pdoOrPedidoId;
        $status = (string)$pedidoIdOrStatus;
    }
    $empresaId = currentEmpresaId();

    $allowed = ['PENDENTE','EM_PREPARO','PRONTO','ENTREGUE','FIADO','PAGO','CANCELADO'];
    if (!in_array($status, $allowed)) return false;

    try {
        $stmt = $pdo->prepare("SELECT status FROM pedidos WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$pedidoId, $empresaId]);
        $row = $stmt->fetch();
        $current = $row['status'] ?? null;
        if ($current === null) return false;
        if ($current === $status) return true;

        $hasItemStatus = false;
        try { $pdo->query("SELECT item_status FROM itens_pedido LIMIT 0"); $hasItemStatus = true; } catch (Throwable $e) {}

        if ($current === 'ENTREGUE' && $status === 'PRONTO') {
            $pdo->prepare("UPDATE pedidos SET status = ?, entregue_at = NULL WHERE id = ? AND empresa_id = ?")->execute([$status, $pedidoId, $empresaId]);
            if ($hasItemStatus) $pdo->prepare("UPDATE itens_pedido SET item_status = 'PRONTO' WHERE pedido_id = ? AND item_status = 'ENTREGUE'")->execute([$pedidoId]);
            return true;
        }
        if ($current === 'PRONTO' && $status === 'EM_PREPARO') {
            $pdo->prepare("UPDATE pedidos SET status = ?, pronto_at = NULL, entregue_at = NULL, pago_at = NULL WHERE id = ? AND empresa_id = ?")->execute([$status, $pedidoId, $empresaId]);
            if ($hasItemStatus) $pdo->prepare("UPDATE itens_pedido SET item_status = 'EM_PREPARO' WHERE pedido_id = ? AND item_status IN ('PRONTO','ENTREGUE')")->execute([$pedidoId]);
            return true;
        }
        if ($current === 'EM_PREPARO' && $status === 'PENDENTE') {
            $pdo->prepare("UPDATE pedidos SET status = ?, em_preparo_at = NULL, pronto_at = NULL, entregue_at = NULL WHERE id = ? AND empresa_id = ?")->execute([$status, $pedidoId, $empresaId]);
            if ($hasItemStatus) $pdo->prepare("UPDATE itens_pedido SET item_status = 'PENDENTE' WHERE pedido_id = ? AND item_status IN ('EM_PREPARO','PRONTO','ENTREGUE')")->execute([$pedidoId]);
            return true;
        }
        if ($current === 'PRONTO' && $status === 'FIADO') {
            $pdo->prepare("UPDATE pedidos SET status = ?, fiado_at = NOW() WHERE id = ? AND empresa_id = ?")->execute([$status, $pedidoId, $empresaId]);
            if ($hasItemStatus) $pdo->prepare("UPDATE itens_pedido SET item_status = 'ENTREGUE' WHERE pedido_id = ? AND item_status = 'PRONTO'")->execute([$pedidoId]);
            return true;
        }
        if ($current === 'ENTREGUE' && $status === 'FIADO') {
            $pdo->prepare("UPDATE pedidos SET status = ?, fiado_at = NOW() WHERE id = ? AND empresa_id = ?")->execute([$status, $pedidoId, $empresaId]);
            return true;
        }
        if ($status === 'EM_PREPARO') {
            $pdo->prepare("UPDATE pedidos SET status = ?, em_preparo_at = NOW() WHERE id = ? AND empresa_id = ?")->execute([$status, $pedidoId, $empresaId]);
            if ($hasItemStatus) $pdo->prepare("UPDATE itens_pedido SET item_status = 'EM_PREPARO' WHERE pedido_id = ? AND item_status = 'PENDENTE'")->execute([$pedidoId]);
            return true;
        }
        if ($status === 'PRONTO') {
            $pdo->prepare("UPDATE pedidos SET status = ?, pronto_at = NOW() WHERE id = ? AND empresa_id = ?")->execute([$status, $pedidoId, $empresaId]);
            if ($hasItemStatus) $pdo->prepare("UPDATE itens_pedido SET item_status = 'PRONTO' WHERE pedido_id = ? AND item_status IN ('PENDENTE','EM_PREPARO')")->execute([$pedidoId]);
            return true;
        }
        if ($status === 'ENTREGUE') {
            $pdo->prepare("UPDATE pedidos SET status = ?, entregue_at = NOW() WHERE id = ? AND empresa_id = ?")->execute([$status, $pedidoId, $empresaId]);
            if ($hasItemStatus) $pdo->prepare("UPDATE itens_pedido SET item_status = 'ENTREGUE' WHERE pedido_id = ? AND item_status IN ('PENDENTE','EM_PREPARO','PRONTO')")->execute([$pedidoId]);
            return true;
        }
        if ($status === 'PAGO') {
            try {
                if (!$pdo->inTransaction()) $pdo->beginTransaction();
                $lockStmt = $pdo->prepare("SELECT status FROM pedidos WHERE id = ? AND empresa_id = ? FOR UPDATE");
                $lockStmt->execute([$pedidoId, $empresaId]);
                $locked = $lockStmt->fetch();
                if (!$locked) { if ($pdo->inTransaction()) $pdo->rollBack(); return false; }
                if ($locked['status'] === 'PAGO') { if ($pdo->inTransaction()) $pdo->commit(); return true; }
                $sessao = getOpenCaixaSessao($pdo);
                if ($sessao) {
                    $sessaoId = (int)$sessao['id'];
                    $pdo->prepare("UPDATE pedidos SET status = ?, pago_at = NOW(), caixa_sessao_id = ? WHERE id = ? AND empresa_id = ?")->execute([$status, $sessaoId, $pedidoId, $empresaId]);
                    if ($hasItemStatus) $pdo->prepare("UPDATE itens_pedido SET item_status = 'ENTREGUE' WHERE pedido_id = ?")->execute([$pedidoId]);
                    $pdo->prepare("UPDATE pedidos SET status = 'PAGO', pago_at = NOW(), caixa_sessao_id = ? WHERE status = 'FIADO' AND fiado_vinculado_pedido_id = ? AND empresa_id = ?")->execute([$sessaoId, $pedidoId, $empresaId]);
                    if ($pdo->inTransaction()) $pdo->commit();
                    return true;
                } else {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    return false;
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
        }
        $pdo->prepare("UPDATE pedidos SET status = ? WHERE id = ? AND empresa_id = ?")->execute([$status, $pedidoId, $empresaId]);
        return true;
    } catch (Throwable $e) {
        try {
            $pdo->prepare("UPDATE pedidos SET status = ? WHERE id = ? AND empresa_id = ?")->execute([$status, $pedidoId, $empresaId]);
            return true;
        } catch (Throwable $e2) { return false; }
    }
}

function sincronizarStatusPedidoComItens(int $pedidoId, ?PDO $pdo = null): string {
    if ($pdo === null) $pdo = getPDO();
    try {
        try { $pdo->query("SELECT item_status FROM itens_pedido LIMIT 0"); } catch (Throwable $e) { $p = getPedido($pedidoId); return $p['status'] ?? 'PENDENTE'; }
        $stmt = $pdo->prepare("SELECT DISTINCT item_status FROM itens_pedido WHERE pedido_id = ?");
        $stmt->execute([$pedidoId]);
        $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (empty($statuses)) { return getPedido($pedidoId)['status'] ?? 'PENDENTE'; }
        $hierarchy = ['PENDENTE' => 0, 'EM_PREPARO' => 1, 'PRONTO' => 2, 'ENTREGUE' => 3];
        $minLevel = 999;
        foreach ($statuses as $s) { $level = $hierarchy[$s] ?? 999; if ($level < $minLevel) $minLevel = $level; }
        $statusMap = array_flip($hierarchy);
        $newStatus = $statusMap[$minLevel] ?? 'PENDENTE';
        $pedido = getPedido($pedidoId);
        $currentStatus = $pedido['status'] ?? 'PENDENTE';
        $empresaId = currentEmpresaId();
        if (in_array($currentStatus, ['PENDENTE', 'EM_PREPARO', 'PRONTO', 'ENTREGUE'])) {
            if ($currentStatus !== $newStatus) {
                switch ($newStatus) {
                    case 'ENTREGUE':
                        $pdo->prepare("UPDATE pedidos SET status = 'ENTREGUE', entregue_at = COALESCE(entregue_at, NOW()) WHERE id = ? AND empresa_id = ?")->execute([$pedidoId, $empresaId]); break;
                    case 'PRONTO':
                        if ($currentStatus === 'ENTREGUE') {
                            $pdo->prepare("UPDATE pedidos SET status = 'PRONTO', entregue_at = NULL WHERE id = ? AND empresa_id = ?")->execute([$pedidoId, $empresaId]);
                        } else {
                            $pdo->prepare("UPDATE pedidos SET status = 'PRONTO', pronto_at = COALESCE(pronto_at, NOW()) WHERE id = ? AND empresa_id = ?")->execute([$pedidoId, $empresaId]);
                        }
                        break;
                    case 'EM_PREPARO':
                        if (in_array($currentStatus, ['PRONTO', 'ENTREGUE'])) {
                            $pdo->prepare("UPDATE pedidos SET status = 'EM_PREPARO', pronto_at = NULL, entregue_at = NULL WHERE id = ? AND empresa_id = ?")->execute([$pedidoId, $empresaId]);
                        } else {
                            $pdo->prepare("UPDATE pedidos SET status = 'EM_PREPARO', em_preparo_at = COALESCE(em_preparo_at, NOW()) WHERE id = ? AND empresa_id = ?")->execute([$pedidoId, $empresaId]);
                        }
                        break;
                    case 'PENDENTE':
                        $pdo->prepare("UPDATE pedidos SET status = 'PENDENTE', em_preparo_at = NULL, pronto_at = NULL, entregue_at = NULL WHERE id = ? AND empresa_id = ?")->execute([$pedidoId, $empresaId]); break;
                    default:
                        $pdo->prepare("UPDATE pedidos SET status = ? WHERE id = ? AND empresa_id = ?")->execute([$newStatus, $pedidoId, $empresaId]); break;
                }
            }
        }
        return $newStatus;
    } catch (Throwable $e) { $p = getPedido($pedidoId); return $p['status'] ?? 'PENDENTE'; }
}

function cancelarPedido(int $pedidoId, string $motivo): bool {
    $pdo = getPDO();
    $empresaId = currentEmpresaId();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT status FROM pedidos WHERE id = ? AND empresa_id = ? FOR UPDATE");
        $stmt->execute([$pedidoId, $empresaId]);
        $r = $stmt->fetch();
        if (!$r) { $pdo->rollBack(); return false; }
        if ($r['status'] === 'PAGO') { $pdo->rollBack(); return false; }
        if ($r['status'] === 'CANCELADO') { $pdo->commit(); return true; }
        $pdo->prepare("UPDATE pedidos SET status = 'CANCELADO', cancel_reason = ?, canceled_at = NOW() WHERE id = ? AND empresa_id = ?")->execute([$motivo, $pedidoId, $empresaId]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        try { $pdo->prepare("UPDATE pedidos SET status = 'CANCELADO' WHERE id = ? AND empresa_id = ?")->execute([$pedidoId, $empresaId]); return true; } catch (Throwable $e2) { return false; }
    }
}

function csrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfVerify(string $token): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
