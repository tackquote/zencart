<?php
/**
 * TackApiClient::checkConnection(): what each ping status means.
 *
 * Self-contained (no Zen Cart, no network): only exchange() is scripted, so the
 * status-to-verdict rules under test are the real code. The ping used to fall back
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

class ScriptedTackApiClient extends TackApiClient
{
    public $answers = array();
    public $paths = array();

    protected function exchange($method, $path, $body = null)
    {
        $this->paths[] = $method . ' ' . $path;

        return $this->answers[$path];
    }
}

function answer($status)
{
    if ($status >= 200 && $status < 300) {
        return array('status' => $status, 'data' => array('ok' => true), 'error' => null);
    }

    return array('status' => $status, 'data' => array(), 'error' => $status === 0 ? 'Connection error: timed out' : 'HTTP ' . $status);
}

function scripted($pingStatus, $healthStatus = 200)
{
    $client = new ScriptedTackApiClient('https://api.tackquote.com/v1', 'live_key');
    $client->answers = array(
        '/integrations/zencart/ping' => answer($pingStatus),
        '/health' => answer($healthStatus),
    );

    return $client;
}

$pingOnly = array('GET /integrations/zencart/ping');
$withHealth = array('GET /integrations/zencart/ping', 'GET /health');

$c = scripted(200);
check('ping 200 -> connected, no fallback', $c->checkConnection()['result'] === TackApiClient::CONNECTION_OK && $c->paths === $pingOnly);
check('ping 200 -> testConnection() is true', scripted(200)->testConnection() === true);

foreach (array(401, 403) as $st) {
    $c = scripted($st, 200);
    check("ping $st -> key rejected, no /health fallback",
        $c->checkConnection()['result'] === TackApiClient::CONNECTION_KEY_REJECTED && $c->paths === $pingOnly);
    $msg = scripted($st, 200)->testConnection();
    check("ping $st -> testConnection() names the rejected key", is_string($msg) && strpos($msg, 'rejected this API key') !== false);
}

foreach (array(0, 404, 500, 503) as $st) {
    $c = scripted($st, 200);
    check("ping $st + /health 200 -> reachable, key unchecked",
        $c->checkConnection()['result'] === TackApiClient::CONNECTION_KEY_UNCHECKED && $c->paths === $withHealth);
    $msg = scripted($st, 200)->testConnection();
    check("ping $st + /health 200 -> testConnection() is not true and says the key was not checked",
        $msg !== true && strpos($msg, 'could not be checked') !== false);
    check("ping $st + /health down -> failed",
        scripted($st, 0)->checkConnection()['result'] === TackApiClient::CONNECTION_FAILED);
}

$c = scripted(429, 200);
check('ping 429 -> failed, no /health fallback',
    $c->checkConnection()['result'] === TackApiClient::CONNECTION_FAILED && $c->paths === $pingOnly);

$noKey = new TackApiClient('https://api.tackquote.com/v1', '');
check('no API key -> failed without any request', $noKey->checkConnection()['result'] === TackApiClient::CONNECTION_FAILED);

if (function_exists('curl_init')) {
    // 127.0.0.1:9 (discard) refuses at once: a real exchange, no network needed.
    $real = (new TackApiClient('http://127.0.0.1:9', 'live_key'))->checkConnection();
    check('real exchange: refused connection -> failed with a Connection error',
        $real['result'] === TackApiClient::CONNECTION_FAILED && strpos($real['message'], 'Connection error') === 0);
}

echo 'passed: ' . $passed . ', failed: ' . count($failures) . PHP_EOL;
exit(empty($failures) ? 0 : 1);
