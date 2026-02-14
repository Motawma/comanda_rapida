COMANDA_RAPIDA (MVP) - Instruções rápidas

1) Coloque a pasta 'comanda_rapida' dentro do htdocs do XAMPP:
   C:\xampp\htdocs\comanda_rapida\

2) Inicie Apache + MySQL no XAMPP.

3) Importe o banco:
   - Abra http://localhost/phpmyadmin
   - Importar -> selecione db.sql -> Executar

4) Ajuste config.php (host, user, pass) se necessário.

5) Impressão térmica (ESC/POS):
   - Na pasta do projeto, instale Composer (se ainda não tiver).
   - No terminal, dentro de C:\xampp\htdocs\comanda_rapida\ rode:
       composer require mike42/escpos-php
   - Configure printer_config.php:
       - tipo = network (recomendado)
       - ip = IP fixo da impressora (porta 9100 normalmente)
       - OU tipo = windows + share_name (impressora compartilhada)

6) Acesso:
   - No PC: http://localhost/comanda_rapida/index.php
   - No celular (mesmo Wi-Fi): descubra o IPv4 do PC (ipconfig) e abra:
       http://SEU_IP/comanda_rapida/index.php

7) Reimpressão:
   - POST JSON em /comanda_rapida/api/reimprimir.php
     { "pedido_id": 123 }

Observação:
- Se a impressão falhar, o pedido fica com impresso=0 e você pode reimprimir.
