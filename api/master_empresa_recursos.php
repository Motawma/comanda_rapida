<?php
// api/master_empresa_recursos.php — GET: overrides de uma empresa | POST: salva overrides
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn() || !isMaster()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Acesso restrito ao master']);
    exit;
}

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $empresa_id = intval($_GET['empresa_id'] ?? 0);
    if (!$empresa_id) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'empresa_id inválido']);
        exit;
    }

    // Plano da empresa
    $stmt = $pdo->prepare("SELECT plano FROM empresas WHERE id = ?");
    $stmt->execute([$empresa_id]);
    $plano = $stmt->fetchColumn() ?: 'basico';

    // Recursos habilitados pelo plano
    $stmt2 = $pdo->prepare("SELECT recurso FROM plano_recursos WHERE plano = ?");
    $stmt2->execute([$plano]);
    $planRecursos = $stmt2->fetchAll(PDO::FETCH_COLUMN);

    // Overrides da empresa
    $stmt3 = $pdo->prepare("SELECT recurso, liberado FROM empresa_recursos WHERE empresa_id = ?");
    $stmt3->execute([$empresa_id]);
    $overrides = [];
    foreach ($stmt3->fetchAll() as $r) {
        $overrides[$r['recurso']] = (bool)$r['liberado'];
    }

    echo json_encode([
        'ok'        => true,
        'plano'     => $plano,
        'plano_rec' => $planRecursos,
        'overrides' => $overrides,
    ]);
    exit;
}

// POST — salva overrides
$input = json_decode(file_get_contents('php://input'), true);
$empresa_id = intval($input['empresa_id'] ?? 0);
$overrides  = $input['overrides'] ?? null;

if (!$empresa_id || !is_array($overrides)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Dados inválidos']);
    exit;
}

$recursos_validos = ['comanda', 'produtos', 'admin', 'financeiro', 'caixa', 'kds', 'fiado', 'estoque', 'ifood'];

try {
    // Remove overrides anteriores
    $pdo->prepare("DELETE FROM empresa_recursos WHERE empresa_id = ?")->execute([$empresa_id]);

    $ins = $pdo->prepare("INSERT INTO empresa_recursos (empresa_id, recurso, liberado) VALUES (?, ?, ?)");
    foreach ($overrides as $recurso => $liberado) {
        if (!in_array($recurso, $recursos_validos)) continue;
        $ins->execute([$empresa_id, $recurso, $liberado ? 1 : 0]);
    }
    echo json_encode(['ok' => true]);
} catch (Throwable $ex) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $ex->getMessage()]);
}
