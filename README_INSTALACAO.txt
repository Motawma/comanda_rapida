COMANDA RÁPIDA — MÓDULO PRODUTOS + ESTOQUE (DROP-IN)

Objetivo
- Tela para cadastrar/editar produtos
- Tela para movimentar estoque e ver histórico
- APIs para CRUD de produtos e movimentos de estoque

IMPORTANTE
- Este pacote NÃO altera arquivos existentes do seu projeto.
- Você só vai copiar os arquivos para dentro do seu projeto e depois linkar as páginas no menu.

1) Copiar arquivos
Copie as pastas/arquivos deste ZIP para a raiz do seu projeto (onde ficam caixa.php e funcoes.php), mantendo a estrutura:
- produtos.php (novo)
- estoque.php (novo)
- api/*.php (novos)
- sql/001_produtos_estoque.sql (novo)

2) Rodar o SQL
No seu MySQL (phpMyAdmin ou CLI), execute o arquivo:
  sql/001_produtos_estoque.sql

Ele cria as tabelas:
- categorias_produtos
- produtos
- estoque_saldo
- estoque_movimentos

3) Ajustar conexão
As APIs e páginas fazem:
  require_once __DIR__.'/funcoes.php' (páginas na raiz)
  require_once __DIR__.'/../funcoes.php' (APIs)

Se o seu projeto usa outro arquivo de conexão, altere apenas essas linhas.

4) Linkar as páginas
Depois de copiar, crie links no seu menu:
- Produtos: /produtos.php
- Estoque: /estoque.php

5) Integração com pedidos (opcional, fase 2)
Este módulo já deixa pronto o caminho para baixar estoque por venda via movimentos.
Você pode integrar depois chamando a API api/estoque_movimentar.php ao adicionar/remover itens do pedido.

Padrão de resposta das APIs
Todas retornam JSON: { ok: true/false, ... }

