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

        // Tenta encontrar pedido aberto (qualquer status exceto PAGO/CANCELADO) para a mesa na sessão atual.
        // SEMPRE mescla no mesmo pedido — novos itens entram com item_status = 'PENDENTE'
        // e só os itens novos aparecem na cozinha.
        $find = $pdo->prepare("SELECT * FROM pedidos WHERE mesa = ? AND caixa_sessao_id = ? AND status NOT IN ('PAGO','CANCELADO','FIADO') ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $find->execute([$mesa, $sessaoId]);
        $open = $find->fetch();

        // Detectar se coluna item_status existe (para compatibilidade durante migração)
        $hasItemStatus = false;
        try {
            $chk = $pdo->query("SELECT item_status FROM itens_pedido LIMIT 0");
            $hasItemStatus = true;
        } catch (Throwable $e) {
            $hasItemStatus = false;
        }

        $insertItem = $pdo->prepare(
            $hasItemStatus
                ? "INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unit, subtotal, item_status) VALUES (?, ?, ?, ?, ?, 'PENDENTE')"
                : "INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unit, subtotal) VALUES (?, ?, ?, ?, ?)"
        );
        $getPreco = $pdo->prepare("SELECT preco FROM produtos WHERE id = ? AND ativo = 1");

        if ($open) {
            // Mesclar itens no pedido existente (qualquer status aberto)
            $pedidoId = (int)$open['id'];
            $currentStatus = $open['status'] ?? 'PENDENTE';

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

                // Sempre insere como nova linha com item_status PENDENTE
                // (não incrementa itens existentes que já podem estar ENTREGUE/PRONTO)
                $insertItem->execute([$pedidoId, $produtoId, $quantidade, $preco, $subtotal]);
            }

            // Recalcula total e atualiza pedido
            $total = calcularTotal($pdo, $pedidoId);
            $u = $pdo->prepare("UPDATE pedidos SET total = ? WHERE id = ?");
            $u->execute([$total, $pedidoId]);

            // Se o pedido estava PRONTO ou ENTREGUE, o status do pedido continua —
            // a cozinha verá os novos itens PENDENTE através do item_status.
            // Porém precisamos garantir que o pedido apareça no KDS,
            // então se o pedido estava ENTREGUE, voltamos para ENTREGUE (sem mudar)
            // e o KDS mostrará os itens novos via item_status.

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
    // Inclui item_status se a coluna existir
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
        // fallback sem item_status
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

    // incluir FIADO e ENTREGUE nos contadores
    $statuses = ['PENDENTE'=>0,'EM_PREPARO'=>0,'PRONTO'=>0,'ENTREGUE'=>0,'FIADO'=>0,'PAGO'=>0,'CANCELADO'=>0];
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
 * Também sincroniza item_status dos itens do pedido.
 */
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

    // aceitar FIADO como status válido
    $allowed = ['PENDENTE','EM_PREPARO','PRONTO','ENTREGUE','FIADO','PAGO','CANCELADO'];
    if (!in_array($status, $allowed)) return false;

    try {
        // Obter status atual
        $stmt = $pdo->prepare("SELECT status FROM pedidos WHERE id = ?");
        $stmt->execute([$pedidoId]);
        $row = $stmt->fetch();
        $current = $row['status'] ?? null;

        if ($current === null) return false;
        if ($current === $status) return true;

        // Detectar se coluna item_status existe
        $hasItemStatus = false;
        try {
            $chk = $pdo->query("SELECT item_status FROM itens_pedido LIMIT 0");
            $hasItemStatus = true;
        } catch (Throwable $e) {
            $hasItemStatus = false;
        }

        // Regras de limpeza ao voltar status
        // ENTREGUE -> PRONTO: limpar entregue_at
        if ($current === 'ENTREGUE' && $status === 'PRONTO') {
            $sql = "UPDATE pedidos SET status = ?, entregue_at = NULL WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status, $pedidoId]);
            if ($hasItemStatus) {
                // Itens ENTREGUE voltam para PRONTO
                $pdo->prepare("UPDATE itens_pedido SET item_status = 'PRONTO' WHERE pedido_id = ? AND item_status = 'ENTREGUE'")->execute([$pedidoId]);
            }
            return true;
        }

        // PRONTO -> EM_PREPARO: limpar pronto_at, entregue_at e pago_at
        if ($current === 'PRONTO' && $status === 'EM_PREPARO') {
            $sql = "UPDATE pedidos SET status = ?, pronto_at = NULL, entregue_at = NULL, pago_at = NULL WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status, $pedidoId]);
            if ($hasItemStatus) {
                $pdo->prepare("UPDATE itens_pedido SET item_status = 'EM_PREPARO' WHERE pedido_id = ? AND item_status IN ('PRONTO','ENTREGUE')")->execute([$pedidoId]);
            }
            return true;
        }

        if ($current === 'EM_PREPARO' && $status === 'PENDENTE') {
            $sql = "UPDATE pedidos SET status = ?, em_preparo_at = NULL, pronto_at = NULL, entregue_at = NULL WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status, $pedidoId]);
            if ($hasItemStatus) {
                $pdo->prepare("UPDATE itens_pedido SET item_status = 'PENDENTE' WHERE pedido_id = ? AND item_status IN ('EM_PREPARO','PRONTO','ENTREGUE')")->execute([$pedidoId]);
            }
            return true;
        }

        // PRONTO -> FIADO: registrar fiado_at
        if ($current === 'PRONTO' && $status === 'FIADO') {
            $sql = "UPDATE pedidos SET status = ?, fiado_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status, $pedidoId]);
            if ($hasItemStatus) {
                $pdo->prepare("UPDATE itens_pedido SET item_status = 'ENTREGUE' WHERE pedido_id = ? AND item_status IN ('PRONTO')")->execute([$pedidoId]);
            }
            return true;
        }

        // ENTREGUE -> FIADO: registrar fiado_at
        if ($current === 'ENTREGUE' && $status === 'FIADO') {
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
            if ($hasItemStatus) {
                // Só avança itens que estão PENDENTE para EM_PREPARO
                $pdo->prepare("UPDATE itens_pedido SET item_status = 'EM_PREPARO' WHERE pedido_id = ? AND item_status = 'PENDENTE'")->execute([$pedidoId]);
            }
            return true;
        }

        if ($status === 'PRONTO') {
            $sql = "UPDATE pedidos SET status = ?, pronto_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status, $pedidoId]);
            if ($hasItemStatus) {
                // Avança itens PENDENTE e EM_PREPARO para PRONTO
                $pdo->prepare("UPDATE itens_pedido SET item_status = 'PRONTO' WHERE pedido_id = ? AND item_status IN ('PENDENTE','EM_PREPARO')")->execute([$pedidoId]);
            }
            return true;
        }

        if ($status === 'ENTREGUE') {
            $sql = "UPDATE pedidos SET status = ?, entregue_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status, $pedidoId]);
            if ($hasItemStatus) {
                // Avança itens PRONTO para ENTREGUE
                $pdo->prepare("UPDATE itens_pedido SET item_status = 'ENTREGUE' WHERE pedido_id = ? AND item_status IN ('PENDENTE','EM_PREPARO','PRONTO')")->execute([$pedidoId]);
            }
            return true;
        }

        if ($status === 'PAGO') {
            try {
                if (!$pdo->inTransaction()) $pdo->beginTransaction();

                $lockStmt = $pdo->prepare("SELECT status FROM pedidos WHERE id = ? FOR UPDATE");
                $lockStmt->execute([$pedidoId]);
                $locked = $lockStmt->fetch();
                if (!$locked) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    return false;
                }
                $lockedStatus = $locked['status'];
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

                    if ($hasItemStatus) {
                        // Todos os itens ficam ENTREGUE ao pagar
                        $pdo->prepare("UPDATE itens_pedido SET item_status = 'ENTREGUE' WHERE pedido_id = ?")->execute([$pedidoId]);
                    }

                    $fiadosLock = $pdo->prepare("SELECT id FROM pedidos WHERE status = 'FIADO' AND fiado_vinculado_pedido_id = ? FOR UPDATE");
                    $fiadosLock->execute([$pedidoId]);
                    $updFiados = $pdo->prepare("UPDATE pedidos SET status = 'PAGO', pago_at = NOW(), caixa_sessao_id = ? WHERE status = 'FIADO' AND fiado_vinculado_pedido_id = ?");
                    $updFiados->execute([$sessaoId, $pedidoId]);

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

        // Outros casos (ex: CANCELADO ou PENDENTE sem timestamp)
        $stmt = $pdo->prepare("UPDATE pedidos SET status = ? WHERE id = ?");
        $stmt->execute([$status, $pedidoId]);
        return true;

    } catch (Throwable $e) {
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
 * Sincroniza o status do pedido com base nos item_status dos seus itens.
 * Chama esta função após mesclar novos itens num pedido que já estava PRONTO/ENTREGUE.
 * Retorna o novo status calculado do pedido.
 * 
 * Agora também grava/limpa timestamps corretamente:
 *   - Todos ENTREGUE → pedido ENTREGUE + entregue_at
 *   - Todos PRONTO ou superiores → pedido PRONTO + pronto_at
 *   - Algum EM_PREPARO → pedido EM_PREPARO + em_preparo_at
 *   - Algum PENDENTE → pedido PENDENTE
 */
function sincronizarStatusPedidoComItens(int $pedidoId, ?PDO $pdo = null): string {
    if ($pdo === null) $pdo = getPDO();

    try {
        // Verifica se coluna item_status existe
        try {
            $pdo->query("SELECT item_status FROM itens_pedido LIMIT 0");
        } catch (Throwable $e) {
            // Sem a coluna, não faz nada
            $p = getPedido($pedidoId);
            return $p['status'] ?? 'PENDENTE';
        }

        // Buscar todos os item_status distintos deste pedido
        $stmt = $pdo->prepare("SELECT DISTINCT item_status FROM itens_pedido WHERE pedido_id = ?");
        $stmt->execute([$pedidoId]);
        $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($statuses)) {
            return getPedido($pedidoId)['status'] ?? 'PENDENTE';
        }

        // Regra: o pedido deve ter o status "mais baixo" entre seus itens
        // Hierarquia: PENDENTE < EM_PREPARO < PRONTO < ENTREGUE
        $hierarchy = ['PENDENTE' => 0, 'EM_PREPARO' => 1, 'PRONTO' => 2, 'ENTREGUE' => 3];
        $minLevel = 999;
        foreach ($statuses as $s) {
            $level = $hierarchy[$s] ?? 999;
            if ($level < $minLevel) $minLevel = $level;
        }

        $statusMap = array_flip($hierarchy);
        $newStatus = $statusMap[$minLevel] ?? 'PENDENTE';

        // Buscar status atual do pedido
        $pedido = getPedido($pedidoId);
        $currentStatus = $pedido['status'] ?? 'PENDENTE';

        // Só atualiza se o pedido está em um status de fluxo de cozinha
        // (não mexe em FIADO, PAGO, CANCELADO)
        if (in_array($currentStatus, ['PENDENTE', 'EM_PREPARO', 'PRONTO', 'ENTREGUE'])) {
            if ($currentStatus !== $newStatus) {
                // Gravar timestamps corretos conforme o novo status
                switch ($newStatus) {
                    case 'ENTREGUE':
                        // Todos os itens entregues → pedido fecha automaticamente
                        $upd = $pdo->prepare("UPDATE pedidos SET status = 'ENTREGUE', entregue_at = COALESCE(entregue_at, NOW()) WHERE id = ?");
                        $upd->execute([$pedidoId]);
                        break;

                    case 'PRONTO':
                        // Limpa entregue_at se voltou de ENTREGUE
                        if ($currentStatus === 'ENTREGUE') {
                            $upd = $pdo->prepare("UPDATE pedidos SET status = 'PRONTO', entregue_at = NULL WHERE id = ?");
                        } else {
                            $upd = $pdo->prepare("UPDATE pedidos SET status = 'PRONTO', pronto_at = COALESCE(pronto_at, NOW()) WHERE id = ?");
                        }
                        $upd->execute([$pedidoId]);
                        break;

                    case 'EM_PREPARO':
                        // Limpa pronto_at e entregue_at se voltou
                        if (in_array($currentStatus, ['PRONTO', 'ENTREGUE'])) {
                            $upd = $pdo->prepare("UPDATE pedidos SET status = 'EM_PREPARO', pronto_at = NULL, entregue_at = NULL WHERE id = ?");
                        } else {
                            $upd = $pdo->prepare("UPDATE pedidos SET status = 'EM_PREPARO', em_preparo_at = COALESCE(em_preparo_at, NOW()) WHERE id = ?");
                        }
                        $upd->execute([$pedidoId]);
                        break;

                    case 'PENDENTE':
                        // Limpa todos os timestamps de avanço
                        $upd = $pdo->prepare("UPDATE pedidos SET status = 'PENDENTE', em_preparo_at = NULL, pronto_at = NULL, entregue_at = NULL WHERE id = ?");
                        $upd->execute([$pedidoId]);
                        break;

                    default:
                        $upd = $pdo->prepare("UPDATE pedidos SET status = ? WHERE id = ?");
                        $upd->execute([$newStatus, $pedidoId]);
                        break;
                }
            }
        }

        return $newStatus;

    } catch (Throwable $e) {
        $p = getPedido($pedidoId);
        return $p['status'] ?? 'PENDENTE';
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
