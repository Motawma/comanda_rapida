-- db_migration.sql
-- Migration 1: timestamps para status
ALTER TABLE pedidos
  ADD COLUMN em_preparo_at DATETIME NULL AFTER status,
  ADD COLUMN pronto_at DATETIME NULL AFTER em_preparo_at,
  ADD COLUMN pago_at DATETIME NULL AFTER pronto_at;

-- Migration 2: campos de cancelamento
ALTER TABLE pedidos
  ADD COLUMN cancel_reason VARCHAR(255) NULL AFTER pago_at,
  ADD COLUMN canceled_at DATETIME NULL AFTER cancel_reason;
