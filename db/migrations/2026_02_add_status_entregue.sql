-- Adiciona status ENTREGUE ao fluxo de pedidos
-- PENDENTE → EM_PREPARO → PRONTO → ENTREGUE → PAGO
ALTER TABLE pedidos
  MODIFY COLUMN status ENUM('PENDENTE','EM_PREPARO','PRONTO','ENTREGUE','FIADO','PAGO','CANCELADO') NOT NULL DEFAULT 'PENDENTE';

-- Coluna para registrar quando o pedido foi entregue/retirado da cozinha
ALTER TABLE pedidos
  ADD COLUMN entregue_at DATETIME DEFAULT NULL AFTER pronto_at;
