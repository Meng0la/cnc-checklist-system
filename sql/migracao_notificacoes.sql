-- AeroCheck — suporte a notificação por e-mail e confirmação ("visto") de não conformidades.
-- Como rodar: phpMyAdmin -> banco aerocheck_db -> aba SQL -> colar este arquivo -> Executar.
-- Seguro rodar mais de uma vez (ADD COLUMN IF NOT EXISTS).

ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS email VARCHAR(150) NULL AFTER setor;

ALTER TABLE checklists
    ADD COLUMN IF NOT EXISTS visto_por INT NULL AFTER status_geral,
    ADD COLUMN IF NOT EXISTS visto_em DATETIME NULL AFTER visto_por,
    ADD COLUMN IF NOT EXISTS escalonamento_enviado TINYINT(1) NOT NULL DEFAULT 0 AFTER visto_em;
