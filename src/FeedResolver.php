<?php

namespace UnifiedFeed;

defined( 'ABSPATH' ) || exit;

/**
 * Résout une URL source en un ou plusieurs flux RSS.
 * Détecte automatiquement les WordPress Multisite via l'API REST.
 */
class FeedResolver {

    private array $httpArgs;

    public function __construct() {
        $this->httpArgs = Config::HTTP_ARGS;
    }

    /**
     * Résout une source en liste de flux RSS.
     * Si l'URL contient déjà /feed, elle est utilisée directement.
     * Sinon, tente la détection Multisite via /wp-json/wp/v2/sites.
     *
     * @param string $sourceUrl
     * @return string[]
     */
    public function resolve( string $sourceUrl ): array {
        // URL contenant déjà /feed → flux explicite, on l'utilise directement
        if ( str_contains( $sourceUrl, '/feed' ) ) {
            return [ $sourceUrl ];
        }

        $baseUrl = rtrim( $sourceUrl, '/' );
        $apiUrl  = trailingslashit( $baseUrl ) . 'wp-json/';

        $response = wp_remote_get( $apiUrl, $this->httpArgs );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return [ trailingslashit( $sourceUrl ) . 'feed/' ];
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $data['namespaces'] ) && in_array( 'wp/v2', $data['namespaces'], true ) ) {
            $multisiteFeeds = $this->resolveMultisite( $baseUrl );
            if ( ! empty( $multisiteFeeds ) ) {
                return $multisiteFeeds;
            }
        }

        return [ trailingslashit( $sourceUrl ) . 'feed/' ];
    }

    /**
     * Tente de récupérer tous les sous-sites d'un Multisite via l'API REST.
     *
     * @return string[]
     */
    private function resolveMultisite( string $baseUrl ): array {
        $sitesUrl = trailingslashit( $baseUrl ) . 'wp-json/wp/v2/sites';
        $response = wp_remote_get( $sitesUrl, $this->httpArgs );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return [];
        }

        $sites = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $sites ) || empty( $sites ) ) {
            return [];
        }

        $feeds = [];
        foreach ( $sites as $site ) {
            if ( isset( $site['link'] ) ) {
                $feeds[] = trailingslashit( $site['link'] ) . 'feed/';
            }
        }

        return $feeds;
    }
}
