<?php
// printer_config.php
// Exemplo de configuração. Edite conforme seu ambiente.
// Agora suporta impressoras separadas para bar e cozinha.

$printer_config = [
    // Layout / dados do estabelecimento
    'nome_restaurante' => 'Meu Bar e Restaurante',
    'paper' => 58, // 58 ou 80

    // Configuração default (compatibilidade): se você usar apenas uma impressora,
    // mantenha a raiz 'tipo' / 'ip' / 'port' ou use printers->default
    'tipo' => 'network',
    'ip' => '192.168.0.200',
    'port' => 9100,

    // Impressoras por função (opcional)
    'printers' => [
        'bar' => [
            'tipo' => 'network',
            'ip' => '192.168.0.201',
            'port' => 9100,
            // 'share_name' => '\\PC-NOME\\EPSON_BAR' // exemplo para windows
        ],
        'cozinha' => [
            'tipo' => 'network',
            'ip' => '192.168.0.200',
            'port' => 9100,
        ],
        // 'default' pode ser usado como fallback
        'default' => [
            'tipo' => 'network',
            'ip' => '192.168.0.200',
            'port' => 9100,
        ]
    ]
];

// Observação: mantenha compatibilidade com versões antigas que usavam apenas as chaves raiz.
// O código de impressão tenta usar printers['bar']/['cozinha'] se existirem, senão cai para raiz.
