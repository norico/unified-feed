<?php

namespace UnifiedFeed;

defined( 'ABSPATH' ) || exit;

/**
 * Récupère et parse un flux RSS 2.0 ou Atom.
 * Retourne un tableau normalisé d'items.
 */
class FeedParser {

    private array $httpArgs;

    public function __construct() {
        $this->httpArgs = Config::HTTP_ARGS;
    }

    /**
     * Récupère et parse le flux à l'URL donnée.
     *
     * @param string $feedUrl
     * @return array<int, array<string, mixed>>
     */
    public function fetch( string $feedUrl ): array {
        $response = wp_remote_get( $feedUrl, $this->httpArgs );

        if ( is_wp_error( $response ) ) {
            error_log( sprintf(
                '[UnifiedFeed] ❌ Erreur réseau : %s — %s',
                $feedUrl,
                $response->get_error_message()
            ) );
            return [];
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            error_log( sprintf(
                '[UnifiedFeed] ❌ HTTP %s : %s',
                $code,
                $feedUrl
            ) );
            return [];
        }

        $body = wp_remote_retrieve_body( $response );
        $body = preg_replace( '/^\xEF\xBB\xBF/', '', $body ); // supprime le BOM

        libxml_use_internal_errors( true );
        $xml = simplexml_load_string( $body );
        libxml_clear_errors();

        if ( ! $xml ) {
            return [];
        }

        if ( isset( $xml->channel ) ) {
            return $this->parseRss( $xml );
        }

        if ( isset( $xml->entry ) ) {
            return $this->parseAtom( $xml );
        }

        return [];
    }

    /**
     * Parse un flux RSS 2.0.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseRss( \SimpleXMLElement $xml ): array {
        $items = [];

        foreach ( $xml->channel->item as $item ) {
            $nsDc      = $item->children( 'http://purl.org/dc/elements/1.1/' );
            $nsContent = $item->children( 'http://purl.org/rss/1.0/modules/content/' );
            $pubDate   = (string) $item->pubDate;

            $items[] = [
                'title'       => (string) $item->title,
                'link'        => (string) $item->link,
                'description' => (string) $item->description,
                'content'     => isset( $nsContent->encoded )
                                    ? (string) $nsContent->encoded
                                    : (string) $item->description,
                'pubDate'     => $pubDate,
                'timestamp'   => $pubDate ? strtotime( $pubDate ) : 0,
                'author'      => isset( $nsDc->creator ) ? (string) $nsDc->creator : '',
                'source'      => (string) $xml->channel->title,
                'source_url'  => (string) $xml->channel->link,
                'guid'        => (string) $item->guid,
            ];
        }

        return $items;
    }

    /**
     * Parse un flux Atom.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseAtom( \SimpleXMLElement $xml ): array {
        $items = [];

        foreach ( $xml->entry as $entry ) {
            $updated = (string) $entry->updated;
            $link    = '';

            foreach ( $entry->link as $l ) {
                $rel = (string) $l['rel'];
                if ( $rel === 'alternate' || $rel === '' ) {
                    $link = (string) $l['href'];
                    break;
                }
            }

            $items[] = [
                'title'       => (string) $entry->title,
                'link'        => $link,
                'description' => (string) $entry->summary,
                'content'     => isset( $entry->content )
                                    ? (string) $entry->content
                                    : (string) $entry->summary,
                'pubDate'     => $updated,
                'timestamp'   => $updated ? strtotime( $updated ) : 0,
                'author'      => isset( $entry->author->name ) ? (string) $entry->author->name : '',
                'source'      => isset( $xml->title ) ? (string) $xml->title : '',
                'source_url'  => isset( $xml->link )  ? (string) $xml->link  : '',
                'guid'        => isset( $entry->id )   ? (string) $entry->id  : $link,
            ];
        }

        return $items;
    }
}
