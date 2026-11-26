-- TackQuote for Zen Cart — uninstaller SQL. Removes the settings and admin menu
-- entries added by install.sql. Safe to re-run.

DELETE FROM configuration WHERE configuration_key IN (
  'TACK_API_URL', 'TACK_API_KEY', 'TACK_ENABLE_WIDGET', 'TACK_BUTTON_LABEL',
  'TACK_CONNECTOR_TOKEN'
);

DELETE FROM admin_pages_to_profiles WHERE page_key IN ('configTackQuote', 'toolsTackQuoteConnection');
DELETE FROM admin_pages WHERE page_key IN ('configTackQuote', 'toolsTackQuoteConnection');

DELETE FROM configuration_group WHERE configuration_group_title = 'TackQuote';
