-- AeroCheck — adiciona a seção de checklist de ferramentas (categoria + quantidade padrão).
-- Como rodar: phpMyAdmin -> banco aerocheck_db -> aba SQL -> colar este arquivo -> Executar.
-- Seguro rodar mais de uma vez (ADD COLUMN IF NOT EXISTS; os itens de ferramenta só são
-- inseridos se ainda não existirem, checando pela descrição).

ALTER TABLE checklist_itens
    ADD COLUMN IF NOT EXISTS categoria ENUM('limpeza','ferramenta') NOT NULL DEFAULT 'limpeza' AFTER descricao,
    ADD COLUMN IF NOT EXISTS qtd_padrao INT NULL AFTER categoria;

INSERT INTO checklist_itens (descricao, categoria, qtd_padrao, ordem, ativo)
SELECT * FROM (
    SELECT 'Martelo de Alumínio' AS descricao, 'ferramenta' AS categoria, 1 AS qtd_padrao, 10 AS ordem, 1 AS ativo
    UNION ALL SELECT 'Lima grande', 'ferramenta', 1, 11, 1
    UNION ALL SELECT 'Paquímetro', 'ferramenta', 1, 12, 1
    UNION ALL SELECT 'Calibre de raio', 'ferramenta', 1, 13, 1
    UNION ALL SELECT 'Chave Alen "T" M6', 'ferramenta', 1, 14, 1
    UNION ALL SELECT 'Rasquete (Rebarbador)', 'ferramenta', 1, 15, 1
    UNION ALL SELECT 'Canetão', 'ferramenta', 1, 16, 1
    UNION ALL SELECT 'Chave fenda Grande', 'ferramenta', 1, 17, 1
    UNION ALL SELECT 'Ponteiras', 'ferramenta', 3, 18, 1
    UNION ALL SELECT 'Anel padrão', 'ferramenta', 1, 19, 1
    UNION ALL SELECT 'Alicate de pressão', 'ferramenta', 1, 20, 1
    UNION ALL SELECT 'Chave Alen M12', 'ferramenta', 1, 21, 1
    UNION ALL SELECT 'Chave Alen M10', 'ferramenta', 1, 22, 1
    UNION ALL SELECT 'Chave Alen M8', 'ferramenta', 1, 23, 1
    UNION ALL SELECT 'Chave Alen M6', 'ferramenta', 1, 24, 1
    UNION ALL SELECT 'Chave Alen M4', 'ferramenta', 1, 25, 1
) AS novos
WHERE NOT EXISTS (
    SELECT 1 FROM checklist_itens ci WHERE ci.descricao = novos.descricao AND ci.categoria = 'ferramenta'
);
