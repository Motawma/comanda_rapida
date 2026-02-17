<?php
// COMANDA RÁPIDA — Lib de apoio (compatível com mysqli/PDO)

function cr_json($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** @return array{driver:string, conn:mixed} */
function cr_get_db(): array {
    // If the project exposes getPDO(), prefer it (single source of truth)
    // Prefer function getPDO() if available
    if (function_exists('getPDO')) {
        try {
            $pdo = getPDO();
            if ($pdo instanceof PDO) return ['driver' => 'pdo', 'conn' => $pdo];
        } catch (Throwable $e) {
            // fall through to other fallbacks
        }
    }

    // Tenta achar uma conexão já existente no projeto.
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        return ['driver' => 'pdo', 'conn' => $GLOBALS['pdo']];
    }
    if (isset($GLOBALS['conn'])) {
        return ['driver' => 'mysqli', 'conn' => $GLOBALS['conn']];
    }
    if (isset($GLOBALS['mysqli']) && $GLOBALS['mysqli'] instanceof mysqli) {
        return ['driver' => 'mysqli', 'conn' => $GLOBALS['mysqli']];
    }

    // Último recurso: tenta uma função comum.
    if (function_exists('db')) {
        $db = db();
        if ($db instanceof PDO) return ['driver' => 'pdo', 'conn' => $db];
        if ($db instanceof mysqli) return ['driver' => 'mysqli', 'conn' => $db];
    }

    cr_json(['ok' => false, 'error' => 'Conexão com o banco não encontrada em funcoes.php (esperado $conn (mysqli) ou $pdo (PDO)).'], 500);
}

function cr_begin($db): void {
    if ($db['driver'] === 'pdo') {
        $db['conn']->beginTransaction();
    } else {
        $db['conn']->begin_transaction();
    }
}
function cr_commit($db): void {
    if ($db['driver'] === 'pdo') {
        $db['conn']->commit();
    } else {
        $db['conn']->commit();
    }
}
function cr_rollback($db): void {
    if ($db['driver'] === 'pdo') {
        if ($db['conn']->inTransaction()) $db['conn']->rollBack();
    } else {
        $db['conn']->rollback();
    }
}

/** @return array<int, array<string,mixed>> */
function cr_fetch_all($stmt, string $driver): array {
    if ($driver === 'pdo') return $stmt->fetchAll(PDO::FETCH_ASSOC);
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/** @return array<string,mixed>|null */
function cr_fetch_one($stmt, string $driver): ?array {
    $all = cr_fetch_all($stmt, $driver);
    return $all[0] ?? null;
}

/** @return mixed */
function cr_param($key, $default = null) {
    if (isset($_POST[$key])) return $_POST[$key];
    if (isset($_GET[$key])) return $_GET[$key];

    // JSON body
    static $json;
    if ($json === null) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw ?: 'null', true);
        if (!is_array($json)) $json = [];
    }
    return $json[$key] ?? $default;
}

function cr_clean_decimal($v): float {
    if ($v === null || $v === '') return 0.0;
    // aceita "1.234,56" e "1234.56"
    $s = trim((string)$v);
    $s = str_replace(['.', ' '], ['', ''], $s);
    $s = str_replace(',', '.', $s);
    return (float)$s;
}

function cr_clean_int($v): int {
    return (int)($v ?? 0);
}

function cr_must_have_tables($db): void {
    // verificação simples
    $sql = "SHOW TABLES LIKE 'produtos'";
    if ($db['driver'] === 'pdo') {
        $st = $db['conn']->query($sql);
        $row = $st->fetch(PDO::FETCH_NUM);
        if (!$row) cr_json(['ok'=>false,'error'=>'Tabelas de produtos/estoque não encontradas. Rode o SQL sql/001_produtos_estoque.sql.'], 500);
    } else {
        $res = $db['conn']->query($sql);
        $row = $res ? $res->fetch_row() : null;
        if (!$row) cr_json(['ok'=>false,'error'=>'Tabelas de produtos/estoque não encontradas. Rode o SQL sql/001_produtos_estoque.sql.'], 500);
    }
}

