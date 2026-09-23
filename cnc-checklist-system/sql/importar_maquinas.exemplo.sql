-- Exemplo de importação em massa de máquinas (dados fictícios).
--
-- codigo             = número curto usado no chão de fábrica (o que aparece no formulário)
-- numero_patrimonio  = número/tag longo de identificação usado pela manutenção
--
-- Como rodar: phpMyAdmin -> banco do sistema -> aba "SQL" -> colar este arquivo -> Executar.
-- Adapte os valores abaixo pro seu parque de máquinas real antes de rodar em produção.

INSERT INTO maquinas
    (codigo, nome, tipo, modelo, fabricante, numero_patrimonio, peca_produzida, setor, criticidade, horas_operacao, status_operacional, ativo, created_at)
VALUES
    ('01', 'Torno CNC 01', 'Torno CNC', 'Quick Turn 200', 'MAZAK', 'PAT-0001', 'Eixo de comando', 'Galpão Principal', 'alta', 0, 'operando', 1, NOW()),
    ('02', 'Torno CNC 02', 'Torno CNC', 'Quick Turn 250', 'MAZAK', 'PAT-0002', 'Flange de fixação', 'Galpão Principal', 'alta', 0, 'operando', 1, NOW()),
    ('FCNC 01', 'Centro de Usinagem 01', 'Centro de Usinagem', 'VF-2', 'HAAS', 'PAT-0101', 'Suporte estrutural', 'Galpão Lateral', 'media', 0, 'operando', 1, NOW()),
    ('FCNC 02', 'Centro de Usinagem 02', 'Centro de Usinagem', 'VF-3', 'HAAS', 'PAT-0102', 'Bracket de fixação', 'Galpão Lateral', 'media', 0, 'operando', 1, NOW()),
    ('FCNC 03', 'Centro de Usinagem 03', 'Centro de Usinagem', 'VF-2SS', 'HAAS', 'PAT-0103', 'Peça de precisão', 'Galpão Lateral', 'critica', 0, 'operando', 1, NOW());
