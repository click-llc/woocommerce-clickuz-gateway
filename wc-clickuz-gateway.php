<?php
/*
 * Plugin Name: Woocommerce CLICK Payment Method
 * Plugin URI: https://click.uz
 * Description: CLICK Payment Method Plugin for WooCommerce
 * Version: 1.0.4
 * Author: OOO "Click"
 * Author URI: https://click.uz

 * Text Domain: clickuz
 * Domain Path: /i18n/languages/

 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

define( 'CLICK_VERSION', '1.0.6' );

define( 'CLICK_LOGO', plugin_dir_url( __FILE__ ) . 'click-logo.png' );

define( 'CLICK_DELIMITER', '|' );

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    class WC_ClickUz {
        public $plugin;

        public function __construct() {

            $this->plugin = plugin_basename( __FILE__ );

            load_plugin_textdomain( 'clickuz', false, dirname( plugin_basename( __FILE__ ) ) . '/i18n/languages/' );

            register_activation_hook( __FILE__, array( $this, 'activate' ) );

            register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

            add_action( 'plugins_loaded', array( $this, 'init' ) );

            add_filter( "plugin_action_links_{$this->plugin}", array( $this, 'settings_link' ) );

            add_action( 'woocommerce_init', array( $this, 'wc_init' ) );

            add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
        }

        public function init() {
            if ( class_exists( 'WC_Payment_Gateway' ) ) {
                require_once 'include/class-wc-gateway-clickuz.php';
                require_once 'include/class-wc-gateway-clickuz-handlers.php';
                new WC_ClickAPI();
            }
        }

        public function wc_init() {

            if ( isset( $_GET['click-return'] ) && $_GET['click-return'] == WC()->customer->get_id() ) {
                WC()->cart->empty_cart( true );
            }
        }

        public function activate() {
            if ( ! function_exists( 'curl_exec' ) ) {
                wp_die( '<pre>This plugin requires PHP CURL library installled in order to be activated </pre>' );
            }

            if ( ! function_exists( 'openssl_verify' ) ) {
                wp_die( '<pre>This plugin requires PHP OpenSSL library installled in order to be activated </pre>' );
            }

            $this->install();

            flush_rewrite_rules();
        }

        public function deactivate() {
            flush_rewrite_rules();
        }

        public function install() {
            global $wpdb;

            $wpdb->hide_errors();

            $collate = '';

            if ( $wpdb->has_cap( 'collation' ) ) {
                if ( ! empty( $wpdb->charset ) ) {
                    $collate .= "DEFAULT CHARACTER SET $wpdb->charset";
                }
                if ( ! empty( $wpdb->collate ) ) {
                    $collate .= " COLLATE $wpdb->collate";
                }
            }

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

            dbDelta( "
                CREATE TABLE `{$wpdb->prefix}wc_click_transactions` (
                    `ID` BIGINT(20)	UNSIGNED NOT NULL AUTO_INCREMENT,
                    `click_trans_id` BIGINT(20) UNSIGNED NOT NULL,
                    `service_id` BIGINT(20) UNSIGNED NOT NULL,
                    `click_paydoc_id` BIGINT(20) UNSIGNED NOT NULL,
                    `merchant_trans_id` BIGINT(20) UNSIGNED NOT NULL,                    
                    `amount`  DECIMAL(20, 2) NOT NULL,
                    `error` BIGINT(20) UNSIGNED NOT NULL,
                    `error_note` NVARCHAR(120),
                    `status` NVARCHAR(32),
                    PRIMARY KEY (`ID`)
                ) $collate; " );
        }

        public function settings_link( $links ) {
            $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=clickuz">' . __( 'Settings' ) . '</a>';

            array_push( $links, $settings_link );

            return $links;
        }

        public function add_gateway( $methods ) {
            $methods[] = 'WC_Gateway_Clickuz';

            return $methods;
        }

    }

    new WC_ClickUz();
}