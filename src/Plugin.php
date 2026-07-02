<?php

namespace UnifiedFeed;

defined( 'ABSPATH' ) || exit;

/**
 * Point d'entrée principal du plugin.
 * Enregistre les hooks WordPress et orchestre les services.
 */
class Plugin {

    private static ?self $instance = null;

    private Settings      $settings;
    private FeedAggregator $aggregator;
    private FeedRenderer  $renderer;

    private function __construct() {
        $this->settings   = new Settings();
        $this->aggregator = new FeedAggregator( new FeedResolver(), new FeedParser() );
        $this->renderer   = new FeedRenderer();
    }

    /**
     * Singleton — une seule instance par requête.
     */
    public static function getInstance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Enregistre tous les hooks WordPress.
     */
    public function boot(): void {
        // Rewrite rule + query var
        add_action( 'init',         [ $this, 'addRewriteRule' ] );
        add_filter( 'query_vars',   [ $this, 'addQueryVar' ] );

        // Flux unifié
        add_action( 'template_redirect', [ $this, 'handleFeedRequest' ] );

        // Administration
        $this->settings->register();
    }

    public function addRewriteRule(): void {
        add_rewrite_rule( '^feed/unified/?$', 'index.php?unified_feed=1', 'top' );
    }

    /**
     * @param string[] $vars
     * @return string[]
     */
    public function addQueryVar( array $vars ): array {
        $vars[] = 'unified_feed';
        return $vars;
    }

    public function handleFeedRequest(): void {
        if ( ! get_query_var( 'unified_feed' ) ) {
            return;
        }

        $sources = Settings::getSources();
        $items   = $this->aggregator->aggregate( $sources );

        $this->renderer->render( $items );
        exit;
    }

    /**
     * Appelé à l'activation du plugin.
     */
    public static function activate(): void {
        add_rewrite_rule( '^feed/unified/?$', 'index.php?unified_feed=1', 'top' );
        flush_rewrite_rules();
    }

    /**
     * Appelé à la désactivation du plugin.
     */
    public static function deactivate(): void {
        flush_rewrite_rules();
    }
}
