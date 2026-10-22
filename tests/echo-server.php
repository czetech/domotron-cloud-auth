<?php
// Router for PHP's built-in server used by CurlTransportTest: echoes the request as JSON.
$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/status/(\d+)$#', $path, $m)) {
    http_response_code((int) $m[1]);
    echo '{}';
    return;
}

$headers = [];
foreach ($_SERVER as $name => $value) {
    if (str_starts_with($name, 'HTTP_')) {
        $headers[strtolower(str_replace('_', '-', substr($name, 5)))] = $value;
    }
}
if (isset($_SERVER['CONTENT_TYPE'])) {
    $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
}

http_response_code(201);
header('Content-Type: application/json');
echo json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'headers' => $headers,
    'body' => file_get_contents('php://input'),
]);
