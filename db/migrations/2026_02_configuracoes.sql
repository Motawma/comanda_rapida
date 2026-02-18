-- Migration: tabela de configurações do sistema
-- Armazena key/value para configurações gerais (tempos KDS, etc.)

CREATE TABLE IF NOT EXISTS configuracoes (
  chave VARCHAR(100) NOT NULL PRIMARY KEY,
  valor TEXT NOT NULL,
  descricao VARCHAR(255) NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Valores padrão dos tempos do KDS (em minutos)
INSERT INTO configuracoes (chave, valor, descricao) VALUES
  ('kds_warn_minutes',  '8',  'Tempo (min) para alerta amarelo no KDS'),
  ('kds_crit_minutes',  '15', 'Tempo (min) para alerta vermelho piscante no KDS'),
  ('kds_refresh_seconds', '5', 'Intervalo de atualização do KDS (segundos)')
ON DUPLICATE KEY UPDATE chave = chave;
