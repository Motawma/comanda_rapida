<?php
// ifood.php — Painel iFood
require_once __DIR__ . '/auth.php';
requireAdminPage();
require_once __DIR__ . '/conexao.php';
$pdo = getPDO();
$empresaId = currentEmpresaId();

// Verifica acesso ao módulo iFood
if (!isMaster() && !empresaTemRecurso($empresaId, 'ifood')) {
    header('Location: ./admin.php');
    exit;
}

$_csrfToken = csrfToken();

// Busca sessão aberta
$sessStmt = $pdo->prepare("SELECT * FROM caixa_sessoes WHERE empresa_id = ? AND closed_at IS NULL ORDER BY id DESC LIMIT 1");
$sessStmt->execute([$empresaId]);
$sessao = $sessStmt->fetch();

// Busca config iFood
$cfgStmt = $pdo->prepare("SELECT * FROM ifood_config WHERE empresa_id = ?");
$cfgStmt->execute([$empresaId]);
$ifoodCfg = $cfgStmt->fetch();
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" type="image/svg+xml" href="favicon.svg"><link rel="shortcut icon" href="favicon.svg">
  <title>iFood — V12</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="theme.css?v=<?= filemtime(__DIR__.'/theme.css') ?>">
  <script src="theme.js?v=<?= filemtime(__DIR__.'/theme.js') ?>"></script>
  <style>
    body { background: var(--bs-body-bg, #111); color: var(--bs-body-color, #eee); min-height: 100vh; }
    .topbar { background: #ea1d2c; padding: .6rem 1rem; display: flex; align-items: center; gap: 1rem; }
    .topbar .brand { color: #fff; font-weight: 800; font-size: 1.2rem; letter-spacing: -0.5px; }
    .topbar .brand span { background: #fff; color: #ea1d2c; border-radius: 4px; padding: 1px 6px; margin-right: 4px; }
    .status-badge { display: inline-block; padding: .25rem .6rem; border-radius: 20px; font-size: .75rem; font-weight: 700; }
    .status-PLACED        { background: #fff3cd; color: #856404; }
    .status-CONFIRMED     { background: #cff4fc; color: #055160; }
    .status-IN_PREPARATION{ background: #cfe2ff; color: #084298; }
    .status-READY_TO_PICKUP{background: #d1e7dd; color: #0a3622; }
    .status-DISPATCHED    { background: #e2d9f3; color: #432874; }
    .status-CONCLUDED     { background: #d1e7dd; color: #0a3622; }
    .status-CANCELLED     { background: #f8d7da; color: #842029; }
    .card-pedido { border: 1px solid rgba(255,255,255,.08); border-radius: 12px; background: rgba(255,255,255,.04); margin-bottom: .75rem; transition: border-color .2s; }
    .card-pedido:hover { border-color: #ea1d2c55; }
    .card-pedido .header { padding: .75rem 1rem .5rem; display: flex; justify-content: space-between; align-items: flex-start; gap: .5rem; }
    .card-pedido .body { padding: 0 1rem .75rem; }
    .item-row { display: flex; justify-content: space-between; font-size: .83rem; padding: .15rem 0; border-bottom: 1px solid rgba(255,255,255,.04); }
    .item-row:last-child { border-bottom: none; }
    .total-line { display: flex; justify-content: space-between; font-weight: 700; font-size: .95rem; margin-top: .5rem; padding-top: .5rem; border-top: 1px solid rgba(255,255,255,.1); }
    .pgto-tag { font-size: .72rem; background: rgba(255,255,255,.08); border-radius: 4px; padding: 2px 6px; }
    .dot-live { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #2ecc71; animation: pulse 1.4s infinite; margin-right: 5px; }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }
    .novo-badge { background: #ea1d2c; color: #fff; border-radius: 20px; font-size: .7rem; padding: 1px 8px; font-weight: 700; animation: pop .3s; }
    @keyframes pop { from{transform:scale(0)} to{transform:scale(1)} }
    .empty-state { text-align: center; padding: 3rem 1rem; opacity: .4; }
    .empty-state .icon { font-size: 3rem; }
    .total-dia { background: linear-gradient(135deg, #ea1d2c22, #ea1d2c11); border: 1px solid #ea1d2c44; border-radius: 12px; padding: 1rem 1.5rem; }
  </style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
  <div class="brand"><span>i</span>Food</div>
  <div class="flex-grow-1"></div>
  <span id="statusConexao" class="badge bg-secondary">Verificando...</span>
  <a href="caixa.php" class="btn btn-sm btn-light">← Caixa</a>
  <a href="admin.php" class="btn btn-sm btn-outline-light">⚙️ Admin</a>
</div>

<div class="container-lg py-3">

  <!-- Config / Credenciais -->
  <div class="card mb-3 border-0" style="background:rgba(255,255,255,.04);" id="cardConfig">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0 fw-bold">🔑 Credenciais iFood</h6>
        <button class="btn btn-sm btn-outline-secondary" onclick="toggleConfig()">▼ Configurar</button>
      </div>
      <div id="formConfig" style="display:none;">
        <div id="msgConfig" class="mb-2"></div>
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label small">Client ID</label>
            <input id="cfg_client_id" class="form-control form-control-sm" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
          </div>
          <div class="col-md-4">
            <label class="form-label small">Client Secret</label>
            <input id="cfg_client_secret" class="form-control form-control-sm" type="password" placeholder="••••••••">
          </div>
          <div class="col-md-4">
            <label class="form-label small">Merchant ID</label>
            <input id="cfg_merchant_id" class="form-control form-control-sm" placeholder="UUID do restaurante">
          </div>
        </div>
        <div class="d-flex gap-2 mt-2">
          <button class="btn btn-sm btn-danger" onclick="salvarConfig()">💾 Salvar</button>
          <button class="btn btn-sm btn-outline-secondary" onclick="testarConexao()">🔌 Testar Conexão</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Resumo do dia -->
  <div class="row g-3 mb-3">
    <div class="col-sm-4">
      <div class="total-dia">
        <div class="small text-muted">Total iFood hoje</div>
        <div class="fs-4 fw-bold" id="totalDia">R$ 0,00</div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="total-dia">
        <div class="small text-muted">Pedidos</div>
        <div class="fs-4 fw-bold" id="qtdPedidos">0</div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="total-dia">
        <div class="small text-muted">Último pedido</div>
        <div class="fs-5 fw-bold" id="ultimoPedido">—</div>
      </div>
    </div>
  </div>

  <!-- Header lista -->
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0 fw-bold">
      <span class="dot-live"></span> Pedidos do dia
      <span id="novoBadge" class="novo-badge" style="display:none;">0 novo(s)</span>
    </h5>
    <div class="d-flex gap-2 align-items-center">
      <span class="small text-muted" id="ultimaAtualizacao">—</span>
      <button class="btn btn-sm btn-outline-danger" onclick="buscarPedidos()">🔄 Atualizar</button>
    </div>
  </div>

  <!-- Lista de pedidos -->
  <div id="listaPedidos">
    <div class="empty-state">
      <div class="icon">🍔</div>
      <div class="mt-2">Carregando pedidos...</div>
    </div>
  </div>

</div>

<!-- Modal detalhes -->
<div class="modal fade" id="modalDetalhe" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header border-secondary py-2">
        <h6 class="modal-title fw-bold">📋 Detalhe do Pedido</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="modalDetalheBody"></div>
      <div class="modal-footer border-secondary py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
        <button type="button" class="btn btn-success btn-sm" id="btnConfirmar" onclick="atualizarStatus('IN_PREPARATION')">✅ Em Preparo</button>
        <button type="button" class="btn btn-primary btn-sm" id="btnPronto" onclick="atualizarStatus('READY_TO_PICKUP')">🎉 Pronto</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.CSRF_TOKEN = '<?= htmlspecialchars($_csrfToken, ENT_QUOTES) ?>';
(function () {
  const _orig = window.fetch.bind(window);
  window.fetch = function (input, init) {
    init = init || {};
    const url = typeof input === 'string' ? input : '';
    if (!url.startsWith('http://') && !url.startsWith('https://')) {
      init.headers = Object.assign({'X-CSRF-Token': window.CSRF_TOKEN}, init.headers || {});
    }
    return _orig(input, init);
  };
})();
</script>
<script>
const SESSION_ID = <?= $sessao ? (int)$sessao['id'] : 0 ?>;
const CFG        = <?= $ifoodCfg ? json_encode(['client_id' => $ifoodCfg['client_id'], 'merchant_id' => $ifoodCfg['merchant_id'], 'ativo' => $ifoodCfg['ativo']]) : 'null' ?>;

let pedidosConhecidos = new Set();
let pedidoAtualId = null;
let pollingTimer = null;

function fmt(v) { return 'R$ ' + parseFloat(v||0).toFixed(2).replace('.',','); }
function fmtData(d) {
  if (!d) return '—';
  const dt = new Date(d.replace(' ','T'));
  return dt.toLocaleTimeString('pt-BR', {hour:'2-digit',minute:'2-digit'});
}

const statusLabel = {
  PLACED:          '🟡 Recebido',
  CONFIRMED:       '🔵 Confirmado',
  IN_PREPARATION:  '🟠 Em Preparo',
  READY_TO_PICKUP: '🟢 Pronto',
  DISPATCHED:      '🚴 A Caminho',
  CONCLUDED:       '✅ Concluído',
  CANCELLED:       '❌ Cancelado',
};

function toggleConfig() {
  const f = document.getElementById('formConfig');
  f.style.display = f.style.display === 'none' ? 'block' : 'none';
  if (f.style.display === 'block' && CFG) {
    document.getElementById('cfg_client_id').value   = CFG.client_id || '';
    document.getElementById('cfg_merchant_id').value = CFG.merchant_id || '';
  }
}

async function salvarConfig() {
  const body = {
    client_id:     document.getElementById('cfg_client_id').value.trim(),
    client_secret: document.getElementById('cfg_client_secret').value.trim(),
    merchant_id:   document.getElementById('cfg_merchant_id').value.trim(),
  };
  const r = await fetch('api/ifood_api.php?action=salvar_config', {
    method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(body)
  });
  const j = await r.json();
  const msg = document.getElementById('msgConfig');
  msg.innerHTML = `<div class="alert alert-${j.ok ? 'success' : 'danger'} py-1 small">${j.message}</div>`;
  if (j.ok) setTimeout(() => location.reload(), 1500);
}

async function testarConexao() {
  const r = await fetch('api/ifood_api.php?action=testar');
  const j = await r.json();
  const msg = document.getElementById('msgConfig');
  msg.innerHTML = `<div class="alert alert-${j.ok ? 'success' : 'danger'} py-1 small">${j.message}</div>`;
}

async function polling() {
  try {
    const r = await fetch('api/ifood_api.php?action=polling');
    const j = await r.json();
    if (j.novos > 0) {
      await buscarPedidos();
      tocarSom();
    }
    document.getElementById('statusConexao').className = 'badge ' + (j.ok ? 'bg-success' : 'bg-danger');
    document.getElementById('statusConexao').textContent = j.ok ? '● Conectado' : '● Erro';
  } catch(e) {
    document.getElementById('statusConexao').className = 'badge bg-warning text-dark';
    document.getElementById('statusConexao').textContent = '● Offline';
  }
}

async function buscarPedidos() {
  const url = SESSION_ID > 0
    ? `api/ifood_api.php?action=listar&sessao_id=${SESSION_ID}`
    : `api/ifood_api.php?action=listar&data=${new Date().toISOString().slice(0,10)}`;

  const r = await fetch(url);
  const j = await r.json();
  if (!j.ok) return;

  const lista = j.pedidos || [];
  document.getElementById('totalDia').textContent    = fmt(j.total);
  document.getElementById('qtdPedidos').textContent  = lista.length;
  document.getElementById('ultimaAtualizacao').textContent = 'Atualizado ' + new Date().toLocaleTimeString('pt-BR', {hour:'2-digit',minute:'2-digit'});

  if (lista.length > 0) {
    document.getElementById('ultimoPedido').textContent = fmtData(lista[0].created_at);
  }

  // Detectar novos
  const novos = lista.filter(p => !pedidosConhecidos.has(p.id) && p.status !== 'CANCELLED');
  lista.forEach(p => pedidosConhecidos.add(p.id));
  const badge = document.getElementById('novoBadge');
  if (novos.length > 0) {
    badge.textContent = novos.length + ' novo(s)';
    badge.style.display = 'inline';
    setTimeout(() => badge.style.display = 'none', 5000);
  }

  renderLista(lista);
}

function renderLista(lista) {
  const el = document.getElementById('listaPedidos');
  if (lista.length === 0) {
    el.innerHTML = '<div class="empty-state"><div class="icon">🍔</div><div class="mt-2">Nenhum pedido iFood hoje</div></div>';
    return;
  }

  el.innerHTML = lista.map(p => `
    <div class="card-pedido" onclick="abrirDetalhe(${p.id})">
      <div class="header">
        <div>
          <span class="fw-bold">#${p.ifood_reference || p.ifood_order_id.slice(-6)}</span>
          <span class="text-muted small ms-1">${fmtData(p.created_at)}</span>
          ${p.cliente_nome ? `<div class="small text-muted">${p.cliente_nome}</div>` : ''}
        </div>
        <div class="text-end">
          <div class="fw-bold">${fmt(p.total)}</div>
          <span class="status-badge status-${p.status}">${statusLabel[p.status] || p.status}</span>
        </div>
      </div>
      <div class="body">
        <span class="pgto-tag">💳 ${p.pgto_label}</span>
        ${p.endereco_entrega ? `<span class="pgto-tag ms-1">📍 Entrega</span>` : '<span class="pgto-tag ms-1">🏪 Retirada</span>'}
        <span class="float-end small text-muted">${(p.itens||[]).length} item(s)</span>
      </div>
    </div>
  `).join('');
}

let _detalheData = {};
function abrirDetalhe(id) {
  const url = SESSION_ID > 0
    ? `api/ifood_api.php?action=listar&sessao_id=${SESSION_ID}`
    : `api/ifood_api.php?action=listar&data=${new Date().toISOString().slice(0,10)}`;

  fetch(url).then(r => r.json()).then(j => {
    const p = (j.pedidos || []).find(x => x.id === id);
    if (!p) return;
    pedidoAtualId = id;
    _detalheData[id] = p;

    const itens = (p.itens || []).map(it =>
      `<div class="item-row"><span>${it.quantidade}x ${it.nome}</span><span>${fmt(it.subtotal)}</span></div>`
    ).join('');

    document.getElementById('modalDetalheBody').innerHTML = `
      <div class="mb-2">
        <strong>#${p.ifood_reference || p.ifood_order_id.slice(-6)}</strong>
        <span class="status-badge status-${p.status} ms-2">${statusLabel[p.status] || p.status}</span>
        <div class="small text-muted">${p.cliente_nome || ''} ${p.cliente_telefone ? '· ' + p.cliente_telefone : ''}</div>
        ${p.endereco_entrega ? `<div class="small text-muted">📍 ${p.endereco_entrega}</div>` : ''}
      </div>
      <div class="mb-2">${itens || '<div class="text-muted small">Sem itens</div>'}</div>
      <div class="item-row"><span class="text-muted">Entrega</span><span>${fmt(p.total_taxa_entrega)}</span></div>
      ${p.total_desconto > 0 ? `<div class="item-row"><span class="text-success">Desconto</span><span class="text-success">-${fmt(p.total_desconto)}</span></div>` : ''}
      <div class="total-line"><span>Total</span><span>${fmt(p.total)}</span></div>
      <div class="mt-2 small text-muted">💳 ${p.pgto_label}${p.troco > 0 ? ' · Troco: ' + fmt(p.troco) : ''}</div>
    `;

    const btnC = document.getElementById('btnConfirmar');
    const btnP = document.getElementById('btnPronto');
    btnC.style.display = ['PLACED','CONFIRMED'].includes(p.status) ? 'inline-block' : 'none';
    btnP.style.display = p.status === 'IN_PREPARATION' ? 'inline-block' : 'none';

    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalhe')).show();
  });
}

async function atualizarStatus(status) {
  if (!pedidoAtualId) return;
  await fetch('api/ifood_api.php?action=atualizar_status', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ id: pedidoAtualId, status })
  });
  bootstrap.Modal.getInstance(document.getElementById('modalDetalhe'))?.hide();
  await buscarPedidos();
}

function tocarSom() {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    [523, 659, 784].forEach((freq, i) => {
      const o = ctx.createOscillator();
      const g = ctx.createGain();
      o.connect(g); g.connect(ctx.destination);
      o.frequency.value = freq;
      g.gain.setValueAtTime(0.3, ctx.currentTime + i * 0.15);
      g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + i * 0.15 + 0.3);
      o.start(ctx.currentTime + i * 0.15);
      o.stop(ctx.currentTime + i * 0.15 + 0.3);
    });
  } catch(e) {}
}

// Inicializar
document.addEventListener('DOMContentLoaded', async () => {
  await buscarPedidos();
  if (CFG) {
    await polling();
    pollingTimer = setInterval(polling, 30000); // polling a cada 30s
  } else {
    document.getElementById('statusConexao').textContent = '⚙️ Configurar';
    document.getElementById('statusConexao').className = 'badge bg-warning text-dark';
    toggleConfig(); // abre form config automaticamente
  }
});
</script>
</body>
</html>
