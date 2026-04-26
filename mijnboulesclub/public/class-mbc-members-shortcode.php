<?php
/**
 * Frontend shortcode for displaying the members list.
 *
 * Usage:
 *   [mijnboulesclub_leden]
 *   [mijnboulesclub_leden status="alle"]
 *   [mijnboulesclub_leden orderby="name" order="DESC"]
 *   [mijnboulesclub_leden toon="naam,email"]
 *
 * Attributes:
 *   status  – "actief" (default) or "alle"
 *   orderby – "name" (default), "email", "created_at"
 *   order   – "ASC" (default) or "DESC"
 *   toon    – comma-separated list of columns to show: naam, email, telefoon
 *
 * @package MijnBoulesClub\Public
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MBC_Members_Shortcode {

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

	/**
	 * Register shortcode and frontend assets.
	 */
	public function init_hooks(): void {
		add_shortcode( 'mijnboulesclub_leden', [ $this, 'render' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue the public stylesheet.
	 */
	public function enqueue_assets(): void {
		wp_enqueue_style(
			'mbc-public',
			MBC_PLUGIN_URL . 'assets/css/mbc-public.css',
			[],
			MBC_VERSION
		);
	}

	/**
	 * Render the shortcode output.
	 *
	 * Always returns a string – never echoes directly.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render( array|string $atts ): string {
		$atts = shortcode_atts(
			[
				'status'  => 'actief',
				'orderby' => 'name',
				'order'   => 'ASC',
				'toon'    => 'naam,email,telefoon',
			],
			$atts,
			'mijnboulesclub_leden'
		);

		// Resolve which columns to display.
		$toon = array_map( 'trim', explode( ',', (string) $atts['toon'] ) );

		$members = $this->members->get_members( [
			'active_only' => 'actief' === $atts['status'],
			'orderby'     => sanitize_key( $atts['orderby'] ),
			'order'       => strtoupper( $atts['order'] ) === 'DESC' ? 'DESC' : 'ASC',
		] );

		if ( empty( $members ) ) {
			return '<p class="mbc-geen-leden">'
				. esc_html__( 'Geen leden gevonden.', 'mijnboulesclub' )
				. '</p>';
		}

		ob_start();
		?>
		<div class="mbc-ledenlijst" role="list">
			<?php foreach ( $members as $member ) : ?>
				<div class="mbc-lid" role="listitem">

					<?php if ( in_array( 'naam', $toon, true ) ) : ?>
						<p class="mbc-lid-naam"><?php echo esc_html( $member->name ); ?></p>
					<?php endif; ?>

					<?php if ( in_array( 'email', $toon, true ) && ! empty( $member->email ) ) : ?>
						<p class="mbc-lid-meta">
							<a href="mailto:<?php echo esc_attr( $member->email ); ?>">
								<?php echo esc_html( $member->email ); ?>
							</a>
						</p>
					<?php endif; ?>

					<?php if ( in_array( 'telefoon', $toon, true ) && ! empty( $member->phone ) ) : ?>
						<p class="mbc-lid-meta"><?php echo esc_html( $member->phone ); ?></p>
					<?php endif; ?>

				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
