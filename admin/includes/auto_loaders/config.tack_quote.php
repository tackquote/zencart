<?php
/**
 * TackQuote for Zen Cart: runs init_tack_quote_language.php right after Zen Cart loads
 * the admin language files (init_languages.php is breakpoint 65 in config.core.php,
 * v1.5.7 through v2.2.2). Zen Cart's InitSystem reads every `config.*.php` file in
 * admin/includes/auto_loaders/.
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

$autoLoadConfig[66][] = array(
    'autoType' => 'init_script',
    'loadFile' => 'init_tack_quote_language.php',
);
