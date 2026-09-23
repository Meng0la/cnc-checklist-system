<?php
/**
 * Cliente SMTP mínimo, sem dependências externas (sem Composer/PHPMailer) — de propósito,
 * pro servidor não precisar de internet nem de nada além do PHP puro do XAMPP. Suporta
 * STARTTLS (porta 587) e SSL implícito (porta 465), com AUTH LOGIN.
 */
class MailerException extends Exception
{
}

class Mailer
{
    /**
     * Envia um e-mail HTML. Nunca lança exceção pra fora — se der erro, registra no log
     * de erros do PHP e retorna false, pra nunca quebrar o fluxo principal (ex.: salvar um
     * checklist) por causa de uma falha de e-mail.
     */
    public static function enviar(array $destinatarios, string $assunto, string $corpoHtml): bool
    {
        $destinatarios = array_values(array_unique(array_filter($destinatarios)));
        if (!$destinatarios || !defined('SMTP_HOST') || SMTP_HOST === '') {
            return false;
        }

        try {
            self::enviarSmtp($destinatarios, $assunto, $corpoHtml);
            return true;
        } catch (Throwable $e) {
            error_log('[AeroCheck Mailer] ' . $e->getMessage());
            return false;
        }
    }

    private static function enviarSmtp(array $destinatarios, string $assunto, string $corpoHtml): void
    {
        $enderecoConexao = (SMTP_SECURE === 'ssl' ? 'ssl://' : '') . SMTP_HOST . ':' . SMTP_PORT;
        $socket = @stream_socket_client($enderecoConexao, $errno, $errstr, 15);
        if (!$socket) {
            throw new MailerException("Falha ao conectar em " . SMTP_HOST . ':' . SMTP_PORT . " — $errstr");
        }
        stream_set_timeout($socket, 15);

        self::ler($socket, [220]);
        self::escrever($socket, 'EHLO aerocheck.local');
        self::ler($socket, [250]);

        if (SMTP_SECURE === 'tls') {
            self::escrever($socket, 'STARTTLS');
            self::ler($socket, [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new MailerException('Falha ao iniciar TLS com o servidor SMTP.');
            }
            self::escrever($socket, 'EHLO aerocheck.local');
            self::ler($socket, [250]);
        }

        if (SMTP_USER !== '') {
            if (SMTP_PASS === '') {
                throw new MailerException('SMTP_USER está definido mas SMTP_PASS está vazio em config/config.php — preencha a senha da caixa ' . SMTP_USER . '.');
            }
            self::escrever($socket, 'AUTH LOGIN');
            self::ler($socket, [334]);
            self::escrever($socket, base64_encode(SMTP_USER));
            self::ler($socket, [334]);
            self::escrever($socket, base64_encode(SMTP_PASS));
            self::ler($socket, [235]);
        }

        self::escrever($socket, 'MAIL FROM:<' . SMTP_FROM_EMAIL . '>');
        self::ler($socket, [250]);

        foreach ($destinatarios as $email) {
            self::escrever($socket, 'RCPT TO:<' . $email . '>');
            self::ler($socket, [250, 251]);
        }

        self::escrever($socket, 'DATA');
        self::ler($socket, [354]);

        $assuntoCodificado = '=?UTF-8?B?' . base64_encode($assunto) . '?=';
        $nomeRemetenteCodificado = '=?UTF-8?B?' . base64_encode(SMTP_FROM_NAME) . '?=';

        $headers = [
            'From: ' . $nomeRemetenteCodificado . ' <' . SMTP_FROM_EMAIL . '>',
            'To: ' . implode(', ', $destinatarios),
            'Subject: ' . $assuntoCodificado,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Date: ' . date('r'),
        ];

        // Escapa linhas que comecem com "." sozinho (marcaria o fim da mensagem no protocolo SMTP)
        $corpoEscapado = preg_replace('/^\./m', '..', $corpoHtml);
        $mensagem = implode("\r\n", $headers) . "\r\n\r\n" . $corpoEscapado . "\r\n.";

        self::escrever($socket, $mensagem);
        self::ler($socket, [250]);

        self::escrever($socket, 'QUIT');
        fclose($socket);
    }

    private static function escrever($socket, string $linha): void
    {
        fwrite($socket, $linha . "\r\n");
    }

    private static function ler($socket, array $codigosEsperados): string
    {
        $resposta = '';
        while (($linha = fgets($socket, 515)) !== false) {
            $resposta .= $linha;
            if (isset($linha[3]) && $linha[3] === ' ') {
                break;
            }
        }
        if ($resposta === '') {
            throw new MailerException('Sem resposta do servidor SMTP.');
        }
        $codigo = (int)substr($resposta, 0, 3);
        if (!in_array($codigo, $codigosEsperados, true)) {
            throw new MailerException('Resposta inesperada do SMTP: ' . trim($resposta));
        }
        return $resposta;
    }
}
