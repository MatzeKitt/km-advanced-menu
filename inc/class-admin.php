<?php
namespace kittmedia\Advanced_Menu;
use WP_Term;
use function add_action;
use function add_submenu_page;
use function admin_url;
use function check_admin_referer;
use function current_user_can;
use function defined;
use function delete_term_meta;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function esc_url;
use function filemtime;
use function get_current_blog_id;
use function get_term_meta;
use function ob_get_clean;
use function ob_start;
use function plugin_dir_path;
use function plugin_dir_url;
use function remove_submenu_page;
use function sanitize_text_field;
use function submit_button;
use function update_term_meta;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_nonce_field;
use function wp_unslash;
use const SCRIPT_DEBUG;

/**
 * Admin related functions.
 * 
 * @author	Matthias Kittsteiner
 * @license	GPL2 <https://www.gnu.org/licenses/gpl-2.0.html>
 */
class Admin {
	/**
	 * @var		\kittmedia\Advanced_Menu\Admin
	 */
	private static $instance;
	
	/**
	 * Heimdall constructor.
	 */
	public function __construct() {
		self::$instance = $this;
	}
	
	/**
	 * Initialize functions.
	 */
	public function init(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_menu', [ $this, 'set_menu_links' ] );
		add_action( 'category_add_form_fields', [ $this, 'add_category_fields' ] );
		add_action( 'category_edit_form_fields', [ $this, 'edit_category_fields' ] );
		add_action( 'created_category', [ $this, 'save_category_fields' ] );
		add_action( 'edited_category', [ $this, 'save_category_fields' ] );
	}
	
	/**
	 * Echo additional category fields.
	 */
	public function add_category_fields(): void {
		?>
		<div class="form-field term-menu-order-wrap">
			<label for="tag-menu-order"><?php esc_html_e( 'Order', 'km-advanced-menu' ); ?></label>
			<input type="text" name="menu-order" id="tag-menu-order" value="" />
		</div>
		<?php
	}
	
	/**
	 * Echo additional category fields.
	 * 
	 * @param	\WP_Term	$term Term object
	 */
	public function edit_category_fields( WP_Term $term ): void {
		$value = ( get_term_meta( $term->term_id, 'menu-order', true ) ?: '' );
		?>
		<tr class="form-field term-menu-order-wrap">
			<th scope="row"><label for="menu-order"><?php esc_html_e( 'Order', 'km-advanced-menu' ); ?></label></th>
			<td><input type="text" name="menu-order" id="tag-menu-order" value="<?php echo esc_attr( $value ); ?>" /></td>
		</tr>
		<?php
	}
	
	/**
	 * Enqueue admin assets.
	 */
	public function enqueue_assets(): void {
		// check for SCRIPT_DEBUG
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		
		$file_path = plugin_dir_path( Advanced_Menu::get_instance()->plugin_file ) . 'assets/style/admin' . $suffix . '.css';
		$file_url = plugin_dir_url( Advanced_Menu::get_instance()->plugin_file ) . 'assets/style/admin' . $suffix . '.css';
		wp_enqueue_style( 'km-advanced-menu-admin', $file_url, [], filemtime( $file_path ) );
		
		if ( ! empty( $_GET['page'] ) && $_GET['page'] === 'km-advanced-menu-menu' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$file_path = plugin_dir_path( Advanced_Menu::get_instance()->plugin_file ) . 'assets/js/menu' . $suffix . '.js';
			$file_url = plugin_dir_url( Advanced_Menu::get_instance()->plugin_file ) . 'assets/js/menu' . $suffix . '.js';
			wp_enqueue_script( 'km-advanced-menu-menu', $file_url, [ 'jquery-ui-sortable', 'jquery-ui-draggable' ], filemtime( $file_path ), true );
		}
	}
	
	/**
	 * Get a unique instance of the class.
	 * 
	 * @return	\kittmedia\Advanced_Menu\Admin
	 */
	public static function get_instance(): Admin {
		if ( static::$instance === null ) {
			static::$instance = new static();
		}
		
		return static::$instance;
	}
	
	/**
	 * Get the menu HTML.
	 * 
	 * @param	array	$items Menu items
	 * @param	int		$depth Current depth
	 * @param	int		$position Absolute item position
	 * @param	int		$parent Parent object ID
	 * @param	string	$parent_type Parent object type
	 * @return	string The menu HTML
	 */
	private function get_menu_html( array $items, int $depth = 0, int &$position = 0, int $parent = 0, string $parent_type = '' ): string {
		ob_start();
		
		foreach ( $items as $key => $item ) :
		if ( $key === 'meta' ) {
			continue;
		}
		
		$position++;
		
		if ( ! empty( $item->name ) ) {
			$id = $item->term_id;
			$object_type = 'taxonomy';
			$title = $item->name;
			$type = 'category';
		}
		else {
			$id = $item->ID;
			$object_type = 'post_type';
			$title = $item->post_title;
			$type = 'page';
		}
		
		if ( empty( $title ) ) {
			$title = __( '(no title)', 'km-advanced-menu' );
		}
		?>
		<li id="menu-item-<?php echo esc_attr( $id ); ?>" class="menu-item menu-item-<?php echo esc_attr( $type ); ?> menu-item-depth-<?php echo esc_attr( $depth ); ?>">
			<div class="menu-item-bar">
				<div class="menu-item-handle ui-sortable-handle">
					<span class="item-title"><span class="menu-item-title"><?php echo esc_html( $title ); ?></span></span>
				</div>
			</div>
			
			<input class="menu-item-data-db-id" type="hidden" name="menu-item-db-id[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $id ); ?>">
			<input class="menu-item-data-object-id" type="hidden" name="menu-item-object-id[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $id ); ?>">
			<input class="menu-item-data-object" type="hidden" name="menu-item-object[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $type ); ?>">
			<input class="menu-item-data-parent-id" type="hidden" name="menu-item-parent-id[<?php echo esc_attr( $id ); ?>][]" value="<?php echo esc_attr( $parent ); ?>">
			<input class="menu-item-data-parent-type" type="hidden" name="menu-item-parent-type[<?php echo esc_attr( $id ); ?>][]" value="<?php echo esc_attr( $parent_type ); ?>">
			<input class="menu-item-data-position" type="hidden" name="menu-item-position[<?php echo esc_attr( $id ); ?>][]" value="<?php echo esc_attr( $position ); ?>">
			<input class="menu-item-data-type" type="hidden" name="menu-item-type[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $object_type ); ?>">
			
			<ul class="menu-item-transport"></ul>
		</li>
		<?php
		if ( ! empty( $item->children ) ) {
			$depth++;
			$old_parent = $parent;
			$old_parent_type = $parent_type;
			$parent = $id;
			$parent_type = $object_type;
			
			echo $this->get_menu_html( $item->children, $depth, $position, $parent, $parent_type ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			
			$depth--;
			$parent = $old_parent;
			$parent_type = $old_parent_type;
		}
		
		endforeach;
		
		return ob_get_clean();
	}
	
	/**
	 * Output the menu (order) page.
	 */
	public function get_menu_page(): void {
		$this->save_menu_order();
		
		$menu_items = Advanced_Menu::get_instance()->get_items();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Menu Structure', 'km-advanced-menu' ); ?></h1>
			
			<form action="<?php echo esc_url( admin_url( 'themes.php?page=km-advanced-menu-menu' ) ); ?>" method="post">
				<?php wp_nonce_field( 'km-advanced-menu-menu' ); ?>
				
				<ul class="menu" id="menu-to-edit">
					<?php echo $this->get_menu_html( $menu_items[ get_current_blog_id() ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</ul>
				
				<?php submit_button( esc_html__( 'Save Sorting', 'km-advanced-menu' ) ); ?>
			</form>
		</div>
		<?php
	}
	
	/**
	 * Add links to admin menu.
	 */
	public function set_menu_links(): void {
		// menu page
		remove_submenu_page( 'themes.php', 'nav-menus.php' );
		add_submenu_page(
			'themes.php',
			__( 'Menu', 'km-advanced-menu' ),
			__( 'Menu', 'km-advanced-menu' ),
			'edit_theme_options',
			'km-advanced-menu-menu',
			[ $this, 'get_menu_page' ]
		);
	}
	
	/**
	 * Save category fields.
	 * 
	 * @param	int		$term_id Term ID
	 */
	public function save_category_fields( int $term_id ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['menu-order'] ) ) {
			update_term_meta( $term_id, 'menu-order', sanitize_text_field( wp_unslash( $_POST['menu-order'] ) ) );
		}
		else {
			delete_term_meta( $term_id, 'menu-order' );
		}
		// phpcs:enable
	}
	
	/**
	 * Save menu order.
	 */
	public function save_menu_order(): void {
		if ( empty( $_POST ) || ! check_admin_referer( 'km-advanced-menu-menu' ) ) {
			return;
		}
		
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}
		
		Advanced_Menu::get_instance()->update_menu_items( 'post' );
	}
}
