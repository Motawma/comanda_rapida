-- db.sql
-- Cria banco e tabelas para Comanda Eletrônica (MVP)

CREATE DATABASE IF NOT EXISTS comanda_rapida CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE comanda_rapida;

-- Tabela produtos
CREATE TABLE IF NOT EXISTS produtos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(255) NOT NULL,
  preco DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  categoria VARCHAR(100),
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabela pedidos (atualizada para suportar FIADO, fiado_at e associar sessão de caixa)
CREATE TABLE IF NOT EXISTS pedidos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mesa VARCHAR(50) NOT NULL,
  status ENUM('PENDENTE','EM_PREPARO','PRONTO','FIADO','PAGO','CANCELADO') NOT NULL DEFAULT 'PENDENTE',
  total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  impresso TINYINT(1) NOT NULL DEFAULT 0,
  fiado_at DATETIME NULL,
  caixa_sessao_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Nova tabela para sessões de caixa (fechamento/abertura)
CREATE TABLE IF NOT EXISTS caixa_sessoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  closed_at DATETIME NULL,
  opening_cash DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  closing_cash DECIMAL(10,2) NULL,
  total_pago DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total_cancelado DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  diferenca DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  obs VARCHAR(255) NULL,
  user_id INT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela itens_pedido
CREATE TABLE IF NOT EXISTS itens_pedido (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  produto_id INT NOT NULL,
  quantidade INT NOT NULL DEFAULT 1,
  preco_unit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT fk_itens_pedido_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
  CONSTRAINT fk_itens_pedido_produto FOREIGN KEY (produto_id) REFERENCES produtos(id)
) ENGINE=InnoDB;

-- Inserts de exemplo
INSERT INTO produtos (nome, preco, categoria) VALUES
('Cerveja Long Neck', 8.50, 'Bebidas'),
('Refrigerante Lata', 5.00, 'Bebidas'),
('Água Mineral', 3.00, 'Bebidas'),
('Hambúrguer Clássico', 18.00, 'Pratos'),
('Porção de Batata', 12.00, 'Petiscos');
