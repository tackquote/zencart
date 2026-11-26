<?php
/**
 * TackApiClient::checkConnection(): what each ping status means.
 *
 * Self-contained (no Zen Cart, no network): only transport(), the raw cURL call, is
 * scripted with real TackQuote response bodies, so decoding and the status-to-verdict
 * rules under test are the real code. The ping used to fall back
 * to /health on ANY failure, and /health answers 200 with no key, so a wrong or
 * revoked key read as connected.
 *
 * Run: php tests/ConnectionTest.php   (exit 0 = all passed)
 */

define('TACK_QUOTE_STANDALONE_TEST', true);
require __DIR__ . '/../includes/classes/tack_api_client.php';

$passed = 0;
$failures = array();

function check($label, $condition)
{
    global $passed, $failures;
    if ($condition) {
        $passed++;
        echo '  ok   ' . $label . PHP_EOL;
    } else {
        $failures[] = $label;
        echo '  FAIL ' . $label . PHP_EOL;
    }
}

// Real TackQuote answers: 401, 404 and /health captured from api.tackquote.com on
// 2026-11-27 (timestamps trimmed); the 403s are the bodies PlanGuard builds
// (apps/api/src/common/guards/plan.guard.ts).
$fix = array(
    'ping_ok' => '{"ok":true,"tenantId":"00000000-0000-4000-8000-000000000001","capabilities":["lifecycle","lifecycle_feedback"]}',
    '401' => '{"statusCode":401,"code":"api_key_invalid","message":"Invalid API key","path":"/v1/integrations/zencart/ping"}',
    '403_plan' => '{"error":"PLAN_LIMIT_EXCEEDED","resource":"apiCalls","limit":1000,"message":"You have reached your plan limit of 1000 API calls per month. Please upgrade your plan."}',
    '403_tenant' => '{"statusCode":403,"message":"Tenant not found for plan limit check","error":"Forbidden"}',
    '404' => '{"statusCode":404,"code":"NOTFOUND","message":"Cannot GET /v1/integrations/zencart/ping","path":"/v1/integrations/zencart/ping"}',
    '500' => '{"statusCode":500,"message":"Internal server error"}',
    '503' => '<html><body><h1>503 Service Unavailable</h1></body></html>',
    'html_200' => '<!doctype html><html><head><title>My shop</title></head><body>Welcome</body></html>',
    'health' => '{"status":"ok","database":"connected","redis":"writable"}',
);

/** Only the raw cURL call is scripted; JSON decoding and every rule after it are real. */
class ScriptedTackApiClient extends TackApiClient
{
    public $answers = array();
    public $paths = array();

    protected function transport($method, $path, $body = null)
    {
        $this->paths[] = $method . ' ' . $path;

        return $this->answers[$path];
    }
}

/** @param array|null $answer array(status, body), or null for no HTTP answer at all */
function raw($answer)
{
    return $answer === null
        ? array('error' => 'Connection error: Operation timed out after 20001 milliseconds', 'status' => 0, 'body' => '')
        : array('error' => null, 'status' => $answer[0], 'body' => $answer[1]);
}

function scripted($ping, $health = 'default')
{
    global $fix;
    if ($health === 'default') {
        $health = array(200, $fix['health']);
    }
    $client = new ScriptedTackApiClient('https://api.tackquote.com/v1', 'live_key');
    $client->answers = array(
        '/integrations/zencart/ping' => raw($ping),
        '/health' => raw($health),
    );

    return $client;
}

$pingOnly = array('GET /integrations/zencart/ping');
$withHealth = array('GET /integrations/zencart/ping', 'GET /health');

$c = scripted(array(200, $fix['ping_ok']));
check('ping 200 {"ok":true} -> connected, no fallback', $c->checkConnection()['result'] === TackApiClient::CONNECTION_OK && $c->paths === $pingOnly);
check('ping 200 -> testConnection() is true', scripted(array(200, $fix['ping_ok']))->testConnection() === true);

$c = scripted(array(200, $fix['html_200']));
check('ping 200 HTML (wrong URL) -> failed, not connected, no fallback',
    $c->checkConnection()['result'] === TackApiClient::CONNECTION_FAILED && $c->paths === $pingOnly);
check('ping 200 {"ok":false} -> not connected', scripted(array(200, '{"ok":false}'))->testConnection() !== true);

$c = scripted(array(401, $fix['401']));
check('ping 401 api_key_invalid -> key rejected, no /health fallback',
    $c->checkConnection()['result'] === TackApiClient::CONNECTION_KEY_REJECTED && $c->paths === $pingOnly);
$msg = scripted(array(401, $fix['401']))->testConnection();
check('ping 401 -> testConnection() names the rejected key', is_string($msg) && strpos($msg, 'rejected this API key') !== false);

foreach (array('403_plan', '403_tenant') as $k) {
    $c = scripted(array(403, $fix[$k]));
    $r = $c->checkConnection();
    $expected = json_decode($fix[$k], true);
    check("ping $k comes with a VALID key -> failed with TackQuote's own message, no fallback",
        $r['result'] === TackApiClient::CONNECTION_FAILED && $c->paths === $pingOnly && $r['message'] === $expected['message']);
    $msg = scripted(array(403, $fix[$k]))->testConnection();
    check("ping $k -> testConnection() is TackQuote's message, not a key error",
        $msg === $expected['message'] && strpos($msg, 'rejected') === false);
}

foreach (array('network' => null, '404' => array(404, $fix['404']), '500' => array(500, $fix['500']), '503' => array(503, $fix['503'])) as $label => $ping) {
    $c = scripted($ping);
    check("ping $label + /health 200 -> reachable, key unchecked",
        $c->checkConnection()['result'] === TackApiClient::CONNECTION_KEY_UNCHECKED && $c->paths === $withHealth);
    $msg = scripted($ping)->testConnection();
    check("ping $label + /health 200 -> testConnection() is not true and says the key was not checked",
        $msg !== true && strpos($msg, 'could not be checked') !== false);
    check("ping $label + /health down -> failed",
        scripted($ping, null)->checkConnection()['result'] === TackApiClient::CONNECTION_FAILED);
}

$c = scripted(array(429, '{"statusCode":429,"message":"ThrottlerException: Too Many Requests"}'));
check('ping 429 -> failed, no /health fallback',
    $c->checkConnection()['result'] === TackApiClient::CONNECTION_FAILED && $c->paths === $pingOnly);

$noKey = new TackApiClient('https://api.tackquote.com/v1', '');
check('no API key -> failed without any request', $noKey->checkConnection()['result'] === TackApiClient::CONNECTION_FAILED);

if (function_exists('curl_init')) {
    // 127.0.0.1:9 (discard) refuses at once: a real transport, no network needed.
    $real = (new TackApiClient('http://127.0.0.1:9', 'live_key'))->checkConnection();
    check('real transport: refused connection -> failed with a Connection error',
        $real['result'] === TackApiClient::CONNECTION_FAILED && strpos($real['message'], 'Connection error') === 0);
}

echo 'passed: ' . $passed . ', failed: ' . count($failures) . PHP_EOL;
exit(empty($failures) ? 0 : 1);
