<?php
// tools/seed_admin.php
require_once __DIR__ . '/../conexao.php';

$pdo = getPDO();
$username = 'admin';
$password = 'admin123';

try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $r = $stmt->fetch();
    if ($r) {
        echo "Admin já existe\n";
        exit(0);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins = $pdo->prepare("INSERT INTO users (username, password_hash, role, active) VALUES (?, ?, 'admin', 1)");
    $ins->execute([$username, $hash]);
    echo "Admin criado\n";
} catch (Throwable $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    exit(1);
}
