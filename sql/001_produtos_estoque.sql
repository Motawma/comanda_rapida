-- COMANDA RÁPIDA — MÓDULO PRODUTOS + ESTOQUE
-- Execute este SQL no banco do projeto.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS categorias_produtos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(80) NOT NULL,
  ordem INT NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categoria_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS produtos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(120) NOT NULL,
  sku VARCHAR(60) NULL,
  categoria_id INT UNSIGNED NULL,
  preco_venda DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  custo DECIMAL(10,2) NULL,
  unidade VARCHAR(10) NOT NULL DEFAULT 'un',
  controla_estoque TINYINT(1) NOT NULL DEFAULT 1,
  estoque_minimo DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_produtos_nome (nome),
  KEY idx_produtos_ativo (ativo),
  KEY idx_produtos_categoria (categoria_id),
  CONSTRAINT fk_produtos_categoria
    FOREIGN KEY (categoria_id) REFERENCES categorias_produtos(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS estoque_saldo (
  produto_id INT UNSIGNED NOT NULL,
  quantidade_atual DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (produto_id),
  CONSTRAINT fk_estoque_saldo_produto
    FOREIGN KEY (produto_id) REFERENCES produtos(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS estoque_movimentos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  produto_id INT UNSIGNED NOT NULL,
  tipo ENUM('ENTRADA','SAIDA','AJUSTE','ESTORNO') NOT NULL,
  quantidade DECIMAL(12,3) NOT NULL,
  origem ENUM('COMPRA','VENDA','INVENTARIO','PERDA','DEVOLUCAO','MANUAL') NOT NULL DEFAULT 'MANUAL',
  referencia_tipo VARCHAR(40) NULL,
  referencia_id BIGINT UNSIGNED NULL,
  observacao VARCHAR(255) NULL,
  user_id INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mov_produto (produto_id),
  KEY idx_mov_created (created_at),
  KEY idx_mov_ref (referencia_tipo, referencia_id),
  CONSTRAINT fk_estoque_mov_produto
    FOREIGN KEY (produto_id) REFERENCES produtos(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Categoria padrão (opcional)
INSERT IGNORE INTO categorias_produtos (id, nome, ordem, ativo) VALUES
(1, 'Geral', 0, 1);
