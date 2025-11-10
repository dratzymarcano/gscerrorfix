<?php
// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('gsc_schema_fix_options');

// For multisite
delete_site_option('gsc_schema_fix_options');

// Clean up any transients
delete_transient('gsc_schema_fix_cache');