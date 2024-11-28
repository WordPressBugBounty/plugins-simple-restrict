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
	 * Restrict content of specific page(s).
	 *
	 * @param string $content The content of the page.
	 * @return string
	 */
	public function restrict_content( $content ) {
		// We must prefix 'simple-restrict' to all the user metas (to not conflict with WordPress existing metas).
		$current_user_permissions          = array();  // User permissions will be prefixed by default.
		$current_page_permissions          = array();  // Page permissions are user-defined, so we prefix them manually in next array.
		$current_page_permissions_prefixed = array();  // This array will prefix each of the page permissions.

		$post_id = get_the_ID();
		// Create an array of the current page's permissions.
		$page_terms_list = wp_get_post_terms( $post_id, 'simple-restrict-permission', array( 'fields' => 'all' ) );
		foreach ( $page_terms_list as $current_term ) {
			if ( ! in_array( $current_term->slug, $current_page_permissions, true ) ) {
				$current_term_slug          = $current_term->slug;
				$current_term_slug_prefixed = 'simple-restrict-' . $current_term_slug;
				array_push( $current_page_permissions, $current_term->slug );
				array_push( $current_page_permissions_prefixed, $current_term_slug_prefixed );
			}
		}

		// If the page has no permissions required, show the content and don't bother checking user.
		if ( empty( $current_page_permissions ) ) {
			return $content;
			// Otherwise check the user to see if it's permissions match the page's permissions.
		} else {
			// Create an array of the current user's permissions by cycling through all possible page permissions and putting any matches into user permissions array.
			$current_user_id = get_current_user_id();
			// Only populate user permissions if this is a registered user, otherwise leave permissions array empty.
			if ( $current_user_id != 0 ) {
				foreach ( $this->taxonomy_terms_object_array as $taxonomy_object ) {
					$taxonomy_slug          = $taxonomy_object->slug;
					$taxonomy_slug_prefixed = 'simple-restrict-' . $taxonomy_slug;
					if ( 'yes' === esc_attr( get_the_author_meta( $taxonomy_slug_prefixed, $current_user_id ) ) ) {
						// Only add to array if it wasn't already there ($current_user_permissions values are always prefixed).
						if ( ! in_array( $taxonomy_slug_prefixed, $current_user_permissions, true ) ) {
							array_push( $current_user_permissions, $taxonomy_slug_prefixed );
						}
					}
				}
			}

			$simple_restrict_setting_redirect = get_option( 'simple_restrict_setting_redirect' );
			// If the user's permissions don't match any of the page's permissions.
			if ( ! array_intersect( $current_page_permissions_prefixed, $current_user_permissions ) ) {
				// Redirect to login or display message.
				if ( isset( $simple_restrict_setting_redirect ) && ( $simple_restrict_setting_redirect == 1 ) ) {
					header( 'Location: /wp-login.php?redirect_to=' . $_SERVER['REQUEST_URI'] ); // phpcs:ignore
					exit;
				} else {
					add_filter( 'the_content', array( $this, 'display_message' ) );
				}
			} else {
				// Otherwise show the regular content because it is restricted but the user has the permission.
				// (Note that $content is empty so below does nothing, and our script simply ends without a restriction).
				return $content;
			}
		}
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

		// We must prefix 'simple-restrict' to all the user metas (to not conflict with WordPress existing metas).
		$current_page_permissions = array();  // Page permissions are user-defined, so we prefix them manually in next array.
		$post_id                  = $post->ID;
		// Create an array of the current page's permissions.
		$page_terms_list = wp_get_post_terms( $post_id, 'simple-restrict-permission', array( 'fields' => 'all' ) );
		foreach ( $page_terms_list as $current_term ) {
			if ( ! in_array( $current_term->slug, $current_page_permissions, true ) ) {
				array_push( $current_page_permissions, $current_term->slug );
			}
		}

		// If the page has no permissions required, show the content and don't bother checking user.
		if ( empty( $current_page_permissions ) ) {
			return $response;
			// Otherwise check the user to see if it's permissions match the page's permissions.
		} else {
			// Check if the user has the required permissions.
			if ( current_user_can( 'edit_posts' ) ) {
				return $response;
			}

			// Send a 403 error if the content is restricted.
			// @todo: What can be done here is to check the request for the user's permissions and then send a 403 error if the user doesn't have the required permissions.
			// @todo: else return the content.
			wp_send_json_error( __( 'Sorry, this content is restricted', 'simple-restrict' ), 403 );

			return $response;
		}
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
		$current_user_id      = 0;
		if ( is_user_logged_in() ) {
			$current_user_id = get_current_user_id();
		}
		if ( ! empty( $restricted_pages ) ) {
			foreach ( $restricted_pages as $page ) {
				// Check if the user is logged in and has the required permissions.
				if ( 0 !== $current_user_id ) {
					// Get the page's permissions.
					$terms = wp_get_post_terms( $page->ID, 'simple-restrict-permission', array( 'fields' => 'all' ) );
					foreach ( $terms as $taxonomy_object ) {
						$taxonomy_slug          = $taxonomy_object->slug;
						$taxonomy_slug_prefixed = 'simple-restrict-' . $taxonomy_slug;
						// If the user has the required permissions, allow access.
						if ( 'yes' === esc_attr( get_the_author_meta( $taxonomy_slug_prefixed, $current_user_id ) ) ) {
							$access = true;
						} else {
							$access = false;
						}
					}
				} else { // User not logged in, so restrict access.
					$access = false;
				}

				if ( ! $access ) {
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
