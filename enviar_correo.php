<?php
/**
 * Envío de correo por SMTP autenticado con STARTTLS, sin librerías externas.
 * Requiere la extensión openssl (habilitada en este XAMPP).
 *
 * enviar_correo_smtp($cfg, $to, $subject, $body, $replyTo, $replyName, $error): bool
 *   $cfg     = arreglo devuelto por config_correo.php
 *   $error   = (por referencia) detalle técnico si falla (para error_log, no para el usuario)
 */
function enviar_correo_smtp(array $cfg, string $to, string $subject, string $body, string $replyTo = '', string $replyName = '', ?string &$error = null): bool
{
    $host      = $cfg['smtp_host']  ?? 'smtp.gmail.com';
    $port      = (int)($cfg['smtp_port'] ?? 587);
    $user      = $cfg['smtp_user']  ?? '';
    $pass      = $cfg['smtp_pass']  ?? '';
    $fromEmail = $cfg['from_email'] ?? $user;
    $fromName  = $cfg['from_name']  ?? 'EcoMadelleine';

    $fp = @stream_socket_client("tcp://$host:$port", $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
    if (!$fp) { $error = "No se pudo conectar a $host:$port ($errstr)"; return false; }
    stream_set_timeout($fp, 20);

    $read = function () use ($fp) {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break; // última línea del bloque
        }
        return $data;
    };
    $cmd  = function ($c) use ($fp, $read) { fwrite($fp, $c . "\r\n"); return $read(); };
    $code = fn($r) => (int)substr((string)$r, 0, 3);

    $r = $read();                if ($code($r) !== 220) { $error = 'Saludo SMTP: ' . trim((string)$r); fclose($fp); return false; }
    $r = $cmd('EHLO localhost'); if ($code($r) !== 250) { $error = 'EHLO: ' . trim((string)$r); fclose($fp); return false; }
    $r = $cmd('STARTTLS');       if ($code($r) !== 220) { $error = 'STARTTLS: ' . trim((string)$r); fclose($fp); return false; }

    $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
    if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
        $crypto |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
    }
    if (!@stream_socket_enable_crypto($fp, true, $crypto)) { $error = 'No se pudo iniciar TLS'; fclose($fp); return false; }

    $r = $cmd('EHLO localhost');        if ($code($r) !== 250) { $error = 'EHLO TLS: ' . trim((string)$r); fclose($fp); return false; }
    $r = $cmd('AUTH LOGIN');            if ($code($r) !== 334) { $error = 'AUTH LOGIN: ' . trim((string)$r); fclose($fp); return false; }
    $r = $cmd(base64_encode($user));    if ($code($r) !== 334) { $error = 'Usuario rechazado: ' . trim((string)$r); fclose($fp); return false; }
    $r = $cmd(base64_encode($pass));    if ($code($r) !== 235) { $error = 'Autenticación fallida (revisa la App Password): ' . trim((string)$r); fclose($fp); return false; }

    $r = $cmd("MAIL FROM:<$fromEmail>"); if ($code($r) !== 250) { $error = 'MAIL FROM: ' . trim((string)$r); fclose($fp); return false; }
    $r = $cmd("RCPT TO:<$to>");          if ($code($r) !== 250 && $code($r) !== 251) { $error = 'RCPT TO: ' . trim((string)$r); fclose($fp); return false; }
    $r = $cmd('DATA');                   if ($code($r) !== 354) { $error = 'DATA: ' . trim((string)$r); fclose($fp); return false; }

    /* Cabeceras */
    $encSubject  = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $headers  = "From: $encFromName <$fromEmail>\r\n";
    $headers .= "To: <$to>\r\n";
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $encReply = $replyName !== '' ? '=?UTF-8?B?' . base64_encode($replyName) . '?= ' : '';
        $headers .= "Reply-To: {$encReply}<$replyTo>\r\n";
    }
    $headers .= 'Subject: ' . $encSubject . "\r\n";
    $headers .= 'Date: ' . date('r') . "\r\n";
    $headers .= 'Message-ID: <' . bin2hex(random_bytes(8)) . '@ecomadelleine>' . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";

    /* Cuerpo: normaliza a CRLF y aplica dot-stuffing */
    $body = str_replace(["\r\n", "\r", "\n"], "\n", $body);
    $body = str_replace("\n", "\r\n", $body);
    $body = preg_replace('/^\./m', '..', $body);

    fwrite($fp, $headers . "\r\n" . $body . "\r\n.\r\n");
    $r = $read(); if ($code($r) !== 250) { $error = 'Envío rechazado: ' . trim((string)$r); fclose($fp); return false; }

    fwrite($fp, "QUIT\r\n");
    fclose($fp);
    return true;
}
