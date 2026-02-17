-- sql/001_estoque.sql
-- Cria tabelas de estoque: saldo e movimentos

CREATE TABLE IF NOT EXISTS estoque_saldo (
  produto_id INT PRIMARY KEY,
  quantidade_atual DECIMAL(10,3) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_estoque_saldo_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS estoque_movimentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  produto_id INT NOT NULL,
  tipo ENUM('ENTRADA','SAIDA','AJUSTE','ESTORNO') NOT NULL,
  origem VARCHAR(50) NULL,
  quantidade DECIMAL(10,3) NOT NULL,
  observacao VARCHAR(255) NULL,
  referencia_tipo VARCHAR(50) NULL,
  referencia_id INT NULL,
  user_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_estoque_mov_produto (produto_id),
  INDEX idx_estoque_mov_created_at (created_at),
  CONSTRAINT fk_estoque_mov_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
