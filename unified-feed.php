<?php
/**
 * Plugin Name: Unified RSS Feed
 * Plugin URI:  https://boulot.wp.local
 * Description: Agrège plusieurs flux RSS externes en un seul flux unifié accessible via /feed/unified/
 * Version:     2.0.0
 * Author:      Studio
 * License:     GPL-2.0+
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

use UnifiedFeed\Plugin;

register_activation_hook(   __FILE__, [ Plugin::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ Plugin::class, 'deactivate' ] );

add_action( 'plugins_loaded', function (): void {
    Plugin::getInstance()->boot();
} );
