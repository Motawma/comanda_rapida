<?php
// admin.php - área administrativa
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/licenca.php';
requireAdminPage(); // exige login admin
require_once __DIR__ . '/funcoes.php';
require_once __DIR__ . '/printer_config.php';
$_printerCfg = getPrinterConfig();
?>
$user = currentUser();
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin - Comanda Rápida</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="theme.css?v=<?= filemtime(__DIR__.'/theme.css') ?>">
  <script src="theme.js?v=<?= filemtime(__DIR__.'/theme.js') ?>"></script>
  <!-- depois vem o Bootstrap normalmente -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.x/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .topbar { display:flex; gap:1rem; align-items:center; justify-content:space-between; }
    .password-wrap { position: relative; }
    .password-wrap .form-control { padding-right: 2.8rem; }
    .btn-eye {
      position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
      background: none; border: none; padding: 0; cursor: pointer;
      color: #6c757d; font-size: 1.15rem; line-height: 1; z-index: 2;
    }
    .btn-eye:hover { color: #212529; }

    /* ===== MODAL TEMPOS KDS ===== */
    #modalTempos .modal-content {
      border: none;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 12px 40px rgba(0,0,0,.25);
    }
    #modalTempos .modal-header {
      background: linear-gradient(135deg, #1a1a2e, #16213e);
      color: #fff;
      border-bottom: none;
      padding: .85rem 1.25rem;
    }
    #modalTempos .modal-header .btn-close {
      filter: brightness(0) invert(1);
    }
    #modalTempos .modal-title {
      font-weight: 700;
      font-size: 1.1rem;
    }
    #modalTempos .modal-body {
      padding: 1.25rem;
    }
    #modalTempos .modal-footer {
      border-top: 1px solid #e9ecef;
      padding: .75rem 1.25rem;
    }

    .tempo-card {
      background: #f8f9fa;
      border: 2px solid #e9ecef;
      border-radius: 14px;
      padding: 1rem 1.25rem;
      margin-bottom: 1rem;
      transition: border-color .2s, box-shadow .2s;
    }
    .tempo-card:hover {
      border-color: #dee2e6;
      box-shadow: 0 2px 8px rgba(0,0,0,.06);
    }
    .tempo-card .tempo-header {
      display: flex;
      align-items: center;
      gap: .6rem;
      margin-bottom: .6rem;
    }
    .tempo-card .tempo-icon {
      font-size: 1.6rem;
      line-height: 1;
    }
    .tempo-card .tempo-label {
      font-weight: 700;
      font-size: .95rem;
      color: #212529;
    }
    .tempo-card .tempo-desc {
      font-size: .78rem;
      color: #6c757d;
      margin-bottom: .5rem;
    }
    .tempo-card .tempo-slider-wrap {
      display: flex;
      align-items: center;
      gap: .75rem;
    }
    .tempo-card .tempo-slider-wrap input[type="range"] {
      flex: 1;
      height: 8px;
      -webkit-appearance: none;
      appearance: none;
      background: #dee2e6;
      border-radius: 4px;
      outline: none;
    }
    .tempo-card .tempo-slider-wrap input[type="range"]::-webkit-slider-thumb {
      -webkit-appearance: none;
      appearance: none;
      width: 22px;
      height: 22px;
      border-radius: 50%;
      cursor: pointer;
      border: 3px solid #fff;
      box-shadow: 0 1px 4px rgba(0,0,0,.2);
    }
    .tempo-card.warn input[type="range"]::-webkit-slider-thumb {
      background: #f39c12;
    }
    .tempo-card.warn input[type="range"]::-moz-range-thumb {
      background: #f39c12;
      border: 3px solid #fff;
      box-shadow: 0 1px 4px rgba(0,0,0,.2);
      width: 22px; height: 22px; border-radius: 50%; cursor: pointer;
    }
    .tempo-card.crit input[type="range"]::-webkit-slider-thumb {
      background: #e74c3c;
    }
    .tempo-card.crit input[type="range"]::-moz-range-thumb {
      background: #e74c3c;
      border: 3px solid #fff;
      box-shadow: 0 1px 4px rgba(0,0,0,.2);
      width: 22px; height: 22px; border-radius: 50%; cursor: pointer;
    }
    .tempo-card.refresh input[type="range"]::-webkit-slider-thumb {
      background: #3498db;
    }
    .tempo-card.refresh input[type="range"]::-moz-range-thumb {
      background: #3498db;
      border: 3px solid #fff;
      box-shadow: 0 1px 4px rgba(0,0,0,.2);
      width: 22px; height: 22px; border-radius: 50%; cursor: pointer;
    }
    .tempo-card .tempo-valor {
      min-width: 56px;
      text-align: center;
      font-weight: 800;
      font-size: 1.15rem;
      border-radius: 10px;
      padding: .3rem .5rem;
      color: #fff;
    }
    .tempo-card.warn .tempo-valor  { background: #f39c12; }
    .tempo-card.crit .tempo-valor  { background: #e74c3c; }
    .tempo-card.refresh .tempo-valor { background: #3498db; }

    .tempo-preview {
      background: #1a1a2e;
      border-radius: 12px;
      padding: .75rem 1rem;
      color: #e0e0e0;
      font-size: .82rem;
      margin-top: .5rem;
    }
    .tempo-preview .preview-bar {
      display: flex;
      align-items: center;
      height: 28px;
      border-radius: 8px;
      overflow: hidden;
      margin-top: .5rem;
    }
    .tempo-preview .bar-ok {
      background: #27ae60;
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .7rem;
      font-weight: 700;
      height: 100%;
      transition: width .3s;
    }
    .tempo-preview .bar-warn {
      background: #f39c12;
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .7rem;
      font-weight: 700;
      height: 100%;
      transition: width .3s;
    }
    .tempo-preview .bar-crit {
      background: #e74c3c;
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .7rem;
      font-weight: 700;
      height: 100%;
      flex: 1;
    }

    .btn-tempos {
      background: linear-gradient(135deg, #1a1a2e, #0f3460);
      border: none;
      color: #fff;
      font-weight: 700;
      border-radius: 12px;
      padding: .5rem 1rem;
      font-size: .9rem;
      transition: all .2s;
    }
    .btn-tempos:hover {
      background: linear-gradient(135deg, #0f3460, #1a1a2e);
      color: #fff;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(15,52,96,.3);
    }

    #temposSaveMsg .alert {
      border-radius: 10px;
      font-size: .85rem;
      padding: .5rem .75rem;
    }
  </style>
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/partials/admin_nav.php'; ?>
<script>window._printerConfig = <?= json_encode($_printerCfg, JSON_UNESCAPED_UNICODE) ?>;</script>
<?php
require_once __DIR__ . '/auth.php';
if (isLoggedIn() && (currentUser()['role'] ?? '') === 'admin') {
  echo '<div style="height:56px"></div>'; // altura padrão da navbar bootstrap
}
?>

<div class="container py-3">
  <!-- topbar provided by partials/admin_nav.php -->

  <h5>Gerenciar Usuários</h5>

  <div class="card mb-3">
    <div class="card-body">
      <form id="createForm" onsubmit="return false;" class="row g-2">
        <div class="col-12 col-md-4">
          <input id="u_username" class="form-control" placeholder="Usuário (3-50)" />
        </div>
        <div class="col-12 col-md-4 password-wrap">
          <input id="u_password" type="password" class="form-control" placeholder="Senha (>=6)" />
          <button type="button" class="btn-eye" onclick="document.getElementById('u_password').type = document.getElementById('u_password').type === 'password' ? 'text' : 'password'">👁️</button>
        </div>
        <div class="col-8 col-md-3">
          <select id="u_role" class="form-select">
            <option value="garcom">garcom</option>
            <option value="cozinha">cozinha</option>
            <option value="admin">admin</option>
          </select>
        </div>
        <div class="col-4 col-md-1">
          <button id="btnCreate" class="btn btn-primary w-100">Criar</button>
        </div>
      </form>
      <div id="createMsg" class="mt-2"></div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h6>Usuários</h6>
      <div class="table-responsive">
        <table class="table table-sm" id="usersTable">
          <thead><tr><th>ID</th><th>Usuário</th><th>Role</th><th>Ativo</th><th>Criado em</th><th>Ações</th></tr></thead>
          <tbody id="usersTbody"><tr><td colspan="6">Carregando...</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Seção Configurações -->
  <hr class="my-4">
  <h5>⚙️ Configurações do Sistema</h5>

  <div class="card mb-3">
    <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>
        <h6 class="mb-1">⏱️ Tempos do KDS (Cozinha)</h6>
        <p class="text-muted mb-0" style="font-size:.85rem;">Configure os tempos de alerta amarelo, vermelho e intervalo de atualização do painel da cozinha.</p>
      </div>
      <button type="button" class="btn btn-tempos" id="btnAbrirTempos">
        ⏱️ Configurar Tempos
      </button>
    </div>
  </div>

  <!-- Card Impressora -->
  <div class="card mb-3">
    <div class="card-body">
      <h6 class="mb-3">🖨️ Configuração da Impressora</h6>
      <div id="msgImpressora"></div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label small fw-bold">Nome do Estabelecimento</label>
          <input type="text" id="imp_nome" class="form-control form-control-sm" maxlength="100" placeholder="Ex: Bar do João">
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-bold">Largura do Papel</label>
          <select id="imp_largura" class="form-select form-select-sm">
            <option value="58mm">58mm (térmica pequena)</option>
            <option value="72mm">72mm (térmica média)</option>
            <option value="80mm" selected>80mm (térmica 80mm)</option>
            <option value="A4">A4 (impressora comum)</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-bold">Auto-imprimir ao abrir</label>
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" id="imp_auto">
            <label class="form-check-label small" for="imp_auto">Abrir diálogo de impressão automaticamente</label>
          </div>
        </div>
        <div class="col-12">
          <label class="form-label small fw-bold">Mensagem de Rodapé</label>
          <input type="text" id="imp_rodape" class="form-control form-control-sm" maxlength="150" placeholder="Ex: Obrigado pela preferência! Volte sempre!">
        </div>
      </div>
      <div class="mt-3 d-flex gap-2">
        <button type="button" class="btn btn-sm btn-primary" onclick="salvarImpressora()">💾 Salvar</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="testarCupom()">🖨️ Testar Cupom</button>
      </div>
    </div>
  </div>

  <!-- Modal Editar Usuário -->
  <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Editar Usuário</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="edit_user_id" />
          <div class="mb-3">
            <label class="form-label">Role</label>
            <select id="edit_user_role" class="form-select">
              <option value="garcom">garcom</option>
              <option value="cozinha">cozinha</option>
              <option value="admin">admin</option>
            </select>
          </div>
          <div class="mb-3 form-check">
            <input id="edit_user_active" type="checkbox" class="form-check-input" />
            <label class="form-check-label" for="edit_user_active">Ativo</label>
          </div>
          <div class="mb-3">
            <label class="form-label">Nova senha (opcional)</label>
            <div class="password-wrap">
              <input id="edit_user_new_password" type="password" class="form-control" placeholder="Deixe vazio para manter" />
              <button type="button" class="btn-eye" onclick="document.getElementById('edit_user_new_password').type = document.getElementById('edit_user_new_password').type === 'password' ? 'text' : 'password'">👁️</button>
            </div>
          </div>
          <div id="editUserMsg"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
          <button id="btnSaveUser" type="button" class="btn btn-primary">Salvar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Configuração de Tempos do KDS -->
  <div class="modal fade" id="modalTempos" tabindex="-1" aria-labelledby="modalTemposLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title" id="modalTemposLabel">⏱️ Tempos do KDS — Painel Cozinha</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">

          <!-- Alerta Amarelo -->
          <div class="tempo-card warn">
            <div class="tempo-header">
              <span class="tempo-icon">🟡</span>
              <span class="tempo-label">Alerta Amarelo</span>
            </div>
            <div class="tempo-desc">Pedido muda para amarelo após esse tempo. Indica que está demorando.</div>
            <div class="tempo-slider-wrap">
              <input type="range" id="sliderWarn" min="1" max="60" value="8" step="1">
              <span class="tempo-valor" id="valorWarn">8 min</span>
            </div>
          </div>

          <!-- Alerta Vermelho -->
          <div class="tempo-card crit">
            <div class="tempo-header">
              <span class="tempo-icon">🔴</span>
              <span class="tempo-label">Alerta Vermelho (Crítico)</span>
            </div>
            <div class="tempo-desc">Pedido começa a piscar em vermelho. Indica urgência máxima.</div>
            <div class="tempo-slider-wrap">
              <input type="range" id="sliderCrit" min="1" max="120" value="15" step="1">
              <span class="tempo-valor" id="valorCrit">15 min</span>
            </div>
          </div>

          <!-- Refresh KDS -->
          <div class="tempo-card refresh">
            <div class="tempo-header">
              <span class="tempo-icon">🔄</span>
              <span class="tempo-label">Atualização Automática</span>
            </div>
            <div class="tempo-desc">Intervalo de atualização dos pedidos no painel da cozinha.</div>
            <div class="tempo-slider-wrap">
              <input type="range" id="sliderRefresh" min="2" max="30" value="5" step="1">
              <span class="tempo-valor" id="valorRefresh">5 seg</span>
            </div>
          </div>

          <!-- Preview visual da timeline -->
          <div class="tempo-preview">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span>📊 Preview da timeline do pedido:</span>
            </div>
            <div class="preview-bar" id="previewBar">
              <div class="bar-ok" id="barOk" style="width:40%;">✅ Normal</div>
              <div class="bar-warn" id="barWarn" style="width:25%;">⚠️ Atenção</div>
              <div class="bar-crit" id="barCrit">🚨 Crítico</div>
            </div>
            <div class="d-flex justify-content-between mt-1" style="font-size:.7rem;color:#aaa;">
              <span>0 min</span>
              <span id="previewWarnLabel">8 min</span>
              <span id="previewCritLabel">15 min</span>
              <span>+</span>
            </div>
          </div>

          <div id="temposSaveMsg" class="mt-3"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" style="border-radius:20px;padding:.4rem 1.5rem;font-weight:600;">Cancelar</button>
          <button type="button" class="btn btn-success btn-sm" id="btnSalvarTempos" style="border-radius:20px;padding:.4rem 1.5rem;font-weight:600;">
            💾 Salvar Configurações
          </button>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
const CURRENT_USER_ID = <?= (int)$user['id'] ?>;
async function loadUsers() {
  const tbody = document.getElementById('usersTbody');
  tbody.innerHTML = '<tr><td colspan="6">Carregando...</td></tr>';
  try {
    const res = await fetch('api/admin_list_users.php');
    const j = await res.json();
    if (!j.success) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-danger">Erro ao carregar</td></tr>';
      return;
    }
    const rows = j.users || [];
    if (rows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6">Nenhum usuário</td></tr>';
      return;
    }
    tbody.innerHTML = '';
    rows.forEach(r => {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${r.id}</td><td>${escapeHtml(r.username)}</td><td>${r.role}</td><td>${r.active ? 'Sim' : 'Não'}</td><td>${r.created_at}</td><td></td>`;
      const tdActions = tr.querySelector('td:last-child');
      tdActions.classList.add('text-nowrap');

      const btnEdit = document.createElement('button');
      btnEdit.type = 'button';
      btnEdit.className = 'btn btn-sm btn-outline-primary me-1';
      btnEdit.textContent = 'Editar';
      btnEdit.addEventListener('click', () => openEditModal(r));

      const btnDelete = document.createElement('button');
      btnDelete.type = 'button';
      btnDelete.className = 'btn btn-sm btn-outline-danger';
      btnDelete.textContent = 'Excluir';
      if (Number(r.id) === Number(CURRENT_USER_ID)) btnDelete.disabled = true;
      btnDelete.addEventListener('click', async () => {
        if (!confirm('Tem certeza que deseja excluir este usuário?')) return;
        try {
          const res2 = await fetch('api/admin_delete_user.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ id: r.id })
          });
          const j2 = await res2.json();
          if (!j2.success) {
            alert('Erro: ' + (j2.message || 'Falha')); return;
          }
          alert('Usuário excluído');
          loadUsers();
        } catch (err) { alert('Erro de rede: ' + err.message); }
      });

      tdActions.appendChild(btnEdit);
      tdActions.appendChild(btnDelete);
      tbody.appendChild(tr);
    });
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="6" class="text-danger">Erro de rede</td></tr>';
  }
}

// escapeHtml segura conforme solicitado
function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, (m) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;"
  }[m]));
}

document.getElementById('btnCreate').addEventListener('click', async () => {
  const u = document.getElementById('u_username').value.trim();
  const p = document.getElementById('u_password').value;
  const role = document.getElementById('u_role').value;
  const msg = document.getElementById('createMsg');
  msg.innerHTML = '';
  if (u.length < 3 || u.length > 50) { msg.innerHTML = '<div class="alert alert-danger">Usuário inválido</div>'; return; }
  if (p.length < 6) { msg.innerHTML = '<div class="alert alert-danger">Senha inválida</div>'; return; }
  try {
    const res = await fetch('api/admin_create_user.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ username: u, password: p, role })
    });
    const j = await res.json();
    if (!j.success) {
      msg.innerHTML = '<div class="alert alert-danger">' + (j.message || 'Erro') + '</div>';
      return;
    }
    msg.innerHTML = '<div class="alert alert-success">Usuário criado</div>';
    document.getElementById('u_username').value = '';
    document.getElementById('u_password').value = '';
    loadUsers();
  } catch (e) {
    msg.innerHTML = '<div class="alert alert-danger">Erro de rede</div>';
  }
});

// Edit modal handlers
let _editModalInstance = null;
function openEditModal(user) {
  const modalEl = document.getElementById('editUserModal');
  if (!modalEl) return;
  if (!_editModalInstance) _editModalInstance = new bootstrap.Modal(modalEl);
  document.getElementById('edit_user_id').value = user.id;
  document.getElementById('edit_user_role').value = user.role;
  document.getElementById('edit_user_active').checked = user.active ? true : false;
  document.getElementById('edit_user_new_password').value = '';
  document.getElementById('editUserMsg').innerHTML = '';
  _editModalInstance.show();
}

document.getElementById('btnSaveUser').addEventListener('click', async () => {
  const id = parseInt(document.getElementById('edit_user_id').value) || 0;
  const role = document.getElementById('edit_user_role').value;
  const active = document.getElementById('edit_user_active').checked ? 1 : 0;
  const new_password = document.getElementById('edit_user_new_password').value || '';
  const msg = document.getElementById('editUserMsg');
  msg.innerHTML = '';
  try {
    const res = await fetch('api/admin_update_user.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ id, role, active, new_password })
    });
    const j = await res.json();
    if (!j.success) { msg.innerHTML = '<div class="alert alert-danger">' + (j.message || 'Erro') + '</div>'; return; }
    if (_editModalInstance) _editModalInstance.hide();
    loadUsers();
  } catch (err) { msg.innerHTML = '<div class="alert alert-danger">Erro de rede</div>'; }
});

loadUsers();

// ===== MODAL CONFIGURAÇÃO DE TEMPOS DO KDS =====
(() => {
  const sliderWarn    = document.getElementById('sliderWarn');
  const sliderCrit    = document.getElementById('sliderCrit');
  const sliderRefresh = document.getElementById('sliderRefresh');
  const valorWarn     = document.getElementById('valorWarn');
  const valorCrit     = document.getElementById('valorCrit');
  const valorRefresh  = document.getElementById('valorRefresh');
  const barOk         = document.getElementById('barOk');
  const barWarn       = document.getElementById('barWarn');
  const previewWarnLabel = document.getElementById('previewWarnLabel');
  const previewCritLabel = document.getElementById('previewCritLabel');
  const msgEl         = document.getElementById('temposSaveMsg');

  let _temposModal = null;

  function updatePreview() {
    const w = parseInt(sliderWarn.value) || 8;
    const c = parseInt(sliderCrit.value) || 15;

    // Garante que crit >= warn + 1
    if (c <= w) {
      sliderCrit.value = w + 1;
    }
    const critVal = parseInt(sliderCrit.value);

    // Atualiza labels
    valorWarn.textContent    = w + ' min';
    valorCrit.textContent    = critVal + ' min';
    valorRefresh.textContent = sliderRefresh.value + ' seg';

    // Atualiza preview bar
    const total = critVal + Math.max(5, Math.round(critVal * 0.3)); // espaço extra depois do crit
    const okPct   = Math.round((w / total) * 100);
    const warnPct = Math.round(((critVal - w) / total) * 100);
    barOk.style.width   = okPct + '%';
    barWarn.style.width  = warnPct + '%';
    barOk.textContent    = okPct > 12 ? '✅ Normal' : '✅';
    barWarn.textContent  = warnPct > 12 ? '⚠️ Atenção' : '⚠️';

    // Atualiza labels do eixo
    previewWarnLabel.textContent = w + ' min';
    previewCritLabel.textContent = critVal + ' min';
  }

  // Eventos dos sliders
  sliderWarn.addEventListener('input', updatePreview);
  sliderCrit.addEventListener('input', updatePreview);
  sliderRefresh.addEventListener('input', updatePreview);

  // ── Impressora ──────────────────────────────────────────────────────
  async function carregarConfigImpressora() {
    try {
      const res  = await fetch('api/salvar_config_impressora.php', { method: 'GET' });
      // GET não existe, então carregamos via uma chamada diferente — usa defaults visuais
      // Config é carregada via página ao montar
    } catch(e) {}
  }

  // Pré-popula campos com valores padrão ou salvos (via dataset na página)
  (function() {
    const cfg = window._printerConfig || {};
    if (cfg.nome_restaurante) document.getElementById('imp_nome').value     = cfg.nome_restaurante;
    if (cfg.largura_papel)    document.getElementById('imp_largura').value   = cfg.largura_papel;
    if (cfg.rodape)           document.getElementById('imp_rodape').value    = cfg.rodape;
    if (cfg.auto_print)       document.getElementById('imp_auto').checked    = true;
  })();

  // Carregar configurações atuais do banco
  async function carregarTempos() {
    try {
      const res = await fetch('api/config_tempos.php');
      const data = await res.json();
      if (data.success && data.config) {
        sliderWarn.value    = data.config.kds_warn_minutes || 8;
        sliderCrit.value    = data.config.kds_crit_minutes || 15;
        sliderRefresh.value = data.config.kds_refresh_seconds || 5;
        updatePreview();
      }
    } catch (e) {
      console.error('Erro ao carregar tempos:', e);
    }
  }

  // Abrir modal
  document.getElementById('btnAbrirTempos').addEventListener('click', async () => {
    const modalEl = document.getElementById('modalTempos');
    if (!_temposModal) _temposModal = new bootstrap.Modal(modalEl);
    msgEl.innerHTML = '';

    await carregarTempos();
    _temposModal.show();
  });

  // Salvar configurações
  document.getElementById('btnSalvarTempos').addEventListener('click', async () => {
    const btn = document.getElementById('btnSalvarTempos');
    const textoOriginal = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳ Salvando...';
    msgEl.innerHTML = '';

    const warnVal = parseInt(sliderWarn.value) || 8;
    let critVal = parseInt(sliderCrit.value) || 15;
    if (critVal <= warnVal) critVal = warnVal + 1;

    try {
      const res = await fetch('api/config_tempos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          kds_warn_minutes: warnVal,
          kds_crit_minutes: critVal,
          kds_refresh_seconds: parseInt(sliderRefresh.value) || 5
        })
      });
      const data = await res.json();

      if (data.success) {
        msgEl.innerHTML = '<div class="alert alert-success">✅ Configurações salvas! O painel da cozinha usará os novos tempos automaticamente.</div>';
        setTimeout(() => {
          if (_temposModal) _temposModal.hide();
          msgEl.innerHTML = '';
        }, 2000);
      } else {
        msgEl.innerHTML = '<div class="alert alert-danger">❌ ' + (data.message || 'Erro ao salvar') + '</div>';
      }
    } catch (e) {
      msgEl.innerHTML = '<div class="alert alert-danger">❌ Erro de conexão</div>';
    } finally {
      btn.disabled = false;
      btn.innerHTML = textoOriginal;
    }
  });

  // Inicializa preview
  updatePreview();
})();

async function salvarImpressora() {
  const nome    = document.getElementById('imp_nome').value.trim();
  const largura = document.getElementById('imp_largura').value;
  const rodape  = document.getElementById('imp_rodape').value.trim();
  const auto    = document.getElementById('imp_auto').checked;
  const msg     = document.getElementById('msgImpressora');

  try {
    const res  = await fetch('api/salvar_config_impressora.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ nome_restaurante: nome, largura_papel: largura, rodape, auto_print: auto })
    });
    const data = await res.json();
    if (data.success) {
      msg.innerHTML = '<div class="alert alert-success py-1 small">✅ Configuração salva! Recarregue a página para aplicar.</div>';
      window._printerConfig = data.config;
    } else {
      msg.innerHTML = `<div class="alert alert-danger py-1 small">❌ ${data.message}</div>`;
    }
  } catch(e) {
    msg.innerHTML = `<div class="alert alert-danger py-1 small">Erro: ${e.message}</div>`;
  }
}

function testarCupom() {
  window.open('printer/cupom_teste.php', '_blank', 'width=400,height=600');
}
</script>

<!-- Bootstrap JS (ativar navbar-toggler do partial admin_nav) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>