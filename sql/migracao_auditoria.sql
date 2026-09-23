-- AeroCheck — tabela de auditoria (log de quem criou/editou/alterou o quê e quando).
-- Como rodar: phpMyAdmin -> banco aerocheck_db -> aba SQL -> colar este arquivo -> Executar.
-- Seguro rodar mais de uma vez (CREATE TABLE IF NOT EXISTS).

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
