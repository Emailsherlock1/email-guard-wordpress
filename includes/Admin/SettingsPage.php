<?php
/**
 * Settings screen under Settings > Email Guard.
 *
 * @package EmailSherlock\EmailGuardWp
 */

namespace EmailSherlock\EmailGuardWp\Admin;

use EmailSherlock\EmailGuardWp\DecisionLog;
use EmailSherlock\EmailGuardWp\Plugin;
use EmailSherlock\EmailGuardWp\Settings;

/**
 * One page, four sections: connection, protected forms, policy, decision
 * log. Saving goes through options.php (nonce and capability handled by
 * core); the log-clear action goes through admin-post with its own nonce.
 */
class SettingsPage {

	const PAGE_SLUG    = 'emailsherlock-email-guard';
	const OPTION_GROUP = 'emailsherlock_email_guard';
	const CLEAR_ACTION = 'emailsherlock_email_guard_clear_log';

	/** @var Plugin */
	private $plugin;

	/** @var string Hook suffix of the page, set on admin_menu. */
	private $hook_suffix = '';

	/**
	 * @param Plugin $plugin The plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Attach all admin hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_' . self::CLEAR_ACTION, array( $this, 'handle_clear_log' ) );
	}

	/**
	 * Add the options page.
	 */
	public function add_menu() {
		$this->hook_suffix = add_options_page(
			__( 'Email Guard', 'emailsherlock-email-guard' ),
			__( 'Email Guard', 'emailsherlock-email-guard' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Register the single settings option with its sanitize callback.
	 */
	public function register_setting() {
		register_setting(
			self::OPTION_GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this->plugin->settings(), 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);
	}

	/**
	 * Load the small helper script on our page only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue( $hook_suffix ) {
		if ( $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_script(
			'emailsherlock-email-guard-admin',
			plugins_url( 'assets/admin.js', EMAILSHERLOCK_EMAIL_GUARD_FILE ),
			array(),
			EMAILSHERLOCK_EMAIL_GUARD_VERSION,
			true
		);
		wp_localize_script(
			'emailsherlock-email-guard-admin',
			'emailGuardAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => ConnectionTest::ACTION,
				'nonce'   => wp_create_nonce( ConnectionTest::ACTION ),
				'testing' => __( 'Testing the connection ...', 'emailsherlock-email-guard' ),
				'failed'  => __( 'The request failed. Check the browser console.', 'emailsherlock-email-guard' ),
			)
		);
	}

	/**
	 * Clear the decision log (admin-post).
	 */
	public function handle_clear_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'emailsherlock-email-guard' ) );
		}
		check_admin_referer( self::CLEAR_ACTION );

		$log = new DecisionLog( $this->plugin->settings() );
		$log->clear();

		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'log-cleared' => '1' ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Render the page.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->plugin->settings()->all();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Email Guard', 'emailsherlock-email-guard' ); ?></h1>
			<p>
				<?php esc_html_e( 'Checks the email address on your forms before a submission goes through. Syntax, reserved domains, and a bundled list of disposable providers are checked locally on your server. An EmailSherlock API key adds live mailbox verification and a fresh disposable list.', 'emailsherlock-email-guard' ); ?>
			</p>

			<?php if ( isset( $_GET['log-cleared'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only notice. ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Decision log cleared.', 'emailsherlock-email-guard' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<h2><?php esc_html_e( 'Connection', 'emailsherlock-email-guard' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="email-guard-api-key"><?php esc_html_e( 'API key', 'emailsherlock-email-guard' ); ?></label></th>
						<td>
							<input type="password" id="email-guard-api-key" class="regular-text" autocomplete="off"
								name="<?php echo esc_attr( Settings::OPTION ); ?>[api_key]"
								value="<?php echo esc_attr( $settings['api_key'] ); ?>">
							<p class="description">
								<?php esc_html_e( 'Optional. Without a key the guard runs local checks only. Get a key in your EmailSherlock dashboard under API keys; it needs the verify and guard:events scopes.', 'emailsherlock-email-guard' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="email-guard-site-key"><?php esc_html_e( 'Site key', 'emailsherlock-email-guard' ); ?></label></th>
						<td>
							<input type="text" id="email-guard-site-key" class="regular-text" autocomplete="off"
								name="<?php echo esc_attr( Settings::OPTION ); ?>[site_key]"
								value="<?php echo esc_attr( $settings['site_key'] ); ?>">
							<p class="description">
								<?php esc_html_e( 'Optional. Create a Guard property for this site in your EmailSherlock dashboard and paste its site key here, so the dashboard attributes decisions to this site.', 'emailsherlock-email-guard' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Connection test', 'emailsherlock-email-guard' ); ?></th>
						<td>
							<button type="button" class="button" id="email-guard-test-button"><?php esc_html_e( 'Test connection', 'emailsherlock-email-guard' ); ?></button>
							<span id="email-guard-test-result" role="status"></span>
							<p class="description">
								<?php esc_html_e( 'Sends one test event (no email address, no page data) to the EmailSherlock API with the key above.', 'emailsherlock-email-guard' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Protected forms', 'emailsherlock-email-guard' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Check submissions on', 'emailsherlock-email-guard' ); ?></th>
						<td>
							<fieldset>
							<?php foreach ( $this->plugin->adapters() as $adapter ) : ?>
								<?php
								$form_id   = $adapter->id();
								$available = $adapter->is_available();
								$enabled   = ! empty( $settings['forms'][ $form_id ] );
								$field     = Settings::OPTION . '[forms][' . $form_id . ']';
								?>
								<label style="display:block;margin-bottom:4px;">
									<?php if ( ! $available ) : ?>
										<input type="hidden" name="<?php echo esc_attr( $field ); ?>" value="<?php echo $enabled ? '1' : ''; ?>">
									<?php endif; ?>
									<input type="checkbox" value="1"
										<?php echo $available ? 'name="' . esc_attr( $field ) . '"' : 'disabled'; ?>
										<?php checked( $enabled ); ?>>
									<?php echo esc_html( $adapter->label() ); ?>
									<?php if ( ! $available ) : ?>
										<em><?php esc_html_e( '(plugin not detected)', 'emailsherlock-email-guard' ); ?></em>
									<?php endif; ?>
								</label>
							<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Policy', 'emailsherlock-email-guard' ); ?></h2>
				<p><?php esc_html_e( 'Block rejects the submission with a message at the field. Flag lets it through but marks it: comments go to moderation, everything else is recorded in the decision log. Allow does nothing.', 'emailsherlock-email-guard' ); ?></p>
				<table class="form-table" role="presentation">
					<?php foreach ( $this->policy_rows() as $token => $row ) : ?>
						<tr>
							<th scope="row"><label for="email-guard-action-<?php echo esc_attr( $token ); ?>"><?php echo esc_html( $row['label'] ); ?></label></th>
							<td>
								<select id="email-guard-action-<?php echo esc_attr( $token ); ?>"
									name="<?php echo esc_attr( Settings::OPTION ); ?>[verdict_actions][<?php echo esc_attr( $token ); ?>]">
									<?php foreach ( $this->action_choices() as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['verdict_actions'][ $token ], $value ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php echo esc_html( $row['description'] ); ?></p>
							</td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'If the API is unreachable', 'emailsherlock-email-guard' ); ?></th>
						<td>
							<label>
								<input type="checkbox" value="1" name="<?php echo esc_attr( Settings::OPTION ); ?>[fail_open]" <?php checked( ! empty( $settings['fail_open'] ) ); ?>>
								<?php esc_html_e( 'Let submissions through (recommended)', 'emailsherlock-email-guard' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Local checks keep working either way. Unchecking this rejects every submission the API cannot answer, which blocks real visitors during an outage.', 'emailsherlock-email-guard' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="email-guard-blocked-message"><?php esc_html_e( 'Message on a blocked address', 'emailsherlock-email-guard' ); ?></label></th>
						<td>
							<input type="text" id="email-guard-blocked-message" class="large-text"
								name="<?php echo esc_attr( Settings::OPTION ); ?>[blocked_message]"
								value="<?php echo esc_attr( $settings['blocked_message'] ); ?>"
								placeholder="<?php echo esc_attr( $this->plugin->settings()->blocked_message() ); ?>">
							<p class="description"><?php esc_html_e( 'Leave empty for the default message.', 'emailsherlock-email-guard' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Decision log', 'emailsherlock-email-guard' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Local log', 'emailsherlock-email-guard' ); ?></th>
						<td>
							<label>
								<input type="checkbox" value="1" name="<?php echo esc_attr( Settings::OPTION ); ?>[log_enabled]" <?php checked( ! empty( $settings['log_enabled'] ) ); ?>>
								<?php esc_html_e( 'Keep a local log of the last decisions', 'emailsherlock-email-guard' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Stores the domain of each checked address, never the address itself.', 'emailsherlock-email-guard' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="email-guard-log-size"><?php esc_html_e( 'Entries to keep', 'emailsherlock-email-guard' ); ?></label></th>
						<td>
							<input type="number" id="email-guard-log-size" min="1" max="200" class="small-text"
								name="<?php echo esc_attr( Settings::OPTION ); ?>[log_size]"
								value="<?php echo esc_attr( (string) (int) $settings['log_size'] ); ?>">
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<?php $this->render_log_table(); ?>
		</div>
		<?php
	}

	/**
	 * Recent decisions plus the clear button (its own form, admin-post).
	 */
	private function render_log_table() {
		$log     = new DecisionLog( $this->plugin->settings() );
		$entries = $log->entries();
		?>
		<h2><?php esc_html_e( 'Recent decisions', 'emailsherlock-email-guard' ); ?></h2>
		<?php if ( array() === $entries ) : ?>
			<p><?php esc_html_e( 'No decisions recorded yet.', 'emailsherlock-email-guard' ); ?></p>
			<?php return; ?>
		<?php endif; ?>
		<table class="widefat striped" style="max-width:900px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'emailsherlock-email-guard' ); ?></th>
					<th><?php esc_html_e( 'Form', 'emailsherlock-email-guard' ); ?></th>
					<th><?php esc_html_e( 'Domain', 'emailsherlock-email-guard' ); ?></th>
					<th><?php esc_html_e( 'Verdict', 'emailsherlock-email-guard' ); ?></th>
					<th><?php esc_html_e( 'Action', 'emailsherlock-email-guard' ); ?></th>
					<th><?php esc_html_e( 'Reasons', 'emailsherlock-email-guard' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $entries as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( wp_date( 'Y-m-d H:i', isset( $entry['time'] ) ? (int) $entry['time'] : 0 ) ); ?></td>
						<td><?php echo esc_html( isset( $entry['form'] ) ? $entry['form'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $entry['domain'] ) && null !== $entry['domain'] ? $entry['domain'] : '-' ); ?></td>
						<td><?php echo esc_html( isset( $entry['verdict'] ) ? $entry['verdict'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $entry['action'] ) ? $entry['action'] : '' ); ?></td>
						<td><?php echo esc_html( implode( ', ', isset( $entry['reasons'] ) && is_array( $entry['reasons'] ) ? $entry['reasons'] : array() ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px;">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::CLEAR_ACTION ); ?>">
			<?php wp_nonce_field( self::CLEAR_ACTION ); ?>
			<?php submit_button( __( 'Clear log', 'emailsherlock-email-guard' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * @return array<string, array{label: string, description: string}>
	 */
	private function policy_rows() {
		return array(
			'invalid'    => array(
				'label'       => __( 'Invalid addresses', 'emailsherlock-email-guard' ),
				'description' => __( 'Bad syntax, reserved domains, or a mailbox the API proved undeliverable.', 'emailsherlock-email-guard' ),
			),
			'disposable' => array(
				'label'       => __( 'Disposable addresses', 'emailsherlock-email-guard' ),
				'description' => __( 'Throwaway providers like Mailinator. Checked against a bundled list; a key adds the live list.', 'emailsherlock-email-guard' ),
			),
			'role'       => array(
				'label'       => __( 'Role addresses', 'emailsherlock-email-guard' ),
				'description' => __( 'Shared inboxes like info@ or support@. Often legitimate. Needs an API key.', 'emailsherlock-email-guard' ),
			),
			'catch_all'  => array(
				'label'       => __( 'Catch-all domains', 'emailsherlock-email-guard' ),
				'description' => __( 'The mail server accepts everything, so the mailbox cannot be proven. Needs an API key.', 'emailsherlock-email-guard' ),
			),
			'unknown'    => array(
				'label'       => __( 'Unverifiable addresses', 'emailsherlock-email-guard' ),
				'description' => __( 'The check could not settle on an answer, for example greylisting. Blocking these also blocks every address when no API key is set.', 'emailsherlock-email-guard' ),
			),
			'relay'      => array(
				'label'       => __( 'Relay and forwarding services', 'emailsherlock-email-guard' ),
				'description' => __( 'Masked addresses from services like Apple Hide My Email or SimpleLogin. They reach a real person. Needs an API key.', 'emailsherlock-email-guard' ),
			),
		);
	}

	/**
	 * @return array<string, string>
	 */
	private function action_choices() {
		return array(
			Settings::ACTION_BLOCK => __( 'Block', 'emailsherlock-email-guard' ),
			Settings::ACTION_FLAG  => __( 'Flag', 'emailsherlock-email-guard' ),
			Settings::ACTION_ALLOW => __( 'Allow', 'emailsherlock-email-guard' ),
		);
	}
}
