<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

// Nome do sistema
define('APP_NAME', 'AeroCheck');
define('APP_VERSION', '1.0.0');

// Caminho base da aplicação a partir da raiz do site.
// Se você copiar esta pasta para C:\xampp\htdocs\aerocheck, deixe '/aerocheck'.
// Se colocar o conteúdo direto em C:\xampp\htdocs (raiz do XAMPP), use ''.
define('APP_BASE_URL', '/aerocheck');

// Dados de conexão com o banco de dados MySQL/MariaDB do XAMPP
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'aerocheck_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Senha padrão para novos usuários e resets (troca obrigatória no 1º login)
define('DEFAULT_PASSWORD', 'Aero@2026');

// Tempo de inatividade (sem cliques/toques/teclas) até deslogar automaticamente.
// Curto de propósito: os dispositivos são compartilhados no chão de fábrica, então o risco
// de um operador ficar logado na conta de outro precisa ficar bem limitado no tempo.
define('SESSAO_IDLE_SEGUNDOS', 10 * 60); // 10 minutos

// URL pública completa (com http/https, host e porta) usada nos links dos e-mails —
// diferente de APP_BASE_URL, que é só o caminho. Ajuste se IP/porta do servidor mudar.
// Obs.: só abre para quem estiver na rede local da fábrica (ou VPN até lá).
define('APP_URL_PUBLICA', 'http://192.168.1.50:8080');

// E-mail (SMTP) — usado para notificar a gestão quando um checklist vem Não Conforme, e
// para os alertas de "não confirmado" (veja scripts/verificar_alertas.php). Deixe
// SMTP_HOST vazio para desligar o envio de e-mails sem quebrar o resto do sistema.
define('SMTP_HOST', 'smtp.suaempresa.com.br');
define('SMTP_PORT', 587);           // 587 = STARTTLS ('tls'), 465 = SSL implícito ('ssl')
define('SMTP_SECURE', 'tls');       // 'tls' | 'ssl' | ''
define('SMTP_USER', 'sistema@suaempresa.com.br');
define('SMTP_PASS', '');            // preencher no servidor — nunca commitar a senha real
define('SMTP_FROM_EMAIL', 'sistema@suaempresa.com.br');
define('SMTP_FROM_NAME', 'AeroCheck');

// Quanto tempo uma não conformidade pode ficar sem alguém marcar "Visto" antes do
// script de alerta (scripts/verificar_alertas.php) disparar um e-mail de cobrança.
// O e-mail só dispara UMA vez por checklist, não importa de quanto em quanto tempo o
// script roda (veja a tarefa agendada no README, seção 9.2).
define('ALERTA_ESCALACAO_MINUTOS', 5 * 60); // 5 horas

// Deixe false em produção (chão de fábrica)
define('APP_DEBUG', false);

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

define('BASE_PATH', dirname(__DIR__));

function base_url(string $path = ''): string
{
    return rtrim(APP_BASE_URL, '/') . '/' . ltrim($path, '/');
}
