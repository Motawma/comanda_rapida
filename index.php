<?php
/**
 * index.php - Página inicial / Tela de Login
 * 
 * Esta é a primeira página que o usuário vê ao acessar o sistema.
 * Exibe um formulário de login (usuário e senha).
 * 
 * Fluxo:
 * 1. Se o usuário já estiver logado, redireciona automaticamente
 *    para a página correspondente ao seu cargo (role):
 *    - admin   → caixa.php (Painel do Caixa)
 *    - cozinha → painel_cozinha.php (Painel da Cozinha)
 *    - garcom  → comanda.php (Tela de Comanda do Garçom)
 * 
 * 2. Se NÃO estiver logado, exibe o formulário de login.
 *    O login é feito via AJAX (fetch) para api/admin_login.php.
 *    Após login bem-sucedido, redireciona conforme o role.
 * 
 * Arquivos relacionados:
 * - auth.php          → funções de autenticação (isLoggedIn, currentUser)
 * - api/admin_login.php → API que processa o login
 * - img/logo.png      → logo exibida no card de login
 * - img/back_ground_index.png → imagem de fundo da página
 */
require_once __DIR__ . '/auth.php';

// ── Verificação de sessão ativa ──
// Se o usuário já está logado, não mostra o formulário de login.
// Redireciona direto para a página do seu cargo.
if (isLoggedIn()) {
    $role = currentUser()['role'] ?? 'garcom'; // se não tiver role, assume garçom
    switch ($role) {
        case 'admin':   header('Location: caixa.php'); break;       // Admin vai pro Caixa
        case 'cozinha': header('Location: painel_cozinha.php'); break; // Cozinha vai pro Painel
        default:        header('Location: comanda.php'); break;      // Garçom vai pra Comanda
    }
    exit;
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Comanda Rápida</title>
  <!-- Bootstrap 5 para estilização -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* ── Reset: página ocupa 100% da altura ── */
    html, body {
      height: 100%;
      margin: 0;
    }

    /* ── Fundo da página: imagem fixa centralizada ── */
    body {
      display: flex;
      align-items: center;       /* centraliza verticalmente */
      justify-content: center;   /* centraliza horizontalmente */
      background: url('img/back_ground_index.png') no-repeat center center fixed;
      background-size: cover;    /* imagem cobre toda a tela */
      position: relative;
    }

    /* ── Overlay escuro sobre o fundo (melhora legibilidade) ── */
    body::before {
      content: '';
      position: fixed;
      inset: 0;                          /* cobre toda a tela */
      background: rgba(0, 0, 0, .45);   /* preto com 45% de opacidade */
      z-index: 0;
    }

    /* ── Container do card de login ── */
    .login-wrapper {
      position: relative;
      z-index: 1;          /* fica acima do overlay escuro */
      width: 360px;        /* largura fixa no desktop */
      max-width: 92vw;     /* no mobile ocupa no máximo 92% da tela */
    }

    /* ── Card de login: branco com transparência e blur ── */
    .login-card {
      border: none;
      border-radius: 20px;                      /* bordas arredondadas */
      overflow: hidden;
      box-shadow: 0 12px 40px rgba(0,0,0,.35);  /* sombra para destaque */
      backdrop-filter: blur(6px);                /* efeito de desfoque no fundo */
      background: rgba(255,255,255,.92);         /* branco com 92% opacidade */
    }

    /* ── Cabeçalho do card (logo + título) ── */
    .login-header {
      text-align: center;
      padding: 2rem 1.5rem 1rem;
    }

    /* ── Logo do sistema ── 
     * max-width: controla o tamanho da logo
     * Para alterar o tamanho, mude o valor de max-width
     * ?v=2 na URL da imagem: força o navegador a recarregar (cache-busting)
     */
    .login-header img {
      max-width: 120px;          /* tamanho máximo da logo */
      margin-bottom: .75rem;
      filter: drop-shadow(0 2px 6px rgba(0,0,0,.15)); /* sombra na logo */
    }

    /* ── Título "Comanda Rápida" ── */
    .login-header h4 {
      font-weight: 800;
      font-size: 1.5rem;
      color: #212529;
      margin: 0;
      letter-spacing: .5px;
    }

    /* ── Subtítulo "Faça login para continuar" ── */
    .login-header p {
      margin: .35rem 0 0;
      font-size: .9rem;
      color: #6c757d;    /* cinza claro */
    }

    /* ── Corpo do card (formulário) ── */
    .login-body {
      padding: 1.25rem 1.5rem 1.75rem;
    }

    /* ── Campos de input (usuário e senha) ── */
    .login-body .form-control {
      border-radius: 12px;
      padding: .7rem 1rem;
      border: 1.5px solid #dee2e6;
      font-size: 1rem;
      transition: border-color .2s, box-shadow .2s;
    }

    /* ── Estilo do input quando está em foco (clicado) ── */
    .login-body .form-control:focus {
      border-color: #28a745;                         /* borda verde */
      box-shadow: 0 0 0 3px rgba(40,167,69,.18);    /* brilho verde suave */
    }

    /* ── Botão "Entrar" ── */
    .btn-entrar {
      border-radius: 12px;
      padding: .7rem;
      font-weight: 700;
      font-size: 1.1rem;
      background: linear-gradient(135deg, #28a745, #20c997); /* degradê verde */
      border: none;
      color: #fff;
      transition: transform .15s, box-shadow .15s;
    }

    /* ── Botão "Entrar" ao passar o mouse ── */
    .btn-entrar:hover {
      transform: translateY(-1px);                          /* sobe levemente */
      box-shadow: 0 4px 14px rgba(40,167,69,.35);          /* sombra verde */
      background: linear-gradient(135deg, #218838, #1baa80); /* verde mais escuro */
      color: #fff;
    }

    /* ── Botão "Entrar" ao clicar ── */
    .btn-entrar:active {
      transform: translateY(0); /* volta à posição normal */
    }

    /* ── Container do campo de senha (para posicionar o botão do olho) ── */
    .password-wrap {
      position: relative;
    }
    .password-wrap .form-control {
      padding-right: 2.8rem; /* espaço para o botão do olho */
    }

    /* ── Botão de mostrar/esconder senha (ícone do olho 👁️) ── */
    .btn-eye {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%); /* centraliza verticalmente */
      background: none;
      border: none;
      padding: 0;
      cursor: pointer;
      color: #6c757d;
      font-size: 1.15rem;
      line-height: 1;
      z-index: 2;
    }
    .btn-eye:hover {
      color: #212529;
    }
  </style>
</head>
<body>

  <!-- ══════════════════════════════════════════════════════
       CARD DE LOGIN
       Contém: logo, título, formulário de usuário e senha
       ══════════════════════════════════════════════════════ -->
  <div class="login-wrapper">
    <div class="card login-card">

      <!-- Cabeçalho: Logo + Título -->
      <div class="login-header">
        <!-- Logo do sistema. ?v=2 = cache-busting (mude o número ao trocar a imagem) -->
        <img src="img/logo.png?v=2" alt="Logo" onerror="this.style.display='none'">
        <h4>Comanda Rápida</h4>
        <p>Faça login para continuar</p>
      </div>

      <!-- Formulário de Login -->
      <div class="login-body">
        <!-- Div para exibir mensagens de erro/sucesso -->
        <div id="msg"></div>

        <form id="loginForm" autocomplete="off">
          <!-- Campo: Usuário -->
          <div class="mb-3">
            <input id="username" class="form-control" placeholder="Usuário" required autocomplete="username">
          </div>

          <!-- Campo: Senha (com botão de mostrar/esconder) -->
          <div class="mb-3 password-wrap">
            <input id="password" type="password" class="form-control" placeholder="Senha" required autocomplete="current-password">
            <!-- Botão olho: alterna entre mostrar e esconder a senha -->
            <button type="button" class="btn-eye" onclick="togglePasswordVisibility()">👁️</button>
          </div>

          <!-- Botão de enviar o formulário -->
          <button type="submit" class="btn btn-entrar w-100">Entrar</button>
        </form>
      </div>
    </div>
  </div>

<!-- ══════════════════════════════════════════════════════
     JAVASCRIPT - Lógica do Login
     ══════════════════════════════════════════════════════ -->
<script>
/**
 * Evento de submit do formulário de login.
 * Envia usuário e senha via AJAX (fetch) para a API.
 * Se login OK, redireciona conforme o cargo (role) do usuário.
 * Se falhar, exibe mensagem de erro na tela.
 */
document.getElementById('loginForm').addEventListener('submit', async (e) => {
  e.preventDefault(); // impede o envio padrão do formulário (reload da página)

  // Pega os valores dos campos
  const user = document.getElementById('username').value.trim();
  const pass = document.getElementById('password').value;
  const msg  = document.getElementById('msg');
  msg.innerHTML = ''; // limpa mensagens anteriores

  // Validação básica: campos obrigatórios
  if (!user || !pass) {
    msg.innerHTML = '<div class="alert alert-warning py-2 small">Preencha usuário e senha</div>';
    return;
  }

  // Desabilita o botão enquanto processa (evita cliques duplos)
  const btn = e.target.querySelector('button[type="submit"]');
  btn.disabled = true;
  btn.textContent = 'Entrando...';

  try {
    // ── Envia requisição para a API de login ──
    const res = await fetch('api/admin_login.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ username: user, password: pass })
    });
    const j = await res.json(); // converte resposta para JSON

    if (j.success) {
      // ── Login bem-sucedido: redireciona conforme o cargo ──
      switch (j.role) {
        case 'admin':   window.location = 'caixa.php'; break;         // Admin → Caixa
        case 'cozinha': window.location = 'painel_cozinha.php'; break; // Cozinha → Painel
        default:        window.location = 'comanda.php'; break;        // Garçom → Comanda
      }
    } else {
      // ── Login falhou: mostra erro ──
      msg.innerHTML = '<div class="alert alert-danger py-2 small">' + (j.message || 'Erro ao entrar') + '</div>';
    }
  } catch (err) {
    // ── Erro de rede (servidor offline, etc) ──
    msg.innerHTML = '<div class="alert alert-danger py-2 small">Erro de rede</div>';
  } finally {
    // Reabilita o botão independente do resultado
    btn.disabled = false;
    btn.textContent = 'Entrar';
  }
});

/**
 * Alterna a visibilidade da senha.
 * Muda o type do input entre 'password' (oculto) e 'text' (visível).
 * Chamada pelo botão do olho 👁️ ao lado do campo de senha.
 */
function togglePasswordVisibility() {
  const passwordInput = document.getElementById('password');
  const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
  passwordInput.setAttribute('type', type);
}
</script>

</body>
</html>
