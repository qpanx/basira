<?php

/** Local HTTP fixture only. It does not inspect uploads or perform inference. */
header('Content-Type: application/json; charset=utf-8');
$language = ($_POST['language'] ?? 'en') === 'ar' ? 'ar' : 'en';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ! isset($_FILES['image'])) {
    http_response_code(422);
    echo json_encode(['error' => 'POST an image to /detect or /read.']);
} elseif ($path === '/detect') {
    echo json_encode(['objects' => [['label' => 'synthetic-test-object', 'confidence' => 0.9, 'position' => 'front']]]);
} elseif ($path === '/read') {
    echo json_encode(['text' => $language === 'ar' ? 'نص تجريبي وليس نتيجة قراءة الصورة' : 'Synthetic fixture text, not extracted from the image', 'language' => $language], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Unknown fixture endpoint.']);
}
