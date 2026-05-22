<?php
// api/balcao_produtos.php — Lista produtos ativos para venda balcão
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireAdminApi();

$empresaId = currentEmpresaId();
$pdo = getPDO();

// Verifica se coluna codigo existe
$temCodigo = false;
try { $pdo->query("SELECT codigo FROM produtos LIMIT 0"); $temCodigo = true; } catch (Throwable $e) {}

$codigoCol = $temCodigo ? ', p.codigo' : ", NULL AS codigo";
$codigoColLegacy = $temCodigo ? ', codigo' : ", NULL AS codigo";

try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.nome, p.preco{$codigoCol},
               COALESCE(c.nome, p.categoria, 'Outros') AS categoria
        FROM produtos p
        LEFT JOIN categorias_produtos c ON c.id = p.categoria_id AND c.empresa_id = p.empresa_id
        WHERE p.empresa_id = ? AND p.ativo = 1
        ORDER BY categoria, p.nome
    ");
    $stmt->execute([$empresaId]);
} catch (Throwable $e) {
    $stmt = $pdo->prepare("
        SELECT id, nome, preco{$codigoColLegacy},
               COALESCE(categoria, 'Outros') AS categoria
        FROM produtos
        WHERE empresa_id = ? AND ativo = 1
        ORDER BY categoria, nome
    ");
    $stmt->execute([$empresaId]);
}

$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'ok'      => true,
    'produtos' => array_map(fn($p) => [
        'id'       => (int)$p['id'],
        'nome'     => $p['nome'],
        'preco'    => (float)$p['preco'],
        'codigo'   => $p['codigo'] ?? null,
        'categoria'=> $p['categoria'],
    ], $produtos),
], JSON_UNESCAPED_UNICODE);
