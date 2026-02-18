<?php
// login.php - Redireciona para index.php (tela de login unificada)
require_once __DIR__ . '/auth.php';

// Se já está logado, redireciona para a página do role
if (isLoggedIn()) {
    $role = currentUser()['role'] ?? 'garcom';
    switch ($role) {
        case 'admin':   header('Location: caixa.php'); break;
        case 'cozinha': header('Location: painel_cozinha.php'); break;
        default:        header('Location: comanda.php'); break;
    }
    exit;
}

// Não logado → vai pro index (login único)
header('Location: index.php');
exit;