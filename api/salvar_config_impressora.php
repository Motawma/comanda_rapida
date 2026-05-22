<?php
// api/salvar_config_impressora.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireAdminApi();

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON inválido']);
    exit;
}

$empresaId = currentEmpresaId();
if ($empresaId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Empresa inválida']);
    exit;
}

$largura   = in_array($input['largura_papel'] ?? '', ['58mm','72mm','80mm','A4']) ? $input['largura_papel'] : '80mm';
$nome      = trim(substr((string)($input['nome_restaurante'] ?? ''), 0, 100));
$rodape    = trim(substr((string)($input['rodape'] ?? ''), 0, 150));
$autoPrint = !empty($input['auto_print']);

$config = [
    'largura_papel'    => $largura,
    'nome_restaurante' => $nome ?: 'Meu Restaurante',
    'rodape'           => $rodape ?: 'Obrigado pela preferência!',
    'auto_print'       => $autoPrint,
];

try {
    $pdo = getPDO();

    // Garante que coluna existe (cria se não tiver)
    try {
        $pdo->exec("ALTER TABLE empresas ADD COLUMN config_impressora TEXT NULL DEFAULT NULL");
    } catch (Throwable $e) { /* coluna já existe */ }

    $stmt = $pdo->prepare("UPDATE empresas SET config_impressora = ? WHERE id = ?");
    $stmt->execute([json_encode($config, JSON_UNESCAPED_UNICODE), $empresaId]);

    echo json_encode(['success' => true, 'message' => 'Configuração salva!', 'config' => $config]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
