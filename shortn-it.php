<?php
/*
Plugin Name: Shortn.It
Plugin URI: http://docof.me/shortn-it
Help & Support: http://docof.me/shortn-it
Description: Personal, customized URL shortening for WordPress.
Version: 1.7.4
Author: David Cochrum
Author URI: http://www.docofmedia.com/

Copyright 2014  David Cochrum  (email : david@docofmedia.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

//	Define constant(s)
define( 'SHORTN_IT_META', '_shortn_it_url' );
define( 'SHORTN_IT_VERSION', '1.7.4' );

//	Define global(s)
global $wpdb;

/**
 * Create Shortn.It class.
 */
class Shortn_It {

	/**
	 * @var string|void
	 */
	private $base_path;

	/**
	 * Initialize Short.In options upon activation.
	 */
	public function __construct() {

		$this->base_path = trailingslashit( parse_url( get_bloginfo( 'url' ), PHP_URL_PATH ) );

		//	Add Shortn.It option defaults
		add_option( 'shortn_it_use_mobile_style', 'yes' );
		add_option( 'shortn_it_link_text', 'shortn url' );
		add_option( 'shortn_it_permalink_prefix', 'default' );
		add_option( 'shortn_it_allow_slash', 'yes' );
		add_option( 'shortn_it_permalink_custom', 'a/' );
		add_option( 'shortn_it_use_lowercase', 'yes' );
		add_option( 'shortn_it_use_uppercase', 'yes' );
		add_option( 'shortn_it_use_numbers', 'yes' );
		add_option( 'shortn_it_length', '5' );
		add_option( 'shortn_use_short_url', 'yes' );
		add_option( 'shortn_use_shortlink', 'yes' );
		add_option( 'shortn_use_canonical', 'yes' );
		//add_option( 'shortn_use_shortlink_header', 'yes' );
		add_option( 'shortn_it_registered', 'no' );
		add_option( 'shortn_it_registered_on', '0' );
		add_option( 'shortn_it_permalink_domain', 'default' );
		add_option( 'shortn_it_domain_custom', '' );
		add_option( 'shortn_it_hide_godaddy', 'no' );
		add_option( 'shortn_it_use_url_as_link_text', 'yes' );
		add_option( 'shortn_it_add_to_rss', 'yes' );
		add_option( 'shortn_it_add_to_rss_text', 'If you require a short URL to link to this article, please use %%link%%' );
		add_option( 'shortn_it_hide_nag', 'yes' );

		//	Create necessary actions
		add_action( 'init', array( &$this, 'shortn_it_headers' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'shortn_it_enqueue_edit_scripts' ) );
		add_action( 'admin_menu', array( &$this, 'shortn_it_admin_panel' ) );
		add_action( 'admin_menu', array( &$this, 'shortn_it_sidebar' ) );
		add_action( 'plugins_loaded', array( &$this, 'shortn_it_url_widget_init' ) );
		add_action( 'save_post', array( &$this, 'shortn_it_save_url' ) );
		add_action( 'wp_ajax_shortn_it_json_check_url', array( &$this, 'shortn_it_json_check_url' ) );
		add_action( 'wp_head', array( &$this, 'shortn_it_short_url_header' ) );

		//	Create necessary filters
		add_filter( 'get_shortlink', array( &$this, 'shortn_it_get_shortlink' ), 10, 3 );
		add_filter( 'tweet_blog_post_url', array( &$this, 'get_shortn_it_url_from_long_url' ) );    // Support for Twitter Tools by Crowd Favorite
		if( get_option( 'shortn_use_canonical' ) == 'yes' )
			add_filter( 'wpseo_canonical', array( &$this, 'get_shortn_it_url_from_long_url' ) );        // Support for WP SEO by Yoast

	}

	/**
	 * Redirect incoming Shortn.It URL page requests to the appropriate post.
	 */
	public function shortn_it_headers() {

		// Get the requested relative URL up to the query string or hash
		$current_rel_url = strtok( strtok( $_SERVER[ 'REQUEST_URI' ], '?' ), '#' );
		// Build a permalink-like version of the curent URL (without query or hash)
		$current_url = 'http' . ( ( ( ! empty( $_SERVER[ 'HTTPS' ] ) && $_SERVER[ 'HTTPS' ] !== 'off' ) || $_SERVER[ 'SERVER_PORT' ] == 443 ) ? 's' : '' ) . '://' . $_SERVER[ 'HTTP_HOST' ] . $current_rel_url;

		//	TODO Add shortlink HTTP header if desired and post has a short URL
		/*if( get_option( 'shortn_use_shortlink_header' ) == 'yes' && $post_id = url_to_postid( $current_url ) ) {
			echo '<pre>'; var_dump( $current_url, $post_id ); exit;
			$short_url = $this->get_shortn_it_url_permalink( $post_id );
			if( $short_url != '' )
				header( 'Link: <' . $short_url . '>; rel=shortlink' );
		}
		//	Unable to match a post, so determine if this is a short link that needs redirecting
		else*/
		if( $post_id = $this->shortn_it_get_matching_post_id( $current_rel_url ) ) {
			$permalink = get_permalink( $post_id );
			if( $permalink != $current_url ) {
				// Redirect to the full permalink URL and re-add the query and hash
				wp_redirect( $permalink . str_replace( $current_rel_url, '', $_SERVER[ 'REQUEST_URI' ] ) );
				exit;
			}
		}

	}

	/**
	 * Get the matching post ID from the given URL.
	 *
	 * @param string $url A URL to attempt to match
	 *
	 * @return int The matching post ID.
	 */
	public function shortn_it_get_matching_post_id( $url ) {

		global $wpdb;

		//	If the URL doesn't begin with the chosen prefix, return nothing
		$url_base = $this->base_path . ltrim( $this->get_shortn_it_url_prefix(), '/' );
		if( stripos( $url, $url_base ) != 0 )
			return '';

		//	Get the Shortn.It URL by removing the prefix
		$the_short = substr_replace( $url, '', 0, strlen( $url_base ) );

		//	Once the prefix has been removed, if there's nothing left but an empty string, return nothing
		if( $the_short == '' )
			return '';

		$the_short = substr_replace( $url, '', 0, strlen( $url_base ) );

		//	If we're allowing a trailing slash, remove it to allow for a match.
		if( $this->get_shortn_it_allow_slash() )
			$the_short = rtrim( $the_short, '/' );

		//	Query the DB for any post that the Shortn.It URL matches the Shortn.It stored meta
		return $wpdb->get_var( 'SELECT `post_id` FROM `' . $wpdb->postmeta . '` where `meta_key` = "' . SHORTN_IT_META . '" and `meta_value` = "' . $the_short . '"' );

	}

	/**
	 * Return the relative base path of the current WP instance.
	 *
	 * @return string|void
	 */
	public function get_shortn_it_base_path() {

		return $this->base_path;

	}

	/**
	 * Get the Shortn.It URL prefix (if there is one).
	 *
	 * @return string The Shortn.It URL prefix.
	 */
	public function get_shortn_it_url_prefix() {

		//	Return the base path and the prefix (if there is one)
		return ( get_option( 'shortn_it_permalink_prefix' ) == 'custom' ? ltrim( get_option( 'shortn_it_permalink_custom' ), '/' ) : '' );

	}

	/**
	 * Get boolean value value of the `allow slash` option.
	 *
	 * @return bool Whether the `allow slash` option is set to 'yes'.
	 */
	public function get_shortn_it_allow_slash() {

		return ( get_option( 'shortn_it_allow_slash' ) == 'yes' );

	}

	/**
	 * Get the complete Shortn.It URL.
	 *
	 * @param int $post_id A post ID.
	 *
	 * @return string The full Shortn.It URL.
	 */
	public function get_shortn_it_url_permalink( $post_id ) {

		//	Get the Shortn.It URL
		$shortn_url = $this->get_shortn_it( $post_id );

		//	If no Shortn.It URL is associated with the post, return nothing, or else return the full URL
		if( $shortn_url == '' )
			return '';
		else
			return $this->get_shortn_it_base_url() . $this->get_shortn_it_url_prefix() . $shortn_url;

	}

	/**
	 * Get the relative Shortn.It URL for the post.
	 *
	 * @param int $post_id A post ID.
	 *
	 * @return string The relative Shortn.It URL.
	 */
	public function get_shortn_it( $post_id ) {

		//	If no post ID was provided, return nothing
		if( $post_id == '' )
			return '';

		//	Get the Shortn.It URL from the matching post meta
		$shortn_url = get_post_meta( $post_id, SHORTN_IT_META, true );

		//	If the Shortn.It URL was found, return it
		if( $shortn_url != '' )
			return $shortn_url;

		//	Or else make a Shortn.It URL, add it to the post meta, and return it
		else {
			$shortn_url = $this->shortn_it_make_url( $post_id );

			return $shortn_url;
		}

	}

	/**
	 * Generate a Shortn.It URL and add it to the post meta.
	 *
	 * @param $post_id The $post post ID.
	 *
	 * @return bool|string
	 */
	private function shortn_it_make_url( $post_id ) {

		if( $post_id != '' ) {
			$short = $this->shortn_it_generate_string();

			return ! update_post_meta( $post_id, SHORTN_IT_META, $short ) ? false : $short;
		}

		return false;
	}

	/**
	 * Generate a random Shortn.It URL that fits the chosen criteria
	 *
	 * @return string A random Shortn.It URL.
	 */
	public function shortn_it_generate_string() {

		$length = get_option( 'shortn_it_length' );
		$valid_chars = '';
		$random_string = '';

		//	Create a string containing all valid characters to be used that fit the chosen criteria
		if( get_option( 'shortn_it_use_lowercase' ) == 'yes' )
			$valid_chars .= 'abcdefghijklmnopqrstuvwxyz';
		if( get_option( 'shortn_it_use_uppercase' ) == 'yes' )
			$valid_chars .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		if( get_option( 'shortn_it_use_numbers' ) == 'yes' )
			$valid_chars .= '0123456789';

		//	Keep generating a random string until one is found that is not already in use
		$unqiue_url = false;
		while( ! $unqiue_url ) {
			// start with an empty random string
			$random_string = '';

			// count the number of chars in the valid chars string so we know how many choices we have
			$num_valid_chars = strlen( $valid_chars );

			// repeat the steps until we've created a string of the right length
			for( $i = 0; $i < $length; $i++ ) {
				// pick a random number from 1 up to the number of valid chars
				$random_pick = mt_rand( 1, $num_valid_chars );

				// take the random character out of the string of valid chars
				// subtract 1 from $random_pick because strings are indexed starting at 0, and we started picking at 1
				$random_char = $valid_chars[ $random_pick - 1 ];

				// add the randomly-chosen char onto the end of our string so far
				$random_string .= $random_char;
			}

			//	Determine if the random string is already set as a Shortn.It URL in another post
			$unqiue_url = ! $this->shortn_it_check_url( $random_string );
		}

		//	Once we have a unique Shortn.It URL, return it
		return $random_string;

	}

	/**
	 * Check if a string matches an existing Shortn.It URL.
	 *
	 * @param string $the_short A URL string.
	 *
	 * @return bool Whether a match was found.
	 */
	private function shortn_it_check_url( $the_short ) {

		global $wpdb;

		// If the string is empty, return false
		if( $the_short == '' )
			return false;

		//	Query for any posts (of any type) that have a Shortn.It URL matching the string
		$post_id = $wpdb->get_var( 'SELECT `post_id` FROM `' . $wpdb->postmeta . '` where `meta_key` = "' . SHORTN_IT_META . '" and `meta_value` = "' . $the_short . '"' );

		//	Return true if there is a match, false if not
		return ( ! empty( $post_id ) );

	}

	/**
	 * Get the Shortn.It domain chosen in the options.
	 *
	 * @return string Shortn.It domain.
	 */
	public function get_shortn_it_domain() {

		$shortn_it_permalink_domain = get_option( 'shortn_it_permalink_domain' );
		$shortn_it_domain_custom = get_option( 'shortn_it_domain_custom' );

		//	If the custom domain option was chosen, return the specified custom domain, or else return the site domain
		return $shortn_it_permalink_domain != 'custom' ? parse_url( get_bloginfo( 'url' ), PHP_URL_HOST ) : $shortn_it_domain_custom;

	}

	/**
	 * Get the Shortn.It domain chosen in the options and base path.
	 *
	 * @return string Shortn.It base URL.
	 */
	public function get_shortn_it_base_url() {

		return 'http://' . $this->get_shortn_it_domain() . $this->base_path;

	}

	/**
	 * Enqueue scripts and styles to be used on post edit and creation pages.
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function shortn_it_enqueue_edit_scripts( $hook_suffix ) {

		//	Enqueue the scripts only on "post.php" and "post-new.php"
		if( 'post.php' == $hook_suffix || 'post-new.php' == $hook_suffix ) {
			//	Enqueue Javascript
			wp_enqueue_script( 'shortn_it_edit_scripts', plugins_url( 'js/shortn-it.js', __FILE__ ), array( 'jquery' ) );
			//	Pass "admin-ajax.php" URL for use in Javascript
			wp_localize_script( 'shortn_it_edit_scripts', 'vars', array( 'ajax' => admin_url( 'admin-ajax.php' ) ) );
			//	Enqueue CSS
			wp_enqueue_style( 'shortn_it_edit_scripts', plugins_url( 'css/shortn-it.css', __FILE__ ) );
		}

	}

	/**
	 * Add side meta boxes on post edit and creation pages.
	 */
	public function shortn_it_sidebar() {

		//	For compaitibility with older versions of WP, check if the "add_meta_box" functionality exists, if not then do it the old way
		if( function_exists( 'add_meta_box' ) ) {
			//	Use "add_meta_box" to create the meta box for public post types
			$post_types = get_post_types( array( 'public' => true ) );
			foreach( $post_types as $post_type ) {
				add_meta_box( 'shortn_it_box', __( 'Shortn.It', 'shortn_it_textdomain' ), array( &$this, 'shortn_it_generate_sidebar' ), $post_type, 'side', 'high' );
			}
		} else {
			//	For older versions, add the meta box to post and page edit/create pages
			add_action( 'dbx_post_sidebar', array( &$this, 'shortn_it_generate_sidebar' ) );
			add_action( 'dbx_page_sidebar', array( &$this, 'shortn_it_generate_sidebar' ) );
		}
	}

	/**
	 * Generate the content within the Shortn.It meta box.
	 */
	public function shortn_it_generate_sidebar() {

		//	Get the id of the currently edited post
		$post_id = esc_sql( $_GET[ 'post' ] );

		//	Get the Shortn.It URL for this post
		$shortn_url = $this->get_shortn_it( $post_id );
		//	Get the full Shortn.It URL for this post
		$shortn_it_permalink = $this->get_shortn_it_url_permalink( $post_id );
		//	If there isn't already a Shortn.It URL for this post, create one
		if( $shortn_url == '' )
			$string = $this->shortn_it_generate_string();

		//	Populate the meta box with the Shortn.It URL information
		?>
		<p class="shortn_it_current_url">
			<?php wp_nonce_field( basename( __FILE__ ), 'shortn_it_nonce' ); ?>

			<?php _e( 'This post\'s shortned url ' . ( ( $shortn_url != '' ) ? 'is' : 'will be' ), 'shortn_it_textdomain' ); ?>
			:<br>
			<span
				class="shortn_it_url_prefix"><?php echo str_replace( 'http://', '', $this->get_shortn_it_base_url() ) . ltrim( $this->get_shortn_it_url_prefix(), '/' ); ?></span>
			<code class="shortn_it_url_wrap">
				<?php echo( ( $shortn_url != '' ) ? '<a href="' . $shortn_it_permalink . '">
						<span class="shortn_it_url">' . $shortn_url . '</span></a>' :
					'<span class="shortn_it_url">' . $string . '</span>' ); ?>
			</code>
			<input type="text" id="shortn_it_url" name="shortn_it_url" class="hide"
			       value="<?php echo( ( $shortn_url != '' ) ? $shortn_url : $string ); ?>" autocomplete="off"></p>
		<?php if( get_option( 'shortn_it_hide_nag' ) == 'no' ) { ?>
			<a href="//docof.me/buy-shortn-it/"><? _e( 'Donate to keep Shortn.It alive.', 'shortn_it_textdomain' ); ?></a>
		<?php
		}
	}

	/**
	 * Register the Shortn.It sidebar widget.
	 */
	public function shortn_it_url_widget_init() {

		wp_register_sidebar_widget( 'shortn-it-sidebar-widget', __( 'Shortn.It', 'shortn_it_textdomain' ), 'shortn_it_url_widget' );

	}

	/**
	 * Wrap the content for the Shortn.It sidebar widget appropriately.
	 *
	 * @param string|array $args Optional args.
	 */
	private function shortn_it_url_widget( $args ) {

		extract( $args );
		echo $before_widget;
		echo $before_title; ?>Shortened Permalink<?php echo $after_title;
		$this->shortn_it_url_widget_content();
		echo $after_widget;

	}

	/**
	 * Populate the content for the Shortn.It sidebar widget.
	 */
	private function shortn_it_url_widget_content() {

		echo '<p>This post\'s short url is <a href="';
		$this->the_full_shortn_url();
		echo '">';
		$this->the_full_shortn_url();
		echo '</a></p>';

	}

	/**
	 * Get the Shortn.It URL for the current post within "the loop".
	 *
	 * @return string Shortn.It URL for the current post.
	 */
	public function get_the_shortn_url() {

		return $this->get_shortn_it( get_the_ID() );

	}

	/**
	 * Echo the Shortn.It URL for the current post within "the loop".
	 */
	public function the_shortn_url() {

		echo $this->get_the_shortn_url();

	}

	/**
	 * Get an anchor tag of the Shortn.It URL for the current post within "the loop".
	 *
	 * @return string Anchor tag of the Shortn.It URL.
	 */
	public function get_the_shortn_url_link() {

		$post_id = get_the_ID();
		$shortn_url = $this->get_shortn_it( $post_id );

		if( $shortn_url == '' )
			return '';

		if( get_option( 'shortn_it_use_url_as_link_text' ) == 'yes' )
			$anchor_text = self::get_the_full_shortn_url();
		else
			$anchor_text = get_option( 'shortn_it_link_text' );

		return '<a href="' . $this->get_shortn_it_url_permalink( $post_id ) . '" class="shortn_it" rel="nofollow" title="shortened permalink for this page">' . htmlspecialchars( $anchor_text, ENT_QUOTES, 'UTF-8' ) . '</a>';

	}

	/**
	 * Echo an anchor tag of the Shortn.It URL for the current post within "the loop".
	 */
	public function the_shortn_url_link() {

		echo $this->get_the_shortn_url_link();

	}

	/**
	 * Get the full Shortn.It URL for the current post within "the loop".
	 *
	 * @return string Post's Shortn.It URL.
	 */
	public function get_the_full_shortn_url() {

		$post_id = get_the_ID();

		$shortn_url = $this->get_shortn_it( $post_id );
		if( $shortn_url == '' )
			return '';

		return $this->get_shortn_it_url_permalink( $post_id );

	}

	/**
	 * Echo the full Shortn.It URL for the current post within "the loop".
	 */
	public function the_full_shortn_url() {

		echo $this->get_the_full_shortn_url();

	}

	/**
	 * Update the Shortn.It URL meta once the post has been saved.
	 *
	 * @param int $post_id ID of post.
	 *
	 * @return string Post's Shortn.It URL.
	 */
	public function shortn_it_save_url( $post_id ) {

		// verify this came from the our screen and with proper authorization.
		if( ! isset( $_POST[ 'shortn_it_nonce' ] ) || ! wp_verify_nonce( $_POST[ 'shortn_it_nonce' ], basename( __FILE__ ) ) )
			return $post_id;

		// verify if this is an auto save routine. If it is our form has not been submitted, so we dont want to do anything
		if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;

		// Check permissions
		if( ! current_user_can( 'edit_post', $post_id ) )
			return $post_id;

		// OK, we're authenticated: we need to find and save the data   
		$post = get_post( $post_id );
		update_post_meta( $post_id, SHORTN_IT_META, esc_attr( $_POST[ 'shortn_it_url' ] ) );

		return esc_attr( $_POST[ 'shortn_it_url' ] );

	}

	/**
	 * Output a JSON string with matching post information (for use with "admin-ajax.php").
	 */
	public function shortn_it_json_check_url() {

		//	If the nonce doesn't match up, too bad
		if( ! isset( $_REQUEST[ 'nonce' ] ) || ! wp_verify_nonce( $_REQUEST[ 'nonce' ], basename( __FILE__ ) ) )
			die( 'Invalid Nonce' );

		//	Output JSON content type header
		header( 'Content-Type: application/json' );
		//	Get the id of the post that matches the requested string
		$match_id = $this->shortn_it_get_matching_post_id( $this->get_shortn_it_url_prefix() . $_REQUEST[ 'string' ] );
		//	If the match's ID is the same as the requested ID, proceed as if there were no match
		if( $match_id == $_REQUEST[ 'id' ] )
			$match_id = '';
		//	Echo a JSON string containing a bool of whether or not there was a match, the ID of the matching post, it's title, and the URL to edit that post
		die( json_encode( array( 'exists' => ! empty( $match_id ), 'match_id' => $match_id, 'match_title' => get_the_title( $match_id ), 'edit_url' => get_edit_post_link( $match_id ) ) ) );

	}

	/**
	 * Add the shorturl and shortlink meta tags to the page header.
	 */
	public function shortn_it_short_url_header() {

		$post_id = get_the_ID();

		$shortn_url = $this->get_shortn_it( $post_id );
		// Proceed if there is a Shortn.It URL in existance and at lease one of the shorturl or shortlink options are selected
		if( $shortn_url != '' && ( get_option( 'shortn_use_short_url' ) == 'yes' || get_option( 'shortn_use_shortlink' ) == 'yes' ) ) {
			$shortn_it_permalink = $this->get_shortn_it_url_permalink( $post_id );

			//	Echo the shorturl and shortlink meta tags depending on whether or not the option for each was selected
			echo '	<!-- Shortn.It version ' . SHORTN_IT_VERSION . " -->\n" .
			     ( ( get_option( 'shortn_use_short_url' ) == 'yes' ) ? "\t" . '<link rel="shorturl" href="' . $shortn_it_permalink . '">' . "\n" : '' ) .
			     ( ( get_option( 'shortn_use_shortlink' ) == 'yes' ) ? "\t" . '<link rel="shortlink" href="' . $shortn_it_permalink . '">' . "\n" : '' ) .
			     "\t" . '<!-- End Shortn.It -->' . "\n";
		}

	}

	/**
	 * Return the Shortn.It URL instead of WP's built-in shortlinks.
	 *
	 * @param string $link    Shortlink URL.
	 * @param int    $id      Post ID, or 0 for the current post.
	 * @param string $context The context for the link. One of 'post' or 'query'.
	 *
	 * @return string Post's Shortn.It URL
	 */
	public function shortn_it_get_shortlink( $link, $id, $context ) {

		return $this->get_shortn_it_url_permalink( $id );

	}

	/**
	 * Return the Shortn.It URL matching the long post URL.
	 *
	 * @param string $long Full length URL for post.
	 *
	 * @return string Post's Shortn.It URL
	 */
	public function get_shortn_it_url_from_long_url( $long ) {

		//	Get the post ID from its long URL
		$post_id = url_to_postid( $long );

		//	Return the full Shortn.It URL
		return $this->get_shortn_it_url_permalink( $post_id );

	}

	/**
	 * Add an options page to the settings in the admin backend.
	 */
	public function shortn_it_admin_panel() {

		//	Register the Shortn.It options page
		add_options_page( 'Shortn.It', 'Shortn.It', 'manage_options', 'shortnit/shortn-it-options.php', array( &$this, 'shortn_it_settings' ) );

		//	If the current user has permission to at least edit posts, add a link to the settings menu
		if( current_user_can( 'edit_posts' ) && function_exists( 'add_submenu_page' ) )
			add_filter( 'plugin_action_links_' . __FILE__, array( &$this, 'shortn_it_plugin_actions' ), 10, 2 );
	}

	/**
	 * Get the Shortn.It settings page from "shortn-it-options.php".
	 */
	public function shortn_it_settings() {

		require_once( 'shortn-it-options.php' );

	}

	/**
	 * Build the link to the Shortn.It admin options page.
	 * Thanks to //wpengineer.com/how-to-improve-wordpress-plugins/ for instructions on adding the Settings link.
	 *
	 * @link //wpengineer.com/how-to-improve-wordpress-plugins/
	 *
	 * @param $links Plugin links array
	 *
	 * @return array
	 */
	public function shortn_it_plugin_actions( $links ) {

		$settings_link = '<a href="options-general.php?page=shortnit/shortn-it-options.php">' . __( 'Settings', 'shortn_it_textdomain' ) . '</a>';
		$links = array_merge( array( $settings_link ), $links ); // before other links
		return $links;

	}

	/**
	 * Store whether the Shortn.It plugin was activated and when.
	 */
	public function shortn_it_register() {

		update_option( 'shortn_it_registered', 'yes' );
		update_option( 'shortn_it_registered_on', time() );

	}

	/**
	 * Change the option to show/hide GoDaddy referral links.
	 *
	 * @param string $option 'yes'/'no' value to update option.
	 */
	public function shortn_it_hide_godaddy( $option ) {

		if( $option == 'yes' )
			update_option( 'shortn_it_hide_godaddy', 'yes' );
		else
			update_option( 'shortn_it_hide_godaddy', 'no' );

	}

	/**
	 * Change the option to show/hide donation request links.
	 *
	 * @param $option 'yes'/'no' value to update option.
	 */
	public function shortn_it_hide_nag( $option ) {

		if( $option == 'yes' )
			update_option( 'shortn_it_hide_nag', 'yes' );
		else
			update_option( 'shortn_it_hide_nag', 'no' );

	}

}

$Shortn_It = new Shortn_It();

/**
 * Get the relative Shortn.It URL for the current post within "the loop".
 *
 * @return string The relative Shortn.It URL.
 */
if( ! function_exists( 'get_the_shortn_url' ) ) {

	function get_the_shortn_url() {

		$Shortn_It = new Shortn_It();

		return $Shortn_It->get_the_shortn_url();

	}

}

/**
 * Output the relative Shortn.It URL for the current post within "the loop".
 */
if( ! function_exists( 'the_shortn_url' ) ) {

	function the_shortn_url() {

		$Shortn_It = new Shortn_It();
		$Shortn_It->the_shortn_url();

	}

}

/**
 * Get an anchor tag of the Shortn.It URL for the current post within "the loop".
 *
 * @return string Anchor tag of the Shortn.It URL.
 */
if( ! function_exists( 'get_the_shortn_url_link' ) ) {

	function get_the_shortn_url_link() {

		$Shortn_It = new Shortn_It();

		return $Shortn_It->get_the_shortn_url_link();

	}

}

/**
 * Output an anchor tag of the Shortn.It URL for the current post within "the loop".
 */
if( ! function_exists( 'the_shortn_url_link' ) ) {

	function the_shortn_url_link() {

		$Shortn_It = new Shortn_It();
		$Shortn_It->the_shortn_url_link();

	}

}

/**
 * Get the full Shortn.It URL for the current post within "the loop".
 *
 * @return string The full Shortn.It URL.
 */
if( ! function_exists( 'get_the_full_shortn_url' ) ) {

	function get_the_full_shortn_url() {

		$Shortn_It = new Shortn_It();

		return $Shortn_It->get_the_full_shortn_url();

	}

}

/**
 * Output the full Shortn.It URL for the current post within "the loop".
 */
if( ! function_exists( 'the_full_shortn_url' ) ) {

	function the_full_shortn_url() {

		$Shortn_It = new Shortn_It();
		$Shortn_It->the_full_shortn_url();

	}

}
?>