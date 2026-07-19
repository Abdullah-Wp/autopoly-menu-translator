<?php
/**
 * Plugin Name: AutoPoly Menu Translator
 * Plugin URI: https://abdullahwp.com
 * Description: Translate and duplicate Polylang navigation menus with Chrome's on-device Translator API while preserving hierarchy.
 * Version: 1.1.0
 * Author: AbdullahWP
 * Author URI: https://abdullahwp.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: autopoly-menu-translator
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: polylang
 */

defined( 'ABSPATH' ) || exit;

final class AutoPoly_Menu_Translator {
	private const VERSION      = '1.1.0';
	private const PAGE_SLUG    = 'autopoly-menu-translator';
	private const NONCE_ACTION = 'apmt_translate_menu';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_apmt_get_menu_data', array( $this, 'ajax_get_menu_data' ) );
		add_action( 'wp_ajax_apmt_save_translated_menu', array( $this, 'ajax_save_translated_menu' ) );
	}

	public function add_menu_page(): void {
		add_submenu_page(
			'themes.php',
			__( 'AutoPoly Menu Translator', 'autopoly-menu-translator' ),
			__( 'AutoPoly Menu Translator', 'autopoly-menu-translator' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'appearance_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'apmt-admin',
			plugins_url( 'assets/admin.js', __FILE__ ),
			array(),
			self::VERSION,
			true
		);

		wp_localize_script(
			'apmt-admin',
			'apmtData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'strings' => array(
					'unsupported' => __( 'Chrome\'s on-device Translator API is not available in this browser.', 'autopoly-menu-translator' ),
					'unavailable' => __( 'The requested language pair is not available on this device.', 'autopoly-menu-translator' ),
					'failed'      => __( 'The menu could not be translated.', 'autopoly-menu-translator' ),
				),
			)
		);
	}

	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage translated menus.', 'autopoly-menu-translator' ) );
		}

		if ( ! $this->polylang_available() ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Polylang must be active before using AutoPoly Menu Translator.', 'autopoly-menu-translator' ) . '</p></div>';
			return;
		}

		$menus      = wp_get_nav_menus();
		$lang_slugs = pll_languages_list( array( 'fields' => 'slug', 'hide_empty' => false ) );
		$lang_names = pll_languages_list( array( 'fields' => 'name', 'hide_empty' => false ) );
		$lang_locales = pll_languages_list( array( 'fields' => 'locale', 'hide_empty' => false ) );
		$languages  = array();
		foreach ( $lang_slugs as $index => $slug ) {
			$languages[] = array(
				'slug'            => $slug,
				'name'            => $lang_names[ $index ] ?? $slug,
				'translator_code' => str_replace( '_', '-', $lang_locales[ $index ] ?? $slug ),
			);
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AutoPoly Menu Translator', 'autopoly-menu-translator' ); ?></h1>
			<p><?php esc_html_e( 'Translate menu titles locally with Chrome, duplicate the menu, and preserve its hierarchy and translated links.', 'autopoly-menu-translator' ); ?></p>

			<?php if ( empty( $menus ) || empty( $languages ) ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Create at least one navigation menu and configure at least one Polylang language first.', 'autopoly-menu-translator' ); ?></p></div>
			<?php else : ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="apmt-source-menu"><?php esc_html_e( 'Source menu', 'autopoly-menu-translator' ); ?></label></th>
						<td><select id="apmt-source-menu">
							<?php foreach ( $menus as $menu ) : ?>
								<option value="<?php echo esc_attr( (string) $menu->term_id ); ?>"><?php echo esc_html( $menu->name ); ?></option>
							<?php endforeach; ?>
						</select></td>
					</tr>
					<tr>
						<th scope="row"><label for="apmt-target-language"><?php esc_html_e( 'Target language', 'autopoly-menu-translator' ); ?></label></th>
						<td><select id="apmt-target-language">
							<?php foreach ( $languages as $language ) : ?>
								<option value="<?php echo esc_attr( $language['slug'] ); ?>" data-translator-language="<?php echo esc_attr( $language['translator_code'] ); ?>"><?php echo esc_html( $language['name'] ); ?></option>
							<?php endforeach; ?>
						</select></td>
					</tr>
					<tr>
						<th scope="row"><label for="apmt-source-language"><?php esc_html_e( 'Source language code', 'autopoly-menu-translator' ); ?></label></th>
						<td><input type="text" id="apmt-source-language" value="en" class="small-text" maxlength="12" pattern="[A-Za-z-]+"> <span class="description"><?php esc_html_e( 'For example: en, fr, es, or zh-Hant.', 'autopoly-menu-translator' ); ?></span></td>
					</tr>
				</table>

				<p><button id="apmt-start-translation" class="button button-primary"><?php esc_html_e( 'Translate and duplicate menu', 'autopoly-menu-translator' ); ?></button></p>

				<div id="apmt-status" class="notice notice-info inline" hidden>
					<p><strong><?php esc_html_e( 'Status:', 'autopoly-menu-translator' ); ?></strong> <span id="apmt-status-text"><?php esc_html_e( 'Ready.', 'autopoly-menu-translator' ); ?></span></p>
					<progress id="apmt-progress" value="0" max="100" style="width:100%;"></progress>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function ajax_get_menu_data(): void {
		$this->guard_ajax_request();

		$menu_id     = isset( $_POST['menu_id'] ) ? absint( wp_unslash( $_POST['menu_id'] ) ) : 0;
		$target_lang = isset( $_POST['target_lang'] ) ? sanitize_key( wp_unslash( $_POST['target_lang'] ) ) : '';

		if ( ! $menu_id || ! $this->valid_language( $target_lang ) ) {
			wp_send_json_error( __( 'The selected menu or language is invalid.', 'autopoly-menu-translator' ), 400 );
		}

		$items = wp_get_nav_menu_items( $menu_id );
		if ( ! is_array( $items ) ) {
			wp_send_json_error( __( 'The selected menu could not be loaded.', 'autopoly-menu-translator' ), 404 );
		}

		$prepared = array();
		foreach ( $items as $item ) {
			$object_id = (int) $item->object_id;
			$url       = $item->url;

			if ( 'post_type' === $item->type ) {
				$translated_id = (int) pll_get_post( $object_id, $target_lang );
				if ( $translated_id ) {
					$object_id = $translated_id;
					$url       = get_permalink( $translated_id );
				}
			} elseif ( 'taxonomy' === $item->type && function_exists( 'pll_get_term' ) ) {
				$translated_id = (int) pll_get_term( $object_id, $target_lang );
				if ( $translated_id ) {
					$object_id = $translated_id;
					$term_link = get_term_link( $translated_id );
					if ( ! is_wp_error( $term_link ) ) {
						$url = $term_link;
					}
				}
			}

			$prepared[] = array(
				'old_id'      => (int) $item->ID,
				'parent'      => (int) $item->menu_item_parent,
				'title'       => wp_strip_all_tags( $item->title ),
				'url'         => esc_url_raw( $url ),
				'obj_id'      => $object_id,
				'type'        => sanitize_key( $item->type ),
				'object'      => sanitize_key( $item->object ),
				'classes'     => array_values( array_filter( array_map( 'sanitize_html_class', (array) $item->classes ) ) ),
				'target'      => '_blank' === $item->target ? '_blank' : '',
				'attr_title'  => sanitize_text_field( $item->attr_title ),
				'xfn'         => sanitize_text_field( $item->xfn ),
				'description' => sanitize_textarea_field( $item->description ),
			);
		}

		wp_send_json_success( $prepared );
	}

	public function ajax_save_translated_menu(): void {
		$this->guard_ajax_request();

		$target_lang     = isset( $_POST['target_lang'] ) ? sanitize_key( wp_unslash( $_POST['target_lang'] ) ) : '';
		$original_menu_id = isset( $_POST['original_menu_id'] ) ? absint( wp_unslash( $_POST['original_menu_id'] ) ) : 0;
		$items_json       = isset( $_POST['items_json'] ) ? wp_unslash( $_POST['items_json'] ) : '';
		$items            = json_decode( $items_json, true );
		$original_menu    = wp_get_nav_menu_object( $original_menu_id );

		if ( ! $this->valid_language( $target_lang ) || ! $original_menu || ! is_array( $items ) || count( $items ) > 500 ) {
			wp_send_json_error( __( 'The translated menu data is invalid.', 'autopoly-menu-translator' ), 400 );
		}

		$new_menu_name = $original_menu->name . ' - ' . strtoupper( $target_lang ) . ' (AI)';
		if ( get_term_by( 'name', $new_menu_name, 'nav_menu' ) ) {
			wp_send_json_error( __( 'A translated menu with this name already exists. Rename or delete it before trying again.', 'autopoly-menu-translator' ), 409 );
		}

		$new_menu_id = wp_create_nav_menu( $new_menu_name );
		if ( is_wp_error( $new_menu_id ) ) {
			wp_send_json_error( $new_menu_id->get_error_message(), 500 );
		}

		pll_set_term_language( $new_menu_id, $target_lang );
		$id_map = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['old_id'] ) ) {
				wp_delete_nav_menu( $new_menu_id );
				wp_send_json_error( __( 'A translated menu item was invalid, so no menu was saved.', 'autopoly-menu-translator' ), 400 );
			}

			$old_id    = absint( $item['old_id'] );
			$old_parent = absint( $item['parent'] ?? 0 );
			$parent_id = $old_parent ? ( $id_map[ $old_parent ] ?? 0 ) : 0;
			$classes   = isset( $item['classes'] ) && is_array( $item['classes'] ) ? $item['classes'] : array();

			$menu_item_data = array(
				'menu-item-title'       => sanitize_text_field( $item['translated_title'] ?? '' ),
				'menu-item-url'         => esc_url_raw( $item['url'] ?? '' ),
				'menu-item-parent-id'   => absint( $parent_id ),
				'menu-item-status'      => 'publish',
				'menu-item-object-id'   => absint( $item['obj_id'] ?? 0 ),
				'menu-item-type'        => sanitize_key( $item['type'] ?? 'custom' ),
				'menu-item-object'      => sanitize_key( $item['object'] ?? 'custom' ),
				'menu-item-attr-title'  => sanitize_text_field( $item['attr_title'] ?? '' ),
				'menu-item-description' => sanitize_textarea_field( $item['description'] ?? '' ),
				'menu-item-classes'     => implode( ' ', array_filter( array_map( 'sanitize_html_class', $classes ) ) ),
				'menu-item-target'      => '_blank' === ( $item['target'] ?? '' ) ? '_blank' : '',
				'menu-item-xfn'         => sanitize_text_field( $item['xfn'] ?? '' ),
			);

			$new_item_id = wp_update_nav_menu_item( $new_menu_id, 0, $menu_item_data );
			if ( is_wp_error( $new_item_id ) ) {
				wp_delete_nav_menu( $new_menu_id );
				wp_send_json_error( __( 'A menu item could not be saved, so the new menu was rolled back.', 'autopoly-menu-translator' ), 500 );
			}

			$id_map[ $old_id ] = (int) $new_item_id;
		}

		wp_send_json_success(
			array(
				'menu_id'   => (int) $new_menu_id,
				'menu_name' => $new_menu_name,
				'message'   => __( 'The translated menu was created successfully.', 'autopoly-menu-translator' ),
			)
		);
	}

	private function guard_ajax_request(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You are not allowed to manage translated menus.', 'autopoly-menu-translator' ), 403 );
		}
		if ( ! $this->polylang_available() ) {
			wp_send_json_error( __( 'Polylang is not available.', 'autopoly-menu-translator' ), 400 );
		}
	}

	private function polylang_available(): bool {
		return function_exists( 'pll_languages_list' ) && function_exists( 'pll_get_post' ) && function_exists( 'pll_set_term_language' );
	}

	private function valid_language( string $language ): bool {
		return in_array( $language, pll_languages_list( array( 'fields' => 'slug', 'hide_empty' => false ) ), true );
	}
}

new AutoPoly_Menu_Translator();
