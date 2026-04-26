<?php
/**
 * Business logic for the Members module.
 *
 * This class is the single entry point for all member operations.
 * It validates and sanitizes data before passing it to the database layer.
 * It never touches $wpdb directly – that is MBC_Database's responsibility.
 *
 * @package MijnBoulesClub\Members
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MBC_Members {

	/**
	 * Database layer.
	 *
	 * @var MBC_Database
	 */
	private MBC_Database $db;

	/**
	 * Validation errors collected during the last operation.
	 *
	 * @var string[]
	 */
	private array $errors = [];

	/**
	 * @param MBC_Database $db Injected database layer.
	 */
	public function __construct( MBC_Database $db ) {
		$this->db = $db;
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Create a new member after sanitization and validation.
	 *
	 * @param array<string, mixed> $raw_data Raw (unsanitized) input, e.g. from $_POST.
	 * @return int|false New member ID on success, false on validation or DB failure.
	 */
	public function create_member( array $raw_data ): int|false {
		$this->errors = [];
		$data         = $this->sanitize( $raw_data );

		if ( ! $this->validate( $data ) ) {
			return false;
		}

		return $this->db->insert_member( $data );
	}

	/**
	 * Update an existing member.
	 *
	 * @param int                  $id       Member primary key.
	 * @param array<string, mixed> $raw_data Raw (unsanitized) input.
	 * @return int|false Rows affected, or false on failure.
	 */
	public function update_member( int $id, array $raw_data ): int|false {
		$this->errors = [];
		$data         = $this->sanitize( $raw_data );

		if ( ! $this->validate( $data ) ) {
			return false;
		}

		return $this->db->update_member( $id, $data );
	}

	/**
	 * Delete a member by primary key.
	 *
	 * @param int $id Member primary key.
	 * @return int|false Rows deleted, or false on failure.
	 */
	public function delete_member( int $id ): int|false {
		$this->errors = [];

		if ( $id <= 0 ) {
			$this->errors[] = __( 'Ongeldig lid-ID opgegeven.', 'mijnboulesclub' );
			return false;
		}

		return $this->db->delete_member( $id );
	}

	/**
	 * Retrieve a single member by primary key.
	 *
	 * @param int $id Member primary key.
	 * @return object|null Member object or null when not found.
	 */
	public function get_member( int $id ): ?object {
		return $this->db->get_member( $id );
	}

	/**
	 * Retrieve a (filtered, paginated) list of members.
	 *
	 * @param array<string, mixed> $args See MBC_Database::get_members() for supported keys.
	 * @return object[]
	 */
	public function get_members( array $args = [] ): array {
		return $this->db->get_members( $args );
	}

	/**
	 * Count members.
	 *
	 * @param bool $active_only Count only active members.
	 * @return int
	 */
	public function count_members( bool $active_only = false ): int {
		return $this->db->count_members( $active_only );
	}

	/**
	 * Return validation errors from the most recent create/update call.
	 *
	 * @return string[]
	 */
	public function get_errors(): array {
		return $this->errors;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Sanitize raw input to a clean data array ready for the database.
	 *
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	private function sanitize( array $raw ): array {
		return [
			'name'   => sanitize_text_field( $raw['name']  ?? '' ),
			'email'  => sanitize_email( $raw['email']      ?? '' ),
			'phone'  => sanitize_text_field( $raw['phone'] ?? '' ),
			// Checkbox: present = 1, absent = 0.
			'active' => isset( $raw['active'] ) ? 1 : 0,
		];
	}

	/**
	 * Validate a sanitized data array and populate $this->errors on failure.
	 *
	 * @param array<string, mixed> $data Sanitized member data.
	 * @return bool True when valid, false when one or more errors exist.
	 */
	private function validate( array $data ): bool {
		if ( empty( $data['name'] ) ) {
			$this->errors[] = __( 'Naam is verplicht.', 'mijnboulesclub' );
		}

		if ( $data['email'] !== '' && ! is_email( $data['email'] ) ) {
			$this->errors[] = __( 'Vul een geldig e-mailadres in.', 'mijnboulesclub' );
		}

		return empty( $this->errors );
	}
}
