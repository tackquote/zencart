<?php
/**
 * TackQuote for Zen Cart: English fallback for the admin text.
 *
 * Zen Cart loads admin/includes/languages/<admin language>/extra_definitions/ only for
 * the admin's own language. On an admin using any language other than English, the
 * TackQuote constants would stay undefined: zen_get_admin_pages() then leaves both
 * TackQuote menu entries out, and the Test connection page stops on an undefined
 * constant. This file runs after the language files (see
 * auto_loaders/config.tack_quote.php) and loads the English file, which defines only the
 * constants still missing (every define there is guarded), so a translated file loaded
 * first always wins, and a partial translation is completed in English.
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

// Guarded: a store that skipped the English file must still be able to open its admin.
// Without the file the TackQuote menu entries are just absent, as they were before.
$tackQuoteEnglishText = __DIR__ . '/../languages/english/extra_definitions/tack_quote.php';
if (is_file($tackQuoteEnglishText)) {
    require_once $tackQuoteEnglishText;
}
unset($tackQuoteEnglishText);
