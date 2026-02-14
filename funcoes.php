<?php
// funcoes.php
require_once __DIR__ . '/conexao.php';

function getOpenCaixaSessao($pdo = null) {
    if ($pdo === null) $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM caixa_sessoes WHERE closed_at IS NULL ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $r = $stmt->fetch();
    return $r ?: null;
}

// Cria pedido e itens; retorna ID do pedido (int) ou array ['error'=>true,'message'=>...]
function criarPedidoNoBanco(string $mesa, array $itens) {
    $pdo = getPDO();

    try {
        $pdo->beginTransaction();

        // Verifica sessão de caixa aberta
        $sessao = getOpenCaixaSessao($pdo);
        if (!$sessao) {
            $pdo->rollBack();
            return ['error' => true, 'message' => 'Caixa fechado. Abra o caixa para iniciar.'];
        }
        $sessaoId = (int)$sessao['id'];

        // Tenta encontrar pedido aberto mais recente para a mesa e travá-lo
        // Buscar pedido aberto somente na sessão de caixa atual
        $find = $pdo->prepare("SELECT * FROM pedidos WHERE mesa = ? AND caixa_sessao_id = ? AND status IN ('PENDENTE','EM_PREPARO','PRONTO') ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $find->execute([$mesa, $sessaoId]);
        $open = $find->fetch();

        $insertItem = $pdo->prepare(
            "INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unit, subtotal) VALUES (?, ?, ?, ?, ?)"
        );
        $getPreco = $pdo->prepare("SELECT preco FROM produtos WHERE id = ? AND ativo = 1");

        if ($open) {
            // Mesclar itens no pedido existente
            $pedidoId = (int)$open['id'];

            // Se pedido não estiver ligado à sessão, atualiza
            if (empty($open['caixa_sessao_id'])) {
                $u = $pdo->prepare("UPDATE pedidos SET caixa_sessao_id = ? WHERE id = ?");
                $u->execute([$sessaoId, $pedidoId]);
            }

            foreach ($itens as $item) {
                $produtoId = (int)($item['produto_id'] ?? 0);
                $quantidade = max(1, (int)($item['quantidade'] ?? 1));

                if ($produtoId <= 0) {
                    throw new Exception("Produto inválido.");
                }

                $getPreco->execute([$produtoId]);
                $prod = $getPreco->fetch();
                if (!$prod) {
                    throw new Exception("Produto ID {$produtoId} não encontrado ou inativo.");
                }

                $preco = (float)$prod['preco'];
                $subtotal = round($preco * $quantidade, 2);

                $insertItem->execute([$pedidoId, $produtoId, $quantidade, $preco, $subtotal]);
            }

            // Recalcula total e atualiza pedido
            $total = calcularTotal($pdo, $pedidoId);
            $u = $pdo->prepare("UPDATE pedidos SET total = ? WHERE id = ?");
            $u->execute([$total, $pedidoId]);

            $pdo->commit();
            return ['pedido_id' => $pedidoId, 'merged' => true];
        }

        // Sem pedido aberto: cria novo pedido vinculado à sessao
        $stmt = $pdo->prepare("INSERT INTO pedidos (mesa, status, total, impresso, caixa_sessao_id) VALUES (?, 'PENDENTE', 0.00, 0, ?)");
        $stmt->execute([$mesa, $sessaoId]);
        $pedidoId = (int)$pdo->lastInsertId();

        foreach ($itens as $item) {
            $produtoId = (int)($item['produto_id'] ?? 0);
            $quantidade = max(1, (int)($item['quantidade'] ?? 1));

            if ($produtoId <= 0) {
                throw new Exception("Produto inválido.");
            }

            $getPreco->execute([$produtoId]);
            $prod = $getPreco->fetch();
            if (!$prod) {
                throw new Exception("Produto ID {$produtoId} não encontrado ou inativo.");
            }

            $preco = (float)$prod['preco'];
            $subtotal = round($preco * $quantidade, 2);

            $insertItem->execute([$pedidoId, $produtoId, $quantidade, $preco, $subtotal]);
        }

        $total = calcularTotal($pdo, $pedidoId);
        $u = $pdo->prepare("UPDATE pedidos SET total = ? WHERE id = ?");
        $u->execute([$total, $pedidoId]);

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
    $stmt = $pdo->prepare("UPDATE pedidos SET impresso = 1 WHERE id = ?");
    return (bool)$stmt->execute([$pedidoId]);
}

function getProdutoList(): array {
    $pdo = getPDO();
    $stmt = $pdo->query("SELECT id, nome, preco, categoria FROM produtos WHERE ativo = 1 ORDER BY categoria, nome");
    return $stmt->fetchAll();
}

function getPedido(int $pedidoId): ?array {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
    $stmt->execute([$pedidoId]);
    $r = $stmt->fetch();
    return $r ?: null;
}

function getItensPedido(int $pedidoId): array {
    $pdo = getPDO();
    $stmt = $pdo->prepare("
        SELECT ip.*, p.nome, p.categoria
        FROM itens_pedido ip
        LEFT JOIN produtos p ON p.id = ip.produto_id
        WHERE ip.pedido_id = ?
        ORDER BY p.categoria, ip.id ASC
    ");
    $stmt->execute([$pedidoId]);
    return $stmt->fetchAll();
}

function listarPedidosPorDia(string $date, ?string $status = null): array {
    $pdo = getPDO();
    if ($status) {
        $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE DATE(created_at) = ? AND status = ? ORDER BY created_at DESC");
        $stmt->execute([$date, $status]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE DATE(created_at) = ? ORDER BY created_at DESC");
        $stmt->execute([$date]);
    }
    return $stmt->fetchAll();
}

function contarPedidosPorDia(string $date): array {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT status, COUNT(*) AS c FROM pedidos WHERE DATE(created_at) = ? GROUP BY status");
    $stmt->execute([$date]);
    $rows = $stmt->fetchAll();

    // incluir FIADO nos contadores
    $statuses = ['PENDENTE'=>0,'EM_PREPARO'=>0,'PRONTO'=>0,'FIADO'=>0,'PAGO'=>0,'CANCELADO'=>0];
    foreach ($rows as $r) {
        $statuses[$r['status']] = (int)$r['c'];
    }
    return $statuses;
}

function totalVendidoDia(string $date): float {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS total FROM pedidos WHERE DATE(created_at) = ? AND status = 'PAGO'");
    $stmt->execute([$date]);
    $r = $stmt->fetch();
    return (float)($r['total'] ?? 0.0);
}

/**
 * Atualiza status e tenta gravar/limpar timestamps conforme transição.
 * Colunas usadas: em_preparo_at, pronto_at, pago_at
 */
function atualizarStatusPedido(int $pedidoId, string $status): bool {
    // aceitar FIADO como status válido
    $allowed = ['PENDENTE','EM_PREPARO','PRONTO','FIADO','PAGO','CANCELADO'];
    if (!in_array($status, $allowed)) return false;

    $pdo = getPDO();

    try {
        // Obter status atual
        $stmt = $pdo->prepare("SELECT status FROM pedidos WHERE id = ?");
        $stmt->execute([$pedidoId]);
        $row = $stmt->fetch();
        $current = $row['status'] ?? null;

        if ($current === null) return false;
        if ($current === $status) return true;

        // Regras de limpeza ao voltar status
        // PRONTO -> EM_PREPARO: limpar pronto_at e pago_at
        // EM_PREPARO -> PENDENTE: limpar em_preparo_at, pronto_at, pago_at
        if ($current === 'PRONTO' && $status === 'EM_PREPARO') {
            $sql = "UPDATE pedidos SET status = ?, pronto_at = NULL, pago_at = NULL WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status, $pedidoId]);
            return true;
        }

        if ($current === 'EM_PREPARO' && $status === 'PENDENTE') {
            $sql = "UPDATE pedidos SET status = ?, em_preparo_at = NULL, pronto_at = NULL, pago_at = NULL WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status, $pedidoId]);
            return true;
        }

        // PRONTO -> FIADO: registrar fiado_at
        if ($current === 'PRONTO' && $status === 'FIADO') {
            $sql = "UPDATE pedidos SET status = ?, fiado_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status, $pedidoId]);
            return true;
        }

        // Avançar: grava timestamp correspondente quando aplicável
        if ($status === 'EM_PREPARO') {
            $sql = "UPDATE pedidos SET status = ?, em_preparo_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status, $pedidoId]);
            return true;
        }

        if ($status === 'PRONTO') {
            $sql = "UPDATE pedidos SET status = ?, pronto_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status, $pedidoId]);
            return true;
        }

        if ($status === 'PAGO') {
            // Ao marcar PAGO, vincular o pedido à sessão de caixa atual quando houver
            // Isso garante que pagamento conte para a sessão em que foi efetuado
            try {
                // tranzaction + lock to avoid race conditions
                if (!$pdo->inTransaction()) $pdo->beginTransaction();

                $lockStmt = $pdo->prepare("SELECT status FROM pedidos WHERE id = ? FOR UPDATE");
                $lockStmt->execute([$pedidoId]);
                $locked = $lockStmt->fetch();
                if (!$locked) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    return false;
                }
                $lockedStatus = $locked['status'];
                // if already paid, nothing to do
                if ($lockedStatus === 'PAGO') {
                    if ($pdo->inTransaction()) $pdo->commit();
                    return true;
                }

                $sessao = getOpenCaixaSessao($pdo);

                if ($sessao) {
                    $sessaoId = (int)$sessao['id'];
                    $sql = "UPDATE pedidos SET status = ?, pago_at = NOW(), caixa_sessao_id = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$status, $sessaoId, $pedidoId]);
                    if ($pdo->inTransaction()) $pdo->commit();
                    return true;
                } else {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    // Sem sessão aberta: não marcar como pago aqui
                    return false;
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
        }

        // Outros casos (ex: CANCELADO ou PENDENTE sem timestamp)
        $stmt = $pdo->prepare("UPDATE pedidos SET status = ? WHERE id = ?");
        $stmt->execute([$status, $pedidoId]);
        return true;

    } catch (Throwable $e) {
        // Se alguma coluna não existir ou erro ocorrer, fallback para update simples
        try {
            $stmt = $pdo->prepare("UPDATE pedidos SET status = ? WHERE id = ?");
            $stmt->execute([$status, $pedidoId]);
            return true;
        } catch (Throwable $e2) {
            return false;
        }
    }
}

/**
 * Cancela um pedido com motivo.
 * Retorna false se pedido não existir ou estiver PAGO.
 * Retorna true se já estiver CANCELADO ou se cancelamento foi aplicado com sucesso.
 */
function cancelarPedido(int $pedidoId, string $motivo): bool {
    $pdo = getPDO();

    try {
        $pdo->beginTransaction();

        // Verifica status atual
        $stmt = $pdo->prepare("SELECT status FROM pedidos WHERE id = ? FOR UPDATE");
        $stmt->execute([$pedidoId]);
        $r = $stmt->fetch();
        if (!$r) {
            $pdo->rollBack();
            return false;
        }
        $status = $r['status'];

        if ($status === 'PAGO') {
            // Não permitir cancelar pagos
            $pdo->rollBack();
            return false;
        }

        if ($status === 'CANCELADO') {
            // Já cancelado
            $pdo->commit();
            return true;
        }

        // Tenta atualizar incluindo motivo e timestamp; se as colunas não existirem, cairá no catch
        $stmt = $pdo->prepare("UPDATE pedidos SET status = 'CANCELADO', cancel_reason = ?, canceled_at = NOW() WHERE id = ?");
        $stmt->execute([$motivo, $pedidoId]);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // Fallback: tentar apenas setar status (se colunas não existirem)
        try {
            $stmt = $pdo->prepare("UPDATE pedidos SET status = 'CANCELADO' WHERE id = ?");
            $stmt->execute([$pedidoId]);
            return true;
        } catch (Throwable $e2) {
            return false;
        }
    }
}
