<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       http://www.awakensolutions.com
 * @since      1.0.0
 *
 * @package    Simple_Restrict
 * @subpackage Simple_Restrict/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Simple_Restrict
 * @subpackage Simple_Restrict/public
 * @author     Awaken Solutions Inc. <info@awakensolutions.com>
 */
class Simple_Restrict_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $simple_restrict The ID of this plugin.
	 */
	private $simple_restrict;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $version The current version of this plugin.
	 */
	private $version;

	public $taxonomy_terms_object_array = array();
	public $generic_restricted_message;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param  string $simple_restrict  The name of the plugin.
	 * @param  string $version          The version of this plugin.
	 *
	 * @since    1.0.0
	 */
	public function __construct( $simple_restrict, $version ) {
		$this->simple_restrict = $simple_restrict;
		$this->version         = $version;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Simple_Restrict_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Simple_Restrict_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->simple_restrict, plugin_dir_url( __FILE__ ) . 'css/simple-restrict-public.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Simple_Restrict_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Simple_Restrict_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->simple_restrict, plugin_dir_url( __FILE__ ) . 'js/simple-restrict-public.js', array( 'jquery' ), $this->version, false );
	}

	// Get an array of all the terms for the Page taxonomy 'simple-restrict-permission' (same as function in class-simple-restrict-admin.php but we also need that data here).
	public function get_taxonomy_terms_object_array() {
		$taxonomy  = 'simple-restrict-permission';
		$term_args = array(
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		);
		$this->taxonomy_terms_object_array = get_terms( $taxonomy, $term_args );
	}

	// Also defined in class-simple-restrict-admin.php.
	public function define_initial_variables() {
		$this->generic_restricted_message = __( 'Sorry, this content is restricted to users who are logged in with the correct permissions.', 'simple-restrict' );
	}

	public function display_message() {
		$user_defined_restricted_message = get_option( 'simple_restrict_setting_one' );

		if ( ( isset( $user_defined_restricted_message ) ) && ( $user_defined_restricted_message != '' ) ) {
			return $user_defined_restricted_message;
		} else {
			return $this->generic_restricted_message;
		}
	}


	/**
	 * Whether the current user can view a given page.
	 * A page is accessible if it has no permissions or the user holds at least one of them.
	 *
	 * @param int $post_id The post ID.
	 * @return bool
	 */
	public function user_can_access( $post_id ) {
		$post_id = (int) $post_id;
		if ( ! $post_id ) {
			return true;
		}

		$page_terms = wp_get_post_terms( $post_id, 'simple-restrict-permission', array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $page_terms ) || empty( $page_terms ) ) {
			return true;
		}

		$current_user_id = get_current_user_id();
		if ( 0 === $current_user_id ) {
			return false;
		}

		foreach ( $page_terms as $slug ) {
			if ( 'yes' === get_user_meta( $current_user_id, 'simple-restrict-' . $slug, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Redirect to login on restricted single pages when the redirect setting is enabled.
	 * Content in archives and feeds is filtered per post by filter_content() / filter_excerpt().
	 */
	public function restrict_content() {
		if ( ! is_singular() || is_feed() ) {
			return;
		}

		if ( 1 != get_option( 'simple_restrict_setting_redirect' ) ) {
			return;
		}

		if ( ! $this->user_can_access( get_queried_object_id() ) ) {
			header( 'Location: /wp-login.php?redirect_to=' . $_SERVER['REQUEST_URI'] ); // phpcs:ignore
			exit;
		}
	}

	/**
	 * Replace the content of each restricted post with the restricted message.
	 *
	 * @param string $content The post content.
	 * @return string
	 */
	public function filter_content( $content ) {
		// REST responses are handled by rest_restrict().
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $content;
		}

		if ( ! $this->user_can_access( get_the_ID() ) ) {
			return $this->display_message();
		}

		return $content;
	}

	/**
	 * Replace the excerpt of each restricted post with the restricted message.
	 *
	 * @param string       $excerpt The post excerpt.
	 * @param WP_Post|null $post    The post object.
	 * @return string
	 */
	public function filter_excerpt( $excerpt, $post = null ) {
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $excerpt;
		}

		$post_id = $post instanceof WP_Post ? $post->ID : get_the_ID();
		if ( ! $this->user_can_access( $post_id ) ) {
			return $this->display_message();
		}

		return $excerpt;
	}

	/**
	 * Restrict content of specific page(s) for REST API
	 *
	 * @param  string $response  The response object.
	 * @param  object $post      The post object.
	 * @param  string $request   The request object.
	 *
	 * @since    1.0.0
	 */
	public function rest_restrict( $response, $post, $request ) {
		// If this is an admin page, don't restrict content.
		if ( is_admin() ) {
			return $response;
		}

		if ( current_user_can( 'edit_post', $post->ID ) ) {
			return $response;
		}

		// Use the page's assigned permissions, not a generic capability check: e.g. current_user_can( 'edit_posts' )
		// would let any Contributor-level user bypass them over the REST API.
		if ( $this->user_can_access( $post->ID ) ) {
			return $response;
		}

		// Otherwise the content is restricted for this user: send a 403 error.
		wp_send_json_error( __( 'Sorry, this content is restricted', 'simple-restrict' ), 403 );

		return $response;
	}

	/**
	 * Get all restricted pages
	 *
	 * @since    1.2.8
	 */
	public function get_all_restricted_pages() {
		$terms                = get_terms( 'simple-restrict-permission' );
		$restricted_pages     = array();
		$restricted_pages_ids = array();
		$args                 = array(
			'post_type'      => 'page',
			'posts_per_page' => -1,
			'tax_query'      => array(
				array(
					'taxonomy' => 'simple-restrict-permission',
					'field'    => 'slug',
					'terms'    => wp_list_pluck( $terms, 'slug' ),
				),
			),
		);
		$restricted_pages     = get_posts( $args );
		if ( ! empty( $restricted_pages ) ) {
			foreach ( $restricted_pages as $page ) {
				if ( ! $this->user_can_access( $page->ID ) ) {
					$restricted_pages_ids[] = $page->ID;
				}
			}
		}

		return $restricted_pages_ids;
	}

	/**
	 * Restrict search results
	 *
	 * @param  object $query  The query object.
	 *
	 * @since    1.2.8
	 */
	public function posts_args_search( $query ) {

		if ( ! $query->is_search ) {
			return;
		}
		// Check if it's a pages query.
		$post_type_query = $query->get( 'post_type' );
		if ( '' !== $post_type_query && ( ( is_array( $post_type_query ) && ! in_array( 'page', $post_type_query, true ) ) || ( is_string( $post_type_query ) && 'page' !== $post_type_query ) ) ) {
			return;
		}

		// Check if it's a search query or a REST request.
		if ( ( ! is_admin() ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST && isset( $query->query_vars['s'] ) ) ) {
			// Remove the filter to avoid infinite loop.
			remove_filter( 'pre_get_posts', array( $this, 'posts_args_search' ), 90 );
			$excluded_post_ids = $this->get_all_restricted_pages();
			// Add the filter back, as the request for the restricted pages is done.
			add_filter( 'pre_get_posts', array( $this, 'posts_args_search' ), 90, 1 );
			$query->set( 'post__not_in', $excluded_post_ids );

		}
	}
}
