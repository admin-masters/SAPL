<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */
/**
 * Database connection information is automatically provided.
 * There is no need to set or change the following database configuration
 * values:
 *   DB_HOST
 *   DB_NAME
 *   DB_USER
 *   DB_PASSWORD
 *   DB_CHARSET
 *   DB_COLLATE
 */
/**
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         '(Spa6TI{Z,v5N6NHXIK:,ULt<8U,Mc-H3D7Q6OU?8mIA,7WkZ~i{DO(A|CUXG6B<');
define('SECURE_AUTH_KEY',  'hv#J=-c)mx4A9a6i3,R}?NA}>k>t+IF@Uc;oir;;*bRO.[%|Yg0]D]hf>jQ3TRm9');
define('LOGGED_IN_KEY',    '9~1HeU;?5np*6h^v]:<@V9(j-TnEsVL!wks^qm^79|sAH<m#)[p_cq(aI[,>..>f');
define('NONCE_KEY',        '.@EXA1_h8B0MJJj}TQoAtd>hZ,m:iwT?WIt)H)Ut;@el=<Ccg-6$5[u,aO^~E_j;');
define('AUTH_SALT',        '??0<u#7)NL(P(TmsMPc4xo{wtg21UxV#05W@I}SF-HIR96U5e_1GzrWSo0o>kCdo');
define('SECURE_AUTH_SALT', 'g)193UVfg4D*s0f_^{btB-8[:+#EHCQGIQ*7{7s_6d#:@,OT2Dg%2wGFj?QQLOrH');
define('LOGGED_IN_SALT',   'Yk_]36JVlry!j7E%SHB+Dt235N2f-u>u;ia{*}VFG~aE,,e_L$^?9ZMe%*W,cU0-');
define('NONCE_SALT',       ']GHTsgKI2@s<~rMK_-utG{hAS=lsVw(7@p^$8XX+>W~2Q0%aJ|,{-92h1^AX.=l,');
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';
/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */

define( 'WP_ENVIRONMENT_TYPE', 'staging' );
/* That's all, stop editing! Happy blogging. */
/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
  define('ABSPATH', dirname(__FILE__) . '/');

// --- Amelia API configuration ---
if ( ! defined('AMELIA_API_KEY') ) {
    define( 'AMELIA_API_KEY', 'u1FWtL7dfRf0YuYN75twgMot2X4lXSuzwpJJuBajraYm' ); // staging key
}
if ( ! defined('AMELIA_DEFAULT_LOCATION_ID') ) {
    define( 'AMELIA_DEFAULT_LOCATION_ID', 1 );
}
if ( ! defined('AMELIA_DEFAULT_SERVICE_IDS') ) {
    define( 'AMELIA_DEFAULT_SERVICE_IDS', [4] ); // must exist in Amelia
}
if ( ! defined('AMELIA_DEFAULT_TZ') ) {
    define( 'AMELIA_DEFAULT_TZ', 'Asia/Kolkata' );
}
// if ( ! defined('AMELIA_PRIMARY_AJAX') ) {
//     define( 'AMELIA_PRIMARY_AJAX', home_url( '/amelia/wp-admin/admin-ajax.php?action=wpamelia_api&call=/api/v1/users/providers' ) );
// }
// if ( ! defined('AMELIA_FALLBACK_AJAX') ) {
//     define( 'AMELIA_FALLBACK_AJAX', admin_url( 'admin-ajax.php?action=wpamelia_api&call=/api/v1/users/providers' ) );
// }
define( 'AMELIA_API_PATH', '/api/v1/users/providers' );


// Optional: enable verbose logging to debug.log (turn off in production)
if ( ! defined('INDITECH_AMELIA_DEBUG') ) {
    define( 'INDITECH_AMELIA_DEBUG', true );
}

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');


// TEMPORARY: Check Fluent Support tables
add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) return;
    
    global $wpdb;
    $table = $wpdb->prefix . 'fs_saved_replies';
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    
    echo '<div class="notice notice-info"><p>';
    echo '<strong>Fluent Support Table Check:</strong><br>';
    echo 'Table: ' . $table . '<br>';
    echo 'Exists: ' . ($exists ? 'YES ?' : 'NO ?') . '<br>';
    
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        echo 'Saved Replies Count: ' . $count;
    }
    echo '</p></div>';
});

