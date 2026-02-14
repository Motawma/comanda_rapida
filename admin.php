<?php
// admin.php - área administrativa
require_once __DIR__ . '/auth.php';
requireAdminPage(); // exige login admin
$user = currentUser();
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin - Comanda Rápida</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .topbar { display:flex; gap:1rem; align-items:center; justify-content:space-between; }
  </style>
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/partials/admin_nav.php'; ?>
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
        <div class="col-12 col-md-4">
          <input id="u_password" type="password" class="form-control" placeholder="Senha (>=6)" />
        </div>
        <div class="col-8 col-md-3">
          <select id="u_role" class="form-select">
            <option value="staff">staff</option>
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
              <option value="staff">staff</option>
              <option value="admin">admin</option>
            </select>
          </div>
          <div class="mb-3 form-check">
            <input id="edit_user_active" type="checkbox" class="form-check-input" />
            <label class="form-check-label" for="edit_user_active">Ativo</label>
          </div>
          <div class="mb-3">
            <label class="form-label">Nova senha (opcional)</label>
            <input id="edit_user_new_password" type="password" class="form-control" placeholder="Deixe vazio para manter" />
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
</script>

<!-- Bootstrap JS (ativar navbar-toggler do partial admin_nav) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>