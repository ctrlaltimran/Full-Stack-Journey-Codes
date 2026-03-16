<?php
if (!defined('ABSPATH')) {
	exit;
}

$logo_url     = !empty($scl_settings['logo_url']) ? $scl_settings['logo_url'] : '';
$right_image  = !empty($scl_settings['right_image']) ? $scl_settings['right_image'] : '';
$heading      = !empty($scl_settings['heading']) ? $scl_settings['heading'] : 'Welcome back';
$subtitle     = !empty($scl_settings['subtitle']) ? $scl_settings['subtitle'] : 'Please enter your details';
$helper_text  = !empty($scl_settings['helper_text']) ? $scl_settings['helper_text'] : '';
$button_color = !empty($scl_settings['button_color']) ? $scl_settings['button_color'] : '#8fb1dc';
$slug         = !empty($scl_settings['login_slug']) ? $scl_settings['login_slug'] : 'logintothesite';

$base_url         = home_url('/' . $slug . '/');
$lostpassword_url = add_query_arg('action', 'lostpassword', $base_url);
$login_url        = $base_url;

$loggedout = !empty($_GET['loggedout']);
$key       = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
$login     = isset($_GET['login']) ? sanitize_text_field(wp_unslash($_GET['login'])) : '';

$reset_action_url = add_query_arg(
	array(
		'action' => 'rp',
		'key'    => $key,
		'login'  => $login,
	),
	$base_url
);

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html(get_bloginfo('name')); ?> - <?php echo esc_html($heading); ?></title>
	<?php wp_head(); ?>
	<style>
		:root {
			--scl-button-color: <?php echo esc_html($button_color); ?>;
		}
	</style>
</head>
<body <?php body_class('scl-login-body'); ?>>

<div class="scl-page">
	<div class="scl-shell">
		<div class="scl-left">
			<div class="scl-panel">
				<div class="scl-brand">
					<?php if (!empty($logo_url)) : ?>
						<img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
					<?php else : ?>
						<div class="scl-brand-text"><?php echo esc_html(get_bloginfo('name')); ?></div>
					<?php endif; ?>
				</div>

				<h1 class="scl-heading"><?php echo esc_html($heading); ?></h1>
				<p class="scl-subtitle"><?php echo esc_html($subtitle); ?></p>

				<?php if ($loggedout) : ?>
					<div class="scl-message"><?php esc_html_e('You have been logged out successfully.', 'secure-custom-login'); ?></div>
				<?php endif; ?>

				<?php if (!empty($scl_success)) : ?>
					<div class="scl-message"><?php echo esc_html($scl_success); ?></div>
				<?php endif; ?>

				<?php if (is_wp_error($scl_errors) && $scl_errors->has_errors()) : ?>
					<div class="scl-error">
						<?php foreach ($scl_errors->get_error_messages() as $message) : ?>
							<div><?php echo esc_html($message); ?></div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ($scl_action === 'lostpassword') : ?>

					<form class="scl-form" method="post" action="<?php echo esc_url($lostpassword_url); ?>">
						<?php wp_nonce_field('scl_login_action', 'scl_nonce'); ?>

						<div class="scl-field">
							<label class="scl-label" for="user_login"><?php esc_html_e('Username or Email Address', 'secure-custom-login'); ?></label>
							<input class="scl-input" type="text" id="user_login" name="user_login" autocomplete="username">
						</div>

						<p class="scl-helper"><?php esc_html_e('Enter your username or email address. A password reset link will be sent to your inbox.', 'secure-custom-login'); ?></p>

						<button class="scl-button" type="submit"><?php esc_html_e('Send Reset Link', 'secure-custom-login'); ?></button>

						<p class="scl-reset-note">
							<a class="scl-link" href="<?php echo esc_url($login_url); ?>"><?php esc_html_e('Back to sign in', 'secure-custom-login'); ?></a>
						</p>
					</form>

				<?php elseif ($scl_action === 'rp') : ?>

					<form class="scl-form" method="post" action="<?php echo esc_url($reset_action_url); ?>">
						<?php wp_nonce_field('scl_login_action', 'scl_nonce'); ?>

						<div class="scl-field">
							<label class="scl-label" for="pass1"><?php esc_html_e('New Password', 'secure-custom-login'); ?></label>
							<input class="scl-input" type="password" id="pass1" name="pass1" autocomplete="new-password">
						</div>

						<div class="scl-field">
							<label class="scl-label" for="pass2"><?php esc_html_e('Confirm Password', 'secure-custom-login'); ?></label>
							<input class="scl-input" type="password" id="pass2" name="pass2" autocomplete="new-password">
						</div>

						<button class="scl-button" type="submit"><?php esc_html_e('Reset Password', 'secure-custom-login'); ?></button>

						<p class="scl-reset-note">
							<a class="scl-link" href="<?php echo esc_url($login_url); ?>"><?php esc_html_e('Back to sign in', 'secure-custom-login'); ?></a>
						</p>
					</form>

				<?php else : ?>

					<form class="scl-form" method="post" action="<?php echo esc_url($login_url); ?>">
						<?php wp_nonce_field('scl_login_action', 'scl_nonce'); ?>

						<div class="scl-field">
							<label class="scl-label" for="log"><?php esc_html_e('Email address / Username', 'secure-custom-login'); ?></label>
							<input class="scl-input" type="text" id="log" name="log" autocomplete="username" required>
						</div>

						<div class="scl-field">
							<label class="scl-label" for="pwd"><?php esc_html_e('Password', 'secure-custom-login'); ?></label>
							<input class="scl-input" type="password" id="pwd" name="pwd" autocomplete="current-password" required>
						</div>

						<?php if (!empty($helper_text)) : ?>
							<p class="scl-helper"><?php echo esc_html($helper_text); ?></p>
						<?php endif; ?>

						<div class="scl-field">
							<label class="scl-label"><?php esc_html_e('Security question', 'secure-custom-login'); ?></label>
							<div class="scl-math-wrap">
								<div class="scl-math-question"><?php echo esc_html($scl_math_label); ?></div>
								<input class="scl-math-input" type="text" name="scl_math" inputmode="numeric" required>
							</div>
						</div>

						<div class="scl-bottom-row">
							<label class="scl-remember">
								<input type="checkbox" name="rememberme" value="forever">
								<span><?php esc_html_e('Remember for 30 days', 'secure-custom-login'); ?></span>
							</label>

							<a class="scl-link" href="<?php echo esc_url($lostpassword_url); ?>"><?php esc_html_e('Forgot password', 'secure-custom-login'); ?></a>
						</div>

						<button class="scl-button" type="submit"><?php esc_html_e('Sign in', 'secure-custom-login'); ?></button>
					</form>

				<?php endif; ?>
			</div>
		</div>

		<div class="scl-right">
			<?php if (!empty($right_image)) : ?>
				<img src="<?php echo esc_url($right_image); ?>" alt="">
			<?php else : ?>
				<div class="scl-image-fallback">
					<div><?php esc_html_e('Upload your right-side login image from the plugin settings.', 'secure-custom-login'); ?></div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<?php wp_footer(); ?>
</body>
</html>
