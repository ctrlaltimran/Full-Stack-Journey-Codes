<?php
if (!defined('ABSPATH')) {
	exit;
}

class SCL_Admin {

	public function __construct() {
		add_action('admin_menu', array($this, 'add_settings_page'));
		add_action('admin_init', array($this, 'register_settings'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
	}

	public function add_settings_page() {
		add_options_page(
			__('Secure Custom Login', 'secure-custom-login'),
			__('Secure Custom Login', 'secure-custom-login'),
			'manage_options',
			'scl-settings',
			array($this, 'render_settings_page')
		);
	}

	public function register_settings() {
		register_setting('scl_settings_group', 'scl_settings', array($this, 'sanitize_settings'));
	}

	public function sanitize_settings($input) {
		$old = get_option('scl_settings', array());

		$output = array();
		$output['login_slug']   = !empty($input['login_slug']) ? sanitize_title($input['login_slug']) : 'logintothesite';
		$output['logo_url']     = !empty($input['logo_url']) ? esc_url_raw($input['logo_url']) : '';
		$output['right_image']  = !empty($input['right_image']) ? esc_url_raw($input['right_image']) : '';
		$output['heading']      = !empty($input['heading']) ? sanitize_text_field($input['heading']) : 'Welcome back';
		$output['subtitle']     = !empty($input['subtitle']) ? sanitize_text_field($input['subtitle']) : 'Please enter your details';
		$output['helper_text']  = !empty($input['helper_text']) ? sanitize_textarea_field($input['helper_text']) : '';
		$output['button_color'] = !empty($input['button_color']) ? sanitize_hex_color($input['button_color']) : '#8fb1dc';

		if (!isset($output['button_color']) || empty($output['button_color'])) {
			$output['button_color'] = '#8fb1dc';
		}

		if (
			isset($old['login_slug'], $output['login_slug']) &&
			$old['login_slug'] !== $output['login_slug']
		) {
			flush_rewrite_rules();
		}

		return $output;
	}

	public function enqueue_admin_assets($hook) {
		if ($hook !== 'settings_page_scl-settings') {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'scl-admin-js',
			SCL_PLUGIN_URL . 'assets/js/admin.js',
			array('jquery'),
			SCL_VERSION,
			true
		);
	}

	public function render_settings_page() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$settings = get_option('scl_settings', array());
		$defaults = array(
			'login_slug'   => 'logintothesite',
			'logo_url'     => '',
			'right_image'  => '',
			'heading'      => 'Welcome back',
			'subtitle'     => 'Please enter your details',
			'helper_text'  => 'This portal is only for authorized users. Please use your assigned username/email and password to continue.',
			'button_color' => '#8fb1dc',
		);
		$settings = wp_parse_args($settings, $defaults);

		$login_url = home_url('/' . $settings['login_slug'] . '/');
		?>
		<div class="wrap">
			<h1><?php esc_html_e('Secure Custom Login Settings', 'secure-custom-login'); ?></h1>

			<p>
				<strong><?php esc_html_e('Custom Login URL:', 'secure-custom-login'); ?></strong>
				<a href="<?php echo esc_url($login_url); ?>" target="_blank"><?php echo esc_html($login_url); ?></a>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields('scl_settings_group'); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="scl_login_slug"><?php esc_html_e('Login Slug', 'secure-custom-login'); ?></label></th>
						<td>
							<input type="text" id="scl_login_slug" name="scl_settings[login_slug]" class="regular-text" value="<?php echo esc_attr($settings['login_slug']); ?>">
							<p class="description">Example: <code>logintothesite</code></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="scl_heading"><?php esc_html_e('Heading', 'secure-custom-login'); ?></label></th>
						<td>
							<input type="text" id="scl_heading" name="scl_settings[heading]" class="regular-text" value="<?php echo esc_attr($settings['heading']); ?>">
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="scl_subtitle"><?php esc_html_e('Subtitle', 'secure-custom-login'); ?></label></th>
						<td>
							<input type="text" id="scl_subtitle" name="scl_settings[subtitle]" class="regular-text" value="<?php echo esc_attr($settings['subtitle']); ?>">
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="scl_helper_text"><?php esc_html_e('Helper Text', 'secure-custom-login'); ?></label></th>
						<td>
							<textarea id="scl_helper_text" name="scl_settings[helper_text]" rows="5" class="large-text"><?php echo esc_textarea($settings['helper_text']); ?></textarea>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="scl_button_color"><?php esc_html_e('Button Color', 'secure-custom-login'); ?></label></th>
						<td>
							<input type="color" id="scl_button_color" name="scl_settings[button_color]" value="<?php echo esc_attr($settings['button_color']); ?>">
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e('Logo', 'secure-custom-login'); ?></th>
						<td>
							<input type="text" id="scl_logo_url" name="scl_settings[logo_url]" class="regular-text" value="<?php echo esc_attr($settings['logo_url']); ?>">
							<button type="button" class="button scl-upload-button" data-target="#scl_logo_url"><?php esc_html_e('Upload / Select Logo', 'secure-custom-login'); ?></button>
							<?php if (!empty($settings['logo_url'])) : ?>
								<div style="margin-top:10px;">
									<img src="<?php echo esc_url($settings['logo_url']); ?>" alt="" style="max-height:60px;">
								</div>
							<?php endif; ?>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e('Right Side Image', 'secure-custom-login'); ?></th>
						<td>
							<input type="text" id="scl_right_image" name="scl_settings[right_image]" class="regular-text" value="<?php echo esc_attr($settings['right_image']); ?>">
							<button type="button" class="button scl-upload-button" data-target="#scl_right_image"><?php esc_html_e('Upload / Select Right Image', 'secure-custom-login'); ?></button>
							<?php if (!empty($settings['right_image'])) : ?>
								<div style="margin-top:10px;">
									<img src="<?php echo esc_url($settings['right_image']); ?>" alt="" style="max-height:120px;">
								</div>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
