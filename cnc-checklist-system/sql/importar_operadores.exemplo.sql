-- Exemplo de importação em massa de operadores (dados fictícios).
-- matricula = identificador único do colaborador (usado como login, sem e-mail).
--
-- Como rodar: phpMyAdmin -> banco do sistema -> aba SQL -> colar este arquivo -> Executar.
-- Seguro rodar de novo: atualiza nome/setor/role se a matrícula já existir, mas nunca mexe
-- em senha/primeiro_login/ativo de quem já existe — ninguém que já trocou a senha é afetado.
--
-- Senha padrão de todos: Aero@2026 (hash bcrypt abaixo), troca obrigatória no primeiro login.
-- Adapte os valores abaixo pro seu quadro de colaboradores real antes de rodar em produção.

SET @senha_padrao_hash = '$2b$10$XJNu0AyZJPjW0vj2dT2T9eb41Mv7Lj.050GTyWv2mDsIbFethMLT6';

INSERT INTO usuarios (matricula, nome, senha_hash, role, setor, ativo, primeiro_login, created_at) VALUES
('1001', 'Ana Souza Ferreira', @senha_padrao_hash, 'operador', 'Centro de Usinagem', 1, 1, NOW()),
('1002', 'Bruno Carvalho Lima', @senha_padrao_hash, 'operador', 'Centro de Usinagem', 1, 1, NOW()),
('1003', 'Carla Mendes Rocha', @senha_padrao_hash, 'operador', 'Torno CNC', 1, 1, NOW()),
('1004', 'Diego Alves Pereira', @senha_padrao_hash, 'operador', 'Torno CNC', 1, 1, NOW()),
('1005', 'Elaine Ribeiro Santos', @senha_padrao_hash, 'operador', 'Ajustagem', 1, 1, NOW())
ON DUPLICATE KEY UPDATE nome = VALUES(nome), setor = VALUES(setor), role = VALUES(role);
