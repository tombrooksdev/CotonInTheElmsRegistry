<?php
declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';

function cfg(): array {
    static $c; return $c ??= require __DIR__ . '/config.php';
}

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $d = cfg()['db'];
        $pdo = new PDO("mysql:host={$d['host']};dbname={$d['name']};charset=utf8mb4", $d['user'], $d['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function http_post(string $url, string $body, array $headers): array {
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers),
        'content' => $body,
        'ignore_errors' => true,
        'timeout' => 15,
    ]]);
    $result = @file_get_contents($url, false, $ctx);
    if ($result === false) {
        throw new RuntimeException("Request to {$url} failed.");
    }
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) $status = (int)$m[1];
    }
    return ['status' => $status, 'body' => $result];
}

function graph_access_token(): string {
    $g = cfg()['graph'];
    $url = "https://login.microsoftonline.com/{$g['tenant_id']}/oauth2/v2.0/token";
    $body = http_build_query([
        'client_id' => $g['client_id'],
        'client_secret' => $g['client_secret'],
        'scope' => 'https://graph.microsoft.com/.default',
        'grant_type' => 'client_credentials',
    ]);
    $res = http_post($url, $body, ['Content-Type: application/x-www-form-urlencoded']);
    $json = json_decode($res['body'], true);
    if ($res['status'] !== 200 || !isset($json['access_token'])) {
        throw new RuntimeException('Microsoft Graph auth failed: ' . ($json['error_description'] ?? $res['body']));
    }
    return $json['access_token'];
}

function send_mail(string $to, string $subject, string $html, array $inlineImages = []): void {
    $g = cfg()['graph'];
    $token = graph_access_token();
    $message = [
        'subject' => $subject,
        'body' => ['contentType' => 'HTML', 'content' => $html],
        'toRecipients' => [['emailAddress' => ['address' => $to]]],
    ];
    if ($inlineImages) {
        $message['attachments'] = [];
        foreach ($inlineImages as $contentId => $path) {
            $message['attachments'][] = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => basename($path),
                'contentType' => mime_content_type($path) ?: 'application/octet-stream',
                'contentBytes' => base64_encode((string)file_get_contents($path)),
                'contentId' => (string)$contentId,
                'isInline' => true,
            ];
        }
    }
    $payload = json_encode(['message' => $message, 'saveToSentItems' => false]);
    $url = 'https://graph.microsoft.com/v1.0/users/' . rawurlencode($g['sender']) . '/sendMail';
    $res = http_post($url, $payload, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ]);
    if ($res['status'] < 200 || $res['status'] >= 300) {
        throw new RuntimeException("Microsoft Graph sendMail failed ({$res['status']}): {$res['body']}");
    }
}
