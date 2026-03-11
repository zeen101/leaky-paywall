<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Leaky_Paywall_List_Builder_Settings
{

    public function __construct()
    {
        add_filter('leaky_paywall_settings_tab_sections', array($this, 'add_setting_section'));
        add_action('leaky_paywall_output_settings_fields', array($this, 'display_settings_fields'), 10, 2);
        add_action('leaky_paywall_update_settings', array($this, 'save_settings_fields'), 20, 3);
    }

    /**
     * Add Leaky Paywall - List Builder section to subscription settings
     *
     * @since 1.0.0
     */
    public function add_setting_section($sections)
    {
        $sections['general'][] = 'list_builder';
        return $sections;
    }

    public function display_settings_fields($current_tab, $current_section)
    {

        if ($current_tab != 'general') {
            return;
        }

        if ($current_section != 'list_builder') {
            return;
        }

        $settings = $this->get_settings();

?>
        <h3 class="hndle"><span>List Builder</span></h3>

        <table id="lp_listbuilder_settings" class="form-table">
            <tr>
                <th rowspan="1"> <?php _e('Enable', 'leaky-paywall'); ?></th>
                <td>
                    <input type="checkbox" id="lp_listbuilder_enabled" name="lp_listbuilder_enabled" value="on" <?php checked($settings['enabled'], 'on'); ?> />
                    <label for="lp_listbuilder_enabled"><?php _e('Enable the List Builder modal on restricted content', 'leaky-paywall'); ?></label>
                </td>
            </tr>

            <tr>
                <th rowspan="1"> <?php _e('Subscription Level', 'leaky-paywall'); ?></th>
                <td>
                    <?php $lp_settings = get_leaky_paywall_settings(); ?>
                    <select id="lp_listbuilder_level_id" name="lp_listbuilder_level_id">
                        <?php foreach ($lp_settings['levels'] as $key => $level) : ?>
                            <?php if (empty($level['label']) || (!empty($level['deleted']) && $level['deleted'] == 1)) continue; ?>
                            <?php if (!empty($level['price']) && floatval($level['price']) > 0) continue; ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($settings['level_id'], $key); ?>><?php echo esc_html(stripslashes($level['label'])); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('The subscription level assigned to new users who register through the List Builder. Must be a free level.', 'leaky-paywall'); ?></p>
                </td>
            </tr>

            <tr>
                <th rowspan="1"> <?php _e('Heading', 'leaky-paywall'); ?></th>
                <td>
                    <input type="text" id="heading" class="large-text" name="heading" value="<?php echo esc_attr($settings['heading']); ?>" />
                </td>
            </tr>

            <tr>
                <th rowspan="1"> <?php _e('Subheading', 'leaky-paywall'); ?></th>
                <td>
                    <input type="text" id="subheading" class="large-text" name="subheading" value="<?php echo esc_attr($settings['subheading']); ?>" />
                </td>
            </tr>

            <tr>
                <th rowspan="1"> <?php _e('Terms and Conditions', 'leaky-paywall'); ?></th>
                <td>
                    <input type="text" id="terms_and_conditions" class="large-text" name="terms_and_conditions" value="<?php echo esc_attr($settings['terms_and_conditions']); ?>" />
                </td>
            </tr>

            <tr>
                <th rowspan="1"> <?php _e('Background Color', 'leaky-paywall'); ?></th>
                <td>
                    <input type="color" id="background_color" name="background_color" value="<?php echo esc_attr($settings['background_color']); ?>" />
                </td>
            </tr>

            <tr>
                <th rowspan="1"> <?php _e('Text Color', 'leaky-paywall'); ?></th>
                <td>
                    <input type="color" id="text_color" name="text_color" value="<?php echo esc_attr($settings['text_color']); ?>" />
                </td>
            </tr>

            <tr>
                <th rowspan="1"> <?php _e('Button Color', 'leaky-paywall'); ?></th>
                <td>
                    <input type="color" id="button_color" name="button_color" value="<?php echo esc_attr($settings['button_color']); ?>" />
                </td>
            </tr>

            <tr>
                <th rowspan="1"> <?php _e('Button Text Color', 'leaky-paywall'); ?></th>
                <td>
                    <input type="color" id="button_text_color" name="button_text_color" value="<?php echo esc_attr($settings['button_text_color']); ?>" />
                </td>
            </tr>


        </table>

<?php
    }

    public function save_settings_fields($settings, $current_tab, $current_section)
    {

        if ($current_tab != 'general') {
            return;
        }

        if ($current_section != 'list_builder') {
            return;
        }

        $settings = $this->get_settings();

        $settings['enabled'] = isset($_POST['lp_listbuilder_enabled']) ? 'on' : '';

        if ( 'on' === $settings['enabled'] ) {
            $lp_settings = get_leaky_paywall_settings();
            if ( empty( $lp_settings['enable_js_cookie_restrictions'] ) || 'on' !== $lp_settings['enable_js_cookie_restrictions'] ) {
                $lp_settings['enable_js_cookie_restrictions'] = 'on';
                update_leaky_paywall_settings( $lp_settings );
            }
        }

        if (isset($_POST['lp_listbuilder_level_id'])) {
            $settings['level_id'] = absint($_POST['lp_listbuilder_level_id']);
        }

        if (isset($_POST['heading'])) {
            $settings['heading'] = sanitize_text_field(wp_unslash($_POST['heading']));
        }

        if (isset($_POST['subheading'])) {
            $settings['subheading'] = sanitize_text_field(wp_unslash($_POST['subheading']));
        }

        if (isset($_POST['terms_and_conditions'])) {
            $settings['terms_and_conditions'] = sanitize_text_field(wp_unslash($_POST['terms_and_conditions']));
        }

        if (isset($_POST['background_color'])) {
            $settings['background_color'] = sanitize_hex_color($_POST['background_color']);
        }

        if (isset($_POST['text_color'])) {
            $settings['text_color'] = sanitize_hex_color($_POST['text_color']);
        }

        if (isset($_POST['button_color'])) {
            $settings['button_color'] = sanitize_hex_color($_POST['button_color']);
        }

        if (isset($_POST['button_text_color'])) {
            $settings['button_text_color'] = sanitize_hex_color($_POST['button_text_color']);
        }

        $this->update_settings($settings);
    }

    public function get_settings()
    {

        $defaults = array(
            'enabled'                    => '',
            'level_id'                   => '0',
            'heading'                    => 'Create a free account, or log in.',
            'subheading'                 => 'Gain access to read this content, plus limited free content.',
            'terms_and_conditions'       => 'Yes! I would like to receive new content and updates.',
            'background_color'           => '#000000',
            'text_color'                 => '#ffffff',
            'button_color'               => '#E45637',
            'button_text_color'          => '#ffffff',
        );

        $settings = get_option('lp-listbuilder');
        $settings = wp_parse_args($settings, $defaults);

        return $settings;
    }

    public function update_settings($settings)
    {
        update_option('lp-listbuilder', $settings);
    }
}

new Leaky_Paywall_List_Builder_Settings();
