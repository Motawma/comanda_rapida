-- sql/002_produtos_controle_estoque_categorias.sql
-- Migração: adiciona controle de estoque e tabela de categorias

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- 1) Alterar tabela produtos: adicionar controla_estoque e estoque_minimo se não existirem
ALTER TABLE produtos
  ADD COLUMN IF NOT EXISTS controla_estoque TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS estoque_minimo DECIMAL(10,3) NULL DEFAULT NULL;

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
-- Insere nomes distintos na tabela categorias_produtos, ignorando duplicatas
INSERT IGNORE INTO categorias_produtos (nome, ativo)
SELECT DISTINCT TRIM(categoria) AS nome, 1
FROM produtos
WHERE categoria IS NOT NULL AND TRIM(categoria) <> '';

-- Garantir categoria 'Geral' existe
INSERT IGNORE INTO categorias_produtos (nome, ativo) VALUES ('Geral', 1);
