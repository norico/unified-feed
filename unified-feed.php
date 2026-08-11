<?php
/**
 * @FilePath: wp-content/mu-plugins/unified-feed.php
 * @Description: Expose les derniers articles du réseau au format JSON avec détails (images, auteurs, taxonomies).
 * @Author: norico
 * @Date: 11 août 2026 21:19:09
 * @LastEditors: norico
 * @LastEditTime: 11 août 2026 23:03:55
 * @Version: 1.1.0
 */

/**
 * Plugin Name: Unified Feed - Global Feed
 */

class UnifiedFeed {

	/**
	 * Configuration de l'API
	 */
	private static int $limit_per_site = 10;                    // Nombre max de contenus récupérés PAR sous-site
	private static int $max_global_posts = 50;                  // Limite globale de contenus renvoyés par le conteneur
	private static array $post_types = ['post', 'page'];        // Types de contenus à inclure
	private static array $category_slugs = [];                  // Catégories à filtrer.
	
	private static bool $content = false;                       // Inclure le contenu des articles.
	
	/*
	 NOTE: l'ajout d'un filtre sur les catégories supprime d'office les pages.
	       (à moins que tu n'utilises une extension qui active les catégories sur les pages)
	 */
	
	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register_route' ] );
	}

	public static function register_route(): void {
		register_rest_route( 'intranet/v1', '/feed', [
			'methods'             => 'GET',
			'callback'            => [ self::class, 'get_global_feed' ],
			'permission_callback' => '__return_true'
		] );
	}

	public static function get_global_feed( \WP_REST_Request $request ): WP_Error|WP_REST_Response|WP_HTTP_Response {
		$articles = [];

		// Les arguments de notre requête WordPress
		$args = [
			'numberposts' => self::$limit_per_site,
			'post_status' => 'publish',
			'post_type'   => self::$post_types,
		];

		if (!empty(self::$category_slugs)) {
			$args['category_name'] = implode(',', self::$category_slugs);
		}
		

		if ( is_multisite() ) {
			$sites = get_sites( [ 'public' => 1, 'archived' => 0, 'deleted' => 0 ] );

			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
				$posts = get_posts( $args );

				foreach ( $posts as $post ) {
					$articles[] = self::format_post( $post, $site->domain );
				}
				restore_current_blog();
			}
		} else {
			$posts = get_posts( $args );
			$host  = $_SERVER['HTTP_HOST'] ?? '';

			foreach ( $posts as $post ) {
				$articles[] = self::format_post( $post, $host );
			}
		}

		// Tri global
		usort( $articles, function ( $a, $b ) {
			return $b['timestamp'] <=> $a['timestamp'];
		} );

		// Limite globale de la réponse
		return rest_ensure_response( array_slice( $articles, 0, self::$max_global_posts ) );
	}

	/**
	 * Formate un objet WP_Post en tableau standardisé pour l'API.
	 */
	private static function format_post( \WP_Post $post, string $host ): array {
		$author_id = $post->post_author;
		
		return [
			'title'       => get_the_title( $post ),
			'permalink'   => get_permalink( $post ),
			'timestamp'   => strtotime( $post->post_date_gmt ),
			'source_host' => self::get_site_details($host),

			'date'        => $post->post_date,

			// Contenu
			'excerpt'     => self::clean_excerpt($post),
			'content'     => self::$content ? apply_filters('the_content', $post->post_content) : 'not parsed',

			
			

			// Type et format
			'post_type'   => get_post_type($post),
			'post_format' => get_post_format($post->ID) ?: 'standard',
			
			// Image à la une
			'thumbnail'   => self::get_thumbnail_details($post->ID),

			// Auteur
			'author'      => self::get_author_details($post->post_author),

			// Taxonomies (utilise wp_list_pluck pour extraire facilement les noms)
			'categories'  => self::get_term_details( $post->ID, 'category' ),
			'tags'        => self::get_term_details( $post->ID, 'post_tag' ),
		];
	}

	/**
	 * Récupère les détails du site/blog source actuel.
	 */
	private static function get_site_details(string $host): array {
		return [
			'ID'          => get_current_blog_id(),
			'name'        => get_bloginfo('name'),
			'description' => get_bloginfo('description'),
			'url'         => home_url('/'),
			'host'        => $host,
		];
	}

	/**
	 * Nettoie et génère un extrait propre (gère la balise <!--more--> et les fins de phrases).
	 */
	private static function clean_excerpt(\WP_Post $post): string {
		$content = $post->post_content;

		// 1. Si l'auteur a explicitement mis une balise <!--more--> dans le contenu
		if ( str_contains( $content, '<!--more-->' ) ) {
			$parts = explode('<!--more-->', $content);
			// On prend tout ce qui est avant le more, en retirant les balises HTML potentielles et les espaces
			$excerpt = wp_strip_all_tags($parts[0]);
			return trim($excerpt);
		}

		// 2. Sinon, on prend l'extrait classique de WordPress
		$excerpt = get_the_excerpt($post);

		// 3. On supprime les balises de suspension indésirables
		$excerpt = str_replace(['[&hellip;]', '[...]', '&hellip;', '&#8230;'], '', $excerpt);
		$excerpt = trim($excerpt);

		// 4. On cherche la dernière occurrence d'une ponctuation forte
		if (preg_match('/^(.*[.?!])/us', $excerpt, $matches)) {
			return trim($matches[1]);
		}

		// 5. Repli final
		return $excerpt;
	}
	
	/**
	 * Récupère les URLs des différentes tailles de l'image à la une.
	 */
	private static function get_thumbnail_details(int $post_id): ?array {
		$thumbnail_id = get_post_thumbnail_id($post_id);
		if (!$thumbnail_id) {
			return null;
		}
		return [
			'ID'        => $thumbnail_id,
			'thumbnail' => wp_get_attachment_image_url($thumbnail_id, 'thumbnail') ?: '',
			'medium'    => wp_get_attachment_image_url($thumbnail_id, 'medium') ?: '',
			'large'     => wp_get_attachment_image_url($thumbnail_id, 'large') ?: '',
			'full'      => wp_get_attachment_image_url($thumbnail_id, 'full') ?: '',
			'html'      => wp_get_attachment_image($thumbnail_id, 'medium') ?: '',
		];
	}

	/**
	 * Récupère les détails de l'auteur.
	 */
	private static function get_author_details(int $author_id): array {
		return [
			'ID'          => $author_id,
			'name'        => get_the_author_meta('display_name', $author_id),
			'description' => get_the_author_meta('description', $author_id),
			'permalink'   => get_author_posts_url($author_id),
			'avatar'      => get_avatar_url($author_id),
		];
	}

	/**
	 * Récupère les détails des termes d'une taxonomie (ID, nom, description, permalien).
	 */
	private static function get_term_details(int $post_id, string $taxonomy): array {
		$terms = get_the_terms($post_id, $taxonomy);

		if (is_wp_error($terms) || empty($terms)) {
			return [];
		}

		$term_details = [];
		foreach ($terms as $term) {
			$link = get_term_link($term);

			$term_details[] = [
				'ID'          => $term->term_id,
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
				'permalink'   => is_wp_error($link) ? '' : $link,
			];
		}

		return $term_details;
	}

}

// Initialisation
UnifiedFeed::init();