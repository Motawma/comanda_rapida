<?php
// api/cardapio_config_get.php — Retorna configuração do cardápio da empresa logada
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireAdminApi();

$empresaId = currentEmpresaId();

try {
    $pdo  = getPDO();
    $stmt = $pdo->prepare("
        SELECT slug, cardapio_ativo, cardapio_cor_primaria, cardapio_cor_secundaria,
               cardapio_logo, cardapio_banner, cardapio_descricao,
               cardapio_delivery, cardapio_mesa, cardapio_pix_chave,
               cardapio_taxa_entrega, cardapio_tempo_estimado,
               cardapio_whatsapp, cardapio_horario_func
        FROM empresas WHERE id = ? LIMIT 1
    ");
    $stmt->execute([$empresaId]);
    $row = $stmt->fetch();

    if (!$row) { echo json_encode(['ok'=>false,'message'=>'Empresa não encontrada.']); exit; }

    echo json_encode([
        'ok'     => true,
        'config' => [
            'slug'             => $row['slug'] ?? '',
            'ativo'            => (bool)$row['cardapio_ativo'],
            'cor_primaria'     => $row['cardapio_cor_primaria']    ?: '#6B2FA0',
            'cor_secundaria'   => $row['cardapio_cor_secundaria']  ?: '#FF6B9D',
            'logo'             => $row['cardapio_logo']            ?? null,
            'banner'           => $row['cardapio_banner']          ?? null,
            'descricao'        => $row['cardapio_descricao']       ?? '',
            'delivery'         => (bool)$row['cardapio_delivery'],
            'mesa'             => (bool)$row['cardapio_mesa'],
            'pix_chave'        => $row['cardapio_pix_chave']       ?? '',
            'taxa_entrega'     => (float)$row['cardapio_taxa_entrega'],
            'tempo_estimado'   => $row['cardapio_tempo_estimado']  ?: '30-45 min',
            'whatsapp'         => $row['cardapio_whatsapp']        ?? '',
            'horario_func'     => $row['cardapio_horario_func']    ?? null,
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);
}
