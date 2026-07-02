<?php

namespace UnifiedFeed;

defined( 'ABSPATH' ) || exit;

/**
 * Agrège les items de toutes les sources et génère le flux RSS unifié.
 */
class FeedAggregator {



    public function __construct(
        private readonly FeedResolver $resolver,
        private readonly FeedParser   $parser,
    ) {}

    /**
     * Collecte, trie et retourne les items de toutes les sources.
     *
     * @param string[] $sources
     * @return array<int, array<string, mixed>>
     */
    public function aggregate( array $sources ): array {
        $items = [];

        foreach ( $sources as $sourceUrl ) {
            $feedUrls = $this->resolver->resolve( $sourceUrl );

            foreach ( $feedUrls as $feedUrl ) {
                $fetched = $this->parser->fetch( $feedUrl );
                $items   = array_merge( $items, $fetched );
            }
        }

        usort( $items, fn( $a, $b ) => $b['timestamp'] - $a['timestamp'] );

        return array_slice( $items, 0, Config::MAX_ITEMS );
    }
}
