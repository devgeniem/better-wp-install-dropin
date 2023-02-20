<?php
/**
 * 
 */

add_action( 'ft_install_defaults', 'ft_install_defaults__set_https_urls' );
add_action( 'ft_install_defaults', 'ft_install_defaults__category' );
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
 * @return array Array keys 'url', 'user_id', 'password', and 'password_message'.
 */
function wp_install( string $blog_title, string $user_name, string $user_email, bool $public, string $deprecated = '', string $user_password = '', string $language = '' ) {

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
/*
	update_option( 'blogname', $blog_title );
	update_option( 'admin_email', $user_email );
	update_option( 'blog_public', $public );
	// Prefer empty description if someone forgots to change it.
	update_option( 'blogdescription', '' );

	// Freshness of site - in the future, this could get more specific about actions taken, perhaps.
	update_option( 'fresh_site', 1 );

	// Use language from installer or env WPLANG or default to 'de_DE'.
	if ( ! empty( $language ) ) {
		update_option( 'WPLANG', $language );
	} elseif ( ! empty( getenv( 'WPLANG' ) ) ) {
		update_option( 'WPLANG', getenv( 'WPLANG' ) );
	} else {
		update_option( 'WPLANG', 'de_DE' );
	}

	$guessurl = wp_guess_url();

	update_option( 'siteurl', $guessurl );

	// If not a public blog, don't ping.
	if ( ! $public ) {
		update_option( 'default_pingback_flag', 0 );
	}
*/
	/*
	* Create default user. If the user already exists, the user tables are
	* being shared among blogs. Just set the role in that case.
	*/
	$user_id        = username_exists( $user_name );
	$user_password  = trim( $user_password );
	$email_password = false;
	$user_created   = false;

	if ( ! $user_id && empty( $user_password ) ) {
		$user_password = wp_generate_password( 12, false );
		$message       = __( '<strong><em>Note that password</em></strong> carefully! It is a <em>random</em> password that was generated just for you.' );
		$user_id       = wp_create_user( $user_name, $user_password, $user_email );
		update_user_meta( $user_id, 'default_password_nag', true );
		$email_password = true;
		$user_created   = true;
	} elseif ( ! $user_id ) {
		// Password has been provided.
		$message      = '<em>' . __( 'Your chosen password.' ) . '</em>';
		$user_id      = wp_create_user( $user_name, $user_password, $user_email );
		$user_created = true;
	} else {
		$message = __( 'User already exists. Password inherited.' );
	}

	$user = new WP_User( $user_id );
	$user->set_role( 'administrator' );

	if ( $user_created ) {
		$user->user_url = $guessurl;
		wp_update_user( $user );
	}

	wp_install_defaults( $user_id );

	wp_install_maybe_enable_pretty_permalinks();

	flush_rewrite_rules();

	// no wp_new_blog_notification() here, like normaly

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
 * @param int $user_id User ID.
 */
function wp_install_defaults( int $user_id ) {
	global $wpdb, $wp_rewrite, $current_site, $table_prefix;

	/**
	* Time zone: Get the one from TZ environmental variable
	*
	* @see wp-admin/options-general.php
	*/
	// update_option( 'timezone_string', ( ! empty( getenv( 'TZ' ) ) ? getenv( 'TZ' ) : 'Europe/Berlin' ) ); // handled by 'Feature__decisions_not_options'

	/**
	* We don't want any default widgets. This fixes 'Undefined index: wp_inactive_widgets'
	*
	* @see wp-includes/widgets.php:1208
	*/
	update_option( 'sidebars_widgets', [ 'wp_inactive_widgets' => [] ] );

	/**
	* Before a comment appears a comment must be manually approved: true
	*
	* @see wp-admin/options-discussion.php
	*/
	// update_option( 'comment_moderation', 1 ); // handled by 'Feature__decisions_not_options'

	/** Before a comment appears the comment author must have a previously approved comment: false */
	update_option( 'comment_whitelist', 0 );

	/** Allow people to post comments on new articles (this setting may be overridden for individual articles): false */
	// update_option( 'default_comment_status', 0 ); // handled by 'Feature__decisions_not_options'

	/** Allow link notifications from other blogs: false */
	// update_option( 'default_ping_status', 0 ); // handled by 'Feature__decisions_not_options'

	/** Attempt to notify any blogs linked to from the article: false */
	// update_option( 'default_pingback_flag', 0 ); // handled by 'Feature__decisions_not_options'

	/**
	* Organize my uploads into month- and year-based folders: true
	*
	* @see wp-admin/options-media.php
	*/
	// update_option( 'uploads_use_yearmonth_folders', 1 ); // handled by 'Feature__decisions_not_options'

	/**
	* Permalink custom structure: /%category%/%postname%
	*
	* @see wp-admin/options-permalink.php
	*/
	// update_option( 'permalink_structure', '/%category%/%year%/%monthnum%/%postname%/' ); // handled by 'Feature__decisions_not_options'
	
//////
//  //
//////


	//
	do_action( 'ft_install_defaults', $user_id );


	/**
	* Create new page and add it as front page
	$id = wp_insert_post( array(
		// Somehow translations won't work always. So check if Finnish was used.
		'post_title' => ( get_option('WPLANG') == 'fi' ) ? 'Etusivu' : __('Front page'),
		'post_type' => 'page',
		'post_status' => 'publish',
		// Prefer empty content if someone forgots to change it.
		'post_content' => ''
	) );

	// Add page we just created as front page
	update_option( 'page_on_front' , $id );
	update_option( 'show_on_front' , 'page' );
	*/


	if ( ! is_super_admin( $user_id ) && ! metadata_exists( 'user', $user_id, 'show_welcome_panel' ) ) {
		update_user_meta( $user_id, 'show_welcome_panel', 2 );
	}

	// if ( is_multisite() ) {
		// Flush rules to pick up the new page.
		$wp_rewrite->init();
		$wp_rewrite->flush_rules();

		$user = new WP_User( $user_id );
		$wpdb->update( $wpdb->options, array( 'option_value' => $user->user_email ), array( 'option_name' => 'admin_email' ) );

		// Remove all perms except for the login user.
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->usermeta WHERE user_id != %d AND meta_key = %s", $user_id, $table_prefix . 'user_level' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->usermeta WHERE user_id != %d AND meta_key = %s", $user_id, $table_prefix . 'capabilities' ) );

		// Delete any caps that snuck into the previously active blog. (Hardcoded to blog 1 for now.)
		// TODO: Get previous_blog_id.
		if ( ! is_super_admin( $user_id ) && 1 != $user_id ) {
			$wpdb->delete(
				$wpdb->usermeta,
				array(
					'user_id'  => $user_id,
					'meta_key' => $wpdb->base_prefix . '1_capabilities',
				)
			);
		}
	// }

}


/**
 * Blocking WordPress from sending installation email notice
 *
 * Empty function
function wp_new_blog_notification( $blog_title, $blog_url, $user_id, $password ) { 
/* empty function * /
 }
 */


function ft_install_defaults__post( int $user_id ) : void {
	
	// First post.
	$now             = current_time( 'mysql' );
	$now_gmt         = current_time( 'mysql', 1 );
	$first_post_guid = get_option( 'home' ) . '/?p=1';

	$first_post = get_site_option( 'first_post' );

	if ( ! $first_post ) {
		$first_post = "<!-- wp:paragraph -->\n<p>" .
		/* translators: First post content. %s: Site link. */
		__( 'Welcome to %s. This is your first post. Edit or delete it, then start writing!' ) .
		"</p>\n<!-- /wp:paragraph -->";
	}

	$first_post = sprintf(
		$first_post,
		sprintf( 
			'<a href="%s">%s</a>', 
			esc_url( network_home_url() ), 
			get_network()->site_name 
		)
	);

	$wpdb->insert(
		$wpdb->posts,
		array(
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
		)
	);

	if ( is_multisite() ) {
		update_posts_count();
	}

	$wpdb->insert(
		$wpdb->term_relationships,
		array(
			'term_taxonomy_id' => $cat_tt_id,
			'object_id'        => 1,
		)
	);

}


function ft_install_defaults__category( int $user_id ) : void {
	/**
	* Create Default category.
	*/
	$cat_name = __( 'Uncategorized' );
	/* translators: Default category slug. */
	$cat_slug = sanitize_title( _x( 'Uncategorized', 'Default category slug' ) );

	$cat_id = 1;

	$wpdb->insert(
		$wpdb->terms,
		array(
			'term_id'    => $cat_id,
			'name'       => $cat_name,
			'slug'       => $cat_slug,
			'term_group' => 0,
		)
	);
	$wpdb->insert(
		$wpdb->term_taxonomy,
		array(
			'term_id'     => $cat_id,
			'taxonomy'    => 'category',
			'description' => '',
			'parent'      => 0,
			'count'       => 1,
		)
	);
	$cat_tt_id = $wpdb->insert_id;
}


/**
 * Plugin auto-activation function
 *
 * https://gist.github.com/brasofilo/4242948#file-install-php-L16
 * http://wordpress.stackexchange.com/q/4041/12615
function wpse_4041_run_activate_plugin( $plugin )
{
    $current = get_option( 'active_plugins' );
    $plugin  = plugin_basename( trim( $plugin ) );

    if( !in_array( $plugin, $current ) )
    {
        $current[] = $plugin;
        sort( $current );
        do_action( 'activate_plugin', trim( $plugin ) );
        update_option( 'active_plugins', $current );
        do_action( 'activate_' . trim( $plugin ) );
        do_action( 'activated_plugin', trim( $plugin ) );
    }

    return null;
}
    // Activate our plugins
    // wpse_4041_run_activate_plugin( 'akismet/akismet.php' );
 */


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
 */
function ft_install_defaults__set_https_urls() {
	$_options_to_change = [
		'home',
		'siteurl',
	];
	array_map(function( $option ){
		$_updated_option = str_replace('http://', 'https://', get_option( $option ) );

		update_option( 
			$option,
			$_updated_option
		);
	}, $_options_to_change);
}
