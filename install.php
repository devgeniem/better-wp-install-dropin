<?php
/**
 * This install.php dropin doesn't install bloat
 * from default install.php and sets only a few wp options.
 *
 * @package   figuren-theater\install.php
 * @author    figuren.theater
 * @copyright 2023 figuren.theater
 * @license   GPL-3.0-or-later
 * @source    https://github.com/figuren-theater/install.php
 * @version   1.2.0
 */

add_action( 'ft_install_defaults', 'ft_install_defaults__set_https_urls' );
add_action( 'ft_install_defaults', 'ft_install_defaults__post' );

/**
 * Installs the site.
 *
 * Runs the required functions to set up and populate the database,
 * including primary admin user and initial options.
 *
 * @since 2.1.0
 *
 * @param string $blog_title    Blog title.
 * @param string $user_name     User's username.
 * @param string $user_email    User's email.
 * @param bool   $public        Whether blog is public.
 * @param string $deprecated    Optional. Not used.
 * @param string $user_password Optional. User's chosen password. Default empty (random password).
 * @param string $language      Optional. Language chosen. Default empty.
 *
 * @return array{
 *    url: string,
 *    user_id: int|int<1, max>|WP_Error,
 *    password: string,
 *    password_message: string
 * }
 */
function wp_install( string $blog_title, string $user_name, string $user_email, bool $public, string $deprecated = '', string $user_password = '', string $language = '' ) :array {

	if ( ! empty( $deprecated ) ) {
		_deprecated_argument( __FUNCTION__, '2.6' );
	}

	$install_defaults = [
		'blogname'    => $blog_title,
		'admin_email' => $user_email,
		'blog_public' => $public,
		// Freshness of site - in the future, this could get more specific about actions taken, perhaps.
		'fresh_site'  => 1,
	];

	wp_check_mysql_version();
	wp_cache_flush();
	make_db_current_silent();
	populate_options( $install_defaults );
	populate_roles();

	$guessurl = wp_guess_url();
	/* update_option( 'siteurl', $guessurl ) was called at this point and it maybe needed again, let's see. */

	/*
	* Create default user. If the user already exists, the user tables are
	* being shared among blogs. Just set the role in that case.
	*/
	$user_id        = username_exists( $user_name );
	$user_password  = trim( $user_password );
	$user_created   = false;

	if ( ! $user_id && empty( $user_password ) ) {
		$user_password = wp_generate_password( 12, false );
		$message       = __( '<strong><em>Note that password</em></strong> carefully! It is a <em>random</em> password that was generated just for you.' );
		$user_id       = wp_create_user( $user_name, $user_password, $user_email );
		if ( ! is_wp_error( $user_id ) ) {
			update_user_meta( $user_id, 'default_password_nag', true );
			$user_created = true;
		}
	} elseif ( ! $user_id ) {
		// Password has been provided.
		$message      = '<em>' . __( 'Your chosen password.' ) . '</em>';
		$user_id      = wp_create_user( $user_name, $user_password, $user_email );
		if ( ! is_wp_error( $user_id ) ) {
			$user_created = true;
		}
	} else {
		$message = __( 'User already exists. Password inherited.' );
	}

	// Make sure we do not deal with a WP_Error.
	// Cast to int - Brutus style.
	$user_id = ( is_int( $user_id ) ) ? $user_id : 1;

	$user = new WP_User( $user_id );
	$user->set_role( 'administrator' );

	if ( $user_created ) {
		$user->user_url = $guessurl;
		wp_update_user( $user );
	}

	wp_install_defaults( $user_id );

	wp_install_maybe_enable_pretty_permalinks();

	flush_rewrite_rules(); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules

	// No `wp_new_blog_notification()` here, like normaly.

	wp_cache_flush();

	/**
	* Fires after a site is fully installed.
	*
	* @since 3.9.0
	*
	* @param WP_User $user The site owner.
	*/
	do_action( 'wp_install', $user );

	return [
		'url'              => $guessurl,
		'user_id'          => $user_id,
		'password'         => $user_password,
		'password_message' => $message,
	];
}

/**
 * Creates the initial content for a newly-installed site.
 * switch_to_blog ran directly before this is called, so we're already in the right context.
 *
 * Adds the default "Uncategorized" category, the first post (with comment),
 * first page, and default widgets for default theme for the current version.
 *
 * @since 2.1.0
 *
 * @param integer $user_id User ID.
 *
 * @return void
 */
function wp_install_defaults( int $user_id ) :void {
	global $wpdb, $wp_rewrite, $table_prefix;

	/**
	* We don't want any default widgets. This fixes 'Undefined index: wp_inactive_widgets'
	*
	* @see wp-includes/widgets.php:1208
	*/
	update_option( 'sidebars_widgets', [ 'wp_inactive_widgets' => [] ] );

	/** Before a comment appears the comment author must have a previously approved comment: false */
	update_option( 'comment_whitelist', 0 );

	/**
	 * Run our custom install steps
	 *
	 * @param int $user_id
	 */
	do_action( 'ft_install_defaults', $user_id );

	if ( ! is_super_admin( $user_id ) && ! metadata_exists( 'user', $user_id, 'show_welcome_panel' ) ) {
		update_user_meta( $user_id, 'show_welcome_panel', 2 );
	}

	// if ( is_multisite() ) {
		// Flush rules to pick up the new page.
		$wp_rewrite->init();
		$wp_rewrite->flush_rules(); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rules_flush_rules

		$user = new WP_User( $user_id );
		$wpdb->update( $wpdb->options, [ 'option_value' => $user->user_email ], [ 'option_name' => 'admin_email' ] );

		// Remove all perms except for the login user.
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->usermeta WHERE user_id != %d AND meta_key = %s", $user_id, $table_prefix . 'user_level' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->usermeta WHERE user_id != %d AND meta_key = %s", $user_id, $table_prefix . 'capabilities' ) );

		// Delete any caps that snuck into the previously active blog. (Hardcoded to blog 1 for now.)
		// TODO: Get previous_blog_id.
	if ( ! is_super_admin( $user_id ) && 1 !== $user_id ) {
		$wpdb->delete(
			$wpdb->usermeta,
			[
				'user_id'  => $user_id,
				'meta_key' => $wpdb->base_prefix . '1_capabilities', // phpcs:ignore
			]
		);
	}
	// }
}

/**
 * Create First post.
 *
 * @param integer $user_id The user who creates the post.
 *
 * @return void
 */
function ft_install_defaults__post( int $user_id ) : void {
	global $wpdb;

	$cat_tt_id = ft_install_defaults__category( $user_id );

	$now             = current_time( 'mysql' );
	$now_gmt         = current_time( 'mysql', 1 );
	$first_post_guid = get_option( 'home' ) . '/?p=1';
	$first_post      = get_site_option( 'first_post' );
	$network         = get_network();

	if ( null === $network ) {
		return;
	}

	if ( ! $first_post ) {
		$first_post = "<!-- wp:paragraph -->\n<p>" .
		/* translators: First post content. %s: Site link. */
		__( 'Welcome to %s. This is your first post. Edit or delete it, then start writing!' ) .
		"</p>\n<!-- /wp:paragraph -->";
	}

	// Cast to string - Brutus style.
	$first_post = '' . $first_post;

	$first_post = sprintf(
		$first_post,
		sprintf(
			'<a href="%s">%s</a>',
			esc_url( network_home_url() ),
			$network->site_name
		)
	);

	$wpdb->insert(
		$wpdb->posts,
		[
			'post_author'           => $user_id,
			'post_date'             => $now,
			'post_date_gmt'         => $now_gmt,
			'post_content'          => $first_post,
			'post_excerpt'          => '',
			'post_title'            => __( 'Hallo Theaterwelt!' ),
			/* translators: Default post slug. */
			'post_name'             => sanitize_title( _x( 'Hallo Theaterwelt', 'Default post slug' ) ),
			'post_modified'         => $now,
			'post_modified_gmt'     => $now_gmt,
			'guid'                  => $first_post_guid,
			'comment_count'         => 1,
			'to_ping'               => '',
			'pinged'                => '',
			'post_content_filtered' => '',
		]
	);

	if ( is_multisite() ) {
		update_posts_count();
	}

	$wpdb->insert(
		$wpdb->term_relationships,
		[
			'term_taxonomy_id' => $cat_tt_id,
			'object_id'        => 1,
		]
	);

}

/**
 * Create Default category.
 *
 * @param integer $user_id The user who creates the term.
 *
 * @return integer
 */
function ft_install_defaults__category( int $user_id ) : int {
	global $wpdb;

	$cat_name = __( 'Uncategorized' );
	/* translators: Default category slug. */
	$cat_slug = sanitize_title( _x( 'Uncategorized', 'Default category slug' ) );

	$cat_id = 1;

	$wpdb->insert(
		$wpdb->terms,
		[
			'term_id'    => $cat_id,
			'name'       => $cat_name,
			'slug'       => $cat_slug,
			'term_group' => 0,
		]
	);
	$wpdb->insert(
		$wpdb->term_taxonomy,
		[
			'term_id'     => $cat_id,
			'taxonomy'    => 'category',
			'description' => '',
			'parent'      => 0,
			'count'       => 1,
		]
	);
	$cat_tt_id = $wpdb->insert_id;

	return $cat_tt_id;
}

/**
 * Set important URLs to HTTPS
 * what is not done by default.
 *
 * The normal install_routine prevents 'https' explicitly for subdomain-installs
 *
 * @see      https://github.com/WordPress/WordPress/blob/ba9dd1d7d7dd84eabef6962e07e50e83763f1e8b/wp-includes/ms-site.php#L696
 *
 * @subpackage Figuren_Theater\Network\Setup
 * @version    2022-10-05
 * @author     Carsten Bach
 *
 * @return void
 */
function ft_install_defaults__set_https_urls() :void {
	$_options_to_change = [
		'home',
		'siteurl',
	];
	array_map(function( $option ) {
		// Cast to string - Brutus style.
		$_opt = '' . get_option( $option );
		$_updated_option = str_replace( 'http://', 'https://', $_opt );

		update_option(
			$option,
			$_updated_option
		);
	}, $_options_to_change);
}
