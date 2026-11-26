<?php
/**
 * TackQuote for Zen Cart: admin text.
 *
 * A plain define() file, not a `lang.*.php` array: Zen Cart 2.x still loads every
 * non-`lang.` file in admin extra_definitions/ (AdminFilesLanguageLoader), and 1.5.7
 * loads nothing else. Each define is guarded, so a second load can never raise a
 * "constant already defined" notice.
 *
 * The two BOX_ constants are the admin menu labels named by the admin_pages rows in
 * zc_install/install.sql; a row whose label constant is undefined is left out of the menu.
 */

$tackQuoteAdminText = array(
    'BOX_TACK_QUOTE_CONNECTION' => 'TackQuote Test connection',
    'BOX_CONFIGURATION_TACKQUOTE' => 'TackQuote',
    'TACK_QUOTE_HEADING_TITLE' => 'TackQuote: Test connection',
    'TACK_QUOTE_TEXT_INTRO' => 'Checks that this store can reach the TackQuote API at the API URL set under Configuration > TackQuote, and that TackQuote accepts the API key. It sends one request to TackQuote and changes nothing.',
    'TACK_QUOTE_TEXT_API_URL' => 'TackQuote API URL:',
    'TACK_QUOTE_TEXT_API_KEY' => 'TackQuote API key:',
    'TACK_QUOTE_TEXT_KEY_SET' => 'set',
    'TACK_QUOTE_TEXT_KEY_NOT_SET' => 'not set',
    'TACK_QUOTE_TEXT_NOT_SET' => '(not set)',
    'TACK_QUOTE_BUTTON_TEST' => 'Test connection',
    'TACK_QUOTE_TEXT_SETTINGS_LINK' => 'Edit the TackQuote settings',
    'TACK_QUOTE_TEXT_CONNECTED' => 'Connected: TackQuote accepted this API key.',
    'TACK_QUOTE_TEXT_NOT_CONNECTED' => 'Not connected:',
    'TACK_QUOTE_TEXT_CLIENT_MISSING' => 'The TackQuote API client is missing: copy includes/classes/tack_api_client.php from the TackQuote release into your store.',
    'TACK_QUOTE_TEXT_TOKEN_INVALID' => 'Your admin session expired. Reload the page and test again.',
);
foreach ($tackQuoteAdminText as $tackQuoteKey => $tackQuoteText) {
    if (!defined($tackQuoteKey)) {
        define($tackQuoteKey, $tackQuoteText);
    }
}
unset($tackQuoteAdminText, $tackQuoteKey, $tackQuoteText);
