<?php
/**
 * send-mail.php — 16vieira.com
 * Secure Brevo mail relay using environment variable BREVO_API_KEY
 */

declare(strict_types=1);

// Função auxiliar de log para Render
function log_event(string $level, string $message, array $context = []): void {
  $timestamp = date('Y-m-d H:i:s');
  $line = sprintf("[%s] [%s] %s %s", $timestamp, strtoupper($level), $message, $context ? json_encode($context) : '');
  error_log($line); // vai aparecer no Render Events
}

// --- CORS ---
$allowedOrigin = 'https://16vieira.com';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Vary: Origin');
header('Access-Control-Allow-Origin: ' . ($origin === $allowedOrigin ? $allowedOrigin : '*'));
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  log_event('warn', 'Rejected request: method not allowed', ['method' => $_SERVER['REQUEST_METHOD']]);
  echo json_encode(['error' => 'Method not allowed']);
  exit;
}

$apiKey = getenv('BREVO_API_KEY');
if (!$apiKey) {
  http_response_code(500);
  log_event('error', 'Missing BREVO_API_KEY environment variable');
  echo json_encode(['error' => 'Server misconfiguration', 'hint' => 'BREVO_API_KEY not set']);
  exit;
}

// Lê JSON
$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '[]', true);

// Campos
$firstName = trim((string)($input['firstName'] ?? ''));
$lastName  = trim((string)($input['lastName'] ?? ''));
$email     = trim((string)($input['email'] ?? ''));
$phone     = trim((string)($input['phone'] ?? ''));
$financing = trim((string)($input['financing'] ?? ''));
$message   = trim((string)($input['message'] ?? ''));

// Validação
$errors = [];
if ($firstName === '') $errors['firstName'] = 'Required';
if ($lastName === '')  $errors['lastName']  = 'Required';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Invalid email';
if ($phone === '') $errors['phone'] = 'Required';

if ($errors) {
  http_response_code(422);
  log_event('warn', 'Validation failed', $errors);
  echo json_encode(['error' => 'Validation failed', 'fields' => $errors]);
  exit;
}

// HTML sanitizado
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$subject = '🏠 URGENT: Property Inquiry - 16 Vieira Dr - ' . $firstName . ' ' . $lastName;

$html = '<p>... (mesmo HTML do seu código anterior) ...</p>';

$brevoData = [
  'sender' => ['name' => $firstName . ' ' . $lastName, 'email' => $email],
  'to' => [
    ['email' => 'gerci.usa@gmail.com', 'name' => 'Gercilaine DeSouza'],
    ['email' => 'luizlz@gmail.com',    'name' => 'Backup Contact'],
  ],
  'replyTo' => ['email' => $email, 'name' => $firstName . ' ' . $lastName],
  'subject' => $subject,
  'htmlContent' => $html,
];

$fixedSenderEmail = getenv('BREVO_SENDER_EMAIL');
$fixedSenderName  = getenv('BREVO_SENDER_NAME') ?: '16Vieira.com';
if ($fixedSenderEmail) {
  $brevoData['sender'] = ['name' => $fixedSenderName, 'email' => $fixedSenderEmail];
}

$ch = curl_init('https://api.brevo.com/v3/smtp/email');
curl_setopt_array($ch, [
  CURLOPT_POST            => true,
  CURLOPT_POSTFIELDS      => json_encode($brevoData, JSON_UNESCAPED_UNICODE),
  CURLOPT_HTTPHEADER      => [
    'accept: application/json',
    'content-type: application/json',
    'api-key: ' . $apiKey,
  ],
  CURLOPT_RETURNTRANSFER  => true,
  CURLOPT_CONNECTTIMEOUT  => 10,
  CURLOPT_TIMEOUT         => 20,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

// Logging detalhado
if ($response === false) {
  http_response_code(502);
  log_event('error', 'cURL failed', ['error' => $curlErr]);
  echo json_encode(['error' => 'Mail provider unreachable', 'details' => $curlErr]);
  exit;
}

if ($httpCode === 201) {
  log_event('info', 'Email sent successfully', ['to' => $brevoData['to'], 'subject' => $subject]);
  echo json_encode(['success' => true]);
} else {
  $parsed = json_decode($response, true);
  log_event('error', 'Email rejected by Brevo', [
    'status'  => $httpCode,
    'response' => $parsed ?: $response,
    'to' => $brevoData['to']
  ]);
  http_response_code(502);
  echo json_encode(['error' => 'Failed to send email', 'status' => $httpCode, 'details' => $parsed ?: $response]);
}
