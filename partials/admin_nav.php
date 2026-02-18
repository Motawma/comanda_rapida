<?php
// partials/admin_nav.php
// Requer: theme.css e theme.js incluídos no <head> de cada página
require_once __DIR__ . '/../auth.php';

if (!isLoggedIn()) return;

$user = currentUser();
$role = $user['role'] ?? '';
$isAdmin = ($role === 'admin');
$isGarcom = ($role === 'garcom');
$isCozinha = ($role === 'cozinha');
$cur = basename($_SERVER['PHP_SELF']);
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top shadow-sm" id="mainNavbar">
  <div class="container-fluid">

    

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminMenu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="adminMenu">
      
      <!-- MENU PRINCIPAL -->
      <ul class="navbar-nav me-auto">
        <?php if ($isAdmin): ?>
        <li class="nav-item">
          <a class="nav-link<?= ($cur === 'caixa.php') ? ' active' : '' ?>" href="caixa.php">💰 Caixa</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= ($cur === 'comanda.php') ? ' active' : '' ?>" href="comanda.php">📋 Comanda</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= ($cur === 'painel_cozinha.php') ? ' active' : '' ?>" href="painel_cozinha.php">🍳 Cozinha</a>
        </li>
        <?php elseif ($isGarcom): ?>
        <li class="nav-item">
          <a class="nav-link<?= ($cur === 'comanda.php') ? ' active' : '' ?>" href="comanda.php">📋 Comanda</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= ($cur === 'painel_cozinha.php') ? ' active' : '' ?>" href="painel_cozinha.php">🍳 Cozinha</a>
        </li>
        <?php elseif ($isCozinha): ?>
        <li class="nav-item">
          <a class="nav-link<?= ($cur === 'painel_cozinha.php') ? ' active' : '' ?>" href="painel_cozinha.php">🍳 Cozinha</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= ($cur === 'comanda.php') ? ' active' : '' ?>" href="comanda.php">📋 Comanda</a>
        </li>
        <?php endif; ?>
      </ul>

      <!-- LADO DIREITO -->
      <ul class="navbar-nav ms-auto d-flex align-items-center">
        <?php if ($isAdmin): ?>
        <li class="nav-item">
          <a class="nav-link<?= ($cur === 'produtos.php') ? ' active' : '' ?>" href="produtos.php">Produtos</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= ($cur === 'estoque.php') ? ' active' : '' ?>" href="estoque.php">Estoque</a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-white p-0" href="admin.php">Admin: <?= htmlspecialchars($user['username']) ?></a>
        </li>
        <?php else: ?>
        <li class="nav-item">
          <span class="nav-link text-white-50"><?= htmlspecialchars($user['username']) ?></span>
        </li>
        <?php endif; ?>
        <!-- Botão Modo Claro/Escuro -->
        <li class="nav-item d-flex align-items-center me-2">
          <button class="btn-theme-toggle" aria-pressed="false" title="Alternar tema">
            🌙 <span class="theme-label">Escuro</span>
          </button>
        </li>
        <li class="nav-item">
          <a class="btn btn-sm btn-outline-light" href="admin_logout.php">Sair</a>
        </li>
      </ul>

    </div>
  </div>
</nav>