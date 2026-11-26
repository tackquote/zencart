<?php
/**
 * Router for `php -S`: a stand-in TackQuote API for AdminPageTest.php. Bodies are
 * TackQuote's real shapes (see ConnectionTest.php). Every request is appended to the
 * log file named by TACK_FAKE_LOG, so a test can prove that no request was made.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$key = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '';
file_put_contents(getenv('TACK_FAKE_LOG'), $_SERVER['REQUEST_METHOD'] . ' ' . $path . "\n", FILE_APPEND);
file_put_contents(getenv('TACK_FAKE_LOG') . '.ua', (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '') . "\n", FILE_APPEND);
header('Content-Type: application/json');

if ($path === '/health' || $path === '/v1/health') {
    echo '{"status":"ok","database":"connected","redis":"writable","version":"unknown","uptime":12.5,"timestamp":"2026-11-27T00:00:00.000Z"}';
    return true;
}
if ($path === '/v1/integrations/zencart/ping') {
    if ($key === 'good_key') {
        echo '{"ok":true,"tenantId":"00000000-0000-4000-8000-000000000001","capabilities":["lifecycle"]}';
    } elseif ($key === 'html_key') {
        http_response_code(403);
        echo '{"statusCode":403,"message":"Plan <img src=x onerror=alert(1)> limit","error":"Forbidden"}';
    } else {
        http_response_code(401);
        echo '{"statusCode":401,"code":"api_key_invalid","message":"Invalid API key"}';
    }
    return true;
}
http_response_code(404);
echo '{"statusCode":404,"code":"NOTFOUND","message":"Cannot GET ' . $path . '"}';
return true;
