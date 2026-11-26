<?php
/**
 * TackQuote for Zen Cart: admin "Test connection" page.
 *
 * Calls TackApiClient::checkConnection() with the saved TACK_API_URL / TACK_API_KEY
 * and shows the verdict. It reads settings and sends one GET to TackQuote; it writes
 * nothing.
 *
 * Built the way Zen Cart's own admin pages are (admin/server_info.php): application_top,
 * admin_html_head.php (Zen Cart 1.5.7+), header.php, footer.php, application_bottom.php.
 * The test runs only on a POST from zen_draw_form(), which carries the admin session's
 * securityToken; init_sessions.php already refuses a POST with an `action` whose token
 * does not match, and this page checks the token again before it calls TackQuote.
 * Every value printed (the API URL, TackQuote's own error text) goes through
 * zen_output_string_protected().
 *
 * Registered in the admin menu by zc_install/install.sql (or upgrade_admin_page.sql)
 * as page key `toolsTackQuoteConnection` under Tools.
 */

require 'includes/application_top.php';

$tackQuoteApiUrl = defined('TACK_API_URL') ? trim((string) TACK_API_URL) : '';
$tackQuoteApiKey = defined('TACK_API_KEY') ? trim((string) TACK_API_KEY) : '';

/** @var array{ok: bool, text: string}|null $tackQuoteVerdict */
$tackQuoteVerdict = null;

$tackQuoteAction = isset($_GET['action']) ? (string) $_GET['action'] : '';
if ($tackQuoteAction === 'test' && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['securityToken'], $_POST['securityToken'])
        || !hash_equals((string) $_SESSION['securityToken'], (string) $_POST['securityToken'])) {
        $tackQuoteVerdict = array('ok' => false, 'text' => TACK_QUOTE_TEXT_TOKEN_INVALID);
    } else {
        $tackQuoteClientFile = DIR_FS_CATALOG . 'includes/classes/tack_api_client.php';
        if (!is_file($tackQuoteClientFile)) {
            $tackQuoteVerdict = array('ok' => false, 'text' => TACK_QUOTE_TEXT_CLIENT_MISSING);
        } else {
            require_once $tackQuoteClientFile;
            $tackQuoteClient = new TackApiClient($tackQuoteApiUrl, $tackQuoteApiKey);
            $tackQuoteMessage = TackApiClient::connectionMessage($tackQuoteClient->checkConnection());
            $tackQuoteVerdict = $tackQuoteMessage === true
                ? array('ok' => true, 'text' => TACK_QUOTE_TEXT_CONNECTED)
                : array('ok' => false, 'text' => TACK_QUOTE_TEXT_NOT_CONNECTED . ' ' . (string) $tackQuoteMessage);
        }
    }
}

// The settings live in the "TackQuote" configuration group that install.sql created.
$tackQuoteSettingsHref = '';
$tackQuoteGroup = $db->Execute(
    "SELECT configuration_group_id FROM " . TABLE_CONFIGURATION_GROUP
    . " WHERE configuration_group_title = 'TackQuote' LIMIT 1"
);
if (!$tackQuoteGroup->EOF) {
    $tackQuoteSettingsHref = zen_href_link(
        FILENAME_CONFIGURATION,
        'gID=' . (int) $tackQuoteGroup->fields['configuration_group_id']
    );
}
?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
<head>
    <?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
</head>
<body>
<!-- header //-->
<?php require DIR_WS_INCLUDES . 'header.php'; ?>
<!-- header_eof //-->
<!-- body //-->
<div class="container-fluid">
    <h1 class="pageHeading"><?php echo TACK_QUOTE_HEADING_TITLE; ?></h1>
    <p><?php echo zen_output_string_protected(TACK_QUOTE_TEXT_INTRO); ?></p>

<?php if ($tackQuoteVerdict !== null) { ?>
    <div class="alert <?php echo $tackQuoteVerdict['ok'] ? 'alert-success' : 'alert-danger'; ?>" role="status" id="tack-quote-verdict"><?php echo zen_output_string_protected($tackQuoteVerdict['text']); ?></div>
<?php } ?>

    <dl class="dl-horizontal">
        <dt><?php echo TACK_QUOTE_TEXT_API_URL; ?></dt>
        <dd><?php echo $tackQuoteApiUrl === '' ? TACK_QUOTE_TEXT_NOT_SET : zen_output_string_protected($tackQuoteApiUrl); ?></dd>
        <dt><?php echo TACK_QUOTE_TEXT_API_KEY; ?></dt>
        <dd><?php echo $tackQuoteApiKey === '' ? TACK_QUOTE_TEXT_KEY_NOT_SET : TACK_QUOTE_TEXT_KEY_SET; ?></dd>
    </dl>

    <?php echo zen_draw_form('tack_quote_test', FILENAME_TACK_QUOTE_CONNECTION, 'action=test', 'post'); ?>
        <button type="submit" class="btn btn-primary"><?php echo TACK_QUOTE_BUTTON_TEST; ?></button>
<?php if ($tackQuoteSettingsHref !== '') { ?>
        <a class="btn btn-default" href="<?php echo $tackQuoteSettingsHref; /* zen_href_link() of an int gID: nothing to escape */ ?>"><?php echo TACK_QUOTE_TEXT_SETTINGS_LINK; ?></a>
<?php } ?>
    </form>
</div>
<!-- body_eof //-->
<!-- footer //-->
<?php require DIR_WS_INCLUDES . 'footer.php'; ?>
<!-- footer_eof //-->
</body>
</html>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
