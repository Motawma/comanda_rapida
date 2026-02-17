<?php
// caixa.php - Painel do Caixa (MVP)
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Caixa - Comanda Rápida</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .status-badge { min-width:100px; display:inline-block; text-align:center; }

    /* ===== RESPONSIVO CAIXA ===== */
    .acoes-cell{
      display:flex;
      flex-wrap:wrap;
      gap:.25rem;
      align-items:center;
    }

    .acoes-cell .btn{
      padding: .15rem .4rem;
      font-size: .78rem;
      line-height: 1.1;
      /* melhorar comportamento em dispositivos touch */
      touch-action: manipulation;
      -webkit-tap-highlight-color: transparent;
    }

    .table td, .table th{
      vertical-align: middle;
    }

    @media (max-width: 576px){
      h3{ font-size:1.1rem; }

      .status-badge{
        min-width:70px;
        font-size:.72rem;
      }

      .table{
        font-size:.78rem;
      }

      .table td, .table th{
        padding:.35rem;
      }

      /* Esconde coluna Criado em no celular */
      .col-criadoem{
        display:none;
      }
    }
  </style>
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/partials/admin_nav.php'; ?>
<?php
require_once __DIR__ . '/auth.php';
if (isLoggedIn() && (currentUser()['role'] ?? '') === 'admin') {
  echo '<div style="height:56px"></div>';
}
?>
<div class="container py-3">
  <!-- SESSÃO DE CAIXA -->
  <div id="caixa_session_bar" class="mb-3 d-flex align-items-center justify-content-between">
    <!-- preenchido via JS -->
  </div>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="m-0">Caixa - Pedidos do dia</h3>
    <div>
      <label class="form-label mb-0 small">Data</label>
      <input id="date" type="date" class="form-control form-control-sm" />
    </div>
  </div>

  <div class="mb-3 d-flex align-items-center gap-3 flex-wrap">
    <div>
      <div class="small">Total vendido (PAGO)</div>
      <div id="total_vendido" class="fs-4 fw-bold">R$ 0,00</div>
    </div>

    <div class="ms-auto d-flex gap-2 align-items-center">
      <div class="small me-2">Filtrar</div>

      <input
        id="mesa_search"
        class="form-control form-control-sm"
        style="max-width:160px"
        placeholder="Buscar mesa..."
        inputmode="numeric"
      />

      <select id="status_filter" class="form-select form-select-sm" style="max-width:160px">
        <option value="ALL">Todos</option>
        <option value="PENDENTE">PENDENTE</option>
        <option value="EM_PREPARO">EM_PREPARO</option>
        <option value="PRONTO">PRONTO</option>
        <option value="FIADO">FIADO</option>
        <option value="PAGO">PAGO</option>
        <option value="CANCELADO">CANCELADO</option>
      </select>
    </div>
  </div>

  <div class="mb-3" id="counters"></div>

  <div class="mb-3">
    <button id="btnPendencias" type="button" class="btn btn-outline-primary btn-sm">Pendências (Fiado)</button>
  </div>

  <div class="table-responsive">
    <table class="table table-sm table-striped align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>Mesa</th>
          <th>Status</th>
          <th>Total</th>
          <th class="col-criadoem">Criado em</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody id="tbody_pedidos">
        <tr><td colspan="6" class="text-center small">Carregando...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Abrir Caixa -->
<div class="modal fade" id="abrirCaixaModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Abrir Caixa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Caixa inicial</label><input id="abrir_opening_cash" class="form-control" inputmode="decimal" placeholder="0.00"></div>
        <div class="mb-3"><label class="form-label">Observações</label><input id="abrir_obs" class="form-control" maxlength="255"></div>
        <div id="abrirMsg"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        <button id="btnAbrirCaixa" type="button" class="btn btn-primary">Abrir Caixa</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Fechar Caixa -->
<div class="modal fade" id="fecharCaixaModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Fechar Caixa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Valor em caixa</label><input id="fechar_closing_cash" class="form-control" inputmode="decimal" placeholder="0.00"></div>
        <div class="mb-3"><label class="form-label">Observações</label><input id="fechar_obs" class="form-control" maxlength="255"></div>
        <div id="fecharMsg"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button id="btnFecharCaixa" type="button" class="btn btn-danger">Fechar Caixa</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Cancelar Pedido -->
<div class="modal fade" id="cancelarModal" tabindex="-1" aria-labelledby="cancelarModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="cancelarModalLabel">Cancelar Pedido</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="cancel_motivo" class="form-label">Motivo do cancelamento <span class="text-danger">*</span></label>
          <input id="cancel_motivo" type="text" class="form-control" maxlength="255" placeholder="Informe o motivo (obrigatório)">
          <input id="cancel_pedido_id" type="hidden" />
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Voltar</button>
        <button id="confirmCancelarBtn" type="button" class="btn btn-danger">Confirmar Cancelamento</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Pendências -->
<div class="modal fade" id="pendenciasModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Pendências (Fiado)</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3 d-flex gap-2 align-items-center">
          <input type="text" id="pendencias_mesa" class="form-control form-control-sm" placeholder="Filtrar por mesa..." style="max-width:180px" />
          <button id="pendencias_buscar" type="button" class="btn btn-primary btn-sm">Buscar</button>
          <button id="pendencias_limpar" type="button" class="btn btn-secondary btn-sm">Limpar</button>
        </div>
        <div id="pendencias_list">Carregando...</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Vincular Fiado -->
<div class="modal fade" id="vincularFiadoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Vincular Fiado</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" id="vincular_fiado_id" />
        <div class="mb-2"><span id="vincular_fiado_info"></span></div>
        <!-- aviso discreto abaixo do texto do fiado -->
        <div id="vincular_warn" class="alert alert-info py-1 small d-none mb-2"></div>
        <div class="mb-3">
          <label class="form-label">Comandas abertas (sessão atual)</label>
          <select id="vincular_pedido_select" class="form-select"></select>
          <div id="vincular_no_pedidos" class="small text-muted mt-1" style="display:none">Nenhuma comanda aberta para esta mesa</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        <button id="vincular_confirmar" type="button" class="btn btn-primary">Confirmar</button>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap JS (modal) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
let isLoading = false;
let _pendenciasModalInstance = null;
let _pendenciasListenersAttached = false;
let currentSessaoId = 0;
let _abrirModalInstance = null;
let _fecharModalInstance = null;
let _cancelarModalInstance = null;
let _vincularModalInstance = null;

function statusClass(status) {
  switch ((status || '').toUpperCase()) {
    case 'PENDENTE': return 'badge bg-secondary';
    case 'EM_PREPARO': return 'badge bg-warning text-dark';
    case 'PRONTO': return 'badge bg-primary';
    case 'FIADO': return 'badge bg-info text-dark';
    case 'PAGO': return 'badge bg-success';
    case 'CANCELADO': return 'badge bg-danger';
    default: return 'badge bg-light text-dark';
  }
}

let _autoRefreshPausedUntil = 0;
function pauseAutoRefresh(ms = 8000) {
  _autoRefreshPausedUntil = Date.now() + ms;
}
function canAutoRefresh() {
  return Date.now() > _autoRefreshPausedUntil;
}

// Added: formatBRL used across this script to format currency in BRL
function formatBRL(value) {
  const num = Number(value) || 0;
  return num.toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL'
  });
}

async function fetchCaixaStatus() {
  try {
    const res = await fetch('api/caixa_status.php');
    const j = await res.json();
    if (!j.success) return null;
    return j.sessao || null;
  } catch (e) { return null; }
}

function renderCaixaBar(sessao) {
  const bar = document.getElementById('caixa_session_bar');
  if (!bar) return;
  bar.innerHTML = '';
  if (!sessao) {
    const left = document.createElement('div');
    left.innerHTML = '<strong>Caixa fechado</strong>';
    const btn = document.createElement('button'); btn.type='button'; btn.className='btn btn-sm btn-primary'; btn.textContent='Abrir Caixa';
    btn.addEventListener('click', () => { const el = document.getElementById('abrirCaixaModal'); if (!_abrirModalInstance) _abrirModalInstance = new bootstrap.Modal(el); _abrirModalInstance.show(); });
    bar.appendChild(left); bar.appendChild(btn);
    return;
  }

  currentSessaoId = parseInt(sessao.id) || 0;
  const left = document.createElement('div');
  left.innerHTML = `<div><strong>Sessão #${sessao.id}</strong> aberta em ${sessao.opened_at}</div>`;
  const btnClose = document.createElement('button'); btnClose.type='button'; btnClose.className='btn btn-sm btn-danger'; btnClose.textContent='Fechar Caixa';
  btnClose.addEventListener('click', () => { const el = document.getElementById('fecharCaixaModal'); if (!_fecharModalInstance) _fecharModalInstance = new bootstrap.Modal(el); _fecharModalInstance.show(); });
  bar.appendChild(left); bar.appendChild(btnClose);
}

// abrir caixa
document.getElementById('btnAbrirCaixa')?.addEventListener('click', async () => {
  const val = parseFloat((document.getElementById('abrir_opening_cash').value || '0').replace(',','.')) || 0.0;
  const obs = (document.getElementById('abrir_obs').value || '').trim();
  const msg = document.getElementById('abrirMsg'); if (msg) msg.innerHTML = '';
  try {
    const res = await fetch('api/caixa_abrir.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ opening_cash: val, obs }) });
    const j = await res.json();
    if (!j.success) { if (msg) msg.innerHTML = '<div class="alert alert-danger">'+(j.message||'Erro')+'</div>'; return; }
    if (_abrirModalInstance) _abrirModalInstance.hide();
    const sessao = await fetchCaixaStatus(); renderCaixaBar(sessao); loadPedidos();
  } catch (e) { if (msg) msg.innerHTML = '<div class="alert alert-danger">Erro de rede</div>'; }
});

// fechar caixa
document.getElementById('btnFecharCaixa')?.addEventListener('click', async () => {
  const val = parseFloat((document.getElementById('fechar_closing_cash').value || '0').replace(',','.')) || 0.0;
  const obs = (document.getElementById('fechar_obs').value || '').trim();
  const msg = document.getElementById('fecharMsg'); if (msg) msg.innerHTML = '';
  if (!confirm('Confirma fechar o caixa?')) return;
  try {
    const res = await fetch('api/caixa_fechar.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ closing_cash: val, obs }) });
    const j = await res.json();
    if (!j.success) { if (msg) msg.innerHTML = '<div class="alert alert-danger">'+(j.message||'Erro')+'</div>'; return; }
    if (_fecharModalInstance) _fecharModalInstance.hide();
    // abrir relatório de fechamento
    window.open('printer/fechamento_caixa.php?sessao_id=' + j.sessao_id, '_blank');
    // recarrega página para mostrar caixa fechado
    setTimeout(() => location.reload(), 400);
  } catch (e) { if (msg) msg.innerHTML = '<div class="alert alert-danger">Erro de rede</div>'; }
});

// === funções globais exigidas pelos botões (devem estar no escopo global) ===
async function atualizarStatus(pedidoId, status) {
  try {
    const res = await fetch('api/atualizar_status.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ pedido_id: pedidoId, status })
    });

    // Tentar ler o body como JSON; se falhar, também capturamos o texto cru
    let j = null;
    let rawText = '';
    try { j = await res.json(); } catch (err) { rawText = await res.text().catch(() => ''); }

    // Se o HTTP não for OK, mostre a mensagem do servidor (preferindo j.message, depois trecho do HTML/plain text)
    if (!res.ok) {
      const msg = (j && j.message) ? j.message : (rawText ? rawText.slice(0,200) : ('Erro HTTP ' + res.status));
      alert(msg);
      return false;
    }

    // HTTP OK, mas payload pode indicar falha
    if (!j || !j.success) {
      const msg = (j && j.message) ? j.message : (rawText ? rawText.slice(0,200) : 'Falha ao atualizar status');
      alert('Erro: ' + msg);
      return false;
    }

    // Sucesso
    return true;
  } catch (e) {
    alert('Erro de rede: ' + e.message);
    return false;
  }
}

async function reimprimir(pedidoId) {
  try {
    const res = await fetch('api/reimprimir.php', {
      method: 'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ pedido_id: pedidoId })
    });
    const j = await res.json();
    if (j.success) alert('Reimpressão iniciada (ou OK)');
    else alert('Erro: ' + (j.message || 'Falha na reimpressão'));
  } catch (e) {
    alert('Erro de rede: ' + e.message);
  }
}

function abrirCancelarModal(pedidoId) {
  const motivoInput = document.getElementById('cancel_motivo');
  const idInput = document.getElementById('cancel_pedido_id');
  const modalEl = document.getElementById('cancelarModal');
  if (!modalEl) return;
  if (!_cancelarModalInstance) _cancelarModalInstance = new bootstrap.Modal(modalEl);
  if (motivoInput) motivoInput.value = '';
  if (idInput) idInput.value = pedidoId;
  _cancelarModalInstance.show();
  setTimeout(() => { if (motivoInput) motivoInput.focus(); }, 200);
}

async function cancelarPedidoRequest() {
  const motivoInput = document.getElementById('cancel_motivo');
  const idInput = document.getElementById('cancel_pedido_id');
  if (!motivoInput || !idInput) return;
  const motivo = (motivoInput.value || '').trim();
  const pedidoId = parseInt(idInput.value) || 0;
  if (!motivo) { alert('O motivo é obrigatório.'); motivoInput.focus(); return; }

  try {
    const res = await fetch('api/cancelar_pedido.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ pedido_id: pedidoId, motivo })
    });

    // Tentar ler o body como JSON; se falhar, também capturamos o texto cru
    let j = null;
    let rawText = '';
    try { j = await res.json(); } catch (err) { rawText = await res.text().catch(() => ''); }

    // Se o HTTP não for OK, mostre a mensagem do servidor (preferindo j.message, depois trecho do HTML/plain text)
    if (!res.ok) {
      const msg = (j && j.message) ? j.message : (rawText ? rawText.slice(0,200) : ('Erro HTTP ' + res.status));
      alert(msg);
      return;
    }

    // HTTP OK, mas payload pode indicar falha
    if (!j || !j.success) {
      const msg = (j && j.message) ? j.message : (rawText ? rawText.slice(0,200) : 'Falha ao cancelar');
      alert('Erro: ' + msg);
      return;
    }

    if (_cancelarModalInstance) _cancelarModalInstance.hide();
    await loadPedidos();
  } catch (e) {
    alert('Erro de rede: ' + (e && e.message ? e.message : e));
  }
}

// função global para abrir modal de vincular fiado
function openVincularFiadoModal(fiado) {
  try {
    const el = document.getElementById('vincularFiadoModal');
    if (!el) return;
    if (!_vincularModalInstance) _vincularModalInstance = new bootstrap.Modal(el);

    const hid = document.getElementById('vincular_fiado_id');
    const info = document.getElementById('vincular_fiado_info');
    const select = document.getElementById('vincular_pedido_select');
    const warn = document.getElementById('vincular_warn');
    const btnConfirm = document.getElementById('vincular_confirmar');
    if (hid) hid.value = fiado.id || '';
    if (info) info.textContent = `Fiado #${fiado.id} — Mesa ${fiado.mesa} — ${formatBRL(fiado.total)}`;

    // Mostrar aviso informativo sempre
    if (warn) {
      warn.textContent = 'Selecione a comanda aberta (qualquer mesa) para vincular esta pendência.';
      warn.classList.remove('d-none');
    }

    // Preencher select a partir de pedidos carregados em loadPedidos
    if (select) {
      select.innerHTML = '';
      const pedidos = window._lastPedidos || [];
      // candidatos: qualquer comanda aberta na SESSÃO atual com status específico (sem filtro por mesa)
      let candidatos = pedidos.filter(p => ['PENDENTE','EM_PREPARO','PRONTO'].includes((p.status||'').toUpperCase()));

      // Ordenar por mesa ASC e id DESC
      candidatos.sort((a, b) => {
        const ma = String(a.mesa || '');
        const mb = String(b.mesa || '');
        const cmp = ma.localeCompare(mb, undefined, {numeric: true});
        if (cmp !== 0) return cmp;
        return (b.id || 0) - (a.id || 0);
      });

      if (!candidatos || candidatos.length === 0) {
        select.innerHTML = '<option value="">Nenhuma comanda aberta nesta sessão</option>';
        select.disabled = true;
        if (btnConfirm) btnConfirm.disabled = true;
      } else {
        candidatos.forEach(p => {
          const opt = document.createElement('option');
          opt.value = p.id;
          opt.textContent = `#${p.id} — Mesa ${p.mesa} — ${formatBRL(p.total)} — ${p.status}`;
          select.appendChild(opt);
        });
        select.disabled = false;
        if (btnConfirm) btnConfirm.disabled = false;
      }
    } else {
      // Se o select não existe, desabilitar confirmar por segurança
      if (btnConfirm) btnConfirm.disabled = true;
    }

    _vincularModalInstance.show();
  } catch (e) {
    console.error('openVincularFiadoModal error', e);
  }
}

// === fim das funções globais ===

async function loadPedidos() {
  if (isLoading) return;
  isLoading = true;
  const tbody = document.getElementById('tbody_pedidos');
  try {
    const dateEl = document.getElementById('date');
    const statusEl = document.getElementById('status_filter');
    const date = dateEl.value || new Date().toISOString().slice(0,10);
    const status = statusEl.value;

    // Prefer sessao context
    let url;
    if (currentSessaoId && currentSessaoId > 0) {
      url = `api/listar_pedidos.php?sessao_id=${encodeURIComponent(currentSessaoId)}&status=${encodeURIComponent(status)}`;
    } else {
      url = `api/listar_pedidos.php?date=${encodeURIComponent(date)}&status=${encodeURIComponent(status)}`;
    }

    const resp = await fetch(url);
    if (!resp.ok) throw new Error('HTTP error ' + resp.status);
    const data = await resp.json();
    if (!data || !data.success) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Erro ao carregar</td></tr>';
      console.error('listar_pedidos error', data);
      return;
    }

    // contadores
    const counters = data.contadores || {};
    const div = document.getElementById('counters');
    div.innerHTML = '';
    Object.keys(counters).forEach(k => {
      const b = document.createElement('span');
      b.className = statusClass(k) + ' me-1';
      b.textContent = `${k}: ${counters[k]}`;
      div.appendChild(b);
    });

    // total vendido
    document.getElementById('total_vendido').textContent = formatBRL(data.total_vendido);

    // tabela
    tbody.innerHTML = '';
    if (!data.pedidos || data.pedidos.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center small">Nenhum pedido</td></tr>';
      return;
    }

    const mesaSearch = (document.getElementById('mesa_search')?.value || '').trim().toLowerCase();
    let pedidos = data.pedidos || [];
    // salvar lista de pedidos para uso por outros modais (vincular fiado)
    window._lastPedidos = pedidos;
    if (mesaSearch) {
      pedidos = pedidos.filter(p => String(p.mesa ?? '').toLowerCase().includes(mesaSearch));
    }

    pedidos.forEach(p => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${p.id}</td>
        <td>${p.mesa}</td>
        <td><span class="${statusClass(p.status)} status-badge">${p.status}</span></td>
        <td>${formatBRL(p.total)}</td>
        <td class="small col-criadoem">${p.created_at}</td>
        <td></td>
      `;

      const tdAcoes = tr.querySelector('td:last-child');
      tdAcoes.classList.add('acoes-cell');

      // Pausar auto-refresh quando o usuário tocar na área de ações (mobile)
      tdAcoes.addEventListener('touchstart', () => { pauseAutoRefresh(); }, { passive: true });
      tdAcoes.addEventListener('touchend', () => { /* short pause after touch */ pauseAutoRefresh(1200); }, { passive: true });

      // Criar botões e garantir btn.type = 'button'
      const makeBtn = (className, text, onClick) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = className;
        b.textContent = text;

        let fired = false;

        // touchend para dispositivos touch (prevenir comportamento padrão e propagação)
        b.addEventListener('touchend', (e) => {
          e.preventDefault();
          e.stopPropagation();
          fired = true;
          try { onClick(); } catch (err) { console.error(err); }
          // reset flag após curta espera para permitir future clicks
          setTimeout(() => fired = false, 400);
        }, { passive: false });

        // click para desktop; evita executar se touchend já disparou
        b.addEventListener('click', (e) => {
          if (fired) return;
          e.preventDefault();
          e.stopPropagation();
          try { onClick(); } catch (err) { console.error(err); }
        });

        return b;
      };

      const btnCupom = makeBtn('btn btn-sm btn-outline-primary me-1','Cupom', () => window.open('printer/cupom.php?pedido_id=' + p.id, '_blank'));
      const btnReimpr = makeBtn('btn btn-sm btn-outline-secondary me-1','Reimprimir', () => reimprimir(p.id));

      // define botões usados nas diferentes branches de status
      const btnPrep = makeBtn('btn btn-sm btn-warning me-1', 'Em preparo', () => {
        (async () => { if (await atualizarStatus(p.id, 'EM_PREPARO')) await loadPedidos(); })();
      });

      const btnPronto = makeBtn('btn btn-sm btn-primary me-1', 'Marcar Pronto', () => {
        (async () => { if (await atualizarStatus(p.id, 'PRONTO')) await loadPedidos(); })();
      });

      const btnPago = (function(){
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn btn-sm btn-success btn-finalizar me-1';
        b.textContent = 'Finalizar Comanda';
        b.dataset.id = p.id;
        return b;
      })();
      const btnFiado = makeBtn('btn btn-sm btn-secondary me-1','Marcar Fiado', () => { (async () => { if (await atualizarStatus(p.id, 'FIADO')) await loadPedidos(); })(); });
      const btnVoltarPendente = makeBtn('btn btn-sm btn-outline-secondary me-1','Voltar Pendente', () => { if (confirm('Confirma voltar este pedido para PENDENTE?')) atualizarStatus(p.id, 'PENDENTE'); });
      const btnVoltarEmPrep = makeBtn('btn btn-sm btn-outline-secondary me-1','Voltar Em preparo', () => { if (confirm('Confirma voltar este pedido para EM_PREPARO?')) atualizarStatus(p.id, 'EM_PREPARO'); });
      const btnCancelar = makeBtn('btn btn-sm btn-danger me-1','Cancelar', () => abrirCancelarModal(p.id));

      tdAcoes.appendChild(btnCupom);
      tdAcoes.appendChild(btnReimpr);

      switch (p.status) {
        case 'PENDENTE':
          tdAcoes.appendChild(btnPrep);
          tdAcoes.appendChild(btnCancelar);
          break;
        case 'EM_PREPARO':
          tdAcoes.appendChild(btnPronto);
          tdAcoes.appendChild(btnVoltarPendente);
          tdAcoes.appendChild(btnCancelar);
          break;
        case 'PRONTO':
          tdAcoes.appendChild(btnPago);
          tdAcoes.appendChild(btnFiado);
          tdAcoes.appendChild(btnVoltarEmPrep);
          tdAcoes.appendChild(btnCancelar);
          break;
        case 'PAGO':
        case 'CANCELADO':
          break;
      }

      tbody.appendChild(tr);
    });

  } catch (err) {
    console.error(err);
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Erro ao carregar (ver console)</td></tr>';
  } finally {
    isLoading = false;
  }
}

// Inicializações controladas após DOM carregado
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('date').value = new Date().toISOString().slice(0,10);
  document.getElementById('status_filter').addEventListener('change', loadPedidos);
  document.getElementById('date').addEventListener('change', loadPedidos);

  // search mesa com debounce
  let mesaTimer = null;
  const mesaInput = document.getElementById('mesa_search');
  if (mesaInput) {
    mesaInput.addEventListener('input', () => {
      clearTimeout(mesaTimer);
      mesaTimer = setTimeout(() => loadPedidos(), 250);
    });
  }

  // Vincula botão confirmar modal (apenas uma vez)
  const btnConfirmCancelar = document.getElementById('confirmCancelarBtn');
  if (btnConfirmCancelar) btnConfirmCancelar.addEventListener('click', cancelarPedidoRequest);

  // Pendências modal
  const btnPend = document.getElementById('btnPendencias');
  if (btnPend) btnPend.addEventListener('click', async () => { openPendenciasModal(); });

  async function openPendenciasModal() {
    const el = document.getElementById('pendenciasModal');
    if (!_pendenciasModalInstance) _pendenciasModalInstance = new bootstrap.Modal(el);
    _pendenciasModalInstance.show();
    await fetchPendencias();

    // register listeners once
    if (!_pendenciasListenersAttached) {
      const buscarBtn = document.getElementById('pendencias_buscar');
      const limparBtn = document.getElementById('pendencias_limpar');
      const mesaInput = document.getElementById('pendencias_mesa');
      if (buscarBtn) buscarBtn.addEventListener('click', () => { fetchPendencias(); });
      if (limparBtn) limparBtn.addEventListener('click', () => { if (mesaInput) mesaInput.value = ''; fetchPendencias(); });
      if (mesaInput) mesaInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); fetchPendencias(); } });
      _pendenciasListenersAttached = true;
    }
  }

  async function fetchPendencias() {
    const div = document.getElementById('pendencias_list');
    if (div) div.innerHTML = 'Carregando...';
    try {
      const mesa = (document.getElementById('pendencias_mesa')?.value || '').trim();
      let url = 'api/listar_pendencias.php';
      if (mesa) url += '?mesa=' + encodeURIComponent(mesa);
      const res = await fetch(url);
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const j = await res.json();
      if (!j || !j.success) { if (div) div.innerHTML = '<div class="text-danger">Erro ao carregar pendências</div>'; return; }
      renderPendencias(j.pendencias || [], (typeof j.total_em_aberto !== 'undefined') ? (parseFloat(j.total_em_aberto) || 0.0) : 0.0);
    } catch (e) {
      if (div) div.innerHTML = '<div class="text-danger">Erro: ' + (e && e.message ? e.message : e) + '</div>';
    }
  }

  function renderPendencias(list, totalEmAberto) {
    const div = document.getElementById('pendencias_list');
    if (!div) return;
    // Always show total em aberto even if list is empty
    let html = '<div class="mb-2 small">Total em aberto: <strong>' + formatBRL(totalEmAberto) + '</strong></div>';
    if (!list || list.length === 0) {
      html += '<div class="small">Nenhuma pendência</div>';
      div.innerHTML = html;
      return;
    }
    // Show list with header 'Fiado em' and prefer fiado_at
    html += '<table class="table table-sm">';
    html += '<thead><tr><th>ID</th><th>Mesa</th><th>Total</th><th>Fiado em</th><th>Ações</th></tr></thead><tbody>';
    list.forEach(p => {
      // Preferir fiado_at, mas tratar zero-date '0000-00-00 00:00:00' como ausente
      const fiadoAt = (p.fiado_at && p.fiado_at !== '0000-00-00 00:00:00') ? p.fiado_at : (p.created_at || '');
      html += `<tr><td>${p.id}</td><td>${p.mesa}</td><td>${formatBRL(p.total)}</td><td class="small">${fiadoAt}</td><td>`;
      // Botão para vincular FIADO a uma comanda aberta da mesma mesa
      html += `<button class="btn btn-sm btn-outline-primary me-1" onclick="openVincularFiadoModal({id:${p.id}, mesa:'${p.mesa}', total:${p.total}, fiado_at:'${fiadoAt}'})">Adicionar à comanda…</button>`;
      // Do NOT close the modal after action; refresh pendências and pedidos instead
      html += `<button class="btn btn-sm btn-success me-1" onclick="(async()=>{ if (!confirm('Receber esta pendência e marcar como PAGO?')) return; if (await atualizarStatus(${p.id}, 'PAGO')) { await fetchPendencias(); await loadPedidos(); } })()">Receber</button>`;
      html += `<button class="btn btn-sm btn-danger" onclick="(async()=>{ if (!confirm('Cancelar esta pendência?')) return; if (await atualizarStatus(${p.id}, 'CANCELADO')) { await fetchPendencias(); await loadPedidos(); } })()">Cancelar</button>`;
      html += '</td></tr>';
    });
    html += '</tbody></table>';
    div.innerHTML = html;
  }

  // Vincular Fiado - confirmar (anexa listener aqui para ter acesso a fetchPendencias in-scope)
  const btnVincularConfirm = document.getElementById('vincular_confirmar');
  if (btnVincularConfirm) {
    btnVincularConfirm.addEventListener('click', async () => {
      try {
        const hid = document.getElementById('vincular_fiado_id');
        const select = document.getElementById('vincular_pedido_select');
        if (!hid || !select) return;
        const fiado_id = parseInt(hid.value) || 0;
        const pedido_id = parseInt(select.value) || 0;
        if (!pedido_id) { alert('Selecione uma comanda válida.'); return; }

        const res = await fetch('api/vincular_fiado.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ fiado_id, pedido_id }) });

        let j = null; let rawText = '';
        try { j = await res.json(); } catch (err) { rawText = await res.text().catch(() => ''); }
        if (!res.ok) {
          const msg = (j && j.message) ? j.message : (rawText ? rawText.slice(0,200) : ('Erro HTTP ' + res.status));
          alert(msg); return;
        }
        if (!j || !j.success) {
          const msg = (j && j.message) ? j.message : (rawText ? rawText.slice(0,200) : 'Falha ao vincular');
          alert('Erro: ' + msg); return;
        }

        alert('Vinculado');
        if (_vincularModalInstance) _vincularModalInstance.hide();
        await fetchPendencias();
        await loadPedidos();

      } catch (e) {
        alert('Erro de rede: ' + (e && e.message ? e.message : e));
      }
    });
  }

  // Verifica sessão de caixa e renderiza UI
  (async () => {
    const sess = await fetchCaixaStatus();
    renderCaixaBar(sess);
    if (sess && sess.id) {
      currentSessaoId = parseInt(sess.id);
      loadPedidos();
    }
  })();

  // Auto-refresh seguro a cada 5 segundos; pausável
  _refreshInterval = setInterval(() => {
    if (canAutoRefresh() && !document.hidden) loadPedidos();
  }, 5000);

  // Pausar quando aba estiver oculta
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) pauseAutoRefresh(300000); // pause for 5 minutes when tab hidden
    else _autoRefreshPausedUntil = 0; // resume immediately when visible
  });

  // Pausar enquanto modal de cancelar estiver aberto
  const cancelarModalEl = document.getElementById('cancelarModal');
  if (cancelarModalEl) {
    cancelarModalEl.addEventListener('shown.bs.modal', () => { pauseAutoRefresh(); });
    cancelarModalEl.addEventListener('hidden.bs.modal', () => { _autoRefreshPausedUntil = 0; });
  }

  // Pausar enquanto modal de fechamento estiver aberto (evitar recarregar a lista durante edição)
  const fechamentoModalEl = document.getElementById('modalFechamento');
  if (fechamentoModalEl) {
    fechamentoModalEl.addEventListener('shown.bs.modal', () => { pauseAutoRefresh(); initCardapioRapido(); });
    fechamentoModalEl.addEventListener('hidden.bs.modal', () => { _autoRefreshPausedUntil = 0; });
  }

  // ===== Fechamento de Comanda: comportamentos adicionais =====
  const FECH = { pedidoId: null, mesa: null, pendencias_total: 0, subtotal: 0, total_a_pagar: 0 };
  let FECH_LAST_PEDIDO = null;

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.btn-finalizar');
    if (!btn) return;
    const pedidoId = btn.dataset.id || btn.getAttribute('data-id');
    if (!pedidoId) return;
    await abrirFechamento(pedidoId);
  });

  async function abrirFechamento(pedidoId) {
    FECH.pedidoId = parseInt(pedidoId, 10);
    document.querySelector('#fechErro').style.display = 'none';

    try {
      const res = await fetch(`api/pedido_detalhes.php?id=${FECH.pedidoId}`);
      const data = await res.json();
      if (!res.ok || !data.ok) return mostrarErro(data.error || 'Erro ao carregar comanda');

      FECH.mesa = data.pedido.mesa;
      FECH.pendencias_total = parseFloat(data.pedido.pendencias_total || 0);
      // guardar subtotal atual para uso local durante o fechamento
      FECH.subtotal = parseFloat(data.pedido.total || 0);
      // guardar último pedido carregado para uso nas recalculações
      FECH_LAST_PEDIDO = data.pedido || null;

      document.querySelector('#fechamentoTitulo').textContent = `Fechamento Mesa ${FECH.mesa} (Comanda #${FECH.pedidoId})`;
      document.querySelector('#fechObs').value = data.pedido.observacoes || '';
      const descontoInicial = parseFloat(data.pedido.desconto || 0);
      document.querySelector('#fechDesconto').value = descontoInicial > 0
        ? descontoInicial.toFixed(2).replace('.', ',')
        : '';

      renderItens(data.itens || []);
      recalcularResumo(data);

      new bootstrap.Modal(document.getElementById('modalFechamento')).show();
    } catch (err) {
      console.error(err);
      mostrarErro('Erro ao carregar comanda');
    }
  }

  // helper: escape HTML for insertion into templates
  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  // helper: lê o valor digitado em #fechDesconto (formato com vírgula) e retorna float ou null
  function getDescontoDigitado() {
    try {
      const v = (document.querySelector('#fechDesconto')?.value || '').trim();
      if (!v) return null;
      const s = v.replace(/\./g, '').replace(',', '.');
      const n = parseFloat(s);
      return isNaN(n) ? null : n;
    } catch (e) { return null; }
  }

  function renderItens(itens) {
    const tb = document.querySelector('#fechItens');
    tb.innerHTML = (itens || []).map(it => `
      <tr data-item-id="${it.item_id}">
        <td>${escapeHtml(it.nome)}</td>
        <td>
          <div class="d-flex align-items-center gap-1">
            <button class="btn btn-sm btn-outline-secondary btn-minus">-</button>
            <span class="px-2">${it.quantidade}</span>
            <button class="btn btn-sm btn-outline-secondary btn-plus">+</button>
          </div>
        </td>
        <td>${formatBRL(it.subtotal)}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-danger btn-del">🗑️</button>
        </td>
      </tr>
    `).join('');
  }

  document.querySelector('#fechItens').addEventListener('click', async (e) => {
    const tr = e.target.closest('tr');
    if (!tr) return;
    const itemId = parseInt(tr.getAttribute('data-item-id'), 10);

    if (e.target.closest('.btn-plus'))  return await ajustarItem(itemId, +1);
    if (e.target.closest('.btn-minus')) return await ajustarItem(itemId, -1);
    if (e.target.closest('.btn-del'))   return await removerItem(itemId);
  });

  async function ajustarItem(itemId, delta) {
    try {
      const res = await fetch('api/itens_pedido_ajustar.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ pedido_id: FECH.pedidoId, item_id: itemId, delta })
      });
      const data = await res.json();
      if (!res.ok || !data.ok) return mostrarErro(data.error || 'Erro ao ajustar item');
      // atualizar último pedido e recalcular
      FECH_LAST_PEDIDO = data.pedido || FECH_LAST_PEDIDO;
      renderItens(data.itens);
      recalcularResumo(data);
    } catch (e) { mostrarErro('Erro de rede'); }
  }

  async function removerItem(itemId) {
    try {
      const res = await fetch('api/itens_pedido_remover.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ pedido_id: FECH.pedidoId, item_id: itemId })
      });
      const data = await res.json();
      if (!res.ok || !data.ok) return mostrarErro(data.error || 'Erro ao remover item');
      FECH_LAST_PEDIDO = data.pedido || FECH_LAST_PEDIDO;
      renderItens(data.itens);
      recalcularResumo(data);
    } catch (e) { mostrarErro('Erro de rede'); }
  }

  // Cardápio rápido: carregar produtos UMA vez e filtrar no cliente
  const TOP_FIXOS = [
    "Cerveja Long Neck",
    "Coca-Cola (Lata)",
    "Espeto de Frango",
    "Espeto de Carne (Fraldinha)",
    "Prato Pronto (Arroz, mandioca, farofa, salada, vinagrete + 2 espetos)",
    "Batata Frita",
    "Suco de Laranja (500ml)",
    "H2O Limão (500ml)",
    "Energético Monster (473ml)",
    "Espeto de Queijo Coalho",
    "Espeto de Coração",
    "Medalhão de Frango com Bacon"
  ];

  let ALL_PRODUCTS = [];
  let FILTERED_PRODUCTS = [];
  let categoriaSelecionada = 'Todas';

  async function loadProdutosParaCardapio(){
    try {
      const res = await fetch('api/produtos_listar.php?ativo=1');
      const j = await res.json();
      if (!j || !j.ok) return;
      // produtos vêm em j.produtos ou j.data
      ALL_PRODUCTS = j.produtos || j.data || [];
      renderCategorias();
      renderTop();
      applyFilters();
    } catch(e){ console.error('Erro carregar produtos', e); }
  }

  function formatPrice(v){ return Number(v||0).toLocaleString('pt-BR', { style:'currency', currency:'BRL' }); }

  // helper para normalizar categorias (trim)
  const catNorm = (c) => String(c || '').trim();

  function renderTop(){
    const container = document.getElementById('topGrid');
    const found = [];
    const usedIds = new Set();
    const lower = s => String(s||'').trim().toLowerCase();

    // 1) tentar match exato (trim + lower)
    for (const t of TOP_FIXOS){
      const lt = lower(t);
      const p = ALL_PRODUCTS.find(a => lower(a.nome) === lt);
      if (p){ found.push(p); usedIds.add(p.id); if (found.length>=12) break; }
    }

    // 2) para os não encontrados, tentar includes (produto.nome inclui TOP_FIXO OR TOP_FIXO inclui produto.nome)
    if (found.length < 12){
      for (const t of TOP_FIXOS){
        if (found.length>=12) break;
        const lt = lower(t);
        // já tem correspondência exata
        const already = ALL_PRODUCTS.find(a => lower(a.nome) === lt);
        if (already) continue;
        const candidate = ALL_PRODUCTS.find(a => {
          const pn = lower(a.nome);
          return pn.includes(lt) || lt.includes(pn);
        });
        if (candidate && !usedIds.has(candidate.id)){
          found.push(candidate); usedIds.add(candidate.id);
        }
      }
    }

    // 3) fallback: completar com os mais baratos
    if (found.length < 12){
      const candidates = ALL_PRODUCTS.filter(p=>!usedIds.has(p.id)).slice().sort((a,b)=> Number(a.preco||0) - Number(b.preco||0));
      for (const c of candidates){ found.push(c); usedIds.add(c.id); if (found.length>=12) break; }
    }

    container.innerHTML = found.map(p => {
      const disabled = (Number(p.controla_estoque||0)===1 && Number(p.estoque_atual||0) <= 0);
      return `<button type="button" class="btn btn-sm btn-outline-secondary" ${disabled? 'disabled': ''} onclick="addProdutoToPedido(${p.id})">${escapeHtml(p.nome)}<br><small>${formatPrice(p.preco)}</small></button>`;
    }).join(' ');
  }

  function renderCategorias(){
    const div = document.getElementById('chipsCategorias');
    // normalizar, ignorar vazios e ordenar alfabeticamente
    const cats = Array.from(new Set(ALL_PRODUCTS.map(p=> catNorm(p.categoria)).filter(c=> c !== ''))).sort((a,b)=> a.localeCompare(b, 'pt-BR'));
    const arr = ['Todas', ...cats];
    div.innerHTML = arr.map(c => `<button type="button" class="btn btn-sm ${c===categoriaSelecionada? 'btn-primary':'btn-outline-primary'} chip-cat" data-cat="${escapeHtml(c)}">${escapeHtml(c)}</button>`).join(' ');
  }

  function applyFilters(){
    const q = (document.getElementById('buscaProdutoRapida').value || '').trim().toLowerCase();
    FILTERED_PRODUCTS = ALL_PRODUCTS.filter(p => {
      const pCat = catNorm(p.categoria);
      if (categoriaSelecionada && categoriaSelecionada !== 'Todas'){
        if (pCat !== catNorm(categoriaSelecionada)) return false;
      }
      if (q){
        return (p.nome||'').toLowerCase().includes(q);
      }
      return true;
    });
    renderLista();
  }

  // debounce search + init-once flag
  let buscaTimer = null;
  let CARDAPIO_READY = false;

  function initCardapioRapido(){
    if (CARDAPIO_READY) return;
    CARDAPIO_READY = true;
    // input listeners
    const input = document.getElementById('buscaProdutoRapida');
    if (input){
      input.addEventListener('input', ()=>{
        clearTimeout(buscaTimer);
        buscaTimer = setTimeout(()=> applyFilters(), 200);
      });
      input.addEventListener('keydown', (e)=>{
        if (e.key === 'Enter'){
          e.preventDefault();
          if (FILTERED_PRODUCTS && FILTERED_PRODUCTS.length>0){
            addProdutoToPedido(FILTERED_PRODUCTS[0].id);
            input.value = '';
            applyFilters();
          }
        }
      });
    }

    // delegated listener for category chips
    const chipsContainer = document.getElementById('chipsCategorias');
    if (chipsContainer){
      chipsContainer.addEventListener('click', (e) => {
        const btn = e.target.closest('.chip-cat');
        if (!btn) return;
        categoriaSelecionada = btn.getAttribute('data-cat') || 'Todas';
        renderCategorias();
        applyFilters();
      });
    }

    // load products once if needed
    if (!ALL_PRODUCTS || ALL_PRODUCTS.length === 0) loadProdutosParaCardapio();
  }

  function renderLista(){
    const div = document.getElementById('listaProdutos');
    if (!FILTERED_PRODUCTS || FILTERED_PRODUCTS.length===0){ div.innerHTML = '<div class="small text-muted">Nenhum item</div>'; return; }
    // mobile-first grid: buttons large
    div.innerHTML = FILTERED_PRODUCTS.map(p => {
      const estoque = Number(p.estoque_atual||0);
      const controla = Number(p.controla_estoque||0);
      const disabled = (controla===1 && estoque<=0);
      const badge = p.categoria ? `<div class="small text-muted">${escapeHtml(p.categoria)}</div>` : '';
      return `
        <div class="col-6 col-sm-4 col-md-3">
          <button type="button" class="btn btn-light w-100 h-100 text-start" ${disabled? 'disabled': ''} onclick="addProdutoToPedido(${p.id})">
            <div class="fw-semibold">${escapeHtml(p.nome)}</div>
            <div class="small text-muted">${formatPrice(p.preco)}</div>
            ${badge}
            ${disabled? '<div class="badge bg-secondary mt-1">Sem estoque</div>' : ''}
          </button>
        </div>
      `;
    }).join('');
  }

  // add product to current pedido (uses same API as existing fechSugestoes)
  window.addProdutoToPedido = async function(produtoId){
    if (!produtoId) return;
    if (!FECH || !FECH.pedidoId) return alert('Abra a comanda primeiro');
    try {
      const res = await fetch('api/itens_pedido_adicionar.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ pedido_id: FECH.pedidoId, produto_id: produtoId })
      });
      const data = await res.json();
      if (!res.ok || !data.ok) return mostrarErro(data.error || 'Erro ao adicionar item');
      FECH_LAST_PEDIDO = data.pedido || FECH_LAST_PEDIDO;
      renderItens(data.itens);
      recalcularResumo(data);
    } catch (e) { mostrarErro('Erro de rede'); }
  };

  document.querySelector('#btnImprimirPrevia')?.addEventListener('click', () => {
    window.open(`printer/cupom.php?pedido_id=${FECH.pedidoId}&previa=1`, '_blank');
  });

  document.querySelector('#btnConfirmarPagamento')?.addEventListener('click', async () => {
    const metodo = document.querySelector('#fechPagamento').value;
    if (!metodo) return mostrarErro('Selecione a forma de pagamento.');

    try {
      const res = await fetch('api/finalizar_comanda.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ pedido_id: FECH.pedidoId, metodo_pagamento: metodo })
      });
      const data = await res.json();
      if (!res.ok || !data.ok) return mostrarErro(data.error || 'Erro ao finalizar');

      bootstrap.Modal.getInstance(document.getElementById('modalFechamento')).hide();
      await loadPedidos();
      await fetchPendencias();
    } catch (e) { mostrarErro('Erro de rede'); }
  });

  function recalcularResumo(data) {
    const pedido = (data && data.pedido) ? data.pedido : (FECH_LAST_PEDIDO || {});
    const subtotal = parseFloat(pedido.total || 0);
    const pend = parseFloat(pedido.pendencias_total || FECH.pendencias_total || 0);
    const desconto = (getDescontoDigitado() !== null) ? getDescontoDigitado() : parseFloat(pedido.desconto || 0);
    const total = (subtotal + pend) - desconto;

    document.querySelector('#fechSubtotal').textContent = formatBRL(subtotal);
    // calcular e mostrar percentual do desconto sobre a base (subtotal + pendencias)
    const base = subtotal + pend;
    let pctTxt = '';
    if (base > 0 && desconto > 0) {
      const pct = (desconto / base) * 100;
      pctTxt = ` (${pct.toFixed(1).replace('.', ',')}%)`;
    }
    document.querySelector('#fechDescTxt').textContent = formatBRL(desconto) + pctTxt;
    document.querySelector('#fechTotal').textContent = formatBRL(total);

    if (pend > 0) {
      document.querySelector('#linhaPend').style.display = '';
      document.querySelector('#fechPendencias').textContent = formatBRL(pend);
    } else {
      document.querySelector('#linhaPend').style.display = 'none';
    }
  }

  function mostrarErro(msg){
    const el = document.querySelector('#fechErro');
    el.textContent = msg;
    el.style.display = '';
  }

}); // fecha o DOMContentLoaded
</script>

<!-- Modal Fechamento de Comanda -->
<div class="modal fade" id="modalFechamento" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="fechamentoTitulo">Fechamento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <!-- Cardápio rápido: busca, categorias, TOP e lista de produtos -->
        <div class="mb-2">
          <div class="mb-2">
            <input id="buscaProdutoRapida" class="form-control" placeholder="Buscar item..." autocomplete="off">
          </div>
          <div id="chipsCategorias" class="mb-2 d-flex gap-2 flex-wrap"></div>

          <div id="topProdutos" class="mb-2">
            <h6 class="mb-2">🔥 Mais pedidos</h6>
            <div id="topGrid" class="d-flex flex-wrap gap-2"></div>
          </div>

          <div id="listaProdutos" class="row g-2"></div>
        </div>

        <!-- Itens -->
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>Item</th>
                <th style="width:120px;">Qtd</th>
                <th style="width:140px;">Subtotal</th>
                <th style="width:60px;"></th>
              </tr>
            </thead>
            <tbody id="fechItens"></tbody>
          </table>
        </div>

        <!-- Observações + desconto -->
        <div class="row g-2">
          <div class="col-md-8">
            <label class="form-label">Observações</label>
            <textarea id="fechObs" class="form-control" rows="2" placeholder="Ex: Desconto de cortesia..."></textarea>
          </div>
          <div class="col-md-4">
            <label class="form-label">Desconto (R$)</label>
            <input id="fechDesconto" class="form-control" inputmode="decimal" placeholder="0,00">
          </div>
        </div>

        <!-- Resumo -->
        <div class="mt-3 p-2 border rounded">
          <div class="d-flex justify-content-between"><span>Subtotal</span><strong id="fechSubtotal">R$ 0,00</strong></div>
          <div class="d-flex justify-content-between" id="linhaPend" style="display:none;">
            <span>Pendências (fiado)</span><strong id="fechPendencias">R$ 0,00</strong>
          </div>
          <div class="d-flex justify-content-between"><span>Desconto</span><strong id="fechDescTxt">R$ 0,00</strong></div>
          <hr class="my-2">
          <div class="d-flex justify-content-between"><span>TOTAL A PAGAR</span><strong id="fechTotal">R$ 0,00</strong></div>
        </div>

        <!-- Ações -->
        <div class="mt-3 d-flex gap-2 flex-wrap">
          <button class="btn btn-outline-secondary" id="btnImprimirPrevia">Imprimir Conferência</button>

          <select class="form-select" id="fechPagamento" style="max-width:260px;">
            <option value="">Selecionar método…</option>
            <option value="DINHEIRO">Dinheiro</option>
            <option value="PIX">PIX</option>
            <option value="CREDITO">Cartão Crédito</option>
            <option value="DEBITO">Cartão Débito</option>
          </select>

          <button class="btn btn-primary ms-auto" id="btnConfirmarPagamento">
            Concluir e baixar comanda
          </button>
        </div>

        <div class="text-danger small mt-2" id="fechErro" style="display:none;"></div>
      </div>
    </div>
  </div>
</div>

</body>
</html>
