<?php
/**
 * Plugin bootstrapper – instantiates all modules and wires up their hooks.
 *
 * Add new modules here when extending the plugin. Each module class must
 * expose a public `init_hooks()` method to register its WordPress hooks.
 *
 * @package MijnBoulesClub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MBC_Loader {

	/**
	 * Registered module instances.
	 *
	 * @var array<string, object>
	 */
	private array $modules = [];

	/**
	 * Instantiate all active modules.
	 *
	 * Dependency injection is done manually here to keep the plugin free of
	 * any external DI container.
	 */
	public function __construct() {
		$db      = new MBC_Database();
		$members = new MBC_Members( $db );

		if ( is_admin() ) {
			$this->modules['members_admin'] = new MBC_Members_Admin( $members );
		}

		// Future modules:
		// $this->modules['competition_admin'] = new MBC_Competition_Admin( new MBC_Competition( $db ) );
		// $this->modules['tournament_admin']  = new MBC_Tournament_Admin( new MBC_Tournament( $db ) );
	}

	/**
	 * Call `init_hooks()` on every registered module.
	 */
	public function run(): void {
		foreach ( $this->modules as $module ) {
			if ( method_exists( $module, 'init_hooks' ) ) {
				$module->init_hooks();
			}
		}
	}
}
