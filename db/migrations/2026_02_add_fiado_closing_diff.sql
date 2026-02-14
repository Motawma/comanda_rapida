-- Migration: 2026-02-13 - Add fiado and caixa closing fields

-- 1) Add fiado_at to pedidos if not exists
ALTER TABLE pedidos
  ADD COLUMN IF NOT EXISTS fiado_at DATETIME NULL;

-- 2) Ensure status enum contains 'FIADO'
-- Modify the status column to include FIADO. Update the list if your schema has other values.
ALTER TABLE pedidos
  MODIFY COLUMN status ENUM('PENDENTE','EM_PREPARO','PRONTO','FIADO','PAGO','CANCELADO') NOT NULL DEFAULT 'PENDENTE';

-- 3) Add closing_cash, total_pago, diferenca to caixa_sessoes if not exists
ALTER TABLE caixa_sessoes
  ADD COLUMN IF NOT EXISTS closing_cash DECIMAL(10,2) NULL,
  ADD COLUMN IF NOT EXISTS total_pago DECIMAL(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS diferenca DECIMAL(10,2) NOT NULL DEFAULT 0;

-- 4) Recommended index to speed up mescla / listagens por mesa+sessao+status
CREATE INDEX IF NOT EXISTS idx_pedidos_mesa_sessao_status_id ON pedidos (mesa, caixa_sessao_id, status, id);

-- Notes:
-- - This migration uses CREATE/ADD ... IF NOT EXISTS which requires MySQL 8.0.16+.
-- - If your MySQL version does not support IF NOT EXISTS for ADD COLUMN or CREATE INDEX,
--   run the ALTER TABLE and CREATE INDEX statements manually after verifying the current schema.
-- - Back up your database before running migrations.
