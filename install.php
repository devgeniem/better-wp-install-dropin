<?php
/**
 * Plugin name: Better WP install.php dropin
 * Plugin URI: https://github.com/devgeniem/better-wp-install-dropin
 * Description: This dropin doesn't install bloat content like the default install.php and sets a few opionated wp options.
 * Author: Onni Hakala / Geniem Oy
 * Author URI: https://github.com/onnimonni
 * License: GPLv3
 * License URI: https://opensource.org/licenses/GPL-3.0
 * Version: 1.0
 */

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
function wp_install( string $blog_title, string $user_name, string $user_email, bool $public,
    string $deprecated = '', string $user_password = '', string $language = '' ) {

  if ( ! empty( $deprecated ) ) {
    _deprecated_argument( __FUNCTION__, '2.6' );
  }

  wp_check_mysql_version();
  wp_cache_flush();
  make_db_current_silent();
  populate_options();
  populate_roles();

  // Use language from installer or env WPLANG or default to 'fi'.
  if ( $language ) {
    update_option( 'WPLANG', $language );
  } elseif ( ! empty( getenv( 'WPLANG' ) ) ) {
    update_option( 'WPLANG', getenv( 'WPLANG' ) );
  } else {
    update_option( 'WPLANG', 'fi' );
  }

  update_option( 'blogname', $blog_title );
  update_option( 'admin_email', $user_email );
  update_option( 'blog_public', $public );

  // Prefer empty description if someone forgots to change it.
  update_option( 'blogdescription', '' );

  $guessurl = wp_guess_url();

  update_option( 'siteurl', $guessurl );

  // If not a public blog, don't ping.
  if ( ! $public ) {
    update_option( 'default_pingback_flag', 0 );
  }

  /*
   * Create default user. If the user already exists, the user tables are
   * being shared among blogs. Just set the role in that case.
   */
  $user_id = username_exists( $user_name );
  $user_password = trim( $user_password );
  $email_password = false;
  if ( ! $user_id && empty( $user_password ) ) {
    $user_password = wp_generate_password( 12, false );
    $message = __('<strong><em>Note that password</em></strong> carefully! It is a <em>random</em> password that was generated just for you.');
    $user_id = wp_create_user($user_name, $user_password, $user_email);
    update_user_option($user_id, 'default_password_nag', true, true);
    $email_password = true;
  } elseif ( ! $user_id ) {
    // Password has been provided.
    $message = '<em>'.__('Your chosen password.').'</em>';
    $user_id = wp_create_user($user_name, $user_password, $user_email);
  } else {
    $message = __('User already exists. Password inherited.');
  }

  $user = new WP_User($user_id);
  $user->set_role('administrator');

  wp_install_defaults($user_id);

  wp_install_maybe_enable_pretty_permalinks();

  flush_rewrite_rules();

  wp_cache_flush();

  /**
   * Fires after a site is fully installed.
   *
   * @since 3.9.0
   *
   * @param WP_User $user The site owner.
   */
  do_action( 'wp_install', $user );

  return array( 'url' => $guessurl, 'user_id' => $user_id, 'password' => $user_password, 'password_message' => $message );
}

/**
 * Creates the initial content for a newly-installed site.
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
  update_option( 'timezone_string', ( ! empty( getenv( 'TZ' ) ) ? getenv( 'TZ' ) : 'Europe/Helsinki' ) );

  /**
   * We don't want any default widgets. This fixes 'Undefined index: wp_inactive_widgets'
   *
   * @see wp-includes/widgets.php:1208
   */
  update_option( 'sidebars_widgets', array( 'wp_inactive_widgets' => array() ) );

  /**
   * Before a comment appears a comment must be manually approved: true
   *
   * @see wp-admin/options-discussion.php
   */
  update_option( 'comment_moderation', 1 );

  /** Before a comment appears the comment author must have a previously approved comment: false */
  update_option( 'comment_whitelist', 0 );

  /** Allow people to post comments on new articles (this setting may be overridden for individual articles): false */
  update_option( 'default_comment_status', 0 );

  /** Allow link notifications from other blogs: false */
  update_option( 'default_ping_status', 0 );

  /** Attempt to notify any blogs linked to from the article: false */
  update_option( 'default_pingback_flag', 0 );

  /**
   * Organize my uploads into month- and year-based folders: true
   *
   * @see wp-admin/options-media.php
   */
  update_option( 'uploads_use_yearmonth_folders', 1 );

  /**
   * Permalink custom structure: /%category%/%postname%
   *
   * @see wp-admin/options-permalink.php
   */
  update_option( 'permalink_structure', '/%category%/%postname%/' );

  /**
   * Create Default category.
   */
  // Somehow translations won't work always. So check if Finnish was used.
  $cat_name = ( get_option('WPLANG') == 'fi' ) ? 'Yleinen' : __('Uncategorized');

  /* translators: Default category slug */
  $cat_slug = sanitize_title( ( get_option('WPLANG') == 'fi' ) ? 'yleinen' : _x('Uncategorized', 'Default category slug') );

  if ( global_terms_enabled() ) {
    $cat_id = $wpdb->get_var( $wpdb->prepare( "SELECT cat_ID FROM {$wpdb->sitecategories} WHERE category_nicename = %s", $cat_slug ) );
    if ( null == $cat_id ) {
      $wpdb->insert( $wpdb->sitecategories, array( 'cat_ID' => 0, 'cat_name' => $cat_name, 'category_nicename' => $cat_slug, 'last_updated' => current_time('mysql', true) ) );
      $cat_id = $wpdb->insert_id;
    }
    update_option('default_category', $cat_id);
  } else {
    $cat_id = 1;
  }

  $wpdb->insert( $wpdb->terms, array( 'term_id' => $cat_id, 'name' => $cat_name, 'slug' => $cat_slug, 'term_group' => 0 ) );
  $wpdb->insert( $wpdb->term_taxonomy, array( 'term_id' => $cat_id, 'taxonomy' => 'category', 'description' => '', 'parent' => 0, 'count' => 1 ) );
  $cat_tt_id = $wpdb->insert_id;

  if ( is_multisite() ) {
    // Flush rules to pick up the new page.
    $wp_rewrite->init();
    $wp_rewrite->flush_rules();

    $user = new WP_User($user_id);
    $wpdb->update( $wpdb->options, array( 'option_value' => $user->user_email ), array( 'option_name' => 'admin_email' ) );

    // Remove all perms except for the login user.
    $wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->usermeta WHERE user_id != %d AND meta_key = %s", $user_id, $table_prefix.'user_level') );
    $wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->usermeta WHERE user_id != %d AND meta_key = %s", $user_id, $table_prefix.'capabilities') );

    // Delete any caps that snuck into the previously active blog. (Hardcoded to blog 1 for now.) TODO: Get previous_blog_id.
    if ( ! is_super_admin( $user_id ) && 1 != $user_id ) {
      $wpdb->delete( $wpdb->usermeta, array( 'user_id' => $user_id, 'meta_key' => $wpdb->base_prefix.'1_capabilities' ) );
    }
  }

  /**
   * Show welcome panel: false
   *
   * @see wp-admin/includes/screen.php
   */
  update_user_meta( $user_id, 'show_welcome_panel', 0 );
}
