<?php
/**
 * TackQuote for Zen Cart: the admin page's filename constant.
 *
 * Zen Cart loads every file in admin/includes/extra_datafiles/ on each admin request,
 * and the admin menu resolves an admin_pages row's `main_page` through this constant
 * (zen_get_admin_pages() skips a row whose constant is not defined).
 */

if (!defined('FILENAME_TACK_QUOTE_CONNECTION')) {
    define('FILENAME_TACK_QUOTE_CONNECTION', 'tack_quote_connection');
}
