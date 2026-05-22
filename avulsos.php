<?php
// avulsos.php — Sangria e Reforço de Caixa
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/conexao.php';
requireAdminOrRedirect();
if (!empresaTemRecurso(currentEmpresaId(), 'caixa')) {
    http_response_code(403);
    echo '<!doctype html><html><head><meta charset="utf-8">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    </head><body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">
    <div class="text-center"><h3>🔒 Módulo não disponível</h3>
    <p class="text-muted">O módulo Caixa não está habilitado no seu plano.</p>
    <a href="comanda.php" class="btn btn-outline-secondary mt-2">Voltar</a>
    </div></body></html>';
    exit;
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" type="image/svg+xml" href="favicon.svg"><link rel="shortcut icon" href="favicon.svg">
  <title>Avulsos - Comanda Rápida</title>
  <link rel="stylesheet" href="theme.css?v=<?= filemtime(__DIR__.'/theme.css') ?>">
  <script src="theme.js?v=<?= filemtime(__DIR__.'/theme.js') ?>"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { padding-top: 70px; }
    .card-acao { cursor: pointer; transition: transform .15s, box-shadow .15s; }
    .card-acao:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,.15); }
    .valor-grande { font-size: 2rem; font-weight: 700; }
    .badge-tipo-SANGRIA  { background: #dc3545; }
    .badge-tipo-REFORCO  { background: #198754; }
  </style>
</head>
<body>

<?php require_once __DIR__ . '/partials/admin_nav.php'; ?>

<div class="container py-4">

  <div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0 fw-bold">💲 Avulsos de Caixa</h4>
    <div id="statusCaixa" class="badge bg-secondary fs-6">verificando...</div>
  </div>

  <!-- Alerta caixa fechado -->
  <div id="alertaCaixaFechado" class="alert alert-warning d-none" role="alert">
    ⚠️ Nenhum caixa aberto. <a href="caixa.php" class="alert-link">Abra o caixa</a> antes de registrar movimentos.
  </div>

  <!-- Ações -->
  <div class="row g-3 mb-4" id="secaoAcoes">
    <div class="col-12 col-sm-6">
      <div class="card border-danger card-acao h-100" onclick="abrirModal('sangria')">
        <div class="card-body text-center py-4">
          <div style="font-size:2.5rem">📤</div>
          <h5 class="card-title text-danger fw-bold mt-2">Sangria</h5>
          <p class="card-text text-muted small">Retirada de dinheiro do caixa</p>
        </div>
      </div>
    </div>
    <div class="col-12 col-sm-6">
      <div class="card border-success card-acao h-100" onclick="abrirModal('reforco')">
        <div class="card-body text-center py-4">
          <div style="font-size:2.5rem">📥</div>
          <h5 class="card-title text-success fw-bold mt-2">Reforço</h5>
          <p class="card-text text-muted small">Adição de dinheiro ao caixa</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Histórico -->
  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
      <span class="fw-bold">Movimentos da sessão atual</span>
      <button class="btn btn-sm btn-outline-secondary" onclick="carregarMovimentos()">↻ Atualizar</button>
    </div>
    <div class="card-body p-0">
      <div id="loadingMovimentos" class="text-center py-4 text-muted">Carregando...</div>
      <table class="table table-hover mb-0 d-none" id="tabelaMovimentos">
        <thead class="table-light">
          <tr>
            <th>Tipo</th>
            <th>Valor</th>
            <th>Observação</th>
            <th>Horário</th>
          </tr>
        </thead>
        <tbody id="corpoMovimentos"></tbody>
        <tfoot id="rodapeMovimentos"></tfoot>
      </table>
      <div id="semMovimentos" class="text-center py-4 text-muted d-none">Nenhum movimento nesta sessão.</div>
    </div>
  </div>

</div>

<!-- Modal Sangria -->
<div class="modal fade" id="modalSangria" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header border-danger">
        <h5 class="modal-title text-danger fw-bold">📤 Sangria</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-bold">Valor (R$)</label>
          <input type="number" id="sangriaValor" class="form-control form-control-lg"
                 min="0.01" step="0.01" placeholder="0,00" autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">Motivo <span class="text-danger">*</span></label>
          <input type="text" id="sangriaMotivo" class="form-control"
                 placeholder="Ex: Pagamento fornecedor" maxlength="200">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-danger fw-bold" onclick="confirmarSangria()">Confirmar Sangria</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Reforço -->
<div class="modal fade" id="modalReforco" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header border-success">
        <h5 class="modal-title text-success fw-bold">📥 Reforço</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-bold">Valor (R$)</label>
          <input type="number" id="reforcoValor" class="form-control form-control-lg"
                 min="0.01" step="0.01" placeholder="0,00" autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">Observação</label>
          <input type="text" id="reforcoObs" class="form-control"
                 placeholder="Ex: Troco inicial" maxlength="200">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-success fw-bold" onclick="confirmarReforco()">Confirmar Reforço</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
let sessaoId = null;
const modais = {
  sangria: new bootstrap.Modal(document.getElementById('modalSangria')),
  reforco: new bootstrap.Modal(document.getElementById('modalReforco')),
};

async function verificarCaixa() {
  try {
    const r = await fetch('api/caixa_status.php');
    const j = await r.json();
    const badge = document.getElementById('statusCaixa');
    if (j.sessao) {
      sessaoId = j.sessao.id;
      badge.className = 'badge bg-success fs-6';
      badge.textContent = '🟢 Caixa Aberto';
      document.getElementById('alertaCaixaFechado').classList.add('d-none');
      document.getElementById('secaoAcoes').classList.remove('d-none');
      carregarMovimentos();
    } else {
      sessaoId = null;
      badge.className = 'badge bg-danger fs-6';
      badge.textContent = '🔴 Caixa Fechado';
      document.getElementById('alertaCaixaFechado').classList.remove('d-none');
      document.getElementById('secaoAcoes').classList.add('d-none');
      document.getElementById('loadingMovimentos').textContent = 'Caixa fechado.';
    }
  } catch (e) {
    document.getElementById('statusCaixa').textContent = 'Erro';
  }
}

async function carregarMovimentos() {
  if (!sessaoId) return;
  document.getElementById('loadingMovimentos').classList.remove('d-none');
  document.getElementById('tabelaMovimentos').classList.add('d-none');
  document.getElementById('semMovimentos').classList.add('d-none');

  try {
    const r = await fetch('api/sangria_listar.php?sessao_id=' + sessaoId);
    const j = await r.json();
    const movimentos = j.movimentos || [];

    document.getElementById('loadingMovimentos').classList.add('d-none');

    if (movimentos.length === 0) {
      document.getElementById('semMovimentos').classList.remove('d-none');
      return;
    }

    const corpo = document.getElementById('corpoMovimentos');
    corpo.innerHTML = '';
    let totalSangria = 0, totalReforco = 0;

    movimentos.forEach(m => {
      const isSangria = m.tipo === 'SANGRIA';
      if (isSangria) totalSangria += m.valor; else totalReforco += m.valor;
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><span class="badge badge-tipo-${m.tipo}">${isSangria ? '📤 Sangria' : '📥 Reforço'}</span></td>
        <td class="${isSangria ? 'text-danger' : 'text-success'} fw-bold">
          ${isSangria ? '-' : '+'}R$ ${fmtValor(m.valor)}
        </td>
        <td class="text-muted small">${m.obs || '-'}</td>
        <td class="text-muted small">${fmtData(m.criado_em)}</td>
      `;
      corpo.appendChild(tr);
    });

    const rodape = document.getElementById('rodapeMovimentos');
    rodape.innerHTML = `
      <tr class="table-secondary fw-bold">
        <td>Total</td>
        <td>
          <span class="text-danger">-R$ ${fmtValor(totalSangria)}</span>
          <span class="mx-2 text-muted">|</span>
          <span class="text-success">+R$ ${fmtValor(totalReforco)}</span>
        </td>
        <td colspan="2"></td>
      </tr>
    `;

    document.getElementById('tabelaMovimentos').classList.remove('d-none');
  } catch (e) {
    document.getElementById('loadingMovimentos').textContent = 'Erro ao carregar.';
  }
}

function abrirModal(tipo) {
  if (!sessaoId) { alert('Abra o caixa primeiro.'); return; }
  if (tipo === 'sangria') {
    document.getElementById('sangriaValor').value = '';
    document.getElementById('sangriaMotivo').value = '';
    modais.sangria.show();
    setTimeout(() => document.getElementById('sangriaValor').focus(), 300);
  } else {
    document.getElementById('reforcoValor').value = '';
    document.getElementById('reforcoObs').value = '';
    modais.reforco.show();
    setTimeout(() => document.getElementById('reforcoValor').focus(), 300);
  }
}

async function confirmarSangria() {
  const valor  = parseFloat(document.getElementById('sangriaValor').value);
  const motivo = document.getElementById('sangriaMotivo').value.trim();
  if (!valor || valor <= 0) { alert('Informe um valor válido.'); return; }
  if (!motivo) { alert('Motivo é obrigatório para Sangria.'); return; }

  try {
    const r = await fetch('api/sangria.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ valor, motivo }),
    });
    const j = await r.json();
    if (j.success) {
      modais.sangria.hide();
      carregarMovimentos();
    } else {
      alert(j.message || 'Erro ao registrar sangria.');
    }
  } catch (e) {
    alert('Erro de comunicação.');
  }
}

async function confirmarReforco() {
  const valor = parseFloat(document.getElementById('reforcoValor').value);
  const obs   = document.getElementById('reforcoObs').value.trim();
  if (!valor || valor <= 0) { alert('Informe um valor válido.'); return; }

  try {
    const r = await fetch('api/caixa_avulso.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ tipo: 'REFORCO', valor, obs }),
    });
    const j = await r.json();
    if (j.success) {
      modais.reforco.hide();
      carregarMovimentos();
    } else {
      alert(j.message || 'Erro ao registrar reforço.');
    }
  } catch (e) {
    alert('Erro de comunicação.');
  }
}

function fmtValor(v) {
  return parseFloat(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtData(d) {
  if (!d) return '-';
  try {
    return new Date(d).toLocaleString('pt-BR');
  } catch { return d; }
}

verificarCaixa();
</script>
</body>
</html>
