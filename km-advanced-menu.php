<?php
namespace kittmedia\Advanced_Menu;
use function array_pop;
use function defined;
use function explode;
use function file_exists;
use function spl_autoload_register;
use function str_replace;
use function strlen;
use function strrpos;
use function strtolower;
use function substr_replace;

/*
Plugin Name:	Advanced Menu
Description:	KittMedia Advanced Menu automatically creates a menu of your categories and pages and allows easy sorting.
Author:			KittMedia
Version:		0.1
License:		GPL2
License URI:	https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:	km-advanced-menu
Domain Path:	/languages

Advanced_Menu is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
any later version.

Advanced_Menu is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Advanced_Menu. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/

// exit if ABSPATH is not defined
defined( 'ABSPATH' ) || exit;

/**
 * Autoload all necessary classes.
 * 
 * @param	string		$class The class name of the auto-loaded class
 */
spl_autoload_register( function( string $class ) {
	$namespace = strtolower( __NAMESPACE__ . '\\' );
	$path = explode( '\\', $class );
	$filename = str_replace( '_', '-', strtolower( array_pop( $path ) ) );
	$class = str_replace(
		[ $namespace, '\\', '_' ],
		[ '', '/', '-' ],
		strtolower( $class )
	);
	$string_position = strrpos( $class, $filename );
	
	if ( $string_position !== false ) {
		$class = substr_replace( $class, 'class-' . $filename, $string_position, strlen( $filename ) );
	}
	
	$maybe_file = __DIR__ . '/inc/' . $class . '.php';
	
	if ( file_exists( $maybe_file ) ) {
		/** @noinspection PhpIncludeInspection */
		require_once( __DIR__ . '/inc/' . $class . '.php' );
	}
} );

Advanced_Menu::get_instance()->set_plugin_file( __FILE__ );
Advanced_Menu::get_instance()->init();
