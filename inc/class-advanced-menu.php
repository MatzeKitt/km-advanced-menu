<?php
namespace kittmedia\Advanced_Menu;
use WP_Post;
use WP_Term;
use function add_action;
use function add_shortcode;
use function array_merge;
use function array_unique;
use function dirname;
use function esc_html;
use function esc_url;
use function file_exists;
use function get_ancestors;
use function get_blog_details;
use function get_categories;
use function get_category_link;
use function get_current_blog_id;
use function get_pages;
use function get_permalink;
use function get_post_field;
use function get_queried_object_id;
use function get_query_var;
use function get_sites;
use function get_term;
use function get_term_meta;
use function get_the_ID;
use function implode;
use function in_array;
use function is_a;
use function load_plugin_textdomain;
use function ms_is_switched;
use function plugin_basename;
use function register_taxonomy_for_object_type;
use function restore_current_blog;
use function set_site_transient;
use function shortcode_atts;
use function sprintf;
use function str_replace;
use function strnatcasecmp;
use function switch_to_blog;
use function uasort;
use function update_term_meta;
use function usort;
use function wp_get_post_categories;
use function wp_get_post_parent_id;
use function wp_set_post_categories;
use function wp_unslash;
use function wp_update_post;
use function wp_update_term;

/**
 * The main Advanced_Menu class.
 * 
 * @author	KittMedia
 * @license	GPL2 <https://www.gnu.org/licenses/gpl-2.0.html>
 */
class Advanced_Menu {
	/**
	 * @var		\kittmedia\Advanced_Menu\Advanced_Menu
	 */
	private static $instance;
	
	/**
	 * @var		string The plugin filename
	 */
	public $plugin_file = '';
	
	/**
	 * Advanced_Menu constructor.
	 */
	public function __construct() {
		self::$instance = $this;
	}
	
	/**
	 * Initialize functions.
	 */
	public function init(): void {
		add_action( 'init', [ $this, 'load_textdomain' ], 0 );
		add_action( 'init', [ $this, 'register_page_category_taxonomy' ] );
		add_action( 'created_category', [ $this, 'reset_menu_hierarchy' ] );
		add_action( 'delete_category', [ $this, 'reset_menu_hierarchy' ] );
		add_action( 'delete_post', [ $this, 'reset_menu_hierarchy' ] );
		add_action( 'edited_category', [ $this, 'reset_menu_hierarchy' ] );
		add_action( 'rest_after_insert_page', [ $this, 'reset_menu_hierarchy' ] );
		
		add_shortcode( 'advanced_menu', [ $this, 'render_shortcode' ] );
		
		Admin::get_instance()->init();
	}
	
	/**
	 * Get a unique instance of the class.
	 * 
	 * @return	\kittmedia\Advanced_Menu\Advanced_Menu
	 */
	public static function get_instance(): Advanced_Menu {
		if ( static::$instance === null ) {
			static::$instance = new static();
		}
		
		return static::$instance;
	}
	
	/**
	 * Load translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'km-advanced-menu', false, dirname( plugin_basename( $this->plugin_file ) ) . '/languages' );
	}
	
	/**
	 * Get all category ancestors of an item.
	 * 
	 * @param	int		$id Current item ID
	 * @return	array All ancestors
	 */
	private function get_ancestor_categories( int $id ): array {
		$ancestors = [];
		
		foreach ( wp_get_post_categories( $id ) as $category_id ) {
			$ancestors = array_merge( [ $category_id ], get_ancestors( $category_id, 'category' ), $ancestors );
		}
		
		return array_unique( $ancestors );
	}
	
	/**
	 * Get advanced menu HTML markup.
	 * 
	 * @param	array		$items The menu items
	 * @param	string[]	$atts Shortcode attributes
	 * @param	string		$output The current markup
	 * @param	int			$current_depth Current menu level
	 * @param	bool		$was_current If only sub items should be displayed, whether the main item has been already processed
	 * @return	string The menu markup
	 */
	private function get_menus( array $items, array $atts, string $output = '', int $current_depth = 0, bool $was_current = false ): string {
		$display_arrows = false;
		
		if ( $atts['arrows'] ) {
			$atts['depth'] = 0;
			$display_arrows = true;
		}
		
		if ( ! empty( $items['meta']['site_id'] ) && (int) $items['meta']['site_id'] !== get_current_blog_id() ) {
			switch_to_blog( $items['meta']['site_id'] );
		}
		
		if ( empty( $output ) ) {
			$output = '<nav class="km-advanced-menu__main-navigation">';
			
			if ( ! empty( $items['meta'] ) ) {
				$output .= '<h2 class="navigation-title' . ( ms_is_switched() ? ' is-hidden' : '' ) . '">' . esc_html( $items['meta']['site_name'] ) . '</h2>';
			}
			
			$output .= '%1$s';
			$output .= '</nav>';
		}
		
		$output = sprintf( $output, '<ul class="menu">%1$s' );
		
		$current_depth++;
		
		foreach ( $items as $key => $item ) {
			if ( $key === 'meta' ) {
				continue;
			}
			
			if ( is_a( $item, 'WP_Term' ) ) {
				$item->id = $item->term_id;
				$item->title = $item->name;
				$item->type = 'category';
				$link = get_category_link( $item->id );
			}
			else if ( is_a( $item, 'WP_Post' ) ) {
				$item->id = $item->ID;
				$item->title = $item->post_title;
				$item->type = 'page';
				$link = get_permalink( $item->id );
			}
			else {
				continue;
			}
			
			if ( $atts['only_sub'] ) {
				$ancestors = [];
				$ancestors[ $item->type ] = get_ancestors( $item->id, $item->type );
				
				if ( $item->type === 'page' ) {
					$ancestors['category'] = $this->get_ancestor_categories( $item->id );
				}
				
				if ( $item->id === get_queried_object_id() ) {
					$output = '<nav class="rh-collector-main-navigation">';
					$output .= '%1$s';
					$output .= '</nav>';
					$was_current = true;
				}
				
				if (
					$was_current && (
						( ! empty( $ancestors['category'] ) && in_array( get_queried_object_id(), $ancestors['category'], true ) )
						|| ( ! empty( $ancestors['page'] ) && in_array( get_queried_object_id(), $ancestors['page'], true ) )
					)
				) {
					$item_classes = $this->get_item_classes( $item, $atts['depth'], $current_depth );
					$output = sprintf( $output, '<li class="' . esc_attr( implode( ' ', $item_classes ) ) . '"><a href="' . esc_url( $link ) . '">' . esc_html( $item->title ) . '</a>%1$s' );
					
					// drop-down arrow
					if ( $display_arrows && in_array( 'menu-item-has-children', $item_classes, true ) ) {
						$output = sprintf( $output, '<span class="drop-down-arrow"></span>%1$s' );
					}
					
					if ( ! empty( $item->children ) && ( ! $atts['depth'] || $current_depth <= $atts['depth'] ) ) {
						$output = $this->get_menus( $item->children, $atts, $output, $current_depth, $was_current );
					}
					
					$output = sprintf( $output, '</li>%1$s' );
				}
				else if ( $item->id === get_queried_object_id() || ! empty( $item->children ) && ( ! $atts['depth'] || $current_depth <= $atts['depth'] ) ) {
					$output = $this->get_menus( $item->children, $atts, $output, $current_depth, $was_current );
				}
				
				if ( $item->id === get_queried_object_id() ) {
					$output = str_replace( '%1$s', '', $output );
					break;
				}
			}
			else {
				$item_classes = $this->get_item_classes( $item, $atts['depth'], $current_depth );
				$output = sprintf( $output, '<li class="' . esc_attr( implode( ' ', $item_classes ) ) . '"><a href="' . esc_url( $link ) . '">' . esc_html( $item->title ) . '</a>%1$s' );
				
				// drop-down arrow
				if ( $display_arrows && in_array( 'menu-item-has-children', $item_classes, true ) ) {
					$output = sprintf( $output, '<span class="drop-down-arrow"></span>%1$s' );
				}
				
				if ( ! empty( $item->children ) && ( ! $atts['depth'] || $current_depth < $atts['depth'] ) ) {
					$output = $this->get_menus( $item->children, $atts, $output, $current_depth );
				}
				
				$output = sprintf( $output, '</li>%1$s' );
			}
		}
		
		$output = sprintf( $output, '</ul>%1$s' );
		
		if ( ! empty( $items['meta']['site_id'] ) ) {
			restore_current_blog();
		}
		
		return $output;
	}
	
	/**
	 * Get CSS classes for a menu item.
	 * 
	 * @param	WP_Post|WP_Term	$item The menu item
	 * @param	int				$depth Maximum menu level to output
	 * @param	int				$current_depth Current menu level
	 * @return string[]
	 */
	private function get_item_classes( $item, int $depth, int $current_depth ): array {
		$current_page = [
			'category' => get_query_var( 'cat' ),
			'page' => get_the_ID(),
		];
		$is_current = false;
		
		if ( $item->id === $current_page[ $item->type ] ) {
			$is_active = true;
			$is_current = true;
		}
		else if ( ! empty( $item->children ) ) {
			$is_active = $this->has_active_children( $item->children, $current_page, $depth, $current_depth );
		}
		else {
			$is_active = false;
		}
		
		$classes = [
			'menu-item',
		];
		
		if ( ! empty( $item->children ) && ( ! $depth || $current_depth <= $depth ) ) {
			$classes[] = 'menu-item-has-children';
		}
		
		if ( $is_active ) {
			$classes[] = 'current-menu-ancestor';
			$classes[] = 'current-page-ancestor';
		}
		
		if ( $is_current ) {
			$classes[] = 'current-menu-item';
		}
		
		if ( ! $is_active && ! $is_current ) {
			$classes[] = 'is-hidden';
		}
		
		return $classes;
	}
	
	/**
	 * Get the menu items in hierarchical order.
	 * 
	 * @return	array Menu items
	 */
	public function get_items(): array {
		$site_relation = [];
		
		foreach ( get_sites( [ 'number' => 10000 ] ) as $site ) {
			switch_to_blog( $site->blog_id );
			
			$categories = get_categories();
			$category_hierarchy = [];
			$pages = get_pages( [ 'sort_column' => 'menu_order' ] );
			$post_hierarchy = [];
			
			// sort categories by their menu order
			usort( $categories, function( $a, $b ) {
				$a_order = (int) get_term_meta( $a->term_id, 'menu-order', true ) ?: 100000;
				$b_order = (int) get_term_meta( $b->term_id, 'menu-order', true ) ?: 100000;
				
				return ( $a_order <=> $b_order );
			} );
			
			$this->sort_terms_hierarchically( $categories, $category_hierarchy );
			$this->sort_posts_hierarchically( $pages, $post_hierarchy );
			
			$site_relation[ $site->blog_id ] = $this->sort_terms_posts_hierarchically( $category_hierarchy, $post_hierarchy );
			$site_relation[ $site->blog_id ]['meta'] = [
				'site_id' => (int) $site->blog_id,
				'site_name' => get_blog_details( $site->blog_id )->blogname,
			];
			
			restore_current_blog();
		}
		
		return $site_relation;
	}
	
	/**
	 * Check if the current menu item has active children.
	 * 
	 * @param	array	$children The menu item children
	 * @param	int[]	$current_page Category ID and page ID of the current page
	 * @param	int		$depth Maximum menu level to output
	 * @param	int		$current_depth Current menu level
	 * @return	bool True if menu item has at least one active child, false otherwise
	 */
	private function has_active_children( array $children, array $current_page, int $depth, int $current_depth = 0 ): bool {
		$has_children = false;
		
		if ( $depth && $current_depth > $depth ) {
			return $has_children;
		}
		
		foreach ( $children as $child ) {
			if ( is_a( $child, 'WP_Post' ) ) {
				$child->id = $child->ID;
				$type = 'page';
			}
			else {
				$child->id = $child->term_id;
				$type = 'category';
			}
			
			if ( ! empty( $current_page[ $type ] ) && $child->id === $current_page[ $type ] ) {
				$has_children = true;
				break;
			}
			
			$has_children = $this->has_active_children( $child->children, $current_page, $depth, $current_depth );
			
			if ( $has_children ) {
				break;
			}
		}
		
		return $has_children;
	}
	
	/**
	 * Register the category taxonomy for pages.
	 */
	public function register_page_category_taxonomy() {
		register_taxonomy_for_object_type( 'category', 'page' );
	}
	
	/**
	 * Advanced menu shortcode.
	 * 
	 * @param	string[]	$atts Shortcode attributes
	 * @return	string The custom menu markup
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts( [
			'arrows' => false,
			'depth' => 0,
			'only_current_site' => 0,
			'only_sub' => 0,
		], $atts );
		$current_blog_id = get_current_blog_id();
		
		if ( ! $menu_items = get_site_transient( 'km_advanced_menu_hierarchy' ) ) {
			$menu_items = $this->get_items();
			
			set_site_transient( 'km_advanced_menu_hierarchy', $menu_items, 60 * 60 * 24 );
		}
		
		// sort menu by site name
		// but the current site is always on top
		uasort( $menu_items, function( $a, $b ) use ( $current_blog_id ) {
			if ( $a['meta']['site_id'] === $current_blog_id ) {
				return -1;
			}
			else if ( $b['meta']['site_id'] === $current_blog_id ) {
				return 1;
			}
			
			return strnatcasecmp( $a['meta']['site_name'], $b['meta']['site_name'] );
		} );
		
		$output = '';
		
		foreach ( $menu_items as $items ) {
			if ( $atts['only_current_site'] && $items['meta']['site_id'] !== $current_blog_id ) {
				continue;
			}
			
			$output .= str_replace( '%1$s', '', $this->get_menus( $items, $atts ) );
			
			// stop after first iteration to get only data of the current site
			if ( $atts['only_sub'] ) {
				break;
			}
		}
		
		return $output;
	}
	
	/**
	 * Reset menu hierarchy on certain actions.
	 */
	public function reset_menu_hierarchy() {
		set_site_transient( 'km_advanced_menu_hierarchy', $this->get_items(), 60 * 60 * 24 );
	}
	
	/**
	 * Set the plugin file.
	 * 
	 * @param	string	$file The path to the file
	 */
	public function set_plugin_file( string $file ): void {
		if ( file_exists( $file ) ) {
			$this->plugin_file = $file;
		}
	}
	
	/**
	 * Assign a post to a category in a hierarchical order.
	 * 
	 * @param	array		$category_hierarchy Hierarchical category list
	 * @param	int			$category_id Category ID to assign the post to
	 * @param	\WP_Post	$post The post to assign
	 * @return	bool True if the post has been assigned to a category, false otherwise
	 */
	private function set_post_to_category( array &$category_hierarchy, int $category_id, WP_Post $post ): bool {
		foreach ( $category_hierarchy as &$category ) {
			if ( $category->term_id === $category_id ) {
				$category->children[] = $post;
				
				return true;
			}
			
			$this->set_post_to_category( $category->children, $category_id, $post );
		}
		
		return false;
	}
	
	/**
	 * Recursively sort an array of posts hierarchically.
	 * Child posts will be placed under a 'children' member of their parent post.
	 * 
	 * @see		https://wordpress.stackexchange.com/a/99516
	 * 
	 * @param	array	$posts Post objects to sort
	 * @param	array	$into Result array to put them in
	 * @param	int		$parent_id Current parent ID to put them in
	 */
	private function sort_posts_hierarchically( array &$posts, array &$into, int $parent_id = 0 ) {
		foreach ( $posts as $i => $post ) {
			if ( $post->post_parent == $parent_id ) {
				$into[] = $post;
				unset( $posts[ $i ] );
			}
		}
		
		foreach ( $into as $top_post ) {
			$top_post->children = [];
			$this->sort_posts_hierarchically( $posts, $top_post->children, $top_post->ID );
		}
	}
	
	/**
	 * Recursively sort an array of taxonomy terms hierarchically.
	 * Child categories will be placed under a 'children' member of their parent term.
	 * 
	 * @see		https://wordpress.stackexchange.com/a/99516
	 * 
	 * @param	array	$cats Taxonomy term objects to sort
	 * @param	array	$into Result array to put them in
	 * @param	int		$parent_id Current parent ID to put them in
	 */
	private function sort_terms_hierarchically( array &$cats, array &$into, int $parent_id = 0 ) {
		foreach ( $cats as $i => $cat ) {
			if ( $cat->parent == $parent_id ) {
				$into[] = $cat;
				unset( $cats[ $i ] );
			}
		}
		
		foreach ( $into as $top_cat ) {
			$top_cat->children = [];
			$this->sort_terms_hierarchically( $cats, $top_cat->children, $top_cat->term_id );
		}
	}
	
	/**
	 * Sort hierarchical posts in a hierarchical category list.
	 * 
	 * @param	array	$categories Categories to sort the posts into
	 * @param	array	$posts Posts to sort in
	 * @return	array A list of hierarchical categories and posts
	 */
	private function sort_terms_posts_hierarchically( array $categories, array &$posts ): array {
		// assign posts to category and remove them from the list
		// all remaining posts have no category
		foreach ( $posts as $key => $post ) {
			$post_categories = wp_get_post_categories( $post->ID );
			
			if ( $post_categories ) {
				foreach ( $post_categories as $category_id ) {
					$this->set_post_to_category( $categories, $category_id, $post );
				}
				
				unset( $posts[ $key ] );
			}
		}
		
		$into = array_merge( $categories, $posts );
		
		// sort items on first level by their menu order
		usort( $into, function( $a, $b ) {
			if ( isset( $a->menu_order ) ) {
				$a_order = $a->menu_order ?: 100000;
			}
			else {
				$a_order = (int) get_term_meta( $a->term_id, 'menu-order', true ) ?: 100000;
			}
			
			
			if ( isset( $b->menu_order ) ) {
				$b_order = $b->menu_order ?: 100000;
			}
			else {
				$b_order = (int) get_term_meta( $b->term_id, 'menu-order', true ) ?: 100000;
			}
			
			return ( $a_order <=> $b_order );
		} );
		
		return $into;
	}
	
	/**
	 * Update menu items.
	 */
	public function update_menu_items(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$items_ids = wp_unslash( $_POST['menu-item-object-id'] );
		$position = 1;
		$updates = [];
		
		foreach ( $items_ids as $id => $item_id ) {
			$object_type = isset( $_POST['menu-item-type'][ $id ] ) ? wp_unslash( $_POST['menu-item-type'][ $id ] ) : '';
			$parent_ids = isset( $_POST['menu-item-parent-id'][ $id ] ) ? wp_unslash( $_POST['menu-item-parent-id'][ $id ] ) : [];
			$parent_types = isset( $_POST['menu-item-parent-type'][ $id ] ) ? wp_unslash( $_POST['menu-item-parent-type'][ $id ] ) : [];
			
			if ( $object_type === 'taxonomy' ) {
				$term = get_term( $item_id );
				$type = isset( $_POST['menu-item-object'][ $id ] ) ? wp_unslash( $_POST['menu-item-object'][ $id ] ) : '';
				
				if ( ! is_a( $term, 'WP_Term' ) ) {
					continue;
				}
				
				// update taxonomy parents
				// only the last one gets saved since taxonomies
				// cannot have multiple parents
				foreach ( $parent_ids as $key => $parent_id ) {
					// no update required
					if ( isset( $term->parent ) && $term->parent === (int) $parent_id ) {
						break;
					}
					
					if ( $parent_types[ $key ] === 'taxonomy' || empty( $parent_types[ $key ] ) ) {
						$updates['terms'][ $item_id ]['parent'] = [
							'parent_id' => $parent_id,
							'type' => $type,
						];
					}
				}
				
				if ( (int) get_term_meta( $id, 'menu-order', true ) !== $position ) {
					$updates['terms'][ $item_id ]['menu_order'] = $position;
				}
			}
			else if ( $object_type === 'post_type' ) {
				// update parents
				$parent_categories = [];
				$parent_post = 0;
				$previous_categories = wp_get_post_categories( $item_id );
				
				// get parents
				foreach ( $parent_ids as $key => $parent_id ) {
					if ( $parent_types[ $key ] === 'taxonomy' ) {
						$parent_categories[] = (int) $parent_id;
					}
					else if ( $parent_types[ $key ] === 'post_type' ) {
						$parent_post = (int) $parent_id;
					}
				}
				
				// update post order and parent
				if (
					get_post_field( 'menu_order', $item_id ) !== $position
					|| wp_get_post_parent_id( $item_id ) !== $parent_post
				) {
					$updates['posts'][ $item_id ] = [
						'post_array' => [
							'ID' => $item_id,
							'menu_order' => $position,
							'post_parent' => $parent_post,
						],
					];
				}
				
				if ( $previous_categories !== $parent_categories ) {
					$updates['posts'][ $item_id ]['categories'] = $parent_categories;
				}
			}
			
			$position++;
		}
		// phpcs:enable
		
		// update values
		foreach ( $updates as $type => $data ) {
			foreach ( $data as $item_id => $update ) {
				if ( $type === 'posts' ) {
					// post data
					if ( isset( $update['post_array'] ) ) {
						wp_update_post( $update['post_array'] );
					}
					
					// post categories
					if ( isset( $update['categories'] ) ) {
						wp_set_post_categories( $item_id, $update['categories'] );
					}
				}
				else if ( $type === 'terms' ) {
					// term parent
					if ( isset( $update['parent'] ) ) {
						wp_update_term( $item_id, $update['parent']['type'], [
							'parent' => $update['parent']['parent_id'],
						] );
					}
					
					// term order
					if ( isset( $update['menu_order'] ) ) {
						update_term_meta( $item_id, 'menu-order', $update['menu_order'] );
					}
				}
			}
		}
		
		$this->reset_menu_hierarchy();
	}
}
