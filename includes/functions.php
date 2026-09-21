<?php
declare(strict_types=1);

function app_config(): array
{
    static $config = null;
    if (is_array($config)) {
        return $config;
    }
    $file = dirname(__DIR__) . '/config.local.php';
    if (!is_file($file)) {
        return [];
    }
    $config = require $file;
    return is_array($config) ? $config : [];
}

function is_installed(): bool
{
    return is_file(dirname(__DIR__) . '/config.local.php')
        && is_file(dirname(__DIR__) . '/storage/install.lock');
}

function ensure_installed(): void
{
    if (!is_installed()) {
        header('Location: ' . guessed_base_url() . '/install.php');
        exit;
    }
}

function guessed_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
    $root = preg_replace('#/(admin/.*|[^/]+\.php)$#', '', $script) ?: '';
    return $scheme . '://' . $host . rtrim($root, '/');
}

function url(string $path = ''): string
{
    $config = app_config();
    $base = rtrim((string)($config['app']['base_url'] ?? guessed_base_url()), '/');
    return $base . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $relativePath = ltrim($path, '/');
    $assetUrl = url('assets/' . $relativePath);
    $localPath = dirname(__DIR__) . '/assets/' . $relativePath;
    if (is_file($localPath)) {
        $modified = filemtime($localPath);
        if ($modified !== false) return $assetUrl . '?v=' . $modified;
    }
    return $assetUrl;
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

function lang(): string
{
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['de', 'en'], true)) {
        $_SESSION['lang'] = $_GET['lang'];
    }
    return $_SESSION['lang'] ?? 'de';
}

function t(string $de, string $en): string
{
    return lang() === 'en' ? $en : $de;
}

/**
 * Returns editable public website settings. Values are cached for the request
 * so the header, footer and page content do not repeat database queries.
 */
function site_settings(): array
{
    static $settings = null;
    if (is_array($settings)) {
        return $settings;
    }
    try {
        $settings = db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Throwable) {
        $settings = [];
    }
    return is_array($settings) ? $settings : [];
}

function site_setting(string $key, string $default = ''): string
{
    $settings = site_settings();
    return isset($settings[$key]) && trim((string)$settings[$key]) !== ''
        ? trim((string)$settings[$key])
        : $default;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Security token expired. Please reload the page.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($messages) ? $messages : [];
}

function money(float|int|string $amount): string
{
    return number_format((float)$amount, 0, ',', '.') . ' €';
}

function money_precise(float|int|string $amount): string
{
    return number_format((float)$amount, 2, ',', '.') . ' €';
}

function price_markup_percent(): float
{
    return max(0.0, min(200.0, (float)str_replace(',', '.', site_setting('global_price_markup_percent', '10'))));
}

function price_markup_amount(float|int|string $subtotal, ?float $percent = null): float
{
    $percent = $percent ?? price_markup_percent();
    return round(max(0.0, (float)$subtotal) * max(0.0, min(200.0, $percent)) / 100, 2);
}

function price_with_markup(float|int|string $amount, ?float $percent = null): float
{
    return round((float)$amount + price_markup_amount($amount, $percent), 2);
}

function slugify(string $value): string
{
    $value = trim($value);
    if (function_exists('transliterator_transliterate')) {
        $value = (string)transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $value);
    } else {
        $value = strtolower(strtr($value, ['ä'=>'ae','ö'=>'oe','ü'=>'ue','ß'=>'ss']));
    }
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim(substr($value, 0, 160), '-');
}

function text_lines(string $value): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R/u', $value) ?: []), static fn(string $line): bool => $line !== ''));
}

function stay_price_basis_label(string $basis): string
{
    return match ($basis) {
        'per_person_night' => t('pro Person / Nacht', 'per person / night'),
        'per_booking' => t('pro Buchung', 'per booking'),
        'free' => t('kostenfrei', 'free'),
        'on_request' => t('Preis auf Anfrage', 'price on request'),
        default => t('pro Person', 'per person'),
    };
}

/**
 * Calculates a single-hotel stay from trusted database values.
 * The standard double room includes two guests. Additional adult beds cost the
 * configured percentage of the room rate; children receive the configured
 * child percentage on both extra-bed and meal/service person rates.
 */
function calculate_stay_pricing(
    array $hotel,
    ?array $mealPlan,
    array $services,
    int $adults,
    int $children,
    int $rooms,
    int $nights
): array {
    $adults = max(1, $adults);
    $children = max(0, $children);
    $rooms = max(1, $rooms);
    $nights = max(1, $nights);
    $standardGuests = max(1, (int)($hotel['standard_room_guests'] ?? 2));
    $maximumGuests = max($standardGuests, (int)($hotel['max_guests_with_extra_bed'] ?? 3));
    $standardCapacity = $rooms * $standardGuests;
    $maximumCapacity = $rooms * $maximumGuests;
    $includedAdults = min($adults, $standardCapacity);
    $remainingIncludedPlaces = max(0, $standardCapacity - $includedAdults);
    $includedChildren = min($children, $remainingIncludedPlaces);
    $extraBedAdults = max(0, $adults - $includedAdults);
    $extraBedChildren = max(0, $children - $includedChildren);
    $extraBedPercent = max(0.0, min(100.0, (float)($hotel['extra_bed_percent'] ?? 30))) / 100;
    $childPercent = max(0.0, min(100.0, (float)($hotel['child_percent'] ?? 50))) / 100;
    $roomRate = max(0.0, (float)($hotel['price_per_person'] ?? 0));
    $roomCost = $roomRate * $rooms * $nights;
    $extraBedCost = ($roomRate * $extraBedPercent * $extraBedAdults * $nights)
        + ($roomRate * $extraBedPercent * $childPercent * $extraBedChildren * $nights);
    $mealRate = max(0.0, (float)($mealPlan['supplement_per_person_night'] ?? 0));
    $weightedGuests = $adults + ($children * $childPercent);
    $mealCost = $mealRate * $weightedGuests * $nights;
    $serviceLines = [];
    $servicesCost = 0.0;
    foreach ($services as $service) {
        $unitPrice = max(0.0, (float)($service['price_per_person'] ?? 0));
        $basis = (string)($service['price_basis'] ?? 'per_person');
        $lineTotal = match ($basis) {
            'per_person_night' => $unitPrice * $weightedGuests * $nights,
            'per_booking' => $unitPrice,
            'free', 'on_request' => 0,
            default => $unitPrice * $weightedGuests,
        };
        $service['line_total'] = round($lineTotal, 2);
        $serviceLines[] = $service;
        $servicesCost += $lineTotal;
    }
    return [
        'valid_occupancy' => ($adults + $children) <= $maximumCapacity,
        'standard_capacity' => $standardCapacity,
        'maximum_capacity' => $maximumCapacity,
        'extra_bed_adults' => $extraBedAdults,
        'extra_bed_children' => $extraBedChildren,
        'child_factor' => $childPercent,
        'room_cost' => round($roomCost, 2),
        'extra_bed_cost' => round($extraBedCost, 2),
        'meal_cost' => round($mealCost, 2),
        'services_cost' => round($servicesCost, 2),
        'services' => $serviceLines,
        'total' => round($roomCost + $extraBedCost + $mealCost + $servicesCost, 2),
    ];
}

function request_is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function selected(string|int $value, string|int|null $current): string
{
    return (string)$value === (string)$current ? ' selected' : '';
}

function checked(bool $value): string
{
    return $value ? ' checked' : '';
}

function upload_image(array $file, string $current = ''): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $current;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Image upload failed or exceeds 5 MB.');
    }
    $info = @getimagesize($file['tmp_name']);
    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($allowed[$info[2]])) {
        throw new RuntimeException('Only JPG, PNG and WEBP images are allowed.');
    }
    $filename = bin2hex(random_bytes(12)) . '.' . $allowed[$info[2]];
    $directory = dirname(__DIR__) . '/uploads';
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Upload directory could not be created.');
    }
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('Uploaded image could not be saved.');
    }
    return 'uploads/' . $filename;
}

function upload_private_guide_document(array $file, string $current = ''): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return $current;
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 8 * 1024 * 1024) {
        throw new RuntimeException('Document upload failed or exceeds 8 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
    if (!isset($allowed[$mime])) throw new RuntimeException('Only PDF, JPG and PNG documents are allowed.');
    $directory = dirname(__DIR__) . '/storage/guide-documents';
    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
        throw new RuntimeException('Private document directory could not be created.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('Uploaded document could not be saved.');
    }
    return 'storage/guide-documents/' . $filename;
}
