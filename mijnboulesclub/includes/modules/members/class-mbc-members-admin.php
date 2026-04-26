<?php
/**
 * Admin UI for the Members module.
 *
 * Contains two classes:
 *  - MBC_Members_List_Table : WP_List_Table subclass that renders the member grid.
 *  - MBC_Members_Admin      : Admin controller – menus, routing, form rendering.
 *
 * All output is escaped at the point of echo (esc_html / esc_attr / esc_url).
 * All POST/GET input is sanitized before use.
 * Every state-changing action is protected by a nonce.
 *
 * @package MijnBoulesClub\Members
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// =============================================================================
// MBC_Members_List_Table
// =============================================================================

/**
 * Renders the members overview as a standard WordPress admin list table.
 */
class MBC_Members_List_Table extends WP_List_Table {

	/**
	 * Members business-logic module.
	 *
	 * @var MBC_Members
	 */
	private MBC_Members $members;

	/**
	 * @param MBC_Members $members Injected members module.
	 */
	public function __construct( MBC_Members $members ) {
		parent::__construct( [
			'singular' => 'lid',
			'plural'   => 'leden',
			'ajax'     => false,
		] );

		$this->members = $members;
	}

	// -------------------------------------------------------------------------
	// Column definitions
	// -------------------------------------------------------------------------

	/**
	 * Returns all registered columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return [
			'cb'         => '<input type="checkbox" />',
			'name'       => __( 'Naam', 'mijnboulesclub' ),
			'email'      => __( 'E-mail', 'mijnboulesclub' ),
			'phone'      => __( 'Telefoon', 'mijnboulesclub' ),
			'active'     => __( 'Status', 'mijnboulesclub' ),
			'created_at' => __( 'Aangemeld op', 'mijnboulesclub' ),
		];
	}

	/**
	 * Returns the sortable columns and their default sort direction.
	 *
	 * @return array<string, array{string, bool}>
	 */
	protected function get_sortable_columns(): array {
		return [
			'name'       => [ 'name', true ],
			'email'      => [ 'email', false ],
			'active'     => [ 'active', false ],
			'created_at' => [ 'created_at', false ],
		];
	}

	// -------------------------------------------------------------------------
	// Column renderers
	// -------------------------------------------------------------------------

	/**
	 * Fallback renderer for columns without a dedicated method.
	 *
	 * @param object $item        Current row object.
	 * @param string $column_name Column key.
	 * @return string
	 */
	protected function column_default( $item, $column_name ): string {
		return esc_html( $item->$column_name ?? '–' );
	}

	/**
	 * Render the Name column with inline row-actions.
	 *
	 * @param object $item Current row object.
	 * @return string
	 */
	protected function column_name( $item ): string {
		$edit_url = add_query_arg( [
			'page'      => 'mbc-members',
			'action'    => 'edit',
			'member_id' => $item->id,
		], admin_url( 'admin.php' ) );

		$delete_url = wp_nonce_url(
			add_query_arg( [
				'page'      => 'mbc-members',
				'action'    => 'delete',
				'member_id' => $item->id,
			], admin_url( 'admin.php' ) ),
			'mbc_delete_member_' . $item->id
		);

		$actions = [
			'edit'   => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $edit_url ),
				esc_html__( 'Bewerken', 'mijnboulesclub' )
			),
			'delete' => sprintf(
				'<a href="%s" onclick="return confirm(\'%s\')">%s</a>',
				esc_url( $delete_url ),
				esc_js( __( 'Weet je zeker dat je dit lid wilt verwijderen?', 'mijnboulesclub' ) ),
				esc_html__( 'Verwijderen', 'mijnboulesclub' )
			),
		];

		return sprintf(
			'<strong><a href="%s">%s</a></strong>%s',
			esc_url( $edit_url ),
			esc_html( $item->name ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Render the Status column with a coloured indicator.
	 *
	 * @param object $item Current row object.
	 * @return string
	 */
	protected function column_active( $item ): string {
		if ( $item->active ) {
			return '<span style="color:#46b450" aria-hidden="true">&#9679;</span> '
				. esc_html__( 'Actief', 'mijnboulesclub' );
		}

		return '<span style="color:#dc3232" aria-hidden="true">&#9679;</span> '
			. esc_html__( 'Inactief', 'mijnboulesclub' );
	}

	/**
	 * Render the checkbox column for bulk actions.
	 *
	 * @param object $item Current row object.
	 * @return string
	 */
	protected function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="member_ids[]" value="%d" />',
			(int) $item->id
		);
	}

	/**
	 * Define available bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions(): array {
		return [
			'bulk_delete' => __( 'Verwijderen', 'mijnboulesclub' ),
		];
	}

	// -------------------------------------------------------------------------
	// Data preparation
	// -------------------------------------------------------------------------

	/**
	 * Query data, set pagination and column headers.
	 *
	 * Called by WP_List_Table before display().
	 */
	public function prepare_items(): void {
		$per_page     = 20;
		$current_page = $this->get_pagenum();
		// phpcs:ignore WordPress.Security.NonceVerification
		$search  = sanitize_text_field( wp_unslash( $_REQUEST['s']       ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification
		$orderby = sanitize_key( $_REQUEST['orderby'] ?? 'name' );
		// phpcs:ignore WordPress.Security.NonceVerification
		$order   = sanitize_key( $_REQUEST['order']   ?? 'ASC' );

		$this->items = $this->members->get_members( [
			'search'  => $search,
			'orderby' => $orderby,
			'order'   => $order,
			'limit'   => $per_page,
			'offset'  => ( $current_page - 1 ) * $per_page,
		] );

		$total = $this->members->count_members();

		$this->set_pagination_args( [
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		] );

		$this->_column_headers = [
			$this->get_columns(),
			[],                          // Hidden columns.
			$this->get_sortable_columns(),
			'name',                      // Primary column.
		];
	}
}

// =============================================================================
// MBC_Members_Admin
// =============================================================================

/**
 * Admin controller for the Members module.
 *
 * Handles:
 *  - Menu registration
 *  - Page routing (list / add / edit / delete)
 *  - POST handling with nonce verification
 *  - View rendering
 */
class MBC_Members_Admin {

	/**
	 * Members business-logic module.
	 *
	 * @var MBC_Members
	 */
	private MBC_Members $members;

	/**
	 * @param MBC_Members $members Injected members module.
	 */
	public function __construct( MBC_Members $members ) {
		$this->members = $members;
	}

	// -------------------------------------------------------------------------
	// Hooks
	// -------------------------------------------------------------------------

	/**
	 * Register all WordPress hooks for this module.
	 * Called by MBC_Loader::run().
	 */
	public function init_hooks(): void {
		add_action( 'admin_menu', [ $this, 'register_menus' ] );
	}

	/**
	 * Register the admin menu and submenu items.
	 */
	public function register_menus(): void {
		add_menu_page(
			__( 'Mijn Boules Club', 'mijnboulesclub' ),
			__( 'Mijn Boules Club', 'mijnboulesclub' ),
			'manage_options',
			'mijnboulesclub',
			[ $this, 'page_members' ],
			'dashicons-groups',
			30
		);

		// Rename the auto-created first submenu item from "Mijn Boules Club" to "Leden".
		add_submenu_page(
			'mijnboulesclub',
			__( 'Leden', 'mijnboulesclub' ),
			__( 'Leden', 'mijnboulesclub' ),
			'manage_options',
			'mijnboulesclub',
			[ $this, 'page_members' ]
		);

		// Future submenus (competitie, toernooien) will be registered here by their own modules.
	}

	// -------------------------------------------------------------------------
	// Page router
	// -------------------------------------------------------------------------

	/**
	 * Main page callback – routes to the correct handler based on `action`.
	 *
	 * Routing table:
	 *  POST  *          → handle_post()
	 *  GET   delete     → handle_delete()
	 *  GET   add | edit → render_form()
	 *  GET   (default)  → render_list()
	 */
	public function page_members(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Geen toegang.', 'mijnboulesclub' ) );
		}

		// $_REQUEST so the action is available both in GET and POST contexts.
		$action = sanitize_key( $_REQUEST['action'] ?? 'list' ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$this->handle_post( $action );
			return;
		}

		if ( 'delete' === $action ) {
			$this->handle_delete();
			return;
		}

		match ( $action ) {
			'add', 'edit' => $this->render_form( $action ),
			default       => $this->render_list(),
		};
	}

	// -------------------------------------------------------------------------
	// Action handlers
	// -------------------------------------------------------------------------

	/**
	 * Handle POST submissions (save member).
	 *
	 * On success: redirect to list with a notice.
	 * On validation failure: re-render form with inline errors (no redirect,
	 * so POST values are preserved in $_POST for the form to repopulate).
	 *
	 * @param string $action 'add' or 'edit'.
	 */
	private function handle_post( string $action ): void {
		check_admin_referer( 'mbc_save_member' );

		$member_id = (int) ( $_POST['member_id'] ?? 0 );

		if ( 'edit' === $action && $member_id > 0 ) {
			$result = $this->members->update_member( $member_id, $_POST );

			if ( false !== $result ) {
				wp_safe_redirect( $this->list_url( 'updated' ) );
				exit;
			}
		} else {
			$new_id = $this->members->create_member( $_POST );

			if ( $new_id ) {
				wp_safe_redirect( $this->list_url( 'added' ) );
				exit;
			}
		}

		// Validation failed: re-render the form in the same request so that
		// $_POST is still populated (field values are preserved for the user).
		$this->render_form( $action, $member_id );
	}

	/**
	 * Handle GET-based single-item delete (nonce required).
	 */
	private function handle_delete(): void {
		$member_id = (int) ( $_GET['member_id'] ?? 0 );

		if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'mbc_delete_member_' . $member_id ) ) {
			wp_die( esc_html__( 'Beveiligingscontrole mislukt. Probeer opnieuw.', 'mijnboulesclub' ) );
		}

		$this->members->delete_member( $member_id );

		wp_safe_redirect( $this->list_url( 'deleted' ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Views
	// -------------------------------------------------------------------------

	/**
	 * Render the members list page.
	 */
	private function render_list(): void {
		$notice     = sanitize_key( $_GET['mbc_notice'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$add_url    = add_query_arg( [ 'page' => 'mijnboulesclub', 'action' => 'add' ], admin_url( 'admin.php' ) );
		$list_table = new MBC_Members_List_Table( $this->members );
		$list_table->prepare_items();

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">
				<?php esc_html_e( 'Leden', 'mijnboulesclub' ); ?>
			</h1>
			<a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action">
				<?php esc_html_e( 'Lid toevoegen', 'mijnboulesclub' ); ?>
			</a>
			<hr class="wp-header-end">

			<?php $this->render_notice( $notice ); ?>

			<form method="get">
				<input type="hidden" name="page" value="mijnboulesclub">
				<?php
				$list_table->search_box( __( 'Zoeken', 'mijnboulesclub' ), 'member' );
				$list_table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the add / edit member form.
	 *
	 * When called after a failed POST, $_SERVER['REQUEST_METHOD'] is still POST
	 * so `$val()` reads field values from $_POST (preserves user input).
	 * When called on a normal GET, `$val()` reads from the database row.
	 *
	 * @param string $action    'add' or 'edit'.
	 * @param int    $member_id Member ID when editing (0 when adding).
	 */
	private function render_form( string $action, int $member_id = 0 ): void {
		$member = null;

		if ( 'edit' === $action ) {
			// ID comes from URL on GET, from hidden field on POST re-render.
			if ( $member_id === 0 ) {
				$member_id = (int) ( $_GET['member_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
			}
			$member = $this->members->get_member( $member_id );
			if ( ! $member ) {
				wp_die( esc_html__( 'Lid niet gevonden.', 'mijnboulesclub' ) );
			}
		}

		$is_post = ( 'POST' === $_SERVER['REQUEST_METHOD'] );
		$errors  = $this->members->get_errors();

		/**
		 * Returns the display value for a form field.
		 * On POST (validation failure): reads from $_POST to preserve input.
		 * On GET: reads from the database row (or empty string for add).
		 */
		$val = static function ( string $field ) use ( $member, $is_post ): string {
			if ( $is_post ) {
				return sanitize_text_field( wp_unslash( $_POST[ $field ] ?? '' ) );
			}
			return $member ? (string) ( $member->$field ?? '' ) : '';
		};

		$is_active = $is_post
			? isset( $_POST['active'] )
			: ( $member ? (bool) $member->active : true );

		$page_title  = 'edit' === $action
			? __( 'Lid bewerken', 'mijnboulesclub' )
			: __( 'Lid toevoegen', 'mijnboulesclub' );

		$form_action = add_query_arg(
			[ 'page' => 'mijnboulesclub', 'action' => $action ],
			admin_url( 'admin.php' )
		);

		?>
		<div class="wrap">
			<h1><?php echo esc_html( $page_title ); ?></h1>

			<p>
				<a href="<?php echo esc_url( $this->list_url() ); ?>">
					&larr; <?php esc_html_e( 'Terug naar ledenlijst', 'mijnboulesclub' ); ?>
				</a>
			</p>

			<?php if ( $errors ) : ?>
				<div class="notice notice-error">
					<ul>
						<?php foreach ( $errors as $error ) : ?>
							<li><?php echo esc_html( $error ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( $form_action ); ?>">
				<?php wp_nonce_field( 'mbc_save_member' ); ?>

				<?php if ( $member_id > 0 ) : ?>
					<input type="hidden" name="member_id" value="<?php echo esc_attr( $member_id ); ?>">
				<?php endif; ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="mbc-name">
								<?php esc_html_e( 'Naam', 'mijnboulesclub' ); ?>
								<span class="required" aria-hidden="true">*</span>
							</label>
						</th>
						<td>
							<input
								type="text"
								id="mbc-name"
								name="name"
								value="<?php echo esc_attr( $val( 'name' ) ); ?>"
								class="regular-text"
								required
								autocomplete="name"
							>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="mbc-email">
								<?php esc_html_e( 'E-mail', 'mijnboulesclub' ); ?>
							</label>
						</th>
						<td>
							<input
								type="email"
								id="mbc-email"
								name="email"
								value="<?php echo esc_attr( $val( 'email' ) ); ?>"
								class="regular-text"
								autocomplete="email"
							>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="mbc-phone">
								<?php esc_html_e( 'Telefoon', 'mijnboulesclub' ); ?>
							</label>
						</th>
						<td>
							<input
								type="tel"
								id="mbc-phone"
								name="phone"
								value="<?php echo esc_attr( $val( 'phone' ) ); ?>"
								class="regular-text"
								autocomplete="tel"
							>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e( 'Status', 'mijnboulesclub' ); ?>
						</th>
						<td>
							<label>
								<input
									type="checkbox"
									name="active"
									value="1"
									<?php checked( $is_active ); ?>
								>
								<?php esc_html_e( 'Actief lid', 'mijnboulesclub' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php
				$button_label = 'edit' === $action
					? __( 'Wijzigingen opslaan', 'mijnboulesclub' )
					: __( 'Lid toevoegen', 'mijnboulesclub' );
				submit_button( $button_label );
				?>
			</form>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Display an admin success notice based on a query-string key.
	 *
	 * @param string $notice One of 'added', 'updated', 'deleted'.
	 */
	private function render_notice( string $notice ): void {
		$messages = [
			'added'   => __( 'Lid is succesvol toegevoegd.', 'mijnboulesclub' ),
			'updated' => __( 'Lid is succesvol bijgewerkt.', 'mijnboulesclub' ),
			'deleted' => __( 'Lid is verwijderd.', 'mijnboulesclub' ),
		];

		if ( isset( $messages[ $notice ] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( $messages[ $notice ] )
			);
		}
	}

	/**
	 * Build the members list URL, optionally appending a notice key.
	 *
	 * @param string $notice Optional notice key ('added', 'updated', 'deleted').
	 * @return string Absolute admin URL.
	 */
	private function list_url( string $notice = '' ): string {
		$args = [ 'page' => 'mijnboulesclub' ];

		if ( $notice !== '' ) {
			$args['mbc_notice'] = $notice;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}
}
