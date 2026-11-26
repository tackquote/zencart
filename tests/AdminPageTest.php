<?php
/**
 * admin/tack_quote_connection.php, the Zen Cart admin "Test connection" page.
 *
 * Each case runs the page in its own PHP process (tests/admin-page/run-case.php) against
 * a stand-in TackQuote API served by `php -S` on 127.0.0.1, so the page's code, the
 * real TackApiClient and its real cURL transport all run; only the Zen Cart core
 * functions the page calls are stubbed.
 *
 * Run: php tests/AdminPageTest.php   (exit 0 = all passed)
 */

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

if (!function_exists('curl_init')) {
    echo "cURL is required for this test\n";
    exit(1);
}

$port = 18000 + (getmypid() % 1000);
$log = sys_get_temp_dir() . '/tack-fake-' . getmypid() . '.log';
$server = proc_open(
    array(PHP_BINARY, '-S', '127.0.0.1:' . $port, __DIR__ . '/admin-page/fake-tackquote.php'),
    array(0 => array('pipe', 'r'), 1 => array('file', '/dev/null', 'w'), 2 => array('file', '/dev/null', 'w')),
    $pipes,
    null,
    array('TACK_FAKE_LOG' => $log)
);
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) {
    usleep(100000);
}
$api = 'http://127.0.0.1:' . $port;

function page($case)
{
    global $log;
    @unlink($log);
    @unlink($log . '.ua');
    touch($log);
    touch($log . '.ua');
    $case += array('apiUrl' => '', 'apiKey' => '', 'post' => false, 'token' => 'session_token_123');
    $out = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/admin-page/run-case.php')
        . ' ' . escapeshellarg(json_encode($case)) . ' 2>&1');

    return array('html' => (string) $out, 'requests' => trim(file_get_contents($log)), 'ua' => trim(file_get_contents($log . '.ua')));
}

$r = page(array('apiUrl' => $api . '/v1', 'apiKey' => 'good_key'));
check('GET shows the form, sends nothing to TackQuote', strpos($r['html'], 'action="index.php?cmd=tack_quote_connection&action=test" method="post"') !== false && $r['requests'] === '');
check('GET form carries the session securityToken', strpos($r['html'], 'name="securityToken" value="session_token_123"') !== false);
check('GET shows no verdict yet', strpos($r['html'], 'tack-quote-verdict') === false);
check('GET links to the TackQuote settings group', strpos($r['html'], 'cmd=configuration&gID=42') !== false);
check('GET never prints the API key', strpos($r['html'], 'good_key') === false);
check('page runs without PHP notices', stripos($r['html'], 'Warning:') === false && stripos($r['html'], 'Notice:') === false && stripos($r['html'], 'Fatal') === false);

$r = page(array('apiUrl' => $api . '/v1', 'apiKey' => 'good_key', 'post' => true));
check('POST, accepted key -> "Connected" (alert-success)', strpos($r['html'], 'alert-success') !== false && strpos($r['html'], 'Connected: TackQuote accepted this API key.') !== false);
check('POST calls the ping, through checkConnection()', $r['requests'] === 'GET /v1/integrations/zencart/ping');

$r = page(array('apiUrl' => $api . '/v1', 'apiKey' => 'wrong_key', 'post' => true));
check('POST, wrong key -> not connected, names the rejected key', strpos($r['html'], 'alert-danger') !== false && strpos($r['html'], 'rejected this API key') !== false);

$r = page(array('apiUrl' => $api, 'apiKey' => 'good_key', 'post' => true));
check('POST, API URL without /v1 -> the "not at the API address" message',
    strpos($r['html'], 'TackQuote answered, but not at the API address: check the TackQuote API URL setting (it should end in /v1).') !== false
    && $r['requests'] === "GET /integrations/zencart/ping\nGET /health");

$r = page(array('apiUrl' => $api . '/v1', 'apiKey' => 'html_key', 'post' => true));
check("POST, TackQuote's own message is escaped", strpos($r['html'], 'Plan &lt;img src=x onerror=alert(1)&gt; limit') !== false && strpos($r['html'], '<img src=x') === false);

$r = page(array('apiUrl' => $api . '/v1', 'apiKey' => 'good_key', 'post' => true, 'token' => 'forged'));
check('POST with a wrong securityToken -> refused, nothing sent to TackQuote', strpos($r['html'], 'session expired') !== false && $r['requests'] === '');
$r = page(array('apiUrl' => $api . '/v1', 'apiKey' => 'good_key', 'post' => true, 'token' => null));
check('POST without a securityToken -> refused, nothing sent', strpos($r['html'], 'session expired') !== false && $r['requests'] === '');

$r = page(array('apiUrl' => 'https://x.example/"><script>alert(1)</script>', 'apiKey' => ''));
check('the API URL setting is escaped when shown', strpos($r['html'], '&quot;&gt;&lt;script&gt;') !== false && strpos($r['html'], '<script>alert') === false);
check('an empty API key shows "not set"', strpos($r['html'], 'not set') !== false);

// User-Agent: the module's real version, the same one tack-connector reports.
$r = page(array('apiUrl' => $api . '/v1', 'apiKey' => 'good_key', 'post' => true));
preg_match("/'version'\s*=>\s*'([0-9.]+)'/", file_get_contents(__DIR__ . '/../tack-connector/index.php'), $cv);
check('User-Agent is TackQuote-ZenCart/<module version>, equal to tack-connector\'s version',
    isset($cv[1]) && $r['ua'] === 'TackQuote-ZenCart/' . $cv[1]);

// A non-English admin: Zen Cart loads no TackQuote language file, the init script fills in English.
$r = page(array('apiUrl' => $api . '/v1', 'apiKey' => 'good_key', 'language' => 'french', 'menuOnly' => true));
check('non-English admin: both menu labels and the page filename are defined (menu rows listed)',
    strpos($r['html'], 'UNDEFINED') === false && strpos($r['html'], 'BOX_TACK_QUOTE_CONNECTION=TackQuote Test connection') !== false
    && strpos($r['html'], 'BAD AUTOLOAD') === false);
$r = page(array('apiUrl' => $api . '/v1', 'apiKey' => 'good_key', 'language' => 'french', 'post' => true));
check('non-English admin: the page renders and tests without an undefined constant',
    strpos($r['html'], 'Connected: TackQuote accepted this API key.') !== false
    && stripos($r['html'], 'Error') === false && stripos($r['html'], 'Warning:') === false);
$r = page(array('apiUrl' => $api . '/v1', 'apiKey' => 'good_key', 'language' => 'french', 'menuOnly' => true,
    'translated' => array('BOX_TACK_QUOTE_CONNECTION' => 'TackQuote : tester la connexion')));
check('a translated label loaded first wins over the English fallback, without a redefinition warning',
    strpos($r['html'], 'BOX_TACK_QUOTE_CONNECTION=TackQuote : tester la connexion') !== false
    && stripos($r['html'], 'already defined') === false && stripos($r['html'], 'Warning') === false);

proc_terminate($server);
proc_close($server);
@unlink($log);
@unlink($log . '.ua');

echo 'passed: ' . $passed . ', failed: ' . count($failures) . PHP_EOL;
exit(empty($failures) ? 0 : 1);
