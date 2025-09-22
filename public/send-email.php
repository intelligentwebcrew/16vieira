<?php
/**
 * send-email.php — 16vieira.com
 * Brevo mail relay seguro com logs para Render
 */

declare(strict_types=1);

// --------- Helpers / logging (vai para Render Events) ---------
function log_event(string $level, string $message, array $ctx = []): void {
  $ts = date('Y-m-d H:i:s');
  // nunca logar segredos; apenas metadados úteis
  $line = sprintf("[%s] [%s] %s", $ts, strtoupper($level), $message);
  if (!empty($ctx)) $line .= ' ' . json_encode($ctx, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  error_log($line);
}
$requestId = bin2hex(random_bytes(6)); // para correlacionar entradas

// --------- CORS / headers ---------
$allowedOrigins = [
  'https://16vieira.com',
  // adicione seus domínios de preview se quiser restringir:
  // 'https://16vieira.onrender.com',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allow = in_array($origin, $allowedOrigins, true) ? $origin : '*';

header('Vary: Origin');
header('Access-Control-Allow-Origin: ' . $allow);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

// Preflight
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// Somente POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  log_event('warn', 'Rejected: method not allowed', ['reqId'=>$requestId,'method'=>$_SERVER['REQUEST_METHOD'] ?? '']);
  echo json_encode(['error' => 'Method not allowed', 'reqId' => $requestId]);
  exit;
}

// --------- Carrega secrets do ambiente ---------
$apiKey = getenv('BREVO_API_KEY') ?: '';
if ($apiKey === '') {
  http_response_code(500);
  log_event('error', 'Missing BREVO_API_KEY', ['reqId'=>$requestId]);
  echo json_encode(['error' => 'Server misconfiguration', 'hint' => 'BREVO_API_KEY not set', 'reqId' => $requestId]);
  exit;
}
$fixedSenderEmail = getenv('BREVO_SENDER_EMAIL') ?: '';
$fixedSenderName  = getenv('BREVO_SENDER_NAME') ?: '16Vieira.com';

// --------- Lê/valida payload ---------
$raw = file_get_contents('php://input') ?: '';
$input = json_decode($raw, true);
if (!is_array($input)) $input = [];

$firstName = trim((string)($input['firstName'] ?? ''));
$lastName  = trim((string)($input['lastName']  ?? ''));
$email     = trim((string)($input['email']     ?? ''));
$phone     = trim((string)($input['phone']     ?? ''));
$financing = trim((string)($input['financing'] ?? ''));
$message   = trim((string)($input['message']   ?? ''));

$errors = [];
if ($firstName === '') $errors['firstName'] = 'Required';
if ($lastName  === '') $errors['lastName']  = 'Required';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Invalid email';
if ($phone === '') $errors['phone'] = 'Required';

if ($errors) {
  http_response_code(422);
  log_event('warn', 'Validation failed', ['reqId'=>$requestId,'fields'=>$errors]);
  echo json_encode(['error' => 'Validation failed', 'fields' => $errors, 'reqId' => $requestId]);
  exit;
}

log_event('info', 'Validated lead', [
  'reqId'=>$requestId,
  'from'=>$email,
  'to'=>['gerci.usa@gmail.com','luizlz@gmail.com']
]);

// --------- Gera HTML do email ---------
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$subject = '🏠 URGENT: Property Inquiry - 16 Vieira Dr - ' . $firstName . ' ' . $lastName;

$html = '
  <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #667eea, #764ba2); padding: 30px; text-align: center; color: white;">
      <h1 style="margin: 0; font-size: 24px;">🏠 New Property Inquiry</h1>
      <p style="margin: 10px 0 0 0; opacity: 0.9;">16 Vieira Dr, Peabody, MA</p>
    </div>
    <div style="padding: 30px; background: white;">
      <h2 style="color: #2d3748; margin-bottom: 20px;">Contact Information</h2>
      <div style="background: #f7fafc; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
        <p><strong>Name:</strong> ' . e($firstName . ' ' . $lastName) . '</p>
        <p><strong>Email:</strong> <a href="mailto:' . e($email) . '">' . e($email) . '</a></p>
        <p><strong>Phone:</strong> <a href="tel:' . e($phone) . '">' . e($phone) . '</a></p>
        <p><strong>Financing Status:</strong> ' . e($financing ?: 'Not specified') . '</p>
      </div>' .
      ($message !== '' ? '
      <h3 style="color: #2d3748; margin-bottom: 10px;">Client Message:</h3>
      <div style="background: #edf2f7; padding: 15px; border-radius: 8px; border-left: 4px solid #667eea;">
        <p style="margin: 0;">' . e($message) . '</p>
      </div>' : '') . '
      <div style="margin-top: 30px; padding: 20px; background: #f0fff4; border-radius: 10px; border: 2px solid #48bb78;">
        <h3 style="color: #22543d; margin-bottom: 10px;">Property Details</h3>
        <p style="margin: 5px 0;"><strong>Address:</strong> 16 Vieira Dr, Peabody, MA 01960</p>
        <p style="margin: 5px 0;"><strong>Price:</strong> $949,900</p>
        <p style="margin: 5px 0;"><strong>MLS:</strong> 73427376</p>
        <p style="margin: 5px 0;"><strong>Features:</strong> 3 bedrooms, 3 bathrooms, 2,330 sq ft</p>
      </div>
    </div>
    <div style="background: #2d3748; color: white; padding: 20px; text-align: center;">
      <p style="margin: 0; font-size: 14px;">Sent through 16vieira.com on ' . date('Y-m-d H:i:s') . '</p>
      <p style="margin: 5px 0 0 0; font-size: 12px; opacity: 0.8;">⚡ Respond within 24 hours for maximum conversion</p>
    </div>
  </div>
';

// --------- Monta payload Brevo ---------
$brevoData = [
  'sender' => [
    'name'  => $firstName . ' ' . $lastName,
    'email' => $email, // será substituído se BREVO_SENDER_EMAIL estiver setado
  ],
  'to' => [
    //['email' => 'gerci.usa@gmail.com', 'name' => 'Gercilaine DeSouza'],
    ['email' => 'infowebcrew@gmail.com', 'name' => 'Gercilaine DeSouza'],
    ['email' => 'luizlz@gmail.com',    'name' => 'Backup Contact'],
  ],
  'replyTo' => [
    'email' => $email,
    'name'  => $firstName . ' ' . $lastName,
  ],
  'subject'     => $subject,
  'htmlContent' => $html,
];

if ($fixedSenderEmail !== '') {
  // melhor prática: remetente do seu domínio verificado; lead fica no reply-to
  $brevoData['sender'] = ['name' => $fixedSenderName, 'email' => $fixedSenderEmail];
}

// --------- Chamada HTTP ---------
$ch = curl_init('https://api.brevo.com/v3/smtp/email');
curl_setopt_array($ch, [
  CURLOPT_POST            => true,
  CURLOPT_POSTFIELDS      => json_encode($brevoData, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
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

// --------- Resposta + logs ---------
if ($response === false) {
  http_response_code(502);
  log_event('error', 'cURL failure contacting Brevo', ['reqId'=>$requestId,'error'=>$curlErr]);
  echo json_encode(['error' => 'Mail provider unreachable', 'details' => $curlErr, 'reqId' => $requestId]);
  exit;
}

$parsed = json_decode($response, true);

if ($httpCode === 201) {
  // normalmente Brevo retorna {"messageId":"<id>"} em sucesso
  $msgId = is_array($parsed) && isset($parsed['messageId']) ? $parsed['messageId'] : null;
  log_event('info', 'Email sent', [
    'reqId'=>$requestId,
    'subject'=>$subject,
    'to'=>array_column($brevoData['to'],'email'),
    'messageId'=>$msgId
  ]);
  echo json_encode(['success' => true, 'messageId' => $msgId, 'reqId' => $requestId]);
} else {
  http_response_code(502);
  log_event('error', 'Email rejected by Brevo', [
    'reqId'=>$requestId,
    'status'=>$httpCode,
    'to'=>array_column($brevoData['to'],'email'),
    'providerResponse'=>$parsed ?: $response
  ]);
  echo json_encode([
    'error'   => 'Failed to send email',
    'status'  => $httpCode,
    'details' => $parsed ?: $response,
    'reqId'   => $requestId
  ]);
}
