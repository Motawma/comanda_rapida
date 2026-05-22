<?php
// licenca_vencida.php — Página exibida quando a licença da empresa venceu
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/conexao.php';

requireLogin();

// Master não deve ver essa página
if (isMaster()) {
    header('Location: master.php');
    exit;
}

$empresaId = currentEmpresaId();
$empresa   = [];
try {
    $pdo  = getPDO();

    // Garante colunas (execução silenciosa)
    try { $pdo->exec("ALTER TABLE empresas ADD COLUMN IF NOT EXISTS trust_unlock_at DATETIME DEFAULT NULL"); } catch(Throwable $e){}
    try { $pdo->exec("ALTER TABLE empresas ADD COLUMN IF NOT EXISTS trust_unlock_expires_at DATETIME DEFAULT NULL"); } catch(Throwable $e){}

    $stmt = $pdo->prepare("SELECT nome, data_expiracao, plano, trust_unlock_at, trust_unlock_expires_at FROM empresas WHERE id = ? LIMIT 1");
    $stmt->execute([$empresaId]);
    $empresa = $stmt->fetch() ?: [];
} catch (Throwable $e) {}

// Se trust unlock ainda ativo → redireciona para o sistema
if (!empty($empresa['trust_unlock_expires_at']) && $empresa['trust_unlock_expires_at'] > date('Y-m-d H:i:s')) {
    header('Location: caixa.php');
    exit;
}

// Se ainda não expirou, redireciona (caso o usuário acesse a URL diretamente)
if (!empty($empresa['data_expiracao']) && $empresa['data_expiracao'] >= date('Y-m-d')) {
    header('Location: caixa.php');
    exit;
}

// Verifica elegibilidade para desbloqueio de confiança
$podeTrustUnlock = false;
$trustMsg        = '';
$agora = date('Y-m-d H:i:s');
if (!empty($empresa['trust_unlock_at'])) {
    $diasDesde = (int)((time() - strtotime($empresa['trust_unlock_at'])) / 86400);
    if ($diasDesde < 30) {
        $podeTrustUnlock = false;
        $trustMsg = 'Disponível novamente em ' . (30 - $diasDesde) . ' dia(s).';
    } else {
        $podeTrustUnlock = true;
    }
} else {
    $podeTrustUnlock = true;
}

$nomeEmpresa    = htmlspecialchars($empresa['nome'] ?? 'Sua empresa');
$dataVencimento = !empty($empresa['data_expiracao'])
    ? date('d/m/Y', strtotime($empresa['data_expiracao']))
    : '-';
$plano = htmlspecialchars($empresa['plano'] ?? '-');
$username = htmlspecialchars(currentUser()['username'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/svg+xml" href="favicon.svg"><link rel="shortcut icon" href="favicon.svg">
  <title>Licença Vencida — V12 Comandas</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root {
      --gold: #c9a84c;
      --gold-light: #e0c068;
      --dark-bg: #1a1a2e;
      --dark-card: #16213e;
      --dark-border: #2a2a4a;
    }
    body {
      background: var(--dark-bg);
      color: #e0e0e0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Segoe UI', sans-serif;
    }
    .card-licenca {
      background: var(--dark-card);
      border: 1px solid var(--dark-border);
      border-radius: 16px;
      padding: 2.5rem;
      max-width: 480px;
      width: 100%;
      text-align: center;
      box-shadow: 0 8px 32px rgba(0,0,0,0.5);
    }
    .icon-lock {
      font-size: 4rem;
      margin-bottom: 1rem;
    }
    .titulo {
      color: #ff6b6b;
      font-size: 1.6rem;
      font-weight: 700;
      margin-bottom: .5rem;
    }
    .subtitulo {
      color: #aaa;
      font-size: .95rem;
      margin-bottom: 1.5rem;
    }
    .info-box {
      background: rgba(255,107,107,0.1);
      border: 1px solid rgba(255,107,107,0.3);
      border-radius: 8px;
      padding: 1rem;
      margin-bottom: 1.5rem;
      text-align: left;
    }
    .info-box .label {
      color: #aaa;
      font-size: .8rem;
      text-transform: uppercase;
      letter-spacing: .05em;
    }
    .info-box .valor {
      color: #fff;
      font-weight: 600;
    }
    .btn-renovar {
      background: var(--gold);
      border: none;
      color: #1a1a2e;
      font-weight: 700;
      font-size: 1rem;
      padding: .75rem 2rem;
      border-radius: 8px;
      width: 100%;
      margin-bottom: .75rem;
      text-decoration: none;
      display: block;
      transition: background .2s;
    }
    .btn-renovar:hover {
      background: var(--gold-light);
      color: #1a1a2e;
    }
    .btn-sair {
      background: transparent;
      border: 1px solid #555;
      color: #aaa;
      font-size: .9rem;
      padding: .5rem 1.5rem;
      border-radius: 8px;
      width: 100%;
      text-decoration: none;
      display: block;
      transition: border-color .2s, color .2s;
    }
    .btn-sair:hover {
      border-color: #888;
      color: #ccc;
    }
    .logo-text {
      color: var(--gold);
      font-size: .85rem;
      font-weight: 600;
      letter-spacing: .1em;
      text-transform: uppercase;
      margin-bottom: 1.5rem;
    }
  </style>
</head>
<body>
  <div class="card-licenca">
    <div class="logo-text">V12 Comandas</div>
    <div class="icon-lock">🔒</div>
    <div class="titulo">Licença Vencida</div>
    <div class="subtitulo">
      O acesso ao sistema foi suspenso.<br>
      Renove sua licença para continuar usando.
    </div>

    <div class="info-box">
      <div class="row g-2">
        <div class="col-12">
          <div class="label">Empresa</div>
          <div class="valor"><?= $nomeEmpresa ?></div>
        </div>
        <div class="col-6 mt-2">
          <div class="label">Venceu em</div>
          <div class="valor" style="color:#ff6b6b"><?= $dataVencimento ?></div>
        </div>
        <div class="col-6 mt-2">
          <div class="label">Plano</div>
          <div class="valor"><?= $plano ?></div>
        </div>
      </div>
    </div>

    <a href="assinar.php" class="btn-renovar">
      💳 Assinar Online
    </a>

    <a href="https://wa.me/5519974030227?text=Quero+renovar+minha+licença+V12+Comandas+-+<?= urlencode($nomeEmpresa) ?>"
       target="_blank" class="btn-sair">
      📱 Renovar pelo WhatsApp
    </a>

    <!-- Desbloqueio de Confiança -->
    <?php if ($podeTrustUnlock): ?>
    <button id="btnTrustUnlock" onclick="solicitarDesbloqueio()" class="btn-sair" style="margin-top:.5rem;border-color:rgba(201,168,76,.5);color:#c9a84c;">
      🔓 Desbloqueio de Confiança (48h)
    </button>
    <div style="font-size:.75rem;color:#888;margin-top:.35rem;">
      Use apenas se for pagar em breve. Liberação imediata por 48 horas.
    </div>
    <?php elseif ($trustMsg): ?>
    <div style="font-size:.78rem;color:#888;margin-top:.75rem;border:1px solid #333;border-radius:8px;padding:.5rem .75rem;">
      🔒 Desbloqueio de confiança: <?= htmlspecialchars($trustMsg) ?>
    </div>
    <?php endif; ?>

    <a href="admin_logout.php" class="btn-sair" style="margin-top:.5rem">Sair do sistema</a>
  </div>
</body>
<script>
async function solicitarDesbloqueio() {
  const btn = document.getElementById('btnTrustUnlock');
  btn.disabled = true;
  btn.textContent = '⏳ Solicitando...';
  try {
    const r = await fetch('api/trust_unlock.php', { method: 'POST' });
    const d = await r.json();
    if (d.ok) {
      btn.textContent = '✅ Acesso liberado!';
      btn.style.borderColor = '#20c997';
      btn.style.color = '#20c997';
      setTimeout(() => { location.href = 'caixa.php'; }, 1200);
    } else {
      btn.textContent = '🔓 Desbloqueio de Confiança (48h)';
      btn.disabled = false;
      alert(d.message || 'Erro ao solicitar desbloqueio.');
    }
  } catch(e) {
    btn.textContent = '🔓 Desbloqueio de Confiança (48h)';
    btn.disabled = false;
    alert('Erro de comunicação.');
  }
}
</script>
</html>
