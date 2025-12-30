<?php
define( 'WP_CACHE', true );

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'u911375652_h6EEM' );

/** Database username */
define( 'DB_USER', 'u911375652_PXj3B' );

/** Database password */
define( 'DB_PASSWORD', 'HvDSe8GFAo' );

/** Database hostname */
define( 'DB_HOST', '127.0.0.1' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          '_k>~f}Z;o?hn_yBiX=-djF+;o~JzKPe$,S!}2]i[9P:!cW5VVd,I#N W}Z*ta_TE' );
define( 'SECURE_AUTH_KEY',   '0kr%(=]Jy7I,NNe]xB}`<l$Cu$r|x]gC~il+>nFJ~dE1&]Eg$_.K8qjRB;4CYa6%' );
define( 'LOGGED_IN_KEY',     '80.nH|T8-S6Q,LT![#rH7X4d=jJ8hCwC>>VSfrDpj2$8:X)9eC@hry8K,/:X@pBF' );
define( 'NONCE_KEY',         'X;Z*+S5BQpX3l[{i%<b$Up9Iqr0JF:L8m1{18ZIO_P&)y&StEB@V+LZhR#Z[+SJB' );
define( 'AUTH_SALT',         'jju+J};kC|(y{X#N_ls{g#) n5WGD?;;M:HZ(_=8d^R^AH!s-z]tc~{RTn5CFZOY' );
define( 'SECURE_AUTH_SALT',  'RXPqA;v%%1K?ihLqt5[&x[PtYATdtG[.W|)D5wJ*iQ3snSuRj)#G?N^~[h!|N{AD' );
define( 'LOGGED_IN_SALT',    '%#=&h;+U9wz,Avoe%]gMMX&6PdD0~3|]/qB-8z5 ^x]pf[KaO7*RiGcXd8wTzDn0' );
define( 'NONCE_SALT',        'zYlw8=Fj1r0CJ!3eSUH*t&J>/ii@uq]$IO:&+WG@jh9b|T=b~[>!GEH/nM5W0w9Y' );
define( 'WP_CACHE_KEY_SALT', ',35{KiF+ozX7@*xnDYyw f;hgOfM-;XUI6qQI@.&%g+A6L%7u.4D^q3}*a-L(:Dn' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'FS_METHOD', 'direct' );
define( 'COOKIEHASH', '4b2fd0aa9cd4bd1e3bd537c2f9383190' );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
define('FORMIDABLE_PRO_LOAD_PDFS', true);
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
