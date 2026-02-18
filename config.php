<?php
// config.php
// Ajuste as credenciais do MySQL/XAMPP aqui

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'comanda_rapida');
define('DB_USER', 'root');
define('DB_PASS', ''); // senha padrão do XAMPP é vazia

// Caminho base (opcional)
define('BASE_PATH', __DIR__);

// ── Timezone do sistema (horário de Brasília) ──
// Mude aqui se o estabelecimento estiver em outro fuso
date_default_timezone_set('America/Sao_Paulo');
