<?php
require_once __DIR__ . '/../funcoes.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/_mod_produtos_estoque_lib.php';

$db = cr_get_db();
if ($db['driver'] !== 'pdo') cr_json(['ok' => false, 'success' => false, 'message' => 'Driver inválido: PDO requerido'], 500);
cr_must_have_tables($db);

$empresaId = currentEmpresaId();
$q = trim((string)cr_param('q', ''));
$categoria_id = cr_clean_int(cr_param('categoria_id', 0));
$categoria_text = trim((string)cr_param('categoria', ''));
$ativo = cr_param('ativo', '1');

$where = ['p.empresa_id = ?'];
$params = [$empresaId];

if ($q !== '') { $where[] = "p.nome LIKE ?"; $params[] = '%' . $q . '%'; }

if ($categoria_id > 0) {
    try {
        $stc = $db['conn']->prepare('SELECT nome FROM categorias_produtos WHERE id = ? AND empresa_id = ? LIMIT 1');
        $stc->execute([$categoria_id, $empresaId]);
        $catRow = $stc->fetch(PDO::FETCH_ASSOC);
        $categoria_text = $catRow ? ($catRow['nome'] ?? '') : '';
    } catch (Throwable $e) { $categoria_text = ''; }
}
if ($categoria_text !== '') { $where[] = "p.categoria = ?"; $params[] = $categoria_text; }
if ($ativo === '0' || $ativo === '1') { $where[] = "p.ativo = ?"; $params[] = (int)$ativo; }

$sql = "SELECT p.id, p.nome, p.preco, p.categoria, p.ativo, COALESCE(s.quantidade_atual, 0) AS estoque_atual, COALESCE(p.controla_estoque, 1) AS controla_estoque, p.estoque_minimo FROM produtos p LEFT JOIN estoque_saldo s ON s.produto_id = p.id";
if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY p.nome ASC";

try {
    $st = $db['conn']->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    cr_json(['ok' => true, 'success' => true, 'produtos' => $rows, 'data' => $rows]);
} catch (Throwable $e) {
    cr_json(['ok' => false, 'success' => false, 'message' => $e->getMessage()], 500);
}
