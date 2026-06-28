<?php
/**
 * Plugin Name:       Duplicate Anything
 * Plugin URI:        https://zubeidhendricks.dev/wp-plugins/duplicate-anything
 * Description:        One-click duplicate any post, page or custom post type as a draft — title, content, taxonomies and meta included.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Author:            Zubeid Hendricks
 * Author URI:        https://zubeidhendricks.dev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       duplicate-anything
 *
 * @package DuplicateAnything
 */

defined( 'ABSPATH' ) || exit;

define( 'DUPLICATE_ANYTHING_VERSION', '1.0.0' );

require_once __DIR__ . '/includes/factory-core.php';

/**
 * Duplicate Anything.
 */
final class DuplicateAnything extends ZubFactory_Plugin {

	protected function configure() {
		$this->slug    = 'duplicate-anything';
		$this->title   = 'Duplicate Anything';
		$this->version = DUPLICATE_ANYTHING_VERSION;
	}

	protected function settings_fields() {
		return array(
			'status'   => array(
				'label'   => __( 'Copy status', 'duplicate-anything' ),
				'type'    => 'select',
				'options' => array(
					'draft'   => __( 'Always create as Draft', 'duplicate-anything' ),
					'inherit' => __( 'Match the original status', 'duplicate-anything' ),
				),
				'default' => 'draft',
			),
			'redirect' => array(
				'label'    => __( 'After duplicating', 'duplicate-anything' ),
				'type'     => 'select',
				'options'  => array(
					'list' => __( 'Return to the list', 'duplicate-anything' ),
					'edit' => __( 'Open the new copy in the editor', 'duplicate-anything' ),
				),
				'default'  => 'edit',
			),
			'bulk'     => array(
				'label'    => __( 'Bulk duplicate', 'duplicate-anything' ),
				'type'     => 'checkbox',
				'cb_label' => __( 'Enable duplicating many items at once', 'duplicate-anything' ),
				'pro'      => true,
			),
		);
	}

	protected function hooks() {
		add_filter( 'post_row_actions', array( $this, 'row_action' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'row_action' ), 10, 2 );
		add_action( 'admin_action_duplicate_anything', array( $this, 'handle' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/** Add the "Duplicate" link to each row. */
	public function row_action( $actions, $post ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return $actions;
		}
		$url = wp_nonce_url(
			admin_url( 'admin.php?action=duplicate_anything&post=' . $post->ID ),
			'duplicate_anything_' . $post->ID
		);
		$actions['duplicate_anything'] = sprintf(
			'<a href="%s" title="%s">%s</a>',
			esc_url( $url ),
			esc_attr__( 'Duplicate this item', 'duplicate-anything' ),
			esc_html__( 'Duplicate', 'duplicate-anything' )
		);
		return $actions;
	}

	/** Perform the duplication. */
	public function handle() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'duplicate-anything' ) );
		}
		check_admin_referer( 'duplicate_anything_' . $post_id );

		$original = get_post( $post_id );
		if ( ! $original ) {
			wp_die( esc_html__( 'Original item not found.', 'duplicate-anything' ) );
		}

		$status = 'inherit' === $this->option( 'status', 'draft' ) ? $original->post_status : 'draft';

		$new_id = wp_insert_post(
			array(
				'post_title'   => $original->post_title . ' (' . __( 'Copy', 'duplicate-anything' ) . ')',
				'post_content' => $original->post_content,
				'post_excerpt' => $original->post_excerpt,
				'post_status'  => $status,
				'post_type'    => $original->post_type,
				'post_author'  => get_current_user_id(),
				'post_parent'  => $original->post_parent,
				'menu_order'   => $original->menu_order,
			),
			true
		);

		if ( is_wp_error( $new_id ) ) {
			wp_die( esc_html( $new_id->get_error_message() ) );
		}

		$this->copy_taxonomies( $original, $new_id );
		$this->copy_meta( $post_id, $new_id );

		if ( 'edit' === $this->option( 'redirect', 'edit' ) ) {
			wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $new_id ) );
		} else {
			$ref = add_query_arg( 'duplicated', 1, wp_get_referer() ?: admin_url( 'edit.php' ) );
			wp_safe_redirect( $ref );
		}
		exit;
	}

	private function copy_taxonomies( $original, $new_id ) {
		$taxonomies = get_object_taxonomies( $original->post_type );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_object_terms( $original->ID, $taxonomy, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) ) {
				wp_set_object_terms( $new_id, $terms, $taxonomy );
			}
		}
	}

	private function copy_meta( $post_id, $new_id ) {
		$meta = get_post_meta( $post_id );
		$skip = array( '_edit_lock', '_edit_last', '_wp_old_slug' );
		foreach ( $meta as $key => $values ) {
			if ( in_array( $key, $skip, true ) ) {
				continue;
			}
			foreach ( $values as $value ) {
				add_post_meta( $new_id, $key, maybe_unserialize( $value ) );
			}
		}
	}

	public function notice() {
		if ( ! empty( $_GET['duplicated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__( 'Item duplicated.', 'duplicate-anything' ) . '</p></div>';
		}
	}
}

add_action(
	'plugins_loaded',
	function () {
		( new DuplicateAnything( __FILE__ ) )->boot();
	}
);
