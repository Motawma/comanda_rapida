<?php
require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
    header('Location: admin.php');
    exit;
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  .password-wrap { position: relative; }
  .password-wrap .form-control { padding-right: 2.8rem; }
  .btn-eye {
    position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
    background: none; border: none; padding: 0; cursor: pointer;
    color: #6c757d; font-size: 1.15rem; line-height: 1; z-index: 2;
  }
  .btn-eye:hover { color: #212529; }
</style>
</head>
<body class="bg-light">

<div class="container py-5">
  <div class="col-md-4 mx-auto">
    <div class="card">
      <div class="card-body">
        <h4 class="mb-3">Login Admin</h4>

        <div id="msg"></div>

        <form id="loginForm">
          <div class="mb-3">
            <input id="username" class="form-control" placeholder="Usuário" required>
          </div>
          <div class="mb-3 password-wrap">
            <input id="password" type="password" class="form-control" placeholder="Senha" required>
            <button type="button" class="btn-eye" onclick="document.getElementById('password').type = document.getElementById('password').type === 'password' ? 'text' : 'password'">👁️</button>
          </div>
          <button class="btn btn-primary w-100">Entrar</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('loginForm').addEventListener('submit', async (e) => {
  e.preventDefault();

  const user = document.getElementById('username').value;
  const pass = document.getElementById('password').value;
  const msg  = document.getElementById('msg');

  const res = await fetch('api/admin_login.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ username:user, password:pass })
  });

  const j = await res.json();

  if (j.success) {
    window.location = 'caixa.php';
  } else {
    msg.innerHTML = '<div class="alert alert-danger">'+j.message+'</div>';
  }
});
</script>

</body>
</html>