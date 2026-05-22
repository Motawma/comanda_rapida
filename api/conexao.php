<?php
// conexao.php
require_once __DIR__ . '/config.php';

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            // Timezone do MySQL alinhado com o PHP (Brasília)
            $pdo->exec("SET time_zone = '-03:00'");
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Erro ao conectar ao banco: ' . $e->getMessage()]);
            exit;
        }
    }
    return $pdo;
}

/**
 * Retorna o limite de garçons ativos permitido pelo plano da empresa.
 * Master e planos sem limite retornam PHP_INT_MAX.
 */
function getLimiteGarcom(int $empresa_id): int {
    if (function_exists('isMaster') && isMaster()) return PHP_INT_MAX;

    // Limites por plano — ajuste aqui conforme necessário
    $limitesPorPlano = [
        'basico'     => 3,
        'pro'        => PHP_INT_MAX,
        'enterprise' => PHP_INT_MAX,
    ];

    try {
        $pdo   = getPDO();
        $stmt  = $pdo->prepare("SELECT plano, COALESCE(garcom_extras, 0) AS garcom_extras FROM empresas WHERE id = ? LIMIT 1");
        $stmt->execute([$empresa_id]);
        $row   = $stmt->fetch();
        $plano = $row['plano'] ?? '';
        $base  = $limitesPorPlano[$plano] ?? PHP_INT_MAX;
        if ($base === PHP_INT_MAX) return PHP_INT_MAX;
        return $base + (int)($row['garcom_extras'] ?? 0);
    } catch (Throwable $e) {
        return PHP_INT_MAX; // em erro, não bloqueia
    }
}

/**
 * Verifica se a empresa tem acesso a um recurso/módulo.
 * Hierarquia: master → sempre true | override empresa → plano da empresa
 */
function empresaTemRecurso(int $empresa_id, string $recurso): bool {
    // Master bypassa tudo
    if (function_exists('isMaster') && isMaster()) return true;

    // Cache na sessão para evitar queries repetidas
    if (session_status() === PHP_SESSION_ACTIVE) {
        $key = "_rec_{$empresa_id}_{$recurso}";
        if (isset($_SESSION[$key])) return (bool)$_SESSION[$key];
    }

    try {
        $pdo = getPDO();

        // 1. Override da empresa
        $stmt = $pdo->prepare("SELECT liberado FROM empresa_recursos WHERE empresa_id = ? AND recurso = ?");
        $stmt->execute([$empresa_id, $recurso]);
        $row = $stmt->fetch();
        if ($row !== false) {
            $result = (bool)$row['liberado'];
            if (session_status() === PHP_SESSION_ACTIVE) $_SESSION[$key] = $result;
            return $result;
        }

        // 2. Plano da empresa
        $stmt2 = $pdo->prepare("SELECT plano FROM empresas WHERE id = ?");
        $stmt2->execute([$empresa_id]);
        $plano = $stmt2->fetchColumn();
        if (!$plano) return false;

        $stmt3 = $pdo->prepare("SELECT COUNT(*) FROM plano_recursos WHERE plano = ? AND recurso = ?");
        $stmt3->execute([$plano, $recurso]);
        $result = $stmt3->fetchColumn() > 0;

        if (session_status() === PHP_SESSION_ACTIVE) $_SESSION[$key] = $result;
        return $result;
    } catch (Throwable $e) {
        return true; // em caso de erro (tabelas não criadas ainda), libera acesso
    }
}
