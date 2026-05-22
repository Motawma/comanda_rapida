<?php
// printer/fechamento_caixa.php
require_once __DIR__ . '/../funcoes.php';

$sessaoId = isset($_GET['sessao_id']) ? (int)$_GET['sessao_id'] : 0;
if ($sessaoId <= 0) { echo 'Sessao invalida'; exit; }

$pdo = getPDO();
$empresaId = currentEmpresaId();

$stmt = $pdo->prepare("SELECT * FROM caixa_sessoes WHERE id = ? AND empresa_id = ?");
$stmt->execute([$sessaoId, $empresaId]);
$sess = $stmt->fetch();
if (!$sess) { echo 'Sessao nao encontrada'; exit; }

$paidStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS v FROM pedidos WHERE caixa_sessao_id = ? AND empresa_id = ? AND status = 'PAGO'");
$paidStmt->execute([$sessaoId, $empresaId]); $total_pago = (float)$paidStmt->fetch()['v'];

$cancelStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS v FROM pedidos WHERE caixa_sessao_id = ? AND empresa_id = ? AND status = 'CANCELADO'");
$cancelStmt->execute([$sessaoId, $empresaId]); $total_cancelado = (float)$cancelStmt->fetch()['v'];

$pfStmt = $pdo->prepare("SELECT UPPER(TRIM(COALESCE(metodo_pagamento,''))) AS metodo, COALESCE(SUM(total),0) AS total FROM pedidos WHERE caixa_sessao_id = ? AND empresa_id = ? AND status = 'PAGO' GROUP BY metodo_pagamento");
$pfStmt->execute([$sessaoId, $empresaId]);
$por_forma = ['DINHEIRO'=>0.0,'PIX'=>0.0,'CREDITO'=>0.0,'DEBITO'=>0.0];
foreach ($pfStmt->fetchAll() as $r) { $m=$r['metodo']; if(isset($por_forma[$m])) $por_forma[$m]+=(float)$r['total']; }

$opening_cash = (float)($sess['opening_cash'] ?? 0.0);
$closing_cash = (float)($sess['closing_cash'] ?? 0.0);
if (isset($sess['diferenca']) && $sess['diferenca'] !== null) { $diferenca = (float)$sess['diferenca']; }
elseif ($closing_cash > 0) { $diferenca = $closing_cash - $total_pago; }
else { $diferenca = 0.0; }
$diferenca_cor   = abs($diferenca) < 0.01 ? 'text-success' : 'text-danger';
$diferenca_label = abs($diferenca) < 0.01 ? '(Exato)' : ($diferenca > 0 ? '(Sobrando)' : '(Faltando)');

$pedPagStmt = $pdo->prepare("SELECT id, mesa, total, created_at FROM pedidos WHERE caixa_sessao_id = ? AND empresa_id = ? AND status = 'PAGO' ORDER BY created_at ASC");
$pedPagStmt->execute([$sessaoId, $empresaId]); $pedPag = $pedPagStmt->fetchAll();
$pedCanStmt = $pdo->prepare("SELECT id, mesa, total, created_at FROM pedidos WHERE caixa_sessao_id = ? AND empresa_id = ? AND status = 'CANCELADO' ORDER BY created_at ASC");
$pedCanStmt->execute([$sessaoId, $empresaId]); $pedCan = $pedCanStmt->fetchAll();
$fmt = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
?><!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Fechamento Caixa #<?= $sess['id'] ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{font-family:Arial,sans-serif;padding:20px;font-size:.9rem}
    .card-pgto{border-radius:8px;padding:10px 16px;margin-bottom:8px;display:flex;justify-content:space-between;font-weight:600}
    .card-dinheiro{background:#d4edda;color:#155724}
    .card-pix{background:#cce5ff;color:#004085}
    .card-credito{background:#fff3cd;color:#856404}
    .card-debito{background:#f8d7da;color:#721c24}
    @media print{.no-print{display:none!important}}
  </style>
</head>
<body>
<div class="container">
  <h4 class="mb-0">Fechamento de Caixa &mdash; Sess&atilde;o #<?= $sess['id'] ?></h4>
  <div class="text-muted small mb-2">Aberto em: <?= $sess['opened_at'] ?><?php if(!empty($sess['closed_at'])) echo ' | Fechado em: '.$sess['closed_at']; ?></div>
  <hr>
  <div class="row mb-3 small">
    <div class="col-md-3">Abertura: <strong><?= $fmt($opening_cash) ?></strong></div>
    <div class="col-md-3">Fechamento (informado): <strong><?= $fmt($closing_cash) ?></strong></div>
    <div class="col-md-3">Total Pago: <strong><?= $fmt($total_pago) ?></strong></div>
    <div class="col-md-3">Diferen&ccedil;a: <strong class="<?= $diferenca_cor ?>"><?= $fmt($diferenca) ?> <?= $diferenca_label ?></strong></div>
  </div>
  <div class="row">
    <div class="col-md-6">
      <h6 class="fw-bold mb-2">Recebimentos por Tipo de Pagamento</h6>
      <div class="card-pgto card-dinheiro"><span>Dinheiro</span><span><?= $fmt($por_forma['DINHEIRO']) ?></span></div>
      <div class="card-pgto card-pix"><span>Pix</span><span><?= $fmt($por_forma['PIX']) ?></span></div>
      <div class="card-pgto card-credito"><span>Cart&atilde;o de Cr&eacute;dito</span><span><?= $fmt($por_forma['CREDITO']) ?></span></div>
      <div class="card-pgto card-debito"><span>Cart&atilde;o de D&eacute;bito</span><span><?= $fmt($por_forma['DEBITO']) ?></span></div>
    </div>
    <div class="col-md-6">
      <table class="table table-bordered table-sm">
        <tr><td>Total Pago:</td><td class="text-end"><?= $fmt($total_pago) ?></td></tr>
        <tr class="fw-bold table-success"><td>Total em Caixa:</td><td class="text-end"><?= $fmt($closing_cash) ?></td></tr>
      </table>
    </div>
  </div>
  <h6 class="mt-2">Pedidos Pagos</h6>
  <table class="table table-sm table-striped">
    <thead><tr><th>ID</th><th>Mesa</th><th>Total</th><th>Hora</th></tr></thead>
    <tbody>
      <?php if(!$pedPag): ?><tr><td colspan="4">Nenhum</td></tr>
      <?php else: foreach($pedPag as $p): ?>
      <tr><td><?= $p['id'] ?></td><td><?= $p['mesa'] ?></td><td><?= $fmt($p['total']) ?></td><td class="text-muted"><?= $p['created_at'] ?></td></tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  <h6>Pedidos Cancelados</h6>
  <table class="table table-sm table-striped">
    <thead><tr><th>ID</th><th>Mesa</th><th>Total</th><th>Hora</th></tr></thead>
    <tbody>
      <?php if(!$pedCan): ?><tr><td colspan="4">Nenhum</td></tr>
      <?php else: foreach($pedCan as $p): ?>
      <tr><td><?= $p['id'] ?></td><td><?= $p['mesa'] ?></td><td><?= $fmt($p['total']) ?></td><td class="text-muted"><?= $p['created_at'] ?></td></tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  <div class="no-print mt-3 d-flex gap-2">
    <button onclick="window.print()" class="btn btn-primary btn-sm">Imprimir</button>
    <button onclick="enviarWhatsApp()" class="btn btn-success btn-sm">Enviar por WhatsApp</button>
  </div>
</div>
<script>
function enviarWhatsApp(){
  const fmt=v=>'R$ '+Number(v).toFixed(2).replace('.',',');
  const msg=['Fechamento #<?= $sess['id'] ?>','Dinheiro: '+fmt(<?= $por_forma['DINHEIRO'] ?>),'Pix: '+fmt(<?= $por_forma['PIX'] ?>),'Credito: '+fmt(<?= $por_forma['CREDITO'] ?>),'Debito: '+fmt(<?= $por_forma['DEBITO'] ?>),'Total: '+fmt(<?= $total_pago ?>)].join('\n');
  window.open('https://wa.me/?text='+encodeURIComponent(msg),'_blank');
}
</script>
</body></html>
