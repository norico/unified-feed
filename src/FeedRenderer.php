<?php

namespace UnifiedFeed;

defined( 'ABSPATH' ) || exit;

/**
 * Génère la sortie RSS 2.0 du flux unifié.
 */
class FeedRenderer {

    /**
     * Envoie les headers et imprime le flux RSS.
     *
     * @param array<int, array<string, mixed>> $items
     */
    public function render( array $items ): void {
        $siteName  = get_bloginfo( 'name' );
        $siteUrl   = get_bloginfo( 'url' );
        $buildDate = gmdate( 'D, d M Y H:i:s +0000' );

        header( 'Content-Type: application/rss+xml; charset=UTF-8' );
        header( 'X-Robots-Tag: noindex' );

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0"'
            . ' xmlns:content="http://purl.org/rss/1.0/modules/content/"'
            . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
            . ' xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        echo '<channel>' . "\n";
        echo '  <title>' . esc_xml( $siteName . ' — Flux unifié' ) . '</title>' . "\n";
        echo '  <link>' . esc_url( $siteUrl ) . '</link>' . "\n";
        echo '  <description>Agrégation des flux RSS de tous les sites</description>' . "\n";
        echo '  <language>fr-FR</language>' . "\n";
        echo '  <lastBuildDate>' . esc_xml( $buildDate ) . '</lastBuildDate>' . "\n";
        echo '  <atom:link href="' . esc_url( $siteUrl . '/feed/unified/' ) . '" rel="self" type="application/rss+xml" />' . "\n";

        foreach ( $items as $item ) {
            $this->renderItem( $item, $buildDate );
        }

        echo '</channel>' . "\n";
        echo '</rss>' . "\n";
    }

    /**
     * Imprime un élément <item>.
     *
     * @param array<string, mixed> $item
     */
    private function renderItem( array $item, string $fallbackDate ): void {
        $pubDate = $item['timestamp']
            ? gmdate( 'D, d M Y H:i:s +0000', (int) $item['timestamp'] )
            : $fallbackDate;

        echo "  <item>\n";
        echo '    <title>' . esc_xml( $item['title'] ) . '</title>' . "\n";
        echo '    <link>' . esc_url( $item['link'] ) . '</link>' . "\n";
        echo '    <pubDate>' . esc_xml( $pubDate ) . '</pubDate>' . "\n";
        echo '    <guid isPermaLink="true">' . esc_url( $item['guid'] ) . '</guid>' . "\n";

        if ( ! empty( $item['author'] ) ) {
            echo '    <dc:creator>' . esc_xml( $item['author'] ) . '</dc:creator>' . "\n";
        }

        echo '    <source url="' . esc_url( $item['source_url'] ) . '">' . esc_xml( $item['source'] ) . '</source>' . "\n";
        echo '    <description><![CDATA[' . $item['description'] . ']]></description>' . "\n";
        echo '    <content:encoded><![CDATA[' . $item['content'] . ']]></content:encoded>' . "\n";
        echo "  </item>\n";
    }
}
