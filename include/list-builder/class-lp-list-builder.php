<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LP_List_Builder
{
    public function run()
    {
        self::includes();

        $settings = get_option('lp-listbuilder', []);

        if (empty($settings['enabled']) || $settings['enabled'] !== 'on') {
            return;
        }

        add_action('wp_footer', [$this, 'output_slider']);
        add_action('wp_enqueue_scripts', array($this, 'load_scripts'));
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('after_setup_theme', [$this, 'maybe_hide_admin_bar']);
    }

    public function maybe_hide_admin_bar()
    {
        if (!is_user_logged_in()) {
            return;
        }

        $user = wp_get_current_user();

        if (in_array('subscriber', (array) $user->roles, true)) {
            show_admin_bar(false);
        }
    }

    public function output_slider()
    {
        $settings = get_option('lp-listbuilder', []);
        $heading = !empty($settings['heading']) ? $settings['heading'] : 'Enter your email to read this article';
        $subheading = !empty($settings['subheading']) ? $settings['subheading'] : 'Gain access to free articles and additional content.';
        $terms_and_conditions = !empty($settings['terms_and_conditions']) ? $settings['terms_and_conditions'] : 'Yes, I would like to receive top content, special offers, and other updates.';

        $bg_color = !empty($settings['background_color']) ? $settings['background_color'] : '#000000';
        $text_color = !empty($settings['text_color']) ? $settings['text_color'] : '#ffffff';
        $button_color = !empty($settings['button_color']) ? $settings['button_color'] : '#E45637';
        $button_text_color = !empty($settings['button_text_color']) ? $settings['button_text_color'] : '#ffffff';

        $upgrade_heading = !empty($settings['upgrade_heading']) ? $settings['upgrade_heading'] : 'Upgrade your subscription';
        $upgrade_subheading = !empty($settings['upgrade_subheading']) ? $settings['upgrade_subheading'] : 'Get full access to all content.';
        $upgrade_button_text = !empty($settings['upgrade_button_text']) ? $settings['upgrade_button_text'] : 'Upgrade Now';

        $lp_settings = get_leaky_paywall_settings();
        $subscribe_url = ! empty( $lp_settings['page_for_subscription'] ) ? get_permalink( $lp_settings['page_for_subscription'] ) : '';

?>

        <style>
            :root {
                --lplb-bg-color: <?php echo esc_attr($bg_color); ?>;
                --lplb-text-color: <?php echo esc_attr($text_color); ?>;
                --lplb-button-color: <?php echo esc_attr($button_color); ?>;
                --lplb-button-text-color: <?php echo esc_attr($button_text_color); ?>;
            }
        </style>

        <div id="lplb-mask"></div>


        <div id="lplb-portal">
            <div class="Slider__Container">
                <div class="Slider Slider--scrolled">

                    <div id="lplb-subscribe-panel" class="Slider__InnerContainer Slider__InnerContainer--scrolled">
                        <h1 class="Slider__ExpandedHeader"><?php echo esc_html($heading); ?></h1>
                        <div class="Slider__ExpandedSubHeader"><?php echo esc_html($subheading); ?></div>
                        <div class="Form__Container" data-lp-list-builder>

                            <form class="lp-list-builder__form" data-step="email">
                                <div class="Slider__InputGroup">
                                    <div class="Slider__InputRow">
                                        <label><?php esc_html_e( 'Email Address', 'leaky-paywall' ); ?></label>
                                        <div class="TextField Slider__EmailAddressField">
                                            <input type="email" name="email" value="" required autocomplete="email" placeholder="<?php esc_attr_e( 'Enter your email', 'leaky-paywall' ); ?>" />
                                        </div>
                                    </div>
                                    <button type="submit" class="Slider__ExpandedButton" tabindex="0">
                                        <?php esc_html_e( 'Continue', 'leaky-paywall' ); ?>
                                    </button>
                                    <div class="Slider__Policy">
                                        <p><?php echo esc_html($terms_and_conditions); ?></p>
                                    </div>
                                </div>
                            </form>

                            <div class="lp-list-builder__msg" aria-live="polite"></div>

                        </div>
                    </div>

                    <div id="lplb-upgrade-panel" class="Slider__InnerContainer Slider__InnerContainer--scrolled" style="display:none;">
                        <h1 class="Slider__ExpandedHeader"><?php echo esc_html($upgrade_heading); ?></h1>
                        <div class="Slider__ExpandedSubHeader"><?php echo esc_html($upgrade_subheading); ?></div>
                        <?php if ( $subscribe_url ) : ?>
                            <a href="<?php echo esc_url($subscribe_url); ?>" class="Slider__ExpandedButton" style="display:inline-block;text-align:center;text-decoration:none;">
                                <?php echo esc_html($upgrade_button_text); ?>
                            </a>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>

    <?php
    }

    public static function handle_flow($request)
    {

        $nonce = $request->get_header('X-WP-Nonce');

        if (! wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_REST_Response([
                'ok'    =>  false,
                'error' => 'invalid_nonce',
                'message'   => __( 'Invalid request.', 'leaky-paywall' ),
            ], 403);
        }

        $email = sanitize_email($request->get_param('email'));

        if (empty($email) || ! is_email($email)) {
            return new WP_REST_Response([
                'ok'    => false,
                'error' => 'invalid_email',
                'message' => __( 'Please enter a valid email address.', 'leaky-paywall' ),
            ], 400);
        }

        $user = get_user_by('email', $email);

        // FLOW A: existing user -> show a password field so they can log in
        if ($user instanceof WP_User) {
            return new WP_REST_Response([
                'ok'    =>  true,
                'step'  =>  'password',
                'email' =>  $email,
                'heading' => __( 'Welcome back', 'leaky-paywall' ),
                'subheading' => __( 'Enter your password to log in.', 'leaky-paywall' ),
                'form_html' => self::render_password_step_form($email)
            ], 200);
        }

        // FLOW B: user does not exist -> show a password field so they can sign up
        return new WP_REST_Response([
            'ok'    =>  true,
            'step'  => 'signup',
            'email' => $email,
            'heading' => __( 'Create a password', 'leaky-paywall' ),
            'subheading' => '',
            'form_html' => self::render_signup_step_form($email)
        ]);
    }

    public static function handle_signup($request)
    {

        $nonce = $request->get_header('X-WP-Nonce');

        if (! wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_REST_Response([
                'ok'    =>  false,
                'error' => 'invalid_nonce',
                'message'   => __( 'Invalid request.', 'leaky-paywall' ),
            ], 403);
        }

        $email = sanitize_email($request->get_param('email'));
        $password = $request->get_param('password');
        $current_url = $request->get_param('current_url');

        if (empty($email) || ! is_email($email)) {
            return new WP_REST_Response([
                'ok'    => false,
                'error' => 'invalid_email',
                'message' => __( 'Please enter a valid email address.', 'leaky-paywall' ),
            ], 400);
        }

        if (! wp_unslash($password)) {
            return new WP_REST_Response([
                'ok'    => false,
                'error' => 'weak_password',
                'message'   => __( 'Password must be at least 4 characters.', 'leaky-paywall' ),
            ]);
        }

        if ( ! empty( $request->get_param('website') ) ) {
            return new WP_REST_Response([
                'ok'      => false,
                'error'   => 'honeypot',
                'message' => __( 'Registration could not be completed.', 'leaky-paywall' ),
            ], 400);
        }

        $validation_error = apply_filters( 'lp_list_builder_signup_validation', '', $request );
        if ( ! empty( $validation_error ) ) {
            return new WP_REST_Response([
                'ok'      => false,
                'error'   => 'validation_failed',
                'message' => $validation_error,
            ], 400);
        }

        if (email_exists($email)) {
            return new WP_REST_Response([
                'ok'    => false,
                'error' => 'email_exists',
                'message'   => __( 'An account with that email already exists. Please sign in instead.', 'leaky-paywall' ),
            ], 409);
        }

        $username = self::generate_unique_username_from_email($email);

        $lb_settings = get_option('lp-listbuilder', []);
        $level_id = isset($lb_settings['level_id']) ? absint($lb_settings['level_id']) : 0;
        $level = get_leaky_paywall_subscription_level($level_id);

        $subscriber_data = array(
            'email' => $email,
            'login' => $username,
            'password'  => $password,
            'first_name' => '',
            'last_name'    => '',
            'level_id'    => $level_id,
            'description' => '',
            'created'    => gmdate('Y-m-d H:i:s'),
            'price'    => 0,
            'interval_count' => $level['interval_count'],
            'interval'    => $level['interval'],
            'new_user'    => true,
            'payment_gateway'    => 'Free Registration',
            'payment_status'    => 'active',
            'site'    => leaky_paywall_get_current_site(),
            'mode' => leaky_paywall_get_current_mode()
        );

        $user_id = leaky_paywall_new_subscriber(NULL, $email, '', $subscriber_data);

        if (is_wp_error($user_id)) {
            return new WP_REST_Response([
                'ok'    => false,
                'error' => 'user_create_failed',
                'message'   => $user_id->get_error_message()
            ], 400);
        }

        $subscriber_data['user_id'] = $user_id;

        $transaction                       = new LP_Transaction($subscriber_data);
        $transaction_id                    = $transaction->create();
        $subscriber_data['transaction_id'] = $transaction_id;

        leaky_paywall_cleanup_incomplete_user($subscriber_data['email']);

        // Send email notifications.
        leaky_paywall_email_subscription_status($user_id, 'new', $subscriber_data);

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        do_action('leaky_paywall_after_process_registration', $subscriber_data);

        $restrictions = new Leaky_Paywall_Restrictions();
        $restrictions->clear_cookie();

        $redirect = esc_url_raw($current_url);

        return new WP_REST_Response([
            'ok'    => true,
            'user_id'   => $user_id,
            'redirect'  => $redirect
        ]);
    }

    public static function handle_login($request)
    {
        $nonce = $request->get_header('X-WP-Nonce');

        if (! wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_REST_Response([
                'ok'    =>  false,
                'error' => 'invalid_nonce',
                'message'   => __( 'Invalid request.', 'leaky-paywall' ),
            ], 403);
        }

        $email = sanitize_email($request->get_param('email'));
        $password = $request->get_param('password');
        $current_url = $request->get_param('current_url');

        if (empty($email) || ! is_email($email)) {
            return new WP_REST_Response([
                'ok'    => false,
                'error' => 'invalid_email',
                'message' => __( 'Please enter a valid email address.', 'leaky-paywall' ),
            ], 400);
        }

        $user = get_user_by('email', $email);

        if (! ($user instanceof WP_User)) {
            return new WP_REST_Response([
                'ok' => false,
                'error' => 'no_user',
                'message' => __( 'No account found for that email. Please create an account.', 'leaky-paywall' ),
            ], 404);
        }

        $creds = [
            'user_login'    => $user->user_login,
            'user_password' => $password,
            'remember'  => true,
        ];

        $logged_in = wp_signon($creds, is_ssl());

        if (is_wp_error($logged_in)) {
            return new WP_REST_Response([
                'ok' => false,
                'error' => 'invalid_credentials',
                'message' => __( 'Incorrect email or password.', 'leaky-paywall' ),
            ], 401);
        }

        wp_set_current_user($logged_in->ID);
        wp_set_auth_cookie($logged_in->ID, true, is_ssl());

        $redirect = esc_url_raw($current_url);

        return new WP_REST_Response([
            'ok'    => true,
            'user_id'   => $logged_in->ID,
            'redirect'  => $redirect
        ], 200);
    }

    public function handle_password_reset_request($request)
    {

        $email_param = $request->get_param('email');
        $email = sanitize_email($email_param);

        $heading = __( 'Check your email', 'leaky-paywall' );
        $subheading = __( 'If an account exists for that email, we sent a 6-digit verification code.', 'leaky-paywall' );

        $generic_ok = new WP_REST_Response([
            'ok'         => true,
            'step'       => 'reset_code',
            'heading'    => $heading,
            'subheading' => $subheading,
            'form_html'  => $this->render_reset_code_form($email),
        ], 200);

        if (empty($email) || !is_email($email)) {
            return $generic_ok;
        }

        $user = get_user_by('email', $email);

        if (!($user instanceof WP_User)) {
            return $generic_ok;
        }

        /* translators: %s: email address */
        $subheading = sprintf( __( 'We sent a 6-digit code to %s (expires in 10 minutes).', 'leaky-paywall' ), $email );

        $user_id = $user->ID;

        $state_key = $this->reset_state_key($user_id);
        $existing = get_transient($state_key);
        if (is_array($existing) && !empty($existing['sent_at']) && (time() - (int)$existing['sent_at'] < 30)) {
            $subheading = __( 'Please wait a moment before requesting another code.', 'leaky-paywall' );
            return new WP_REST_Response([
                'ok'         => true,
                'step'       => 'reset_code',
                'heading'    => $heading,
                'subheading' => $subheading,
                'form_html'  => $this->render_reset_code_form($email),
            ], 200);
        }

        $code = $this->generate_6_digit_code();

        $hash = wp_hash_password($code);

        set_transient($state_key, [
            'code_hash' => $hash,
            'expires'   => time() + 10 * MINUTE_IN_SECONDS,
            'attempts'  => 0,
            'sent_at'   => time(),
        ], 10 * MINUTE_IN_SECONDS);

        $subject = __( 'Your Verification Code', 'leaky-paywall' );
        /* translators: %s: 6-digit verification code */
        $message = sprintf( __( "Your verification code is: %s\n\nThis code expires in 10 minutes.", 'leaky-paywall' ), $code );

        wp_mail($email, $subject, $message);

        /* translators: %s: email address */
        $subheading = sprintf( __( 'We sent a 6-digit code to %s (expires in 10 minutes).', 'leaky-paywall' ), $email );

        return new WP_REST_Response([
            'ok'         => true,
            'step'       => 'reset_code',
            'heading'    => $heading,
            'subheading' => $subheading,
            'form_html'  => $this->render_reset_code_form($email),
        ], 200);
    }

    public function handle_password_reset_verify($request)
    {
        $email_param = $request->get_param('email');
        $email = sanitize_email($email_param);

        $code_param = $request->get_param('code');
        $code = preg_replace('/\D+/', '', $code_param);

        if (empty($email) || !is_email($email) || strlen($code) !== 6) {
            return new WP_REST_Response([
                'ok' => false,
                'message' => __( 'Invalid code.', 'leaky-paywall' ),
            ], 400);
        }

        $user = get_user_by('email', $email);

        if (!($user instanceof WP_User)) {
            return new WP_REST_Response(['ok' => false, 'message' => __( 'Invalid code.', 'leaky-paywall' )], 400);
        }

        $user_id = (int) $user->ID;
        $state_key = $this->reset_state_key($user_id);
        $state = get_transient($state_key);

        if (!is_array($state) || empty($state['code_hash']) || empty($state['expires']) || time() > (int)$state['expires']) {
            return new WP_REST_Response(['ok' => false, 'message' => __( 'Code expired. Please request a new one.', 'leaky-paywall' )], 400);
        }

        $attempts = (int)($state['attempts'] ?? 0);
        if ($attempts >= 8) {
            return new WP_REST_Response(['ok' => false, 'message' => __( 'Too many attempts. Request a new code.', 'leaky-paywall' )], 429);
        }

        $state['attempts'] = $attempts + 1;
        set_transient($state_key, $state, max(60, (int)$state['expires'] - time()));

        if (!wp_check_password($code, (string)$state['code_hash'], $user_id)) {
            return new WP_REST_Response(['ok' => false, 'message' => __( 'Invalid code.', 'leaky-paywall' )], 400);
        }

        $token = wp_generate_password(32, false, false);
        set_transient($this->reset_token_key($user_id), [
            'token'   => $token,
            'expires' => time() + 10 * MINUTE_IN_SECONDS,
        ], 10 * MINUTE_IN_SECONDS);

        // Clear the code state
        delete_transient($state_key);

        $heading = __( 'Set a new password', 'leaky-paywall' );
        $subheading = __( 'Choose a new password for your account.', 'leaky-paywall' );

        return new WP_REST_Response([
            'ok'         => true,
            'step'       => 'reset_new_password',
            'heading'    => $heading,
            'subheading' => $subheading,
            'form_html'  => $this->render_reset_new_password_form($email, $token),
        ], 200);
    }

    public function handle_password_reset_confirm($request)
    {
        $email_param = $request->get_param('email');
        $email = sanitize_email((string) $email_param);

        $token_param = $request->get_param('token');
        $token = (string) $token_param;

        $pass_param = $request->get_param('password');
        $password = (string) $pass_param;

        if (empty($email) || !is_email($email) || empty($token) || strlen($password) < 4) {
            return new WP_REST_Response(['ok' => false, 'message' => __( 'Invalid request.', 'leaky-paywall' )], 400);
        }

        $user = get_user_by('email', $email);
        if (!($user instanceof WP_User)) {
            return new WP_REST_Response(['ok' => false, 'message' => __( 'Invalid request.', 'leaky-paywall' )], 400);
        }

        $user_id = (int) $user->ID;
        $stored = get_transient($this->reset_token_key($user_id));

        if (!is_array($stored) || empty($stored['token']) || empty($stored['expires']) || time() > (int)$stored['expires']) {
            return new WP_REST_Response(['ok' => false, 'message' => __( 'Reset session expired. Please request a new code.', 'leaky-paywall' )], 400);
        }

        if (!hash_equals((string)$stored['token'], $token)) {
            return new WP_REST_Response(['ok' => false, 'message' => __( 'Invalid reset session.', 'leaky-paywall' )], 400);
        }

        wp_set_password($password, $user_id);

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true, is_ssl());

        delete_transient($this->reset_token_key($user_id));

        $heading = __( 'All set', 'leaky-paywall' );
        $subheading = __( 'Your password has been updated.', 'leaky-paywall' );

        $success_html = '<div class="lp-inline-auth__success" aria-live="polite"><p><strong>' . esc_html__( 'Password updated.', 'leaky-paywall' ) . '</strong></p><p>' . esc_html__( 'You can continue.', 'leaky-paywall' ) . '</p></div>';

        return new WP_REST_Response([
            'ok'         => true,
            'step'       => 'reset_done',
            'heading'    => $heading,
            'subheading' => $subheading,
            'form_html'  => $success_html,
        ], 200);
    }

    private static function generate_unique_username_from_email($email)
    {
        $base = sanitize_user(preg_replace('/@.+$/', '', $email), true);

        if (empty($base)) {
            $base = 'user';
        }

        $username = $base;
        $i = 0;

        while (username_exists($username)) {
            $i++;
            $username = $base . $i;
            if ($i > 1000) {
                $username = $base . '_' . wp_generate_password(6, false, false);
                break;
            }
        }

        return $username;
    }

    public static function render_password_step_form($email)
    {
        ob_start(); ?>

        <form class="lp-list-builder-auth__form" data-step="password">

            <div class="Slider__InputRow">
                <label><?php esc_html_e( 'Email Address', 'leaky-paywall' ); ?></label>
                <div class="TextField Slider__EmailAddressField">
                    <input type="email" name="email" required autocomplete="email" value="<?php echo esc_attr($email); ?>" />
                </div>
            </div>

            <div class="Slider__InputRow">
                <label><?php esc_html_e( 'Password', 'leaky-paywall' ); ?></label>
                <div class="TextField Slider__PasswordField">
                    <input type="password" name="password" required autocomplete="current-password" />
                </div>
            </div>

            <p class="lp-list-builder__subtle">
                <a href="#" data-action="forgot-password"><?php esc_html_e( 'Forgot your password?', 'leaky-paywall' ); ?></a>
            </p>

            <button type="submit" class="Slider__ExpandedButton"><?php esc_html_e( 'Log in', 'leaky-paywall' ); ?></button>
        </form>

    <?php
        return ob_get_clean();
    }

    public static function render_signup_step_form($email)
    {
        ob_start(); ?>

        <form class="lp-list-builder-auth__form" data-step="signup">
            <div class="Slider__InputRow">
                <label><?php esc_html_e( 'Email Address', 'leaky-paywall' ); ?></label>
                <div class="TextField Slider__EmailAddressField">
                    <input type="email" name="email" required autocomplete="email" value="<?php echo esc_attr($email); ?>" />
                </div>
            </div>

            <div class="Slider__InputRow">
                <label><?php esc_html_e( 'Password', 'leaky-paywall' ); ?></label>
                <div class="TextField Slider__PasswordField">
                    <input type="password" name="password" required autocomplete="new-password" minlength="4" />
                </div>
            </div>

            <?php
            $lp_settings     = get_leaky_paywall_settings();
            $tc_enabled      = ! empty( $lp_settings['enable_terms_and_conditions'] ) && 'on' === $lp_settings['enable_terms_and_conditions'];
            $signup_tc_text  = '';

            if ( $tc_enabled && ! empty( $lp_settings['terms_and_conditions_text'] ) ) {
                $tc_text    = $lp_settings['terms_and_conditions_text'];
                $tc_page_id = ! empty( $lp_settings['page_for_terms'] ) ? $lp_settings['page_for_terms'] : 0;

                if ( $tc_page_id ) {
                    $tc_url         = get_permalink( $tc_page_id );
                    $signup_tc_text = preg_replace(
                        '/\[(.+?)\]/',
                        '<a href="' . esc_url( $tc_url ) . '" target="_blank">$1</a>',
                        esc_html( $tc_text )
                    );
                } else {
                    $signup_tc_text = esc_html( preg_replace( '/\[(.+?)\]/', '$1', $tc_text ) );
                }
            } else {
                $signup_tc_text = esc_html__( 'By creating an account, you agree to the Terms of Sale, Terms of Service, and Privacy Policy.', 'leaky-paywall' );
            }

            $signup_tc_text = apply_filters( 'lp_list_builder_signup_terms_text', $signup_tc_text );
            ?>
            <p><?php echo wp_kses( $signup_tc_text, array( 'a' => array( 'href' => array(), 'target' => array() ) ) ); ?></p>

            <input name="website" type="text" value="" autocomplete="off" tabindex="-1" style="display:none !important; position:absolute; left:-9999px;" />

            <?php do_action( 'lp_list_builder_signup_form_fields', $email ); ?>

            <button type="submit" class="Slider__ExpandedButton"><?php esc_html_e( 'Create account', 'leaky-paywall' ); ?></button>

        </form>

    <?php
        return ob_get_clean();
    }

    public static function render_reset_code_form($email)
    {
        ob_start(); ?>

        <form class="lp-list-builder-auth__form" data-step="reset-code">

            <input type="hidden" name="email" value="<?php echo esc_attr($email); ?>" />

            <div class="Slider__InputRow">
                <label>
                    <?php esc_html_e( 'Verification code', 'leaky-paywall' ); ?>
                </label>
                <div class="TextField Slider__VerficationField">
                    <input type="text"
                        name="code"
                        inputmode="numeric"
                        pattern="\d{6}"
                        maxlength="6"
                        required
                        autocomplete="one-time-code" />
                </div>
            </div>

            <button type="submit" class="Slider__ExpandedButton"><?php esc_html_e( 'Verify code', 'leaky-paywall' ); ?></button>

        </form>

    <?php
        return ob_get_clean();
    }

    public function render_reset_new_password_form($email, $token)
    {
        ob_start(); ?>
        <form class="lp-list-builder-auth__form" data-step="reset-new-password">
            <input type="hidden" name="email" value="<?php echo esc_attr($email); ?>" />
            <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>" />

            <div class="Slider__InputRow">
                <label>
                    <?php esc_html_e( 'New password', 'leaky-paywall' ); ?>
                </label>
                <div class="TextField Slider__ResetNewField">
                    <input type="password"
                        name="password"
                        minlength="4"
                        required
                        autocomplete="new-password" />
                </div>
            </div>

            <button type="submit" class="Slider__ExpandedButton"><?php esc_html_e( 'Set new password', 'leaky-paywall' ); ?></button>
        </form>
<?php
        return (string) ob_get_clean();
    }

    public function load_scripts()
    {

        wp_enqueue_style('lp-list-builder', LEAKY_PAYWALL_URL . 'css/lp-list-builder.css', array(), LEAKY_PAYWALL_VERSION);

        wp_register_script(
            'lp-list-builder',
            LEAKY_PAYWALL_URL . 'js/lp-list-builder.js',
            [],
            LEAKY_PAYWALL_VERSION,
            true
        );

        $lb_settings = get_option('lp-listbuilder', []);
        $lp_settings = get_leaky_paywall_settings();
        $subscribe_page_url = ! empty( $lp_settings['page_for_subscription'] ) ? get_permalink( $lp_settings['page_for_subscription'] ) : '';

        wp_add_inline_script('lp-list-builder', 'var LP_LIST_BUILDER = ' . wp_json_encode([
            'flowUrl'   => esc_url_raw(rest_url('lp-list-builder/v1/flow')),
            'signupUrl' => esc_url_raw(rest_url('lp-list-builder/v1/signup')),
            'loginUrl'  => esc_url_raw(rest_url('lp-list-builder/v1/login')),
            'pwResetRequestUrl' => esc_url_raw(rest_url('lp-list-builder/v1/password-reset/request')),
            'pwResetVerifyUrl'  => esc_url_raw(rest_url('lp-list-builder/v1/password-reset/verify')),
            'pwResetConfirmUrl' => esc_url_raw(rest_url('lp-list-builder/v1/password-reset/confirm')),
            'nonce'            => wp_create_nonce('wp_rest'),
            'subscribeUrl'     => esc_url_raw( $subscribe_page_url ),
            'upgradeEnabled'   => ( ! empty( $lb_settings['upgrade_enabled'] ) && 'on' === $lb_settings['upgrade_enabled'] ),
        ]) . ';', 'before');

        wp_enqueue_script('lp-list-builder');
    }


    public function register_routes()
    {

        // handle beginning of the journey
        register_rest_route('lp-list-builder/v1', '/flow', [
            'methods'   =>  'POST',
            'callback'  =>  [$this, 'handle_flow'],
            'permission_callback'   =>  '__return_true',
            'args'  => [
                'email' =>  [
                    'type'  => 'string',
                    'required'  => true,
                ]
            ]
        ]);

        // handle new signups
        register_rest_route('lp-list-builder/v1', '/signup', [
            'methods'   =>  'POST',
            'callback'  =>  [$this, 'handle_signup'],
            'permission_callback'   => '__return_true',
            'args'  => [
                'email' => [
                    'type'  => 'string',
                    'required'  => true,
                ],
                'password' => [
                    'type'  => 'string',
                    'required'  => true,
                ],
                'current_url'   => [
                    'type'  => 'string',
                    'required'  => true
                ]
            ]
        ]);

        // handle login
        register_rest_route('lp-list-builder/v1', '/login', [
            'methods'   =>  'POST',
            'callback'  => [$this, 'handle_login'],
            'permission_callback'   => '__return_true',
            'args'  =>  [
                'email' => [
                    'type'  => 'string',
                    'required'  => true,
                ],
                'password' => [
                    'type'  => 'string',
                    'required'  => true,
                ],
                'current_url'   => [
                    'type'  => 'string',
                    'required'  => true,
                ]
            ]
        ]);

        register_rest_route('lp-list-builder/v1', '/password-reset/request', [
            'methods'   => 'POST',
            'callback'  => [$this, 'handle_password_reset_request'],
            'permission_callback'   => '__return_true',
            'args'  => [
                'email' => ['required' => true]
            ]
        ]);

        register_rest_route('lp-list-builder/v1', '/password-reset/verify', [
            'methods'   => 'POST',
            'callback'  => [$this, 'handle_password_reset_verify'],
            'permission_callback'   => '__return_true',
            'args'  => [
                'email' => ['required'  => true],
                'code'  => ['required'  => true]
            ]
        ]);

        register_rest_route('lp-list-builder/v1', '/password-reset/confirm', [
            'methods'   => 'POST',
            'callback'  => [$this, 'handle_password_reset_confirm'],
            'permission_callback'   => '__return_true',
            'args'  => [
                'email' => ['required' => true],
                'token' => ['required' => true],
                'password'  => ['required' => true]
            ]
        ]);
    }

    private function reset_state_key($user_id)
    {
        return 'lplb_pwreset_state_' . $user_id;
    }

    private function reset_token_key($user_id)
    {
        return 'lplb_pwreset_token_' . $user_id;
    }

    private function generate_6_digit_code()
    {
        return str_pad(wp_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function includes()
    {
        require_once LEAKY_PAYWALL_PATH . 'include/list-builder/settings.php';
    }
}

$lp_list_builder = new LP_List_Builder();
$lp_list_builder->run();
