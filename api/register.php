<?php
declare(strict_types=1);

require __DIR__ . '/db.php';
require __DIR__ . '/ratelimit.php';

header('Content-Type: application/json; charset=utf-8');

// ---- CORS (only needed if frontend is on a different origin) ----
$config = null;
if (file_exists(__DIR__ . '/config.php')) {
    $config = dd_get_config();
    $allowedOrigin = $config['allowed_origin'] ?? '';
    if ($allowedOrigin !== '') {
        header('Access-Control-Allow-Origin: ' . $allowedOrigin);
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['error' => 'METHOD_NOT_ALLOWED', 'message' => 'Only POST is supported here.']);
}

// ---- Rate limiting: 20 registration attempts / 15 min / IP ----
if (!dd_rate_limit_check('register', 20, 900)) {
    respond(429, ['error' => 'RATE_LIMITED', 'message' => 'Trop de tentatives. Réessayez plus tard.']);
}

// ---- Parse + validate input ----
$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '', true);

if (!is_array($input)) {
    respond(400, ['error' => 'VALIDATION_ERROR', 'message' => 'Invalid JSON body.']);
}

function clean_string($v): string
{
    return is_string($v) ? trim($v) : '';
}

$firstName = clean_string($input['firstName'] ?? '');
$lastName  = clean_string($input['lastName'] ?? '');
$phone     = clean_string($input['phone'] ?? '');
$email     = clean_string($input['email'] ?? '');
$level     = clean_string($input['level'] ?? '');
$establishment = clean_string($input['ofpptEstablishment'] ?? '');
$termsAccepted   = ($input['termsAccepted'] ?? false) === true;
$privacyAccepted = ($input['privacyAccepted'] ?? false) === true;

$errors = [];

if ($firstName === '' || mb_strlen($firstName) > 100) {
    $errors['firstName'] = 'First name is required.';
}
if ($lastName === '' || mb_strlen($lastName) > 100) {
    $errors['lastName'] = 'Last name is required.';
}
// Moroccan mobile: 06/07/05 + 8 digits, or +212 equivalent
if (!preg_match('/^(0|\+212)[5-7][0-9]{8}$/', str_replace(' ', '', $phone))) {
    $errors['phone'] = 'Invalid phone number.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Invalid email address.';
}
if ($level !== '' && !in_array($level, ['1', '2'], true)) {
    $errors['level'] = 'Invalid level.';
}
if (!$termsAccepted) {
    $errors['termsAccepted'] = 'Terms must be accepted.';
}
if (!$privacyAccepted) {
    $errors['privacyAccepted'] = 'Privacy policy must be accepted.';
}

if (!empty($errors)) {
    respond(400, ['error' => 'VALIDATION_ERROR', 'message' => 'Invalid registration data.', 'details' => $errors]);
}

$pdo = dd_get_pdo();

// ---- Duplicate check ----
$dupStmt = $pdo->prepare('SELECT id FROM registrations WHERE email = :email OR phone = :phone LIMIT 1');
$dupStmt->execute(['email' => $email, 'phone' => $phone]);
if ($dupStmt->fetch()) {
    respond(409, [
        'error'   => 'DUPLICATE_REGISTRATION',
        'message' => 'Une inscription avec cet email ou ce téléphone existe déjà.',
    ]);
}

// ---- Generate a unique public reference: DD-YYYY-XXXXXX ----
function generate_public_id(PDO $pdo): string
{
    $checkStmt = $pdo->prepare('SELECT id FROM registrations WHERE public_id = :pid LIMIT 1');
    for ($i = 0; $i < 5; $i++) {
        $candidate = 'DD-' . date('Y') . '-' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $checkStmt->execute(['pid' => $candidate]);
        if (!$checkStmt->fetch()) {
            return $candidate;
        }
    }
    // Extremely unlikely fallback
    return 'DD-' . date('Y') . '-' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT) . '-' . bin2hex(random_bytes(2));
}

$publicId = generate_public_id($pdo);

try {
    $insert = $pdo->prepare(
        'INSERT INTO registrations
            (public_id, first_name, last_name, phone, email, ofppt_establishment, level, status,
             terms_accepted, privacy_accepted, accepted_at, created_at, updated_at)
         VALUES
            (:public_id, :first_name, :last_name, :phone, :email, :establishment, :level, "PENDING",
             1, 1, NOW(), NOW(), NOW())'
    );
    $insert->execute([
        'public_id'     => $publicId,
        'first_name'    => $firstName,
        'last_name'     => $lastName,
        'phone'         => $phone,
        'email'         => $email,
        'establishment' => $establishment !== '' ? $establishment : null,
        'level'         => $level !== '' ? $level : null,
    ]);
} catch (PDOException $e) {
    error_log('Insert failed: ' . $e->getMessage());
    respond(500, ['error' => 'INTERNAL_ERROR', 'message' => "Une erreur est survenue, réessayez plus tard."]);
}

respond(201, [
    'registration' => [
        'publicId' => $publicId,
        'firstName' => $firstName,
        'lastName'  => $lastName,
        'email'     => $email,
        'phone'     => $phone,
        'status'    => 'PENDING',
        'price'     => 150,
        'currency'  => 'MAD',
    ],
]);
