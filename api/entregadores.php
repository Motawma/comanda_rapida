<?php
// api/entregadores.php — CRUD de entregadores
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../funcoes.php';
require_once __DIR__ . '/../auth.php';

requireAdminApi(); // só admin

function ent_json(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo       = getPDO();
$empresaId = currentEmpresaId();

// Cria tabela se não existir
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS entregadores (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id  INT NOT NULL,
        nome        VARCHAR(100) NOT NULL,
        telefone    VARCHAR(20) NOT NULL,
        ativo       TINYINT(1) NOT NULL DEFAULT 1,
        criado_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_empresa (empresa_id)
    )");
} catch (Throwable $e) {
    ent_json(['ok' => false, 'message' => 'Erro ao criar tabela: ' . $e->getMessage()], 500);
}

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: listar ───────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $st = $pdo->prepare("SELECT id, nome, telefone, ativo FROM entregadores WHERE empresa_id = ? ORDER BY nome ASC");
    $st->execute([$empresaId]);

    $se = $pdo->prepare("SELECT slug FROM empresas WHERE id = ? LIMIT 1");
    $se->execute([$empresaId]);
    $slug = $se->fetchColumn() ?: '';

    ent_json(['ok' => true, 'slug' => $slug, 'entregadores' => $st->fetchAll()]);
}

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim((string)($input['action'] ?? $_POST['action'] ?? ''));

// ── POST: criar ───────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'criar') {
    $nome     = trim((string)($input['nome']     ?? ''));
    $telefone = preg_replace('/\D/', '', (string)($input['telefone'] ?? ''));
    if (!$nome)     ent_json(['ok' => false, 'message' => 'Nome obrigatório.'], 400);
    if (!$telefone) ent_json(['ok' => false, 'message' => 'Telefone obrigatório.'], 400);

    $ck = $pdo->prepare("SELECT id FROM entregadores WHERE empresa_id = ? AND telefone = ? LIMIT 1");
    $ck->execute([$empresaId, $telefone]);
    if ($ck->fetch()) ent_json(['ok' => false, 'message' => 'Já existe um entregador com esse telefone.'], 409);

    $st = $pdo->prepare("INSERT INTO entregadores (empresa_id, nome, telefone) VALUES (?, ?, ?)");
    $st->execute([$empresaId, $nome, $telefone]);
    ent_json(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
}

// ── POST: editar ──────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'editar') {
    $id       = (int)($input['id'] ?? 0);
    $nome     = trim((string)($input['nome']     ?? ''));
    $telefone = preg_replace('/\D/', '', (string)($input['telefone'] ?? ''));
    if (!$id)       ent_json(['ok' => false, 'message' => 'ID inválido.'], 400);
    if (!$nome)     ent_json(['ok' => false, 'message' => 'Nome obrigatório.'], 400);
    if (!$telefone) ent_json(['ok' => false, 'message' => 'Telefone obrigatório.'], 400);

    $ck = $pdo->prepare("SELECT id FROM entregadores WHERE empresa_id = ? AND telefone = ? AND id != ? LIMIT 1");
    $ck->execute([$empresaId, $telefone, $id]);
    if ($ck->fetch()) ent_json(['ok' => false, 'message' => 'Já existe outro entregador com esse telefone.'], 409);

    $st = $pdo->prepare("UPDATE entregadores SET nome = ?, telefone = ? WHERE id = ? AND empresa_id = ?");
    $st->execute([$nome, $telefone, $id, $empresaId]);
    ent_json(['ok' => true]);
}

// ── POST: excluir ─────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'excluir') {
    $id = (int)($input['id'] ?? 0);
    if (!$id) ent_json(['ok' => false, 'message' => 'ID inválido.'], 400);
    $pdo->prepare("DELETE FROM entregadores WHERE id = ? AND empresa_id = ?")->execute([$id, $empresaId]);
    ent_json(['ok' => true]);
}

ent_json(['ok' => false, 'message' => 'Ação inválida.'], 400);
