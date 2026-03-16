<?php
if (!defined('ABSPATH')) {
	exit;
}

class SCL_Public {

	public function __construct() {
		add_action('template_redirect', array($this, 'render_custom_login'));
		add_action('template_redirect', array($this, 'handle_blocked_requests'), 0);

		add_action('login_init', array($this, 'block_wp_login_php'), 0);
		add_action('admin_init', array($this, 'block_wp_admin_for_guests'), 0);

		add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
	}

	private function get_settings() {
		$defaults = array(
			'login_slug'   => 'logintothesite',
			'logo_url'     => '',
			'right_image'  => '',
			'heading'      => 'Welcome back',
			'subtitle'     => 'Please enter your details',
			'helper_text'  => 'This portal is only for authorized users. Please use your assigned username/email and password to continue.',
			'button_color' => '#8fb1dc',
		);

		return wp_parse_args(get_option('scl_settings', array()), $defaults);
	}

	public function enqueue_assets() {
		if ((int) get_query_var('scl_login') !== 1) {
			return;
		}

		wp_enqueue_style(
			'scl-login-css',
			SCL_PLUGIN_URL . 'assets/css/login.css',
			array(),
			SCL_VERSION
		);
	}

	public function block_wp_login_php() {
		if (is_user_logged_in()) {
			return;
		}

		$this->send_404();
	}

	public function block_wp_admin_for_guests() {
		if (is_user_logged_in()) {
			return;
		}

		if (wp_doing_ajax()) {
			return;
		}

		if (defined('DOING_CRON') && DOING_CRON) {
			return;
		}

		if (defined('WP_CLI') && WP_CLI) {
			return;
		}

		$script = isset($_SERVER['SCRIPT_NAME']) ? wp_unslash($_SERVER['SCRIPT_NAME']) : '';

		if (strpos($script, 'admin-post.php') !== false || strpos($script, 'async-upload.php') !== false) {
			return;
		}

		$this->send_404();
	}

	public function handle_blocked_requests() {
		// Extra safety for direct requests.
		if (is_user_logged_in()) {
			return;
		}

		$request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
		$request_uri = trim($request_uri);

		if ($request_uri === '') {
			return;
		}

		$parsed = wp_parse_url(home_url($request_uri));
		$path   = isset($parsed['path']) ? trim($parsed['path'], '/') : '';

		if ($path === 'wp-login.php' || $path === 'wp-admin') {
			$this->send_404();
		}
	}

	private function send_404() {
		global $wp_query;

		if (!headers_sent()) {
			status_header(404);
			nocache_headers();
		}

		if (isset($wp_query) && is_object($wp_query)) {
			$wp_query->set_404();
		}

		$template_404 = get_404_template();
		if ($template_404) {
			include $template_404;
		} else {
			wp_die(
				esc_html__('404 Not Found', 'secure-custom-login'),
				esc_html__('404 Not Found', 'secure-custom-login'),
				array('response' => 404)
			);
		}
		exit;
	}

	private function generate_math_question() {
		$a = wp_rand(10, 19);
		$b = wp_rand(10, 19);

		if (!session_id()) {
			session_start();
		}

		$_SESSION['scl_math_answer'] = $a + $b;
		$_SESSION['scl_math_label']  = $a . ' + ' . $b . ' =';

		return array(
			'label'  => $_SESSION['scl_math_label'],
			'answer' => $_SESSION['scl_math_answer'],
		);
	}

	private function get_math_label() {
		if (!session_id()) {
			session_start();
		}

		if (!isset($_SESSION['scl_math_label'], $_SESSION['scl_math_answer'])) {
			$math = $this->generate_math_question();
			return $math['label'];
		}

		return $_SESSION['scl_math_label'];
	}

	private function validate_math_answer($user_answer) {
		if (!session_id()) {
			session_start();
		}

		$expected = isset($_SESSION['scl_math_answer']) ? intval($_SESSION['scl_math_answer']) : null;
		$actual   = intval($user_answer);

		return ($expected !== null && $actual === $expected);
	}

	private function clear_math_session() {
		if (!session_id()) {
			session_start();
		}

		unset($_SESSION['scl_math_answer'], $_SESSION['scl_math_label']);
	}

	private function handle_login_submission() {
		$errors  = new WP_Error();
		$success = '';
		$action  = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : 'login';

		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			return array($errors, $success);
		}

		if (!isset($_POST['scl_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['scl_nonce'])), 'scl_login_action')) {
			$errors->add('nonce', __('Security check failed.', 'secure-custom-login'));
			return array($errors, $success);
		}

		if ($action === 'lostpassword') {
			$user_login = isset($_POST['user_login']) ? sanitize_text_field(wp_unslash($_POST['user_login'])) : '';

			if (empty($user_login)) {
				$errors->add('empty_username', __('Please enter your username or email address.', 'secure-custom-login'));
				return array($errors, $success);
			}

			$result = retrieve_password($user_login);

			if (is_wp_error($result)) {
				return array($result, $success);
			}

			$success = __('Password reset email sent. Please check your inbox.', 'secure-custom-login');
			return array($errors, $success);
		}

		if ($action === 'rp') {
			$rp_key   = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
			$rp_login = isset($_GET['login']) ? sanitize_text_field(wp_unslash($_GET['login'])) : '';

			$user = check_password_reset_key($rp_key, $rp_login);

			if (is_wp_error($user)) {
				return array($user, $success);
			}

			$pass1 = isset($_POST['pass1']) ? (string) wp_unslash($_POST['pass1']) : '';
			$pass2 = isset($_POST['pass2']) ? (string) wp_unslash($_POST['pass2']) : '';

			if (empty($pass1) || empty($pass2)) {
				$errors->add('empty_password', __('Please enter both password fields.', 'secure-custom-login'));
				return array($errors, $success);
			}

			if ($pass1 !== $pass2) {
				$errors->add('password_mismatch', __('Passwords do not match.', 'secure-custom-login'));
				return array($errors, $success);
			}

			reset_password($user, $pass1);
			$success = __('Password changed successfully. You can now sign in.', 'secure-custom-login');
			return array($errors, $success);
		}

		$remember   = !empty($_POST['rememberme']);
		$user_login = isset($_POST['log']) ? sanitize_text_field(wp_unslash($_POST['log'])) : '';
		$user_pass  = isset($_POST['pwd']) ? (string) wp_unslash($_POST['pwd']) : '';
		$math       = isset($_POST['scl_math']) ? sanitize_text_field(wp_unslash($_POST['scl_math'])) : '';

		if (!$this->validate_math_answer($math)) {
			$errors->add('invalid_math', __('Security question answer is incorrect.', 'secure-custom-login'));
			$this->generate_math_question();
			return array($errors, $success);
		}

		$this->clear_math_session();

		$creds = array(
			'user_login'    => $user_login,
			'user_password' => $user_pass,
			'remember'      => $remember,
		);

		$user = wp_signon($creds, is_ssl());

		if (is_wp_error($user)) {
			$this->generate_math_question();
			return array($user, $success);
		}

		wp_set_current_user($user->ID);
		wp_set_auth_cookie($user->ID, $remember);

		$redirect_to = admin_url();
		wp_safe_redirect($redirect_to);
		exit;
	}

	public function render_custom_login() {
		if ((int) get_query_var('scl_login') !== 1) {
			return;
		}

		$settings = $this->get_settings();

		if (is_user_logged_in() && empty($_GET['action'])) {
			wp_safe_redirect(admin_url());
			exit;
		}

		$action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : 'login';

		if ($action === 'logout') {
			wp_logout();
			wp_safe_redirect(home_url('/' . $settings['login_slug'] . '/?loggedout=1'));
			exit;
		}

		list($errors, $success) = $this->handle_login_submission();

		$math_label = $this->get_math_label();

		$template_file = SCL_PLUGIN_DIR . 'templates/login-template.php';
		if (file_exists($template_file)) {
			$scl_settings   = $settings;
			$scl_errors     = $errors;
			$scl_success    = $success;
			$scl_math_label = $math_label;
			$scl_action     = $action;

			include $template_file;
			exit;
		}
	}
}
