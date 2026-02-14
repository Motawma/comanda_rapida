-- db_caixa_sessao_migration.sql
-- Cria tabela de sessões de caixa e adiciona coluna em pedidos

CREATE TABLE IF NOT EXISTS caixa_sessoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  closed_at DATETIME NULL,
  opening_cash DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  closing_cash DECIMAL(10,2) NULL,
  total_pago DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total_cancelado DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  obs VARCHAR(255) NULL,
  user_id INT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Adiciona coluna caixa_sessao_id em pedidos, se ainda não existir
ALTER TABLE pedidos
  ADD COLUMN IF NOT EXISTS caixa_sessao_id INT NULL;

-- Nota: se seu MySQL não suportar ADD COLUMN IF NOT EXISTS, execute manualmente apenas se a coluna não existir.
