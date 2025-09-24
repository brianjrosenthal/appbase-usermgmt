<?php
// Email functionality for RAG application
require_once __DIR__ . '/config.php';

function send_email_with_error(string $to, string $subject, string $htmlBody, string $toName = '', string &$errorMessage = ''): bool {
    // Check if SMTP is configured
    if (!defined('SMTP_HOST') || SMTP_HOST === '' || 
        !defined('SMTP_USER') || SMTP_USER === '' || 
        !defined('SMTP_PASS') || SMTP_PASS === '') {
        $errorMessage = 'SMTP not configured - missing SMTP_HOST, SMTP_USER, or SMTP_PASS';
        error_log('SMTP not configured - email not sent to: ' . $to);
        return false;
    }

    try {
        // Helper function to check SMTP response codes and capture raw responses
        $checkResponse = function($smtp, $expectedCodes, $command) use (&$errorMessage) {
            $response = fgets($smtp, 512);
            $code = (int)substr($response, 0, 3);
            if (!in_array($code, $expectedCodes)) {
                $errorMessage = "SMTP $command failed: " . trim($response);
                error_log($errorMessage);
                throw new Exception($errorMessage);
            }
            return $response;
        };

        // Create SMTP connection (SSL or plain)
        $context = stream_context_create();
        if (defined('SMTP_SECURE') && SMTP_SECURE === 'ssl') {
            // SSL connection from the start
            $smtp = stream_socket_client('ssl://' . SMTP_HOST . ':' . SMTP_PORT, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
        } else {
            // Plain connection (may upgrade to TLS later)
            $smtp = fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 30);
        }
        
        if (!$smtp) {
            $errorMessage = "SMTP connection failed: $errstr ($errno)";
            error_log($errorMessage);
            return false;
        }

        // Read initial response (220)
        $checkResponse($smtp, [220], 'connection');

        // EHLO
        fputs($smtp, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
        $checkResponse($smtp, [250], 'EHLO');

        // STARTTLS if using TLS
        if (defined('SMTP_SECURE') && SMTP_SECURE === 'tls') {
            fputs($smtp, "STARTTLS\r\n");
            $checkResponse($smtp, [220], 'STARTTLS');
            stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            
            // EHLO again after STARTTLS
            fputs($smtp, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
            $checkResponse($smtp, [250], 'EHLO after STARTTLS');
        }

        // AUTH LOGIN
        fputs($smtp, "AUTH LOGIN\r\n");
        $checkResponse($smtp, [334], 'AUTH LOGIN');
        fputs($smtp, base64_encode(SMTP_USER) . "\r\n");
        $checkResponse($smtp, [334], 'AUTH username');
        fputs($smtp, base64_encode(SMTP_PASS) . "\r\n");
        $checkResponse($smtp, [235], 'AUTH password');

        // MAIL FROM
        fputs($smtp, "MAIL FROM: <" . SMTP_FROM_EMAIL . ">\r\n");
        $checkResponse($smtp, [250], 'MAIL FROM');

        // RCPT TO
        fputs($smtp, "RCPT TO: <$to>\r\n");
        $checkResponse($smtp, [250], 'RCPT TO');

        // DATA
        fputs($smtp, "DATA\r\n");
        $checkResponse($smtp, [354], 'DATA');

        // Headers and body
        $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'RAG Knowledge Base';
        $headers = "From: $fromName <" . SMTP_FROM_EMAIL . ">\r\n";
        $headers .= "To: " . ($toName ? "$toName <$to>" : $to) . "\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "\r\n";

        fputs($smtp, $headers . $htmlBody . "\r\n.\r\n");
        $checkResponse($smtp, [250], 'message data');

        // QUIT
        fputs($smtp, "QUIT\r\n");
        $checkResponse($smtp, [221], 'QUIT');

        fclose($smtp);
        return true;

    } catch (Exception $e) {
        if (isset($smtp) && is_resource($smtp)) {
            fclose($smtp);
        }
        if (!$errorMessage) {
            $errorMessage = $e->getMessage();
        }
        error_log('Email sending failed: ' . $e->getMessage());
        return false;
    }
}

function send_email(string $to, string $subject, string $htmlBody, string $toName = ''): bool {
    // Check if SMTP is configured
    if (!defined('SMTP_HOST') || SMTP_HOST === '' || 
        !defined('SMTP_USER') || SMTP_USER === '' || 
        !defined('SMTP_PASS') || SMTP_PASS === '') {
        error_log('SMTP not configured - email not sent to: ' . $to);
        return false;
    }

    try {
        // Helper function to check SMTP response codes
        $checkResponse = function($smtp, $expectedCodes, $command) {
            $response = fgets($smtp, 512);
            $code = (int)substr($response, 0, 3);
            if (!in_array($code, $expectedCodes)) {
                $error = "SMTP $command failed: $response";
                error_log($error);
                throw new Exception($error);
            }
            return $response;
        };

        // Create a simple SMTP connection
        $smtp = fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 30);
        if (!$smtp) {
            error_log("SMTP connection failed: $errstr ($errno)");
            return false;
        }

        // Read initial response (220)
        $checkResponse($smtp, [220], 'connection');

        // EHLO
        fputs($smtp, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
        $checkResponse($smtp, [250], 'EHLO');

        // STARTTLS if using TLS
        if (defined('SMTP_SECURE') && SMTP_SECURE === 'tls') {
            fputs($smtp, "STARTTLS\r\n");
            $checkResponse($smtp, [220], 'STARTTLS');
            stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            
            // EHLO again after STARTTLS
            fputs($smtp, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
            $checkResponse($smtp, [250], 'EHLO after STARTTLS');
        }

        // AUTH LOGIN
        fputs($smtp, "AUTH LOGIN\r\n");
        $checkResponse($smtp, [334], 'AUTH LOGIN');
        fputs($smtp, base64_encode(SMTP_USER) . "\r\n");
        $checkResponse($smtp, [334], 'AUTH username');
        fputs($smtp, base64_encode(SMTP_PASS) . "\r\n");
        $checkResponse($smtp, [235], 'AUTH password');

        // MAIL FROM
        fputs($smtp, "MAIL FROM: <" . SMTP_FROM_EMAIL . ">\r\n");
        $checkResponse($smtp, [250], 'MAIL FROM');

        // RCPT TO
        fputs($smtp, "RCPT TO: <$to>\r\n");
        $checkResponse($smtp, [250], 'RCPT TO');

        // DATA
        fputs($smtp, "DATA\r\n");
        $checkResponse($smtp, [354], 'DATA');

        // Headers and body
        $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'RAG Knowledge Base';
        $headers = "From: $fromName <" . SMTP_FROM_EMAIL . ">\r\n";
        $headers .= "To: " . ($toName ? "$toName <$to>" : $to) . "\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "\r\n";

        fputs($smtp, $headers . $htmlBody . "\r\n.\r\n");
        $checkResponse($smtp, [250], 'message data');

        // QUIT
        fputs($smtp, "QUIT\r\n");
        $checkResponse($smtp, [221], 'QUIT');

        fclose($smtp);
        return true;

    } catch (Exception $e) {
        if (isset($smtp) && is_resource($smtp)) {
            fclose($smtp);
        }
        error_log('Email sending failed: ' . $e->getMessage());
        return false;
    }
}

function send_verification_email(string $email, string $token, string $firstName = ''): bool {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $verifyUrl = $scheme . '://' . $host . '/verify_email.php?token=' . urlencode($token);
    
    $siteTitle = Settings::siteTitle();
    $name = $firstName ? htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') : htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    
    $html = '<p>Hello ' . $name . ',</p>'
          . '<p>Please verify your email to activate your account for ' . htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') . '.</p>'
          . '<p><a href="' . htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') . '</a></p>'
          . '<p>After verifying, you will be prompted to set your password.</p>';
    
    return send_email($email, 'Verify your ' . $siteTitle . ' account', $html, $name);
}

function send_password_reset_email(string $email, string $token, string $firstName = ''): bool {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $resetUrl = $scheme . '://' . $host . '/reset_password.php?token=' . urlencode($token);
    
    $siteTitle = Settings::siteTitle();
    $name = $firstName ? htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') : htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    
    $html = '<p>Hello ' . $name . ',</p>'
          . '<p>You requested a password reset for your ' . htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') . ' account.</p>'
          . '<p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '</a></p>'
          . '<p>This link will expire in 30 minutes. If you did not request this reset, you can safely ignore this email.</p>';
    
    return send_email($email, 'Reset your ' . $siteTitle . ' password', $html, $name);
}
