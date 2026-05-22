<?php
// backup_run.php — Executa backup do banco de dados
// Pode ser chamado via cron: php /caminho/para/backup_run.php
// Ou via web com autenticação master

define('BACKUP_DIR', __DIR__ . '/backups');
define('MAX_BACKUPS', 30); // manter últimos 30 backups

// Credenciais do banco
$db_host = 'localhost';
$db_name = 'will4163_comanda';
$db_user = 'will4163_comanda';
$db_pass = 'Mota@9745';

// Criar pasta de backups se não existir
if (!is_dir(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0755, true);
    // Proteger acesso direto via browser
    file_put_contents(BACKUP_DIR . '/.htaccess', "Order deny,allow\nDeny from all\n");
}

$timestamp  = date('Y-m-d_H-i-s');
$filename   = "backup_{$db_name}_{$timestamp}.sql";
$filepath   = BACKUP_DIR . '/' . $filename;
$filepathGz = $filepath . '.gz';

// Monta o comando mysqldump
$cmd = sprintf(
    'mysqldump -h %s -u %s -p%s --single-transaction --routines --triggers %s 2>&1',
    escapeshellarg($db_host),
    escapeshellarg($db_user),
    escapeshellarg($db_pass),
    escapeshellarg($db_name)
);

// Executa e captura output
$output = shell_exec($cmd);

// Valida se o dump é válido (deve conter CREATE TABLE ou INSERT)
if (!$output || (stripos($output, 'error') !== false && stripos($output, 'CREATE TABLE') === false)) {
    $erro = "ERRO no backup em {$timestamp}: " . substr($output ?? 'sem output', 0, 300);
    file_put_contents(BACKUP_DIR . '/backup.log', $erro . "\n", FILE_APPEND);
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => $erro]);
    }
    exit(1);
}

// Salva o .sql
file_put_contents($filepath, $output);

// Compacta com gzip se disponível
$savedFile = $filepath;
if (function_exists('gzopen')) {
    $gz = gzopen($filepathGz, 'wb9');
    if ($gz) {
        gzwrite($gz, $output);
        gzclose($gz);
        unlink($filepath);
        $savedFile = $filepathGz;
    }
}

$tamanho = round(filesize($savedFile) / 1024, 1);

// Log de sucesso
$log = "[{$timestamp}] OK — " . basename($savedFile) . " ({$tamanho} KB)\n";
file_put_contents(BACKUP_DIR . '/backup.log', $log, FILE_APPEND);

// Limpa backups antigos (mantém MAX_BACKUPS)
$arquivos = glob(BACKUP_DIR . '/backup_*.{sql,sql.gz}', GLOB_BRACE) ?: [];
usort($arquivos, fn($a, $b) => filemtime($a) - filemtime($b));
while (count($arquivos) > MAX_BACKUPS) {
    unlink(array_shift($arquivos));
}

if (php_sapi_name() !== 'cli') {
    echo json_encode(['ok' => true, 'arquivo' => basename($savedFile), 'tamanho_kb' => $tamanho]);
}
