<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

$input = json_decode(file_get_contents('php://input'), true);

$brevoData = [
    'sender' => [
        'name' => $input['firstName'] . ' ' . $input['lastName'],
        'email' => $input['email']
    ],
    'to' => [
        [
            'email' => 'gerci.usa@gmail.com',
            'name' => 'Gercilaine DeSouza'
        ],
        [
            'email' => 'luizlz@gmail.com',
            'name' => 'Backup Contact'
        ]
    ],
    'subject' => '🏠 URGENT: Property Inquiry - 16 Vieira Dr - ' . $input['firstName'] . ' ' . $input['lastName'],
    'htmlContent' => '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: linear-gradient(135deg, #667eea, #764ba2); padding: 30px; text-align: center; color: white;">
                <h1 style="margin: 0; font-size: 24px;">🏠 New Property Inquiry</h1>
                <p style="margin: 10px 0 0 0; opacity: 0.9;">16 Vieira Dr, Peabody, MA</p>
            </div>
            
            <div style="padding: 30px; background: white;">
                <h2 style="color: #2d3748; margin-bottom: 20px;">Contact Information</h2>
                
                <div style="background: #f7fafc; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                    <p><strong>Name:</strong> ' . htmlspecialchars($input['firstName'] . ' ' . $input['lastName']) . '</p>
                    <p><strong>Email:</strong> <a href="mailto:' . htmlspecialchars($input['email']) . '">' . htmlspecialchars($input['email']) . '</a></p>
                    <p><strong>Phone:</strong> <a href="tel:' . htmlspecialchars($input['phone']) . '">' . htmlspecialchars($input['phone']) . '</a></p>
                    <p><strong>Financing Status:</strong> ' . htmlspecialchars($input['financing'] ?? 'Not specified') . '</p>
                </div>
                
                ' . (!empty($input['message']) ? '
                <h3 style="color: #2d3748; margin-bottom: 10px;">Client Message:</h3>
                <div style="background: #edf2f7; padding: 15px; border-radius: 8px; border-left: 4px solid #667eea;">
                    <p style="margin: 0;">' . htmlspecialchars($input['message']) . '</p>
                </div>
                ' : '') . '
                
                <div style="margin-top: 30px; padding: 20px; background: #f0fff4; border-radius: 10px; border: 2px solid #48bb78;">
                    <h3 style="color: #22543d; margin-bottom: 10px;">Property Details</h3>
                    <p style="margin: 5px 0;"><strong>Address:</strong> 16 Vieira Dr, Peabody, MA 01960</p>
                    <p style="margin: 5px 0;"><strong>Price:</strong> $949,900</p>
                    <p style="margin: 5px 0;"><strong>MLS:</strong> 73427376</p>
                    <p style="margin: 5px 0;"><strong>Features:</strong> 3 bedrooms, 3 bathrooms, 2,330 sq ft</p>
                </div>
                
                <div style="margin-top: 30px; text-align: center;">
                    <a href="tel:' . htmlspecialchars($input['phone']) . '" style="background: #48bb78; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block; margin-right: 10px;">📞 Call Now</a>
                    <a href="mailto:' . htmlspecialchars($input['email']) . '" style="background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;">📧 Reply Email</a>
                </div>
            </div>
            
            <div style="background: #2d3748; color: white; padding: 20px; text-align: center;">
                <p style="margin: 0; font-size: 14px;">Sent through 16vieira.com on ' . date('Y-m-d H:i:s') . '</p>
                <p style="margin: 5px 0 0 0; font-size: 12px; opacity: 0.8;">⚡ Respond within 24 hours for maximum conversion</p>
            </div>
        </div>
    ',
    'replyTo' => [
        'email' => $input['email'],
        'name' => $input['firstName'] . ' ' . $input['lastName']
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($brevoData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'accept: application/json',
    'api-key: REDACTED',
    'content-type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 201) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send email', 'details' => $response]);
}
?>