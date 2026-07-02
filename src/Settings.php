<?php

namespace UnifiedFeed;

defined( 'ABSPATH' ) || exit;

/**
 * Gestion des réglages (options WordPress + page d'administration).
 */
class Settings {

    public const OPTION_KEY = 'unified_feed_sources';
    public const GROUP      = 'unified_feed_group';
    public const PAGE_SLUG  = 'unified-feed';

    /**
     * Retourne la liste des sources enregistrées.
     * Fallback sur le flux du site courant si aucun réglage.
     *
     * @return string[]
     */
    public static function getSources(): array {
        $saved = get_option( self::OPTION_KEY, [] );

        if ( ! empty( $saved ) && is_array( $saved ) ) {
            return apply_filters( 'unified_feed_sources', $saved );
        }

        $default = [ trailingslashit( home_url() ) . 'feed/' ];

        return apply_filters( 'unified_feed_sources', $default );
    }

    /**
     * Enregistre les hooks d'administration.
     */
    public function register(): void {
        add_action( 'admin_menu',  [ $this, 'addMenuPage' ] );
        add_action( 'admin_init',  [ $this, 'registerSetting' ] );
        add_action( 'wp_ajax_unified_feed_check', [ $this, 'ajaxCheckFeed' ] );
    }

    public function addMenuPage(): void {
        add_options_page(
            'Flux RSS Unifié',
            'Flux RSS Unifié',
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'renderPage' ]
        );
    }

    public function registerSetting(): void {
        register_setting(
            self::GROUP,
            self::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [ $this, 'sanitizeSources' ],
            ]
        );
    }

    /**
     * Nettoie et dédoublonne les URLs saisies.
     *
     * @param mixed $input
     * @return string[]
     */
    public function sanitizeSources( mixed $input ): array {
        if ( ! is_array( $input ) ) {
            return [];
        }

        $clean = [];
        foreach ( $input as $url ) {
            $url = esc_url_raw( trim( $url ) );
            if ( ! empty( $url ) ) {
                $clean[] = $url;
            }
        }

        return array_unique( $clean );
    }

    /**
     * AJAX : vérifie qu'un flux est accessible.
     */
    public function ajaxCheckFeed(): void {
        check_ajax_referer( 'unified_feed_check_nonce', false, false );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'error' => 'Non autorisé' ] );
        }

        $url      = esc_url_raw( wp_unslash( $_GET['url'] ?? '' ) );
        $response = wp_remote_get( $url, Config::HTTP_ARGS_FAST );

        if ( is_wp_error( $response ) ) {
            $error = $response->get_error_message();
            error_log( sprintf(
                '[UnifiedFeed] ❌ Flux inaccessible : %s — Raison : %s',
                $url,
                $error
            ) );
            wp_send_json( [ 'ok' => false, 'error' => $error ] );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $ok   = $code === 200;

        if ( ! $ok ) {
            error_log( sprintf(
                '[UnifiedFeed] ❌ Flux inaccessible : %s — HTTP %s',
                $url,
                $code
            ) );
        }

        wp_send_json( [ 'ok' => $ok ] );
    }

    /**
     * Affiche la page de réglages.
     */
    public function renderPage(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $sources  = self::getSources();
        $feed_url = home_url( '/feed/unified/' );
        $ajax_url = admin_url( 'admin-ajax.php' );
        $nonce    = wp_create_nonce( 'unified_feed_check_nonce' );
        ?>
        <div class="wrap">
            <h1>⚙️ Flux RSS Unifié</h1>

            <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;margin:16px 0;max-width:700px;">
                <strong>🔗 URL de votre flux unifié :</strong><br>
                <code style="font-size:14px;background:#f0f0f1;padding:6px 10px;display:inline-block;margin-top:6px;border-radius:3px;user-select:all;"><?php echo esc_url( $feed_url ); ?></code>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields( self::GROUP ); ?>

                <h2>Sources RSS</h2>
                <p>Ajoutez les URLs des flux RSS à agréger. Le flux d'un site WordPress se termine généralement par <code>/feed/</code>.</p>

                <table class="widefat" style="max-width:700px;">
                    <thead>
                        <tr>
                            <th>URL du flux RSS</th>
                            <th style="width:80px;">Statut</th>
                            <th style="width:60px;">Suppr.</th>
                        </tr>
                    </thead>
                    <tbody id="unified-feed-rows">
                    <?php foreach ( $sources as $url ) : ?>
                        <tr class="feed-row">
                            <td>
                                <input
                                    type="url"
                                    name="<?php echo esc_attr( self::OPTION_KEY ); ?>[]"
                                    value="<?php echo esc_attr( $url ); ?>"
                                    class="regular-text"
                                    placeholder="https://monsite.com/feed/"
                                    style="width:100%;"
                                >
                            </td>
                            <td style="text-align:center;" class="feed-status">⏳</td>
                            <td style="text-align:center;">
                                <button type="button" class="button-link remove-row" style="color:#b32d2e;font-size:18px;line-height:1;" title="Supprimer">&times;</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <p>
                    <button type="button" id="add-feed-row" class="button">+ Ajouter un flux</button>
                </p>

                <?php submit_button( 'Enregistrer les sources' ); ?>
            </form>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ajaxUrl = <?php echo wp_json_encode( $ajax_url ); ?>;
            const nonce   = <?php echo wp_json_encode( $nonce ); ?>;

            document.querySelectorAll('.feed-row').forEach(checkRow);

            document.getElementById('add-feed-row').addEventListener('click', function () {
                const tbody = document.getElementById('unified-feed-rows');
                const row   = document.createElement('tr');
                row.className = 'feed-row';
                row.innerHTML = `
                    <td><input type="url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[]" value="" class="regular-text" placeholder="https://monsite.com/feed/" style="width:100%;"></td>
                    <td style="text-align:center;" class="feed-status">—</td>
                    <td style="text-align:center;"><button type="button" class="button-link remove-row" style="color:#b32d2e;font-size:18px;line-height:1;" title="Supprimer">&times;</button></td>
                `;
                tbody.appendChild(row);
                bindRemove(row);
            });

            document.querySelectorAll('.remove-row').forEach(btn => bindRemove(btn.closest('tr')));

            function bindRemove(row) {
                row.querySelector('.remove-row').addEventListener('click', () => row.remove());
            }

            function checkRow(row) {
                const input  = row.querySelector('input[type=url]');
                const status = row.querySelector('.feed-status');
                const url    = input ? input.value.trim() : '';
                if (!url) { status.textContent = '—'; return; }

                fetch(ajaxUrl + '?action=unified_feed_check&_wpnonce=' + nonce + '&url=' + encodeURIComponent(url))
                    .then(r => r.json())
                    .then(data => {
                        status.textContent = data.ok ? '✅' : '❌';
                        status.title = data.ok ? 'Flux accessible' : (data.error || 'Inaccessible');
                    })
                    .catch(() => { status.textContent = '❓'; });
            }
        });
        </script>
        <?php
    }
}
