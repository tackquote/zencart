<?php
/**
 * Runs admin/tack_quote_connection.php once, outside Zen Cart, with the few Zen Cart
 * functions it calls stubbed (the stubs mirror core: zen_draw_form() adds the
 * securityToken hidden field to a POST form; zen_output_string_protected() is
 * htmlspecialchars). The page's own code and the real TackApiClient (real cURL) run
 * unchanged. Called by AdminPageTest.php, one process per case, with the case as JSON
 * in argv[1]: {apiUrl, apiKey, post, token, language?, translated?, menuOnly?}.
 */
$case = json_decode($argv[1], true);
$repo = dirname(__DIR__, 2);

define('DIR_WS_INCLUDES', 'includes/');
define('DIR_FS_CATALOG', $repo . '/');
define('HTML_PARAMS', 'dir="ltr" lang="en"');
define('TABLE_CONFIGURATION_GROUP', 'configuration_group');
define('FILENAME_CONFIGURATION', 'configuration');
define('TACK_API_URL', $case['apiUrl']);
define('TACK_API_KEY', $case['apiKey']);
define('IS_ADMIN_FLAG', true);
require $repo . '/admin/includes/extra_datafiles/tack_quote.php';
// Zen Cart loads extra_definitions/ only for the admin's own language (breakpoint 65),
// then runs the auto_loaders' init scripts (TackQuote's at 66). `language` other than
// english = no TackQuote file for that language; a `translated` map stands in for a
// partial translation loaded at 65.
$language = isset($case['language']) ? $case['language'] : 'english';
if ($language === 'english') {
    require $repo . '/admin/includes/languages/english/extra_definitions/tack_quote.php';
}
foreach (isset($case['translated']) ? $case['translated'] : array() as $k => $v) {
    define($k, $v);
}
$autoLoadConfig = array();
require $repo . '/admin/includes/auto_loaders/config.tack_quote.php';
ksort($autoLoadConfig);
foreach ($autoLoadConfig as $breakpoint => $entries) {
    foreach ($entries as $entry) {
        if ($breakpoint <= 65 || $entry['autoType'] !== 'init_script') {
            echo 'BAD AUTOLOAD ENTRY ' . $breakpoint . PHP_EOL;
            continue;
        }
        require $repo . '/admin/includes/init_includes/' . $entry['loadFile'];
    }
}
if (!empty($case['menuOnly'])) {
    // What zen_get_admin_pages() needs for the two admin_pages rows install.sql adds.
    foreach (array('BOX_TACK_QUOTE_CONNECTION', 'BOX_CONFIGURATION_TACKQUOTE', 'FILENAME_TACK_QUOTE_CONNECTION') as $c) {
        echo $c . '=' . (defined($c) ? constant($c) : 'UNDEFINED') . PHP_EOL;
    }
    exit(0);
}

session_id('test');
$_SESSION = array('securityToken' => 'session_token_123');
$_GET = array();
$_POST = array();
$_SERVER['REQUEST_METHOD'] = 'GET';
if (!empty($case['post'])) {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_GET['action'] = 'test';
    if ($case['token'] !== null) {
        $_POST['securityToken'] = $case['token'];
    }
}

function zen_output_string_protected($s) { return htmlspecialchars((string) $s, ENT_COMPAT, 'UTF-8'); }
function zen_href_link($page, $params = '') { return 'index.php?cmd=' . $page . ($params === '' ? '' : '&' . $params); }
function zen_draw_form($name, $action, $params = '', $method = 'post')
{
    return '<form name="' . $name . '" action="' . zen_href_link($action, $params) . '" method="' . $method . '">'
        . ($method === 'post' ? '<input type="hidden" name="securityToken" value="' . $_SESSION['securityToken'] . '">' : '');
}
class FakeDbResult { public $EOF = false; public $fields = array('configuration_group_id' => 42); }
class FakeDb { public function Execute($sql) { return new FakeDbResult(); } }
$db = new FakeDb();

// application_top.php, admin_html_head.php, header.php, footer.php, application_bottom.php
$stage = sys_get_temp_dir() . '/tack-admin-stage-' . getmypid();
@mkdir($stage . '/includes', 0777, true);
foreach (array('application_top.php', 'admin_html_head.php', 'header.php', 'footer.php', 'application_bottom.php') as $f) {
    file_put_contents($stage . '/includes/' . $f, '<?php /* stub */');
}
chdir($stage);
require $repo . '/admin/tack_quote_connection.php';
