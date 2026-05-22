<?php
// master.php - Painel Master para gerenciar empresas
require_once __DIR__ . '/auth.php';
if (!isLoggedIn() || !isMaster()) {
    header('Location: ./index.php'); exit;
}
require_once __DIR__ . '/conexao.php';
$pdo      = getPDO();
$empresas = $pdo->query("SELECT * FROM empresas ORDER BY id DESC")->fetchAll();
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Painel Master</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f4f6f9; }
    .card { border: none; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
    .card-header { background: #fff; font-weight: 700; border-bottom: 2px solid #e9ecef; }
    th { font-size: .82rem; white-space: nowrap; }
    td { font-size: .85rem; vertical-align: middle; }
  </style>
</head>
<body>
<div class="container-lg py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">⚙️ Painel Master</h4>
    <a href="backup_manager.php" class="btn btn-outline-info btn-sm me-2">🗄️ Backups</a>
    <a href="admin_logout.php" class="btn btn-outline-secondary btn-sm">Sair</a>
  </div>

  <!-- Formulário nova empresa -->
  <div class="card mb-4">
    <div class="card-header">➕ Cadastrar novo cliente</div>
    <div class="card-body">
      <div id="msg"></div>
      <div class="row g-2">
        <div class="col-md-4">
          <label class="form-label small fw-bold">Nome da Empresa <span class="text-danger">*</span></label>
          <input id="nome" class="form-control form-control-sm" placeholder="Ex: Bar do João">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-bold">Email <span class="text-danger">*</span></label>
          <input id="email" class="form-control form-control-sm" type="email" placeholder="contato@empresa.com">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-bold">CNPJ / CPF</label>
          <input id="cnpj" class="form-control form-control-sm" placeholder="00.000.000/0001-00">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-bold">Telefone</label>
          <input id="telefone" class="form-control form-control-sm" placeholder="(19) 99999-9999">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-bold">Responsável</label>
          <input id="responsavel" class="form-control form-control-sm" placeholder="Nome do dono/responsável">
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-bold">Plano</label>
          <select id="plano" class="form-select form-select-sm">
            <option value="basico">Básico</option>
            <option value="pro">Pro</option>
            <option value="enterprise">Enterprise</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-bold">Usuário Admin <span class="text-danger">*</span></label>
          <input id="admin_user" class="form-control form-control-sm" placeholder="login">
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-bold">Senha Admin <span class="text-danger">*</span></label>
          <input id="admin_pass" class="form-control form-control-sm" type="password" placeholder="mínimo 6 caracteres">
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button class="btn btn-primary btn-sm w-100" onclick="cadastrar()">✅ Cadastrar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Lista de empresas -->
  <div class="card">
    <div class="card-header">🏢 Empresas cadastradas (<?= count($empresas) ?>)</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Nome</th>
              <th>Email</th>
              <th>CNPJ/CPF</th>
              <th>Telefone</th>
              <th>Responsável</th>
              <th>Plano</th>
              <th>Status</th>
              <th>Criado em</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($empresas as $e): ?>
            <tr>
              <td><?= $e['id'] ?></td>
              <td class="fw-semibold"><?= htmlspecialchars($e['nome']) ?></td>
              <td><?= htmlspecialchars($e['email']) ?></td>
              <td class="text-muted"><?= htmlspecialchars($e['cnpj'] ?? '—') ?></td>
              <td class="text-muted"><?= htmlspecialchars($e['telefone'] ?? '—') ?></td>
              <td class="text-muted"><?= htmlspecialchars($e['responsavel'] ?? '—') ?></td>
              <td><span class="badge bg-secondary"><?= $e['plano'] ?? 'basico' ?></span></td>
              <td>
                <span class="badge <?= $e['ativo'] ? 'bg-success' : 'bg-danger' ?>">
                  <?= $e['ativo'] ? 'Ativo' : 'Inativo' ?>
                </span>
              </td>
              <td class="text-muted" style="font-size:.78rem"><?= substr($e['created_at'] ?? '', 0, 16) ?></td>
              <td>
                <button class="btn btn-outline-primary btn-sm me-1"
                  onclick="abrirEditar(<?= htmlspecialchars(json_encode($e), ENT_QUOTES) ?>)">
                  ✏️ Editar
                </button>
                <button class="btn btn-outline-<?= $e['ativo'] ? 'danger' : 'success' ?> btn-sm"
                  onclick="toggleAtivo(<?= $e['id'] ?>, <?= $e['ativo'] ?>)">
                  <?= $e['ativo'] ? 'Desativar' : 'Ativar' ?>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal Editar Empresa -->
<div class="modal fade" id="modalEditar" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">✏️ Editar Empresa</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="msgEditar"></div>
        <input type="hidden" id="edit_id">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label small fw-bold">Nome da Empresa <span class="text-danger">*</span></label>
            <input id="edit_nome" class="form-control form-control-sm">
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-bold">Email</label>
            <input id="edit_email" class="form-control form-control-sm" type="email">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-bold">CNPJ / CPF</label>
            <input id="edit_cnpj" class="form-control form-control-sm" placeholder="00.000.000/0001-00">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-bold">Telefone</label>
            <input id="edit_telefone" class="form-control form-control-sm" placeholder="(19) 99999-9999">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-bold">Responsável</label>
            <input id="edit_responsavel" class="form-control form-control-sm">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-bold">Plano</label>
            <select id="edit_plano" class="form-select form-select-sm">
              <option value="basico">Básico</option>
              <option value="pro">Pro</option>
              <option value="enterprise">Enterprise</option>
            </select>
          </div>
          <div class="col-12"><hr class="my-1"><p class="small text-muted mb-1">Deixe em branco para não alterar a senha do admin.</p></div>
          <div class="col-md-6">
            <label class="form-label small fw-bold">Nova Senha Admin</label>
            <input id="edit_senha" class="form-control form-control-sm" type="password" placeholder="mínimo 6 caracteres">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary btn-sm" onclick="salvarEdicao()">💾 Salvar</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
async function cadastrar() {
  const nome       = document.getElementById('nome').value.trim();
  const email      = document.getElementById('email').value.trim();
  const cnpj       = document.getElementById('cnpj').value.trim();
  const telefone   = document.getElementById('telefone').value.trim();
  const responsavel= document.getElementById('responsavel').value.trim();
  const plano      = document.getElementById('plano').value;
  const user       = document.getElementById('admin_user').value.trim();
  const pass       = document.getElementById('admin_pass').value.trim();
  const msg        = document.getElementById('msg');

  if (!nome || !email || !user || !pass) {
    msg.innerHTML = '<div class="alert alert-warning py-1 small">Preencha os campos obrigatórios (*).</div>';
    return;
  }
  const res  = await fetch('api/master_cadastrar_empresa.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ nome, email, cnpj, telefone, responsavel, plano, admin_user: user, admin_pass: pass })
  });
  const data = await res.json();
  if (data.ok) {
    msg.innerHTML = '<div class="alert alert-success py-1 small">✅ Empresa cadastrada! Recarregando...</div>';
    setTimeout(() => location.reload(), 1500);
  } else {
    msg.innerHTML = `<div class="alert alert-danger py-1 small">❌ ${data.message}</div>`;
  }
}

function abrirEditar(e) {
  document.getElementById('edit_id').value          = e.id;
  document.getElementById('edit_nome').value        = e.nome || '';
  document.getElementById('edit_email').value       = e.email || '';
  document.getElementById('edit_cnpj').value        = e.cnpj || '';
  document.getElementById('edit_telefone').value    = e.telefone || '';
  document.getElementById('edit_responsavel').value = e.responsavel || '';
  document.getElementById('edit_plano').value       = e.plano || 'basico';
  document.getElementById('edit_senha').value       = '';
  document.getElementById('msgEditar').innerHTML    = '';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditar')).show();
}

async function salvarEdicao() {
  const id   = document.getElementById('edit_id').value;
  const nome = document.getElementById('edit_nome').value.trim();
  if (!nome) {
    document.getElementById('msgEditar').innerHTML = '<div class="alert alert-warning py-1 small">Nome é obrigatório.</div>';
    return;
  }
  const body = {
    id:          parseInt(id),
    nome,
    email:       document.getElementById('edit_email').value.trim(),
    cnpj:        document.getElementById('edit_cnpj').value.trim(),
    telefone:    document.getElementById('edit_telefone').value.trim(),
    responsavel: document.getElementById('edit_responsavel').value.trim(),
    plano:       document.getElementById('edit_plano').value,
    nova_senha:  document.getElementById('edit_senha').value.trim(),
  };
  const res  = await fetch('api/master_editar_empresa.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(body)
  });
  const data = await res.json();
  if (data.ok) {
    document.getElementById('msgEditar').innerHTML = '<div class="alert alert-success py-1 small">✅ Salvo!</div>';
    setTimeout(() => location.reload(), 1200);
  } else {
    document.getElementById('msgEditar').innerHTML = `<div class="alert alert-danger py-1 small">❌ ${data.message}</div>`;
  }
}

async function toggleAtivo(id, ativo) {
  if (!confirm(`${ativo ? 'Desativar' : 'Ativar'} esta empresa?`)) return;
  const res  = await fetch('api/master_toggle_empresa.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ empresa_id: id, ativo: ativo ? 0 : 1 })
  });
  const data = await res.json();
  if (data.ok) location.reload();
  else alert(data.message || 'Erro');
}
</script>
</body>
</html>
