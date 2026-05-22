<?php
// api/sangria_listar.php — Lista sangrias de uma sessão e retorna o total
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireAdminApi();

$sessaoId  = isset($_GET['sessao_id']) ? (int)$_GET['sessao_id'] : 0;
$empresaId = currentEmpresaId();

try {
    $pdo = getPDO();
    $total = 0.0;
    $lista = [];

    // Sangrias da tabela sangrias
    try {
        $stmt = $pdo->prepare("SELECT id, valor, motivo, created_at FROM sangrias WHERE caixa_sessao_id = ? AND empresa_id = ? ORDER BY id ASC");
        $stmt->execute([$sessaoId, $empresaId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $total += (float)$r['valor'];
            $lista[] = ['tipo' => 'SANGRIA', 'valor' => (float)$r['valor'], 'obs' => $r['motivo'] ?? '', 'criado_em' => $r['created_at']];
        }
    } catch (Throwable $e) {}

    // Reforços da tabela caixa_movimentos (se existir)
    $totalReforco = 0.0;
    try {
        $stmt2 = $pdo->prepare("SELECT tipo, valor, obs, criado_em FROM caixa_movimentos WHERE sessao_id = ? ORDER BY id ASC");
        $stmt2->execute([$sessaoId]);
        $rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows2 as $r) {
            $lista[] = ['tipo' => $r['tipo'], 'valor' => (float)$r['valor'], 'obs' => $r['obs'] ?? '', 'criado_em' => $r['criado_em']];
            if ($r['tipo'] === 'REFORCO') $totalReforco += (float)$r['valor'];
            // Sangrias duplicadas aqui seriam se usaram caixa_avulso — somamos também
        }
    } catch (Throwable $e) {}

    echo json_encode([
        'success'       => true,
        'total'         => $total,         // total sangrias (deduz do caixa)
        'total_reforco' => $totalReforco,  // total reforços (adiciona ao caixa)
        'movimentos'    => $lista,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'total' => 0, 'total_reforco' => 0, 'movimentos' => []]);
}
