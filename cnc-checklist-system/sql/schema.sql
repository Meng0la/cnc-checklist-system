-- AeroCheck — esquema do banco de dados
-- Importe este arquivo pelo phpMyAdmin do XAMPP (ou via console mysql) antes de usar o sistema.

CREATE DATABASE IF NOT EXISTS aerocheck_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE aerocheck_db;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    matricula VARCHAR(20) NOT NULL UNIQUE,
    nome VARCHAR(150) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    role ENUM('operador','administrador','supervisor_producao','gerente','manutencao') NOT NULL,
    setor VARCHAR(60) NULL,
    email VARCHAR(150) NULL COMMENT 'opcional; usado para alertas de não conformidade nos perfis de gestão',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    primeiro_login TINYINT(1) NOT NULL DEFAULT 1,
    tentativas_falhas INT NOT NULL DEFAULT 0,
    bloqueado_ate DATETIME NULL,
    ultimo_login DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS maquinas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(30) NOT NULL UNIQUE,
    nome VARCHAR(150) NOT NULL,
    tipo VARCHAR(60) NULL,
    modelo VARCHAR(60) NULL,
    fabricante VARCHAR(60) NULL,
    numero_patrimonio VARCHAR(30) NULL COMMENT 'número/tag longo usado pela manutenção; "codigo" é o número curto usado no chão de fábrica',
    peca_produzida VARCHAR(150) NULL,
    setor VARCHAR(100) NULL,
    criticidade ENUM('baixa','media','alta','critica') NULL,
    horas_operacao DECIMAL(10,1) NOT NULL DEFAULT 0,
    proxima_revisao DATE NULL,
    status_operacional ENUM('operando','manutencao','parada') NOT NULL DEFAULT 'operando',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS maquina_operadores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    maquina_id INT NOT NULL,
    user_id INT NOT NULL,
    turno ENUM('manha','tarde','noite') NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_maquina_operador (maquina_id, user_id),
    CONSTRAINT fk_mo_maquina FOREIGN KEY (maquina_id) REFERENCES maquinas(id) ON DELETE CASCADE,
    CONSTRAINT fk_mo_usuario FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS checklist_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(255) NOT NULL,
    categoria ENUM('limpeza','ferramenta') NOT NULL DEFAULT 'limpeza',
    qtd_padrao INT NULL COMMENT 'quantidade padrão esperada; usado só na categoria ferramenta',
    ordem INT NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS checklists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    maquina_id INT NOT NULL,
    user_id INT NOT NULL,
    tipo_checklist ENUM('inicio','fim') NOT NULL,
    turno ENUM('manha','tarde','noite') NOT NULL,
    data_checklist DATE NOT NULL,
    status_geral ENUM('conforme','nao_conforme') NOT NULL DEFAULT 'conforme',
    visto_por INT NULL COMMENT 'usuário de gestão que confirmou a não conformidade',
    visto_em DATETIME NULL,
    escalonamento_enviado TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'já disparou e-mail de cobrança por falta de confirmação',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ck_maquina FOREIGN KEY (maquina_id) REFERENCES maquinas(id),
    CONSTRAINT fk_ck_usuario FOREIGN KEY (user_id) REFERENCES usuarios(id),
    CONSTRAINT fk_ck_visto_por FOREIGN KEY (visto_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_data (data_checklist),
    INDEX idx_maquina_data (maquina_id, data_checklist),
    INDEX idx_status (status_geral)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS checklist_respostas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    checklist_id INT NOT NULL,
    item_id INT NOT NULL,
    status ENUM('conforme','nao_conforme') NOT NULL,
    observacao TEXT NULL,
    CONSTRAINT fk_cr_checklist FOREIGN KEY (checklist_id) REFERENCES checklists(id) ON DELETE CASCADE,
    CONSTRAINT fk_cr_item FOREIGN KEY (item_id) REFERENCES checklist_itens(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    acao VARCHAR(60) NOT NULL,
    entidade VARCHAR(40) NOT NULL,
    entidade_id INT NULL,
    descricao VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_aud_usuario FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_aud_data (created_at),
    INDEX idx_aud_entidade (entidade, entidade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Itens padrão do checklist de limpeza de CNC (editáveis depois pelo painel de administração)
INSERT INTO checklist_itens (descricao, categoria, qtd_padrao, ordem, ativo) VALUES
('Região da carenagem do eixo X e eixo Z — livre de cavacos', 'limpeza', NULL, 1, 1),
('Plataforma e área de fixação da peça — livre de cavacos e fluido em excesso', 'limpeza', NULL, 2, 1),
('Calhas de drenagem de fluido de corte — desobstruídas', 'limpeza', NULL, 3, 1),
('Recipiente de resíduos metálicos — cavacos depositados corretamente', 'limpeza', NULL, 4, 1),
('Área ao redor do equipamento — limpa e organizada', 'limpeza', NULL, 5, 1);

-- Itens padrão do checklist de ferramentas (jogo de ferramentas que deve acompanhar a máquina)
INSERT INTO checklist_itens (descricao, categoria, qtd_padrao, ordem, ativo) VALUES
('Martelo de Alumínio', 'ferramenta', 1, 10, 1),
('Lima grande', 'ferramenta', 1, 11, 1),
('Paquímetro', 'ferramenta', 1, 12, 1),
('Calibre de raio', 'ferramenta', 1, 13, 1),
('Chave Alen "T" M6', 'ferramenta', 1, 14, 1),
('Rasquete (Rebarbador)', 'ferramenta', 1, 15, 1),
('Canetão', 'ferramenta', 1, 16, 1),
('Chave fenda Grande', 'ferramenta', 1, 17, 1),
('Ponteiras', 'ferramenta', 3, 18, 1),
('Anel padrão', 'ferramenta', 1, 19, 1),
('Alicate de pressão', 'ferramenta', 1, 20, 1),
('Chave Alen M12', 'ferramenta', 1, 21, 1),
('Chave Alen M10', 'ferramenta', 1, 22, 1),
('Chave Alen M8', 'ferramenta', 1, 23, 1),
('Chave Alen M6', 'ferramenta', 1, 24, 1),
('Chave Alen M4', 'ferramenta', 1, 25, 1);

-- Depois de importar este arquivo, acesse install.php no navegador para criar o primeiro
-- usuário administrador (matrícula + senha padrão Aero@2026).
