-- sql/002_produtos_controle_estoque_categorias_compat.sql
-- Migração compatível com MySQL 5.7 / MariaDB (sem 'ADD COLUMN IF NOT EXISTS')
-- Execute este script no seu banco. Se a execução falhar por "Duplicate column name",
-- significa que a coluna já existe e você pode ignorar esse erro (rodar apenas uma vez).

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- 1) Alterar tabela produtos: adicionar controla_estoque e estoque_minimo
ALTER TABLE produtos
  ADD COLUMN controla_estoque TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN estoque_minimo DECIMAL(10,3) NULL DEFAULT NULL;

-- 2) Criar tabela categorias_produtos
CREATE TABLE IF NOT EXISTS categorias_produtos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(80) NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categoria_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Popular categorias a partir de produtos.categoria (valores não vazios)
INSERT IGNORE INTO categorias_produtos (nome, ativo)
SELECT DISTINCT TRIM(categoria) AS nome, 1
FROM produtos
WHERE categoria IS NOT NULL AND TRIM(categoria) <> '';

-- Garantir categoria 'Geral' existe
INSERT IGNORE INTO categorias_produtos (nome, ativo) VALUES ('Geral', 1);
