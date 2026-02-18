-- Migration: 2026-02-17 - Add item_status to itens_pedido
-- Permite controlar o status de cada item individualmente.
-- Novos itens entram como PENDENTE; a cozinha só vê itens PENDENTE/EM_PREPARO.
-- Itens já entregues ficam como ENTREGUE e não reaparecem na cozinha.

-- 1) Adicionar coluna item_status com default ENTREGUE
--    (itens antigos já existentes são considerados "entregues")
ALTER TABLE itens_pedido
  ADD COLUMN IF NOT EXISTS item_status ENUM('PENDENTE','EM_PREPARO','PRONTO','ENTREGUE') NOT NULL DEFAULT 'ENTREGUE';

-- 2) Atualizar itens de pedidos que ainda estão em andamento para refletir o status do pedido
--    Pedidos PENDENTE -> itens ficam PENDENTE
UPDATE itens_pedido ip
  JOIN pedidos p ON p.id = ip.pedido_id
  SET ip.item_status = 'PENDENTE'
  WHERE p.status = 'PENDENTE';

--    Pedidos EM_PREPARO -> itens ficam EM_PREPARO
UPDATE itens_pedido ip
  JOIN pedidos p ON p.id = ip.pedido_id
  SET ip.item_status = 'EM_PREPARO'
  WHERE p.status = 'EM_PREPARO';

--    Pedidos PRONTO -> itens ficam PRONTO
UPDATE itens_pedido ip
  JOIN pedidos p ON p.id = ip.pedido_id
  SET ip.item_status = 'PRONTO'
  WHERE p.status = 'PRONTO';

-- 3) Índice para busca eficiente pela cozinha (itens pendentes/em preparo)
CREATE INDEX IF NOT EXISTS idx_itens_item_status ON itens_pedido (item_status);

-- 4) Adicionar coluna created_at nos itens para saber quando foram inseridos
ALTER TABLE itens_pedido
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
