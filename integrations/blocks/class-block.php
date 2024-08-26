<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WC_Clickuz_Gateway_Blocks extends AbstractPaymentMethodType
{
    private $gateway;
    protected $name = 'clickuz';// your payment gateway name

    public function initialize()
    {
        $this->settings = get_option('woocommerce_clickuz_settings', []);
        $this->gateway = new WC_Gateway_Clickuz();
    }

    public function is_active()
    {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles()
    {
        wp_register_script(
            'clickuz-blocks-integration',
            plugin_dir_url(__FILE__) . 'checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );
        if (function_exists('wp_set_script_translations')) {
            $settings = array(
                'title' => $this->gateway->get_option('click_button_title')
            );
            wp_localize_script('clickuz-blocks-integration', 'clickuz_settings', $settings);

        }
        return ['clickuz-blocks-integration'];
    }

    public function get_payment_method_data()
    {
        return [
            'title' => $this->gateway->title,
            //'description' => $this->gateway->description,
        ];
    }
}