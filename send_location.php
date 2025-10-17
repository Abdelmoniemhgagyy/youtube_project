<?php
// send_location.php
header('Content-Type: application/json; charset=UTF-8');

// ---------- إعداد مسارات ملفات السجل ----------
$log_dir = __DIR__ . '/logs'; // يُفضّل تغييره لمجلد خارج web root
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0750, true);
}

$human_log_file = $log_dir . '/location_log.txt';   // ملف نصي إنساني
$csv_file       = $log_dir . '/locations.csv';      // ملف CSV (Excel-friendly)
$jsonl_file     = $log_dir . '/locations.jsonl';    // JSON Lines (سطر لكل سجل)

// ---------- قراءات البيانات الواردة ----------
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

// تحقق أساسي من البيانات
if (!is_array($data) || !isset($data['lat']) || !isset($data['lon'])) {
    echo json_encode(['success' => false, 'error' => 'بيانات ناقصة أو غير صحيحة']);
    exit;
}

$lat = filter_var($data['lat'], FILTER_VALIDATE_FLOAT);
$lon = filter_var($data['lon'], FILTER_VALIDATE_FLOAT);

if ($lat === false || $lon === false) {
    echo json_encode(['success' => false, 'error' => 'قيم خطوط إحداثيات غير صالحة']);
    exit;
}

// بيانات إضافية
$userIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$timestamp = date('Y-m-d H:i:s');

// ---------- تهيئة السجل كبائن بيانات ----------
$entry = [
    'timestamp'   => $timestamp,
    'lat'         => $lat,
    'lon'         => $lon,
    'ip'          => $userIP,
    'user_agent'  => $userAgent,
    'referer'     => $referer,
];

// --------------------- 1) Append human-readable log ---------------------
$human_line = sprintf(
    "[%s] Lat: %s | Lon: %s | IP: %s | UA: %s | Referer: %s%s",
    $timestamp,
    $lat,
    $lon,
    $userIP,
    str_replace(["\r", "\n"], ['',''], $userAgent),
    str_replace(["\r", "\n"], ['',''], $referer),
    PHP_EOL
);

file_put_contents($human_log_file, $human_line, FILE_APPEND | LOCK_EX);

// --------------------- 2) Append CSV (create header if needed) ---------------------
$csv_header = ['timestamp','lat','lon','ip','user_agent','referer'];

$need_header = !file_exists($csv_file) || filesize($csv_file) === 0;

$fp = fopen($csv_file, 'a');
if ($fp !== false) {
    // قفل الملف أثناء الكتابة
    if (flock($fp, LOCK_EX)) {
        if ($need_header) {
            fputcsv($fp, $csv_header);
        }
        $csv_row = [$timestamp, $lat, $lon, $userIP, $userAgent, $referer];
        fputcsv($fp, $csv_row);
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
} else {
    // فشل فتح ملف CSV — نسجل في اللوج النصي
    file_put_contents($human_log_file, "[".date('Y-m-d H:i:s')."] ERROR: cannot open CSV file".PHP_EOL, FILE_APPEND | LOCK_EX);
}

// --------------------- 3) Append JSON Lines ---------------------
$json_line = json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL;
file_put_contents($jsonl_file, $json_line, FILE_APPEND | LOCK_EX);

// --------------------- 4) Optional: محاولة إرسال إيميل (يمكن تعطيلها) ---------------------
// إذا تريد تعطيل الإيميل، اجعل $send_mail = false
$send_mail = false; // غيّرها true لو تريد تجربة mail()
$email_result = null;

if ($send_mail) {
    $to = 'moniemhgagy@gmail.com';
    $subject = "📍 موقع مستخدم - $timestamp";
    $message = "تم تسجيل موقع جديد:\n\n" .
               "Latitude: $lat\nLongitude: $lon\n\n" .
               "Google Maps: https://www.google.com/maps?q=$lat,$lon\n\n" .
               "IP: $userIP\nUser-Agent: $userAgent\nReferer: $referer\nTime: $timestamp\n";
    $headers = "From: Location Tracker <noreply@yoursite.com>\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $mailSent = mail($to, $subject, $message, $headers);
    $email_result = $mailSent ? 'sent' : 'failed';
}

// --------------------- 5) Response to frontend ---------------------
$response = [
    'success' => true,
    'saved_to' => [
        'human_log' => basename($human_log_file),
        'csv' => basename($csv_file),
        'jsonl' => basename($jsonl_file),
    ],
    'email' => $email_result,
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>

