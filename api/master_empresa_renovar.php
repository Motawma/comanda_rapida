<?php
// api/master_empresa_renovar.php — Renovar/criar licença de uma empresa
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

requireMasterApi();

$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$empresaId  = (int)($body['empresa_id'] ?? 0);
$dias       = (int)($body['dias']       ?? 30);
$plano      = trim($body['plano']       ?? '');
$valor      = (float)($body['valor']    ?? 0);
$formaPgto  = trim($body['forma_pagamento'] ?? '');
$obs        = trim($body['obs']         ?? '');
$dataExata  = trim($body['data_exata']  ?? '');

if ($empresaId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa inválida.']);
    exit;
}
// Valida: ou data exata ou dias válidos
$usandoDataExata = $dataExata && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataExata);
if (!$usandoDataExata && ($dias <= 0 || $dias > 3650)) {
    echo json_encode(['success' => false, 'message' => 'Quantidade de dias inválida (1–3650).']);
    exit;
}

try {
    $pdo = getPDO();

    // Busca empresa
    $stmt = $pdo->prepare("SELECT id, nome, data_expiracao, plano FROM empresas WHERE id = ? LIMIT 1");
    $stmt->execute([$empresaId]);
    $empresa = $stmt->fetch();
    if (!$empresa) {
        echo json_encode(['success' => false, 'message' => 'Empresa não encontrada.']);
        exit;
    }

    // Plano: usa o atual se não for enviado
    $planoFinal = $plano ?: ($empresa['plano'] ?? 'basico');

    $hoje       = date('Y-m-d');
    $dataInicio = $hoje;

    if ($usandoDataExata) {
        // Data exata informada pelo master — usa diretamente
        $dataFim = $dataExata;
    } else {
        // Adiciona dias: se ainda válida, estende a partir do vencimento atual
        $dataAtual = $empresa['data_expiracao'] ?? $hoje;
        if (!empty($dataAtual) && $dataAtual >= $hoje) {
            $dataFim = date('Y-m-d', strtotime($dataAtual . " +{$dias} days"));
        } else {
            $dataFim = date('Y-m-d', strtotime($hoje . " +{$dias} days"));
        }
    }

    // Garante tabela de pagamentos ANTES da transaction (DDL causa commit implicito)
    $pdo->exec("CREATE TABLE IF NOT EXISTS licenca_pagamentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        plano VARCHAR(50) NOT NULL DEFAULT 'basico',
        data_pagamento DATETIME DEFAULT CURRENT_TIMESTAMP,
        data_inicio DATE NOT NULL,
        data_fim DATE NOT NULL,
        forma_pagamento VARCHAR(50) DEFAULT NULL,
        referencia_externa VARCHAR(255) DEFAULT NULL,
        obs TEXT DEFAULT NULL,
        FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->beginTransaction();

    // Atualiza empresa e marca como ativo no pipeline
    $upd = $pdo->prepare("UPDATE empresas SET data_expiracao = ?, plano = ?, crm_status = 'ativo' WHERE id = ?");
    $upd->execute([$dataFim, $planoFinal, $empresaId]);

    // Registra pagamento
    $ins = $pdo->prepare("INSERT INTO licenca_pagamentos
        (empresa_id, valor, plano, data_inicio, data_fim, forma_pagamento, obs)
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $ins->execute([$empresaId, $valor, $planoFinal, $dataInicio, $dataFim, $formaPgto ?: null, $obs ?: null]);

    $pdo->commit();

    echo json_encode([
        'success'        => true,
        'message'        => 'Licença renovada com sucesso!',
        'data_expiracao' => date('d/m/Y', strtotime($dataFim)),
        'plano'          => $planoFinal,
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
