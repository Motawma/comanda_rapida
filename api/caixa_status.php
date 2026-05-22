<?php
// api/caixa_status.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../funcoes.php';

requireCaixaApi();

try {
    $pdo = getPDO();
    $empresaId = currentEmpresaId();

    // Busca sessão aberta APENAS da empresa atual
    $stmt = $pdo->prepare("SELECT * FROM caixa_sessoes WHERE closed_at IS NULL AND empresa_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$empresaId]);
    $sessao = $stmt->fetch();

    if (!$sessao) {
        echo json_encode(['success' => true, 'sessao' => null]);
        exit;
    }
    echo json_encode(['success' => true, 'sessao' => $sessao], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no servidor: ' . $e->getMessage()]);
    exit;
}
