<?php
// printer/fechamento_caixa.php
require_once __DIR__ . '/../conexao.php';

$sessaoId = isset($_GET['sessao_id']) ? (int)$_GET['sessao_id'] : 0;
if ($sessaoId <= 0) {
    echo 'Sessão inválida'; exit;
}
$pdo = getPDO();
// Obter sessao
$stmt = $pdo->prepare("SELECT * FROM caixa_sessoes WHERE id = ?");
$stmt->execute([$sessaoId]);
$sess = $stmt->fetch();
if (!$sess) { echo 'Sessão não encontrada'; exit; }

// Totais calculados (reforço)
$paidStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS total_pago FROM pedidos WHERE caixa_sessao_id = ? AND status = 'PAGO'");
$paidStmt->execute([$sessaoId]); $pr = $paidStmt->fetch(); $total_pago = (float)($pr['total_pago'] ?? 0.0);
$cancelStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS total_cancelado FROM pedidos WHERE caixa_sessao_id = ? AND status = 'CANCELADO'");
$cancelStmt->execute([$sessaoId]); $cr = $cancelStmt->fetch(); $total_cancelado = (float)($cr['total_cancelado'] ?? 0.0);

// Calcular diferença e rótulo
$opening_cash = (float)($sess['opening_cash'] ?? 0.0);
$closing_cash = (float)($sess['closing_cash'] ?? 0.0);
// Use valor salvo em sessao se existir, caso contrário calcula a partir dos valores
if (array_key_exists('diferenca', $sess) && $sess['diferenca'] !== null) {
  $diferenca = (float)$sess['diferenca'];
} else {
  $expected_total_in_cash = $opening_cash + $total_pago - $total_cancelado;
  $diferenca = $closing_cash - $expected_total_in_cash;
}
// Rótulo: Sobrando (positivo), Faltando (negativo), Exato (aprox zero)
if ($diferenca > 0.005) $diferenca_label = 'Sobrando';
else if ($diferenca < -0.005) $diferenca_label = 'Faltando';
else $diferenca_label = 'Exato';

// Pedidos pagos e cancelados
$pedPagStmt = $pdo->prepare("SELECT id, mesa, total, created_at FROM pedidos WHERE caixa_sessao_id = ? AND status = 'PAGO' ORDER BY created_at ASC");
$pedPagStmt->execute([$sessaoId]); $pedPag = $pedPagStmt->fetchAll();
$pedCanStmt = $pdo->prepare("SELECT id, mesa, total, created_at FROM pedidos WHERE caixa_sessao_id = ? AND status = 'CANCELADO' ORDER BY created_at ASC");
$pedCanStmt->execute([$sessaoId]); $pedCan = $pedCanStmt->fetchAll();

?><!doctype html>
<html><head><meta charset="utf-8"><title>Fechamento Caixa #<?= htmlspecialchars($sess['id']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{font-family:Arial,Helvetica,sans-serif;padding:20px} .small{font-size:0.9rem}</style>
</head><body>
  <div class="container">
    <h4>Fechamento de Caixa - Sessão #<?= htmlspecialchars($sess['id']) ?></h4>
    <div class="small text-muted">Aberto em: <?= htmlspecialchars($sess['opened_at']) ?><?php if (!empty($sess['closed_at'])) echo ' | Fechado em: '.htmlspecialchars($sess['closed_at']); ?></div>
    <hr />

    <div class="row mb-3">
      <div class="col-md-3">Abertura: R$ <?= number_format($opening_cash,2,',','.') ?></div>
      <div class="col-md-3">Fechamento (informado): R$ <?= number_format($closing_cash,2,',','.') ?></div>
      <div class="col-md-3">Total Pago: R$ <?= number_format($total_pago,2,',','.') ?></div>
      <div class="col-md-3">Diferença: R$ <?= number_format($diferenca,2,',','.') ?> <span class="small text-muted">(<?= $diferenca_label ?>)</span></div>
    </div>

    <h6>Pedidos Pagos</h6>
    <table class="table table-sm">
      <thead><tr><th>ID</th><th>Mesa</th><th>Total</th><th>Hora</th></tr></thead>
      <tbody>
        <?php if (count($pedPag) === 0) echo '<tr><td colspan="4">Nenhum</td></tr>'; else foreach ($pedPag as $p): ?>
          <tr>
            <td><?= htmlspecialchars($p['id']) ?></td>
            <td><?= htmlspecialchars($p['mesa']) ?></td>
            <td>R$ <?= number_format((float)$p['total'],2,',','.') ?></td>
            <td class="small"><?= htmlspecialchars($p['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <h6>Pedidos Cancelados</h6>
    <table class="table table-sm">
      <thead><tr><th>ID</th><th>Mesa</th><th>Total</th><th>Hora</th></tr></thead>
      <tbody>
        <?php if (count($pedCan) === 0) echo '<tr><td colspan="4">Nenhum</td></tr>'; else foreach ($pedCan as $p): ?>
          <tr>
            <td><?= htmlspecialchars($p['id']) ?></td>
            <td><?= htmlspecialchars($p['mesa']) ?></td>
            <td>R$ <?= number_format((float)$p['total'],2,',','.') ?></td>
            <td class="small"><?= htmlspecialchars($p['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <hr />
    <div class="d-flex justify-content-between">
      <div>Total Cancelado: <strong>R$ <?= number_format($total_cancelado,2,',','.') ?></strong></div>
      <div>Total em Caixa (pagos - cancelados + abertura): <strong>R$ <?= number_format($opening_cash + $total_pago - $total_cancelado,2,',','.') ?></strong></div>
    </div>

    <div class="mt-4"><button onclick="window.print()" class="btn btn-primary btn-sm">Imprimir</button></div>
  </div>
</body></html>
