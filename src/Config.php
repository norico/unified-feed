<?php

namespace UnifiedFeed;

defined( 'ABSPATH' ) || exit;

/**
 * Centralise toute la configuration du plugin.
 */
class Config {

    /** Nombre maximum d'items retournés dans le flux unifié. */
    public const int MAX_ITEMS = 100;

    /** Arguments HTTP communs à toutes les requêtes wp_remote_get. */
    public const array HTTP_ARGS = [
        'timeout'   => 30,
        'sslverify' => false,
    ];

    /** Arguments HTTP pour les requêtes rapides (détection, vérification). */
    public const array HTTP_ARGS_FAST = [
        'timeout'   => 15,
        'sslverify' => false,
    ];
}
