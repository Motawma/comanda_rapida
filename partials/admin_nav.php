<?php
// partials/admin_nav.php
require_once __DIR__ . '/../auth.php';

if (!isLoggedIn()) return;

$user = currentUser();
if (($user['role'] ?? '') !== 'admin') return;
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top shadow-sm">
  <div class="container-fluid">

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminMenu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="adminMenu">
      
      <!-- MENU PRINCIPAL -->
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link" href="caixa.php">Caixa</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="index.php">Comanda</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="painel_cozinha.php">Cozinha</a>
        </li>
      </ul>

      <!-- LADO DIREITO -->
      <div class="d-flex align-items-center gap-3">
        <a class="nav-link text-white p-0" href="admin.php">
          Admin: <?= htmlspecialchars($user['username']) ?>
        </a>
        <a class="btn btn-sm btn-outline-light" href="admin_logout.php">Sair</a>
      </div>

    </div>
  </div>
</nav>