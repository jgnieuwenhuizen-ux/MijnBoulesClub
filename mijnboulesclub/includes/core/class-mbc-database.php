<?php
/**
 * Low-level database layer for MijnBoulesClub.
 *
 * Responsible for:
 *  - Creating and updating plugin tables via dbDelta.
 *  - Providing type-safe CRUD methods that wrap $wpdb.
 *
 * All SQL that accepts external values must go through $wpdb->prepare().
 * Table names are whitelisted class properties – never interpolated from
 * user input.
 *
 * @package MijnBoulesClub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MBC_Database {

	/**
	 * WordPress database object.
	 *
	 * @var wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Fully qualified members table name (includes WP prefix).
	 *
	 * @var string
	 */
	public string $table_members;

	public function __construct() {
		global $wpdb;
		$this->wpdb          = $wpdb;
		$this->table_members = $wpdb->prefix . 'mbc_members';
	}

	// -------------------------------------------------------------------------
	// Schema
	// -------------------------------------------------------------------------

	/**
	 * Create or upgrade plugin database tables.
	 *
	 * Safe to call on every activation – dbDelta only modifies what changed.
	 * Column order matters for dbDelta: add new columns at the end.
	 *
	 * @see https://developer.wordpress.org/reference/functions/dbdelta/
	 */
	public function create_tables(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $this->wpdb->get_charset_collate();

		// Two spaces before (id) are required by dbDelta for PRIMARY KEY.
		$sql = "CREATE TABLE {$this->table_members} (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL DEFAULT '',
  email varchar(150) NOT NULL DEFAULT '',
  phone varchar(30) NOT NULL DEFAULT '',
  active tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
  updated_at datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
  PRIMARY KEY  (id),
  KEY idx_active (active),
  KEY idx_email (email)
) {$charset_collate};";

		dbDelta( $sql );
	}

	// -------------------------------------------------------------------------
	// Members CRUD
	// -------------------------------------------------------------------------

	/**
	 * Insert a new member row.
	 *
	 * @param array<string, mixed> $data Column => value pairs (sanitized).
	 * @return int|false Auto-increment ID on success, false on failure.
	 */
	public function insert_member( array $data ): int|false {
		$now              = current_time( 'mysql' );
		$data['created_at'] = $now;
		$data['updated_at'] = $now;

		$result = $this->wpdb->insert(
			$this->table_members,
			$data,
			$this->get_formats( $data )
		);

		return $result !== false ? (int) $this->wpdb->insert_id : false;
	}

	/**
	 * Update an existing member row.
	 *
	 * @param int                  $id   Member primary key.
	 * @param array<string, mixed> $data Column => value pairs (sanitized).
	 * @return int|false Rows updated, or false on DB error.
	 */
	public function update_member( int $id, array $data ): int|false {
		$data['updated_at'] = current_time( 'mysql' );

		return $this->wpdb->update(
			$this->table_members,
			$data,
			[ 'id' => $id ],
			$this->get_formats( $data ),
			[ '%d' ]
		);
	}

	/**
	 * Delete a member row by primary key.
	 *
	 * @param int $id Member primary key.
	 * @return int|false Rows deleted, or false on DB error.
	 */
	public function delete_member( int $id ): int|false {
		return $this->wpdb->delete(
			$this->table_members,
			[ 'id' => $id ],
			[ '%d' ]
		);
	}

	/**
	 * Retrieve a single member by primary key.
	 *
	 * @param int $id Member primary key.
	 * @return object|null Row object or null when not found.
	 */
	public function get_member( int $id ): ?object {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_members} WHERE id = %d LIMIT 1",
				$id
			)
		);
	}

	/**
	 * Retrieve a list of members with optional filtering and pagination.
	 *
	 * @param array{
	 *   active_only?: bool,
	 *   search?:      string,
	 *   orderby?:     string,
	 *   order?:       string,
	 *   limit?:       int,
	 *   offset?:      int
	 * } $args Query arguments.
	 * @return object[] Array of row objects.
	 */
	public function get_members( array $args = [] ): array {
		$defaults = [
			'active_only' => false,
			'search'      => '',
			'orderby'     => 'name',
			'order'       => 'ASC',
			'limit'       => 0,
			'offset'      => 0,
		];
		$args = wp_parse_args( $args, $defaults );

		// Whitelist sortable columns to block ORDER BY injection.
		$allowed_columns = [ 'id', 'name', 'email', 'active', 'created_at' ];
		$orderby = in_array( $args['orderby'], $allowed_columns, true )
			? $args['orderby']
			: 'name';
		$order = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

		$where_clauses = [];
		$prepare_values = [];

		if ( $args['active_only'] ) {
			$where_clauses[] = 'active = 1';
		}

		if ( $args['search'] !== '' ) {
			$like              = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
			$where_clauses[]   = '(name LIKE %s OR email LIKE %s)';
			$prepare_values[]  = $like;
			$prepare_values[]  = $like;
		}

		$sql = "SELECT * FROM {$this->table_members}";

		if ( $where_clauses ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where_clauses );
		}

		$sql .= " ORDER BY {$orderby} {$order}";

		if ( (int) $args['limit'] > 0 ) {
			$sql            .= ' LIMIT %d OFFSET %d';
			$prepare_values[] = (int) $args['limit'];
			$prepare_values[] = (int) $args['offset'];
		}

		if ( $prepare_values ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $this->wpdb->prepare( $sql, $prepare_values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $this->wpdb->get_results( $sql ) ?: [];
	}

	/**
	 * Count members in the table.
	 *
	 * @param bool $active_only When true, count only active members.
	 * @return int Total member count.
	 */
	public function count_members( bool $active_only = false ): int {
		$sql = "SELECT COUNT(*) FROM {$this->table_members}";
		if ( $active_only ) {
			$sql .= ' WHERE active = 1';
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $this->wpdb->get_var( $sql );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Map column names to their $wpdb format string (%s, %d).
	 *
	 * @param array<string, mixed> $data
	 * @return string[]
	 */
	private function get_formats( array $data ): array {
		$format_map = [
			'id'         => '%d',
			'active'     => '%d',
			'name'       => '%s',
			'email'      => '%s',
			'phone'      => '%s',
			'created_at' => '%s',
			'updated_at' => '%s',
		];

		return array_map(
			static fn( string $col ) => $format_map[ $col ] ?? '%s',
			array_keys( $data )
		);
	}
}
