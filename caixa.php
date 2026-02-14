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
        <div id="pendencias_list">Carregando...</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap JS (modal) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
let currentSessaoId = 0;
let _abrirModalInstance = null;
let _fecharModalInstance = null;

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
    const j = await res.json();
    if (!j.success) {
      alert('Erro: ' + (j.message || 'Falha ao atualizar status'));
      return;
    }
    await loadPedidos();
  } catch (e) {
    alert('Erro de rede: ' + e.message);
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
    const j = await res.json();
    if (!j.success) { alert('Erro: ' + (j.message || 'Falha ao cancelar')); return; }
    if (_cancelarModalInstance) _cancelarModalInstance.hide();
    await loadPedidos();
  } catch (e) {
    alert('Erro de rede: ' + e.message);
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
      b.className = 'badge bg-' + (statusClass[k] || 'secondary') + ' me-1';
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
    if (mesaSearch) {
      pedidos = pedidos.filter(p => String(p.mesa ?? '').toLowerCase().includes(mesaSearch));
    }

    pedidos.forEach(p => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${p.id}</td>
        <td>${p.mesa}</td>
        <td><span class="badge bg-${statusClass[p.status] || 'secondary'} status-badge">${p.status}</span></td>
        <td>${formatBRL(p.total)}</td>
        <td class="small col-criadoem">${p.created_at}</td>
        <td></td>
      `;

      const tdAcoes = tr.querySelector('td:last-child');
      tdAcoes.classList.add('acoes-cell');

      // Pausar auto-refresh quando o usuário tocar na área de ações (mobile)
      tdAcoes.addEventListener('touchstart', () => { pauseAutoRefresh = true; }, { passive: true });
      tdAcoes.addEventListener('touchend', () => { setTimeout(() => pauseAutoRefresh = false, 1200); }, { passive: true });

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
      const btnPrep = makeBtn('btn btn-sm btn-warning me-1','Em preparo', () => atualizarStatus(p.id, 'EM_PREPARO'));
      const btnPronto = makeBtn('btn btn-sm btn-info me-1','Pronto', () => atualizarStatus(p.id, 'PRONTO'));
      const btnPago = makeBtn('btn btn-sm btn-success me-1','Marcar Pago', () => atualizarStatus(p.id, 'PAGO'));
      const btnFiado = makeBtn('btn btn-sm btn-secondary me-1','Marcar Fiado', () => atualizarStatus(p.id, 'FIADO'));
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
    if (!el) return;
    if (!_pendenciasModalInstance) _pendenciasModalInstance = new bootstrap.Modal(el);
    _pendenciasModalInstance.show();
    await fetchPendencias();
  }

  async function fetchPendencias() {
    const div = document.getElementById('pendencias_list');
    if (!div) return;
    div.innerHTML = 'Carregando...';
    try {
      const res = await fetch('api/listar_pendencias.php');
      const j = await res.json();
      if (!j.success) { div.innerHTML = '<div class="text-danger">Erro ao carregar pendências</div>'; return; }
      // Pass total_em_aberto to renderer so UI shows total + list
      renderPendencias(j.pendencias || [], (typeof j.total_em_aberto !== 'undefined') ? (parseFloat(j.total_em_aberto) || 0.0) : 0.0);
    } catch (e) {
      div.innerHTML = '<div class="text-danger">Erro de rede</div>';
    }
  }

  function renderPendencias(list, totalEmAberto) {
    const div = document.getElementById('pendencias_list');
    if (!div) return;
    if (!list || list.length === 0) { div.innerHTML = '<div class="small">Nenhuma pendência</div>'; return; }
    // Show total em aberto acima da lista
    let html = '<div class="mb-2 small">Total em aberto: <strong>' + formatBRL(totalEmAberto) + '</strong></div>';
    html += '<table class="table table-sm">';
    html += '<thead><tr><th>ID</th><th>Mesa</th><th>Total</th><th>Criado em</th><th>Ações</th></tr></thead><tbody>';
    list.forEach(p => {
      html += `<tr><td>${p.id}</td><td>${p.mesa}</td><td>${formatBRL(p.total)}</td><td class="small">${p.created_at ?? p.fiado_at ?? ''}</td><td>`;
      html += `<button class="btn btn-sm btn-success me-1" onclick="(async()=>{ if (!confirm('Receber esta pendência e marcar como PAGO?')) return; await atualizarStatus(${p.id}, 'PAGO'); _pendenciasModalInstance.hide(); await loadPedidos(); })()">Receber</button>`;
      html += `<button class="btn btn-sm btn-danger" onclick="(async()=>{ if (!confirm('Cancelar esta pendência?')) return; await atualizarStatus(${p.id}, 'CANCELADO'); _pendenciasModalInstance.hide(); await loadPedidos(); })()">Cancelar</button>`;
      html += '</td></tr>';
    });
    html += '</tbody></table>';
    div.innerHTML = html;
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
  _refreshInterval = setInterval(() => { if (!pauseAutoRefresh && !document.hidden) loadPedidos(); }, 5000);

  // Pausar quando aba estiver oculta
  document.addEventListener('visibilitychange', () => {
    pauseAutoRefresh = document.hidden;
  });

  // Pausar enquanto modal de cancelar estiver aberto
  const cancelarModalEl = document.getElementById('cancelarModal');
  if (cancelarModalEl) {
    cancelarModalEl.addEventListener('shown.bs.modal', () => { pauseAutoRefresh = true; });
    cancelarModalEl.addEventListener('hidden.bs.modal', () => { pauseAutoRefresh = false; });
  }
});

// Limpa intervalo ao sair da página
window.addEventListener('beforeunload', () => clearInterval(_refreshInterval));
</script>
</body>
</html>
