<?php
/**
 * send-mail.php — 16vieira.com
 * Secure Brevo (Sendinblue) mail relay using environment variable BREVO_API_KEY
 */

declare(strict_types=1);

// --- CORS (ajuste o domínio se quiser restringir) ---
$allowedOrigin = 'https://16vieira.com';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Vary: Origin');
header('Access-Control-Allow-Origin: ' . ($origin === $allowedOrigin ? $allowedOrigin : '*'));
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// Somente POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method not allowed']);
  exit;
}

// Lê API key do ambiente
$apiKey = getenv('BREVO_API_KEY');
if (!$apiKey) {
  http_response_code(500);
  echo json_encode(['error' => 'Server misconfiguration', 'hint' => 'BREVO_API_KEY not set']);
  exit;
}

// Lê e valida o JSON
$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '[]', true);

$firstName = trim((string)($input['firstName'] ?? ''));
$lastName  = trim((string)($input['lastName'] ?? ''));
$email     = trim((string)($input['email'] ?? ''));
$phone     = trim((string)($input['phone'] ?? ''));
$financing = trim((string)($input['financing'] ?? ''));
$message   = trim((string)($input['message'] ?? ''));

// Validações simples
$errors = [];
if ($firstName === '') $errors['firstName'] = 'Required';
if ($lastName === '')  $errors['lastName']  = 'Required';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Invalid email';
if ($phone === '') $errors['phone'] = 'Required';

if ($errors) {
  http_response_code(422);
  echo json_encode(['error' => 'Validation failed', 'fields' => $errors]);
  exit;
}

// Sanitiza para injetar no HTML
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Monta payload Brevo
$subject = '🏠 URGENT: Property Inquiry - 16 Vieira Dr - ' . $firstName . ' ' . $lastName;

$html = '
  <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #667eea, #764ba2); padding: 30px; text-align: center; color: white;">
      <h1 style="margin:0;font-size:24px;">🏠 New Property Inquiry</h1>
      <p style="margin:10px 0 0; opacity:.9;">16 Vieira Dr, Peabody, MA</p>
    </div>

    <div style="padding:30px;background:#fff;">
      <h2 style="color:#2d3748;margin:0 0 20px;">Contact Information</h2>
      <div style="background:#f7fafc;padding:20px;border-radius:10px;margin-bottom:20px;">
        <p><strong>Name:</strong> ' . e($firstName . ' ' . $lastName) . '</p>
        <p><strong>Email:</strong> <a href="mailto:' . e($email) . '">' . e($email) . '</a></p>
        <p><strong>Phone:</strong> <a href="tel:' . e($phone) . '">' . e($phone) . '</a></p>
        <p><strong>Financing Status:</strong> ' . e($financing ?: 'Not specified') . '</p>
      </div>' .
      ($message !== '' ? '
      <h3 style="color:#2d3748;margin:0 0 10px;">Client Message:</h3>
      <div style="background:#edf2f7;padding:15px;border-radius:8px;border-left:4px solid #667eea;">
        <p style="margin:0;">' . e($message) . '</p>
      </div>' : '') .
      '
      <div style="margin-top:30px;padding:20px;background:#f0fff4;border-radius:10px;border:2px solid #48bb78;">
        <h3 style="color:#22543d;margin:0 0 10px;">Property Details</h3>
        <p style="margin:5px 0;"><strong>Address:</strong> 16 Vieira Dr, Peabody, MA 01960</p>
        <p style="margin:5px 0;"><strong>Price:</strong> $949,900</p>
        <p style="margin:5px 0;"><strong>MLS:</strong> 73427376</p>
        <p style="margin:5px 0;"><strong>Features:</strong> 3 bedrooms, 3 bathrooms, 2,330 sq ft</p>
      </div>
    </div>

    <div style="background:#2d3748;color:#fff;padding:20px;text-align:center;">
      <p style="margin:0;font-size:14px;">Sent through 16vieira.com on ' . date('Y-m-d H:i:s') . '</p>
      <p style="margin:5px 0 0;font-size:12px;opacity:.8;">⚡ Respond within 24 hours for maximum conversion</p>
    </div>
  </div>
';

$brevoData = [
  'sender' => [
    // Remetente precisa ser verificado no Brevo; use um domínio seu
    'name'  => $firstName . ' ' . $lastName,
    'email' => $email, // como "reply-to", alguns provedores preferem que o sender seja do seu domínio
  ],
  'to' => [
    ['email' => 'gerci.usa@gmail.com', 'name' => 'Gercilaine DeSouza'],
    ['email' => 'luizlz@gmail.com',    'name' => 'Backup Contact'],
  ],
  'replyTo' => [
    'email' => $email,
    'name'  => $firstName . ' ' . $lastName,
  ],
  'subject'     => $subject,
  'htmlContent' => $html,
];

// Opcional: forçar sender do seu domínio e manter reply-to do cliente
$fixedSenderEmail = getenv('BREVO_SENDER_EMAIL'); // e.g., "no-reply@16vieira.com"
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

// Respostas
if ($response === false) {
  http_response_code(502);
  echo json_encode(['error' => 'Mail provider unreachable', 'details' => $curlErr]);
  exit;
}

if ($httpCode === 201) {
  echo json_encode(['success' => true]);
} else {
  // repasse controlado do erro da Brevo
  http_response_code(502);
  echo json_encode([
    'error'   => 'Failed to send email',
    'status'  => $httpCode,
    'details' => json_decode($response, true) ?: $response,
  ]);
}
