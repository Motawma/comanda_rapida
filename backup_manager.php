<?php
// backup_manager.php — Gerenciador de backups (acessível pelo master)
require_once __DIR__ . '/auth.php';
if (!isLoggedIn() || !isMaster()) {
    header('Location: ./index.php'); exit;
}

define('BACKUP_DIR', __DIR__ . '/backups');

// Ação: baixar backup
if (isset($_GET['download'])) {
    $file = basename($_GET['download']);
    $path = BACKUP_DIR . '/' . $file;
    if (file_exists($path) && preg_match('/^backup_.+\.(sql|sql\.gz)$/', $file)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
    die('Arquivo não encontrado.');
}

// Ação: deletar backup
if (isset($_POST['delete'])) {
    $file = basename($_POST['delete']);
    $path = BACKUP_DIR . '/' . $file;
    if (file_exists($path)) unlink($path);
    header('Location: backup_manager.php'); exit;
}

// Listar backups
$arquivos = [];
if (is_dir(BACKUP_DIR)) {
    $lista = glob(BACKUP_DIR . '/backup_*.{sql,sql.gz}', GLOB_BRACE) ?: [];
    usort($lista, fn($a, $b) => filemtime($b) - filemtime($a));
    foreach ($lista as $f) {
        $arquivos[] = [
            'nome'     => basename($f),
            'tamanho'  => round(filesize($f) / 1024, 1),
            'data'     => date('d/m/Y H:i:s', filemtime($f)),
        ];
    }
}

// Log
$log = '';
$logFile = BACKUP_DIR . '/backup.log';
if (file_exists($logFile)) {
    $linhas = array_slice(array_filter(explode("\n", file_get_contents($logFile))), -20);
    $log = implode("\n", array_reverse($linhas));
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Gerenciador de Backups</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f4f6f9; }
    .card { border:none; box-shadow:0 1px 4px rgba(0,0,0,.08); }
    .card-header { background:#fff; font-weight:700; border-bottom:2px solid #e9ecef; }
    .badge-sql  { background:#0d6efd; }
    .badge-gz   { background:#198754; }
    pre { background:#1a1a2e; color:#a8ff78; padding:12px; border-radius:8px; font-size:.78rem; max-height:200px; overflow-y:auto; }
  </style>
</head>
<body>
<div class="container-lg py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">🗄️ Backups do Banco de Dados</h4>
    <div class="d-flex gap-2">
      <button class="btn btn-success btn-sm" id="btnBackupAgora">⚡ Fazer Backup Agora</button>
      <a href="master.php" class="btn btn-outline-secondary btn-sm">← Voltar</a>
    </div>
  </div>

  <div id="msgBackup" class="mb-3"></div>

  <!-- Instruções do Cron -->
  <div class="card mb-4">
    <div class="card-header">⏰ Agendamento Automático (Cron)</div>
    <div class="card-body">
      <p class="small text-muted mb-2">Adicione uma linha no cron do servidor para backup automático. Acesse o painel de hospedagem → Cron Jobs e adicione:</p>
      <div class="row g-2">
        <div class="col-md-6">
          <label class="form-label small fw-bold">📅 Diário (todo dia às 03:00)</label>
          <code class="d-block bg-dark text-success p-2 rounded small">0 3 * * * php <?= __DIR__ ?>/backup_run.php</code>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-bold">📅 A cada 12 horas</label>
          <code class="d-block bg-dark text-success p-2 rounded small">0 */12 * * * php <?= __DIR__ ?>/backup_run.php</code>
        </div>
      </div>
    </div>
  </div>

  <!-- Lista de backups -->
  <div class="card mb-4">
    <div class="card-header">📦 Backups Disponíveis (<?= count($arquivos) ?>)</div>
    <div class="card-body p-0">
      <?php if (empty($arquivos)): ?>
        <div class="p-4 text-center text-muted">Nenhum backup encontrado. Clique em "Fazer Backup Agora".</div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light">
            <tr><th>Arquivo</th><th>Tamanho</th><th>Data</th><th>Ações</th></tr>
          </thead>
          <tbody>
            <?php foreach ($arquivos as $a): ?>
            <tr>
              <td>
                <span class="badge <?= str_ends_with($a['nome'], '.gz') ? 'badge-gz' : 'badge-sql' ?> me-1">
                  <?= str_ends_with($a['nome'], '.gz') ? 'GZ' : 'SQL' ?>
                </span>
                <span class="small"><?= htmlspecialchars($a['nome']) ?></span>
              </td>
              <td class="text-muted small"><?= $a['tamanho'] ?> KB</td>
              <td class="text-muted small"><?= $a['data'] ?></td>
              <td>
                <a href="backup_manager.php?download=<?= urlencode($a['nome']) ?>"
                   class="btn btn-outline-primary btn-sm me-1">⬇️ Baixar</a>
                <form method="post" class="d-inline"
                      onsubmit="return confirm('Apagar este backup?')">
                  <input type="hidden" name="delete" value="<?= htmlspecialchars($a['nome']) ?>">
                  <button class="btn btn-outline-danger btn-sm">🗑️</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Log -->
  <?php if ($log): ?>
  <div class="card">
    <div class="card-header">📋 Log dos Últimos Backups</div>
    <div class="card-body p-2">
      <pre><?= htmlspecialchars($log) ?></pre>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
document.getElementById('btnBackupAgora').addEventListener('click', async () => {
  const btn = document.getElementById('btnBackupAgora');
  const msg = document.getElementById('msgBackup');
  btn.disabled = true;
  btn.textContent = '⏳ Fazendo backup...';
  msg.innerHTML = '';
  try {
    const res = await fetch('backup_run.php');
    const j   = await res.json();
    if (j.ok) {
      msg.innerHTML = `<div class="alert alert-success py-2">✅ Backup criado: <strong>${j.arquivo}</strong> (${j.tamanho_kb} KB)</div>`;
      setTimeout(() => location.reload(), 1500);
    } else {
      msg.innerHTML = `<div class="alert alert-danger py-2">❌ ${j.message}</div>`;
    }
  } catch(e) {
    msg.innerHTML = '<div class="alert alert-danger py-2">❌ Erro ao executar backup</div>';
  } finally {
    btn.disabled = false;
    btn.textContent = '⚡ Fazer Backup Agora';
  }
});
</script>
</body>
</html>
