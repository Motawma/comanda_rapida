<?php
// api/master_excluir_empresa.php — Exclui empresa e todos os dados relacionados
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireMasterApi();

$input     = json_decode(file_get_contents('php://input'), true) ?: [];
$empresaId = (int)($input['empresa_id'] ?? 0);

if (!$empresaId) {
    echo json_encode(['ok' => false, 'message' => 'ID inválido']); exit;
}

$pdo = getPDO();

// Verifica se empresa existe
$emp = $pdo->prepare("SELECT nome FROM empresas WHERE id = ? LIMIT 1");
$emp->execute([$empresaId]);
$empresa = $emp->fetch();

if (!$empresa) {
    echo json_encode(['ok' => false, 'message' => 'Empresa não encontrada']); exit;
}

try {
    $pdo->beginTransaction();

    // Exclui na ordem correta para evitar FK violations
    $tabelas = [
        "DELETE FROM itens_pedido WHERE pedido_id IN (SELECT id FROM pedidos WHERE empresa_id = ?)",
        "DELETE FROM pedidos WHERE empresa_id = ?",
        "DELETE FROM caixa_movimentos WHERE sessao_id IN (SELECT id FROM caixa_sessoes WHERE empresa_id = ?)",
        "DELETE FROM sangrias WHERE empresa_id = ?",
        "DELETE FROM caixa_sessoes WHERE empresa_id = ?",
        "DELETE FROM produtos WHERE empresa_id = ?",
        "DELETE FROM categorias_produtos WHERE empresa_id = ?",
        "DELETE FROM configuracoes WHERE empresa_id = ?",
        "DELETE FROM licenca_pagamentos WHERE empresa_id = ?",
        "DELETE FROM empresa_recursos WHERE empresa_id = ?",
        "DELETE FROM asaas_cobrancas WHERE empresa_id = ?",
        "DELETE FROM users WHERE empresa_id = ?",
        "DELETE FROM empresas WHERE id = ?",
    ];

    // Tabelas opcionais (podem não existir)
    $tabelasOpcionais = [
        "DELETE FROM produto_escolha_opcoes WHERE escolha_id IN (SELECT pe.id FROM produto_escolhas pe JOIN produtos p ON pe.produto_id = p.id WHERE p.empresa_id = ?)",
        "DELETE FROM produto_escolhas WHERE produto_id IN (SELECT id FROM produtos WHERE empresa_id = ?)",
        "DELETE FROM pedidos_ifood WHERE empresa_id = ?",
        "DELETE FROM ifood_config WHERE empresa_id = ?",
        "DELETE FROM estoque_saldo WHERE empresa_id = ?",
        "DELETE FROM estoque_movimentos WHERE empresa_id = ?",
    ];

    foreach ($tabelas as $sql) {
        $pdo->prepare($sql)->execute([$empresaId]);
    }

    foreach ($tabelasOpcionais as $sql) {
        try {
            $pdo->prepare($sql)->execute([$empresaId]);
        } catch (Throwable $e) {
            // Ignora se tabela não existir
        }
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'message' => 'Empresa "' . $empresa['nome'] . '" excluída com sucesso.']);

} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('[master_excluir_empresa] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Erro ao excluir: ' . $e->getMessage()]);
}
