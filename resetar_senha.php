<?php
require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
    header('Location: caixa.php'); exit;
}

$token = trim($_GET['token'] ?? '');
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" type="image/svg+xml" href="favicon.svg"><link rel="shortcut icon" href="favicon.svg">
  <title>Nova Senha — V12 Comandas</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --gold:        #c9a84c;
      --gold-light:  #e8c96a;
      --dark-card:   rgba(10, 10, 10, 0.82);
      --input-bg:    rgba(255,255,255,0.06);
      --input-bdr:   rgba(255,255,255,0.12);
      --text-main:   #f0ece4;
      --text-muted:  rgba(240,236,228,0.45);
    }

    html, body { height: 100%; margin: 0; font-family: 'Inter', sans-serif; }

    body {
      display: flex;
      align-items: center;
      justify-content: center;
      background: url('img/v12comandas.png') no-repeat center center fixed;
      background-size: cover;
      position: relative;
    }

    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.55);
      z-index: 0;
    }

    @media (min-width: 768px) {
      body {
        justify-content: flex-start;
        padding-left: 6vw;
      }
      body::before {
        background: linear-gradient(
          to right,
          rgba(0,0,0,0.90) 0%,
          rgba(0,0,0,0.60) 45%,
          rgba(0,0,0,0.08) 100%
        );
      }
    }

    @media (max-width: 767px) {
      body {
        align-items: center;
        padding-top: 0;
        background: #000 !important;
      }
      body::before { display: none; }
      .login-card {
        background:
          url('img/v12comandas.png') no-repeat center center / cover,
          rgba(10,10,10,0.82);
        background-blend-mode: luminosity;
      }
      .login-card::after {
        content: '';
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,0.62);
        border-radius: 16px;
        z-index: 0;
        pointer-events: none;
      }
      .login-card { position: relative; overflow: hidden; }
      .login-card > * { position: relative; z-index: 1; }
    }

    .login-wrapper {
      position: relative;
      z-index: 1;
      width: 370px;
      max-width: 88vw;
      animation: fadeUp 0.55s cubic-bezier(.22,.68,0,1.2) both;
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(24px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .login-card {
      border: 1px solid rgba(201,168,76,0.22);
      border-radius: 16px;
      overflow: hidden;
      box-shadow:
        0 0 0 1px rgba(255,255,255,0.04) inset,
        0 28px 64px rgba(0,0,0,0.75),
        0 0 50px rgba(201,168,76,0.07);
      backdrop-filter: blur(20px);
      background: var(--dark-card);
    }

    .login-card::before {
      content: '';
      display: block;
      height: 3px;
      background: linear-gradient(90deg,
        transparent 0%, var(--gold) 30%, var(--gold-light) 50%,
        var(--gold) 70%, transparent 100%
      );
    }

    .login-header { text-align: center; padding: 2rem 1.75rem 1.25rem; }
    .login-header h4 {
      font-family: 'Rajdhani', sans-serif; font-weight: 700;
      font-size: 1.25rem; color: var(--text-main);
      margin: 0; letter-spacing: 3px; text-transform: uppercase;
    }
    .login-header p {
      margin: .4rem 0 0; font-size: .8rem; color: var(--text-muted);
      letter-spacing: .8px; text-transform: uppercase;
    }

    .login-divider {
      height: 1px; margin: 0 1.75rem;
      background: linear-gradient(90deg, transparent, rgba(201,168,76,0.28), transparent);
    }

    .login-body { padding: 1.5rem 1.75rem 2rem; }

    .field-label {
      display: block; font-size: .7rem; font-weight: 500;
      letter-spacing: 1.8px; text-transform: uppercase;
      color: var(--text-muted); margin-bottom: .4rem;
    }

    .login-body .form-control {
      border-radius: 10px; padding: .75rem 1rem;
      border: 1.5px solid var(--input-bdr);
      background: var(--input-bg); color: var(--text-main);
      font-size: .97rem; transition: border-color .2s, box-shadow .2s, background .2s;
    }
    .login-body .form-control::placeholder { color: rgba(240,236,228,0.22); }
    .login-body .form-control:focus {
      outline: none; border-color: var(--gold);
      background: rgba(201,168,76,0.07);
      box-shadow: 0 0 0 3px rgba(201,168,76,0.18); color: var(--text-main);
    }

    .btn-entrar {
      border-radius: 10px; padding: .8rem;
      font-family: 'Rajdhani', sans-serif; font-weight: 700;
      font-size: 1.05rem; letter-spacing: 3px; text-transform: uppercase;
      background: linear-gradient(135deg, #a87c28, var(--gold), var(--gold-light), var(--gold));
      border: none; color: #1a1000;
      transition: transform .15s, box-shadow .2s, filter .2s;
      box-shadow: 0 4px 22px rgba(201,168,76,0.28); margin-top: .3rem;
    }
    .btn-entrar:hover {
      transform: translateY(-2px); filter: brightness(1.12);
      box-shadow: 0 8px 30px rgba(201,168,76,0.48); color: #0d0900;
    }
    .btn-entrar:active  { transform: translateY(0); }
    .btn-entrar:disabled { opacity: .65; transform: none; }

    .password-wrap { position: relative; }
    .password-wrap .form-control { padding-right: 2.8rem; }
    .btn-eye {
      position: absolute; right: 11px; top: 50%; transform: translateY(-50%);
      background: none; border: none; padding: 0; cursor: pointer;
      color: var(--text-muted); font-size: 1.05rem; line-height: 1;
      z-index: 2; transition: color .15s;
    }
    .btn-eye:hover { color: var(--gold); }

    #msg .alert-success {
      background: rgba(16,185,129,0.14); border: 1px solid rgba(16,185,129,0.32);
      color: #6ee7b7; border-radius: 10px; font-size: .87rem;
    }
    #msg .alert-danger {
      background: rgba(220,53,69,0.14); border: 1px solid rgba(220,53,69,0.32);
      color: #ff8a94; border-radius: 10px; font-size: .87rem;
    }
    #msg .alert-warning {
      background: rgba(201,168,76,0.12); border: 1px solid rgba(201,168,76,0.28);
      color: var(--gold-light); border-radius: 10px; font-size: .87rem;
    }

    .login-link {
      text-align: center; margin-top: 1rem;
      font-size: .85rem; color: var(--text-muted);
    }
    .login-link a { color: var(--gold); text-decoration: none; font-weight: 500; }
    .login-link a:hover { text-decoration: underline; color: var(--gold-light); }
  </style>
</head>
<body>
  <div class="login-wrapper">
    <div class="card login-card">
      <div class="login-header">
        <h4>V12 Comandas</h4>
        <p>Nova senha</p>
      </div>
      <div class="login-divider"></div>
      <div class="login-body">
        <div id="msg"></div>

        <?php if (!$token): ?>
        <div class="alert alert-danger py-2 small" style="background:rgba(220,53,69,0.14);border:1px solid rgba(220,53,69,0.32);color:#ff8a94;border-radius:10px;">
          Link inválido. Solicite uma nova recuperação de senha.
        </div>
        <?php else: ?>
        <form id="resetForm" autocomplete="off">
          <div class="mb-3 password-wrap">
            <label class="field-label" for="password">Nova senha</label>
            <input id="password" type="password" class="form-control" placeholder="Mínimo 6 caracteres" required minlength="6" autocomplete="new-password">
            <button type="button" class="btn-eye" onclick="togglePass('password')">👁️</button>
          </div>
          <div class="mb-3 password-wrap">
            <label class="field-label" for="password2">Confirmar nova senha</label>
            <input id="password2" type="password" class="form-control" placeholder="Repita a nova senha" required minlength="6" autocomplete="new-password">
            <button type="button" class="btn-eye" onclick="togglePass('password2')">👁️</button>
          </div>
          <button type="submit" class="btn btn-entrar w-100" id="btnSalvar">Salvar nova senha</button>
        </form>
        <?php endif; ?>

        <div class="login-link">
          <a href="index.php">← Voltar ao login</a>
        </div>
      </div>
    </div>
  </div>

<script>
const TOKEN = <?= json_encode($token) ?>;

<?php if ($token): ?>
document.getElementById('resetForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const password  = document.getElementById('password').value;
  const password2 = document.getElementById('password2').value;
  const msg = document.getElementById('msg');
  const btn = document.getElementById('btnSalvar');
  msg.innerHTML = '';

  if (password.length < 6) {
    msg.innerHTML = '<div class="alert alert-warning py-2 small">A senha deve ter no mínimo 6 caracteres.</div>';
    return;
  }
  if (password !== password2) {
    msg.innerHTML = '<div class="alert alert-danger py-2 small">As senhas não coincidem.</div>';
    return;
  }

  btn.disabled    = true;
  btn.textContent = 'Salvando...';

  try {
    const res = await fetch('api/resetar_senha.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: TOKEN, password }),
    });
    const j = await res.json();

    if (j.success) {
      msg.innerHTML = '<div class="alert alert-success py-2 small">✅ ' + j.message + ' Redirecionando...</div>';
      document.getElementById('resetForm').style.display = 'none';
      setTimeout(() => window.location = 'index.php', 3000);
    } else {
      msg.innerHTML = '<div class="alert alert-danger py-2 small">' + (j.message || 'Erro ao salvar.') + '</div>';
      btn.disabled    = false;
      btn.textContent = 'Salvar nova senha';
    }
  } catch (err) {
    msg.innerHTML = '<div class="alert alert-danger py-2 small">Erro de rede. Tente novamente.</div>';
    btn.disabled    = false;
    btn.textContent = 'Salvar nova senha';
  }
});
<?php endif; ?>

function togglePass(id) {
  const input = document.getElementById(id);
  input.type = input.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
