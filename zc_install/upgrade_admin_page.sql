-- TackQuote for Zen Cart: upgrade patch that adds the admin menu entries
-- (Configuration > TackQuote and Tools > TackQuote Test connection) to a store
-- that ran install.sql before 1.1.1.
--
-- Run it after copying the new admin/ files (see README). Safe to re-run: each
-- INSERT is guarded by NOT EXISTS on its page key. Requires the "TackQuote"
-- configuration group from install.sql.

-- Admin menu entries (Zen Cart 1.5.0+ builds the admin menu ONLY from `admin_pages`;
-- zen_get_admin_pages() lists a row when its main_page and language_key constants
-- are defined). Both are guarded by NOT EXISTS on the page key, so this is safe to re-run.
-- Each subquery's SELECT sits on its own line ON PURPOSE: Tools > Install SQL Patches
-- (admin/sqlpatch.php) adds the store's table prefix line by line, and only to a line
-- that STARTS with a keyword it knows (INSERT INTO, SELECT ... FROM, FROM, ...). A
-- `FROM admin_pages` in the middle of an `AND NOT EXISTS (...)` line stays unprefixed,
-- and a prefixed store then fails with MySQL error 1146. tests/SqlPatchPrefixTest.php
-- runs every file here through that parser's rules.
--   * Configuration > TackQuote: the settings above.
--   * Tools > TackQuote Test connection: admin/tack_quote_connection.php.
-- The constants come from admin/includes/extra_datafiles/tack_quote.php and
-- admin/includes/languages/english/extra_definitions/tack_quote.php.
INSERT INTO admin_pages
  (page_key, language_key, main_page, page_params, menu_key, display_on_menu, sort_order)
SELECT 'configTackQuote', 'BOX_CONFIGURATION_TACKQUOTE', 'FILENAME_CONFIGURATION',
       CONCAT('gID=', cg.configuration_group_id), 'configuration', 'Y', 99
FROM configuration_group cg
WHERE cg.configuration_group_title = 'TackQuote'
  AND NOT EXISTS (
    SELECT 1 FROM admin_pages ap WHERE ap.page_key = 'configTackQuote'
  )
LIMIT 1;

INSERT INTO admin_pages
  (page_key, language_key, main_page, page_params, menu_key, display_on_menu, sort_order)
SELECT 'toolsTackQuoteConnection', 'BOX_TACK_QUOTE_CONNECTION', 'FILENAME_TACK_QUOTE_CONNECTION',
       '', 'tools', 'Y', 99
FROM configuration_group cg
WHERE cg.configuration_group_title = 'TackQuote'
  AND NOT EXISTS (
    SELECT 1 FROM admin_pages ap WHERE ap.page_key = 'toolsTackQuoteConnection'
  )
LIMIT 1;
