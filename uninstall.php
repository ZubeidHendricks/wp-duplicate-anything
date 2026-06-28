<?php
/**
 * Uninstall cleanup.
 *
 * @package DuplicateAnything
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'duplicate-anything_options' );
