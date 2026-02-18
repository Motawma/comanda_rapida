<?php
/**
 * api/config_tempos.php — Lê e salva configurações de tempo do KDS
 *
 * GET  → retorna as configurações atuais
 * POST → salva novas configurações (requer admin)
 *
 * Chaves suportadas:
 *   kds_warn_minutes   — tempo (min) para alerta amarelo
 *   kds_crit_minutes   — tempo (min) para alerta vermelho piscante
 *   kds_refresh_seconds — intervalo de refresh do KDS (segundos)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../conexao.php';

// Chaves permitidas com seus limites (min, max, padrão)
$CHAVES = [
    'kds_warn_minutes'    => ['min' => 1,  'max' => 120, 'default' => 8],
    'kds_crit_minutes'    => ['min' => 1,  'max' => 180, 'default' => 15],
    'kds_refresh_seconds' => ['min' => 2,  'max' => 60,  'default' => 5],
];

try {
    $pdo = getPDO();

    // Garante que a tabela existe
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS configuracoes (
            chave VARCHAR(100) NOT NULL PRIMARY KEY,
            valor TEXT NOT NULL,
            descricao VARCHAR(255) NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ── GET: retorna configurações ──
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare("SELECT chave, valor FROM configuracoes WHERE chave IN (" .
            implode(',', array_fill(0, count($CHAVES), '?')) . ")");
        $stmt->execute(array_keys($CHAVES));
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $result = [];
        foreach ($CHAVES as $chave => $limits) {
            $result[$chave] = isset($rows[$chave]) ? (int)$rows[$chave] : $limits['default'];
        }

        echo json_encode(['success' => true, 'config' => $result], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── POST: salva configurações (somente admin) ──
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireLogin();
        $user = currentUser();
        if (($user['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !is_array($input)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dados inválidos'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $updated = [];
        $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?)
                               ON DUPLICATE KEY UPDATE valor = VALUES(valor)");

        foreach ($CHAVES as $chave => $limits) {
            if (!isset($input[$chave])) continue;

            $valor = (int)$input[$chave];
            $valor = max($limits['min'], min($limits['max'], $valor));

            $stmt->execute([$chave, (string)$valor]);
            $updated[$chave] = $valor;
        }

        if (empty($updated)) {
            echo json_encode(['success' => false, 'message' => 'Nenhuma configuração válida enviada'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Configurações salvas com sucesso',
            'updated' => $updated
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido'], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
