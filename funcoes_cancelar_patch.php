<?php
// funcoes_cancelar_patch.php
// Cole este bloco no seu funcoes.php (junto das outras funções).

function cancelarPedido(int $pedidoId, string $motivo): bool {
    $pdo = getPDO();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT status FROM pedidos WHERE id = ? FOR UPDATE");
        $stmt->execute([$pedidoId]);
        $r = $stmt->fetch();
        if (!$r) {
            $pdo->rollBack();
            return false;
        }
        $status = $r['status'];

        if ($status === 'PAGO') {
            $pdo->rollBack();
            return false;
        }

        if ($status === 'CANCELADO') {
            $pdo->commit();
            return true;
        }

        $stmt = $pdo->prepare("UPDATE pedidos SET status = 'CANCELADO', cancel_reason = ?, canceled_at = NOW() WHERE id = ?");
        $stmt->execute([$motivo, $pedidoId]);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        try {
            $stmt = $pdo->prepare("UPDATE pedidos SET status = 'CANCELADO' WHERE id = ?");
            $stmt->execute([$pedidoId]);
            return true;
        } catch (Throwable $e2) {
            return false;
        }
    }
}
