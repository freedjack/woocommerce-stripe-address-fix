<?php
/**
 * Plugin Name: WooCommerce Stripe Address Fix
 * Description: Custom modifications for WooCommerce Stripe Gateway
 * Version: 1.0.0
 * Author: Jack F.
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Custom_Stripe_Fixes {
    
    public function __construct() {
        // Debug: Log when plugin is loaded
        error_log( 'Custom_Stripe_Fixes: Plugin loaded' );
        
        // Hook into plugins_loaded to ensure dependencies are available
        add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
        
        // Also try hooking into init as backup
        add_action( 'init', array( $this, 'init' ), 5 );
    }
    
    public function init() {
        // Check if WooCommerce and Stripe are active
        if ( ! class_exists( 'WooCommerce' ) ) {
            error_log( 'Custom_Stripe_Fixes: WooCommerce not active' );
            return;
        }
        
        if ( ! class_exists( 'WC_Stripe' ) ) {
            error_log( 'Custom_Stripe_Fixes: Stripe not active' );
            return;
        }
        
        error_log( 'Custom_Stripe_Fixes: Initializing filters' );
        
        // Hook into Stripe customer creation
        add_filter( 'wc_stripe_create_customer_args', array( $this, 'fix_address_fields' ), 10, 1 );
        
        // Debug logging
        add_filter( 'wc_stripe_create_customer_args', array( $this, 'debug_address_data' ), 5, 1 );
        
        // Test filter to see if it's being called
        add_filter( 'wc_stripe_create_customer_args', array( $this, 'test_filter_called' ), 1, 1 );
        
        // Emergency fallback
        add_filter( 'wc_stripe_create_customer_args', array( $this, 'emergency_fallback' ), 20, 1 );
        
        // Also try hooking into checkout process
        add_action( 'woocommerce_checkout_process', array( $this, 'debug_checkout_process' ) );
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'debug_order_processed' ), 10, 3 );
        
        error_log( 'Custom_Stripe_Fixes: Filters added successfully' );
    }
    
    /**
     * Fix missing address fields from POST data
     */
    public function fix_address_fields( $args ) {
        error_log( 'Custom_Stripe_Fixes: fix_address_fields called' );
        
        // Address field mapping from WooCommerce to Stripe
        $address_field_mapping = [
            'billing_address_1' => 'line1',
            'billing_address_2' => 'line2',
            'billing_city'      => 'city',
            'billing_country'   => 'country',
            'billing_postcode'  => 'postal_code',
            'billing_state'     => 'state',
        ];
        
        // Ensure address array exists
        if ( ! isset( $args['address'] ) ) {
            $args['address'] = [];
        }
        
        // Map missing fields from POST data
        foreach ( $address_field_mapping as $wc_field => $stripe_field ) {
            // Check if Stripe field is missing or empty
            if ( empty( $args['address'][ $stripe_field ] ) ) {
                // Check if POST data exists for this field
                
                if ( ! empty( $_POST[ $wc_field ] ) ) {
                    $args['address'][ $stripe_field ] = sanitize_text_field( $_POST[ $wc_field ] );
                    
                    // Log the fix for debugging
                    error_log( "Stripe Address Fix: Mapped {$wc_field} -> {$stripe_field}: " . $args['address'][ $stripe_field ] );
                }
            }
        }
        
        // Additional safety check for required line1 field
        if ( empty( $args['address']['line1'] ) ) {
            // Try alternative sources
            if ( ! empty( $_POST['billing_address_1'] ) ) {
                $args['address']['line1'] = sanitize_text_field( $_POST['billing_address_1'] );
            } elseif ( ! empty( $_POST['shipping_address_1'] ) ) {
                $args['address']['line1'] = sanitize_text_field( $_POST['shipping_address_1'] );
            } else {
                // Last resort fallback
                $args['address']['line1'] = 'Address Required';
                error_log( 'Stripe Address Warning: Using fallback address for line1' );
            }
        }
        
        return $args;
    }
    
    /**
     * Test: Check if filter is being called
     */
    public function test_filter_called( $args ) {
        error_log( 'Custom_Stripe_Fixes: Filter wc_stripe_create_customer_args called!' );
        error_log( 'Custom_Stripe_Fixes: Args received: ' . print_r( $args, true ) );
        return $args;
    }
    
    /**
     * Debug: Checkout process
     */
    public function debug_checkout_process() {
        error_log( 'Custom_Stripe_Fixes: Checkout process started' );
        error_log( 'Custom_Stripe_Fixes: POST data: ' . print_r( $_POST, true ) );
    }
    
    /**
     * Debug: Order processed
     */
    public function debug_order_processed( $order_id, $posted_data, $order ) {
        error_log( 'Custom_Stripe_Fixes: Order processed - ID: ' . $order_id );
        error_log( 'Custom_Stripe_Fixes: Payment method: ' . $order->get_payment_method() );
        
        if ( $order->get_payment_method() === 'stripe' ) {
            error_log( 'Custom_Stripe_Fixes: Stripe order detected!' );
        }
    }
    
    /**
     * Debug address data
     */
    public function debug_address_data( $args ) {
        if ( isset( $args['address'] ) ) {
            error_log( '=== Stripe Address Debug ===' );
            error_log( 'Address data: ' . print_r( $args['address'], true ) );
            
            // Check POST data
            $post_fields = [
                'billing_address_1',
                'billing_address_2',
                'billing_city',
                'billing_country',
                'billing_postcode',
                'billing_state'
            ];
            
            foreach ( $post_fields as $field ) {
                $value = isset( $_POST[ $field ] ) ? $_POST[ $field ] : 'NOT SET';
                error_log( "POST {$field}: {$value}" );
            }
        }
        
        return $args;
    }
    
    /**
     * Emergency fallback for critical address fields
     */
    public function emergency_fallback( $args ) {
        
        // Critical fallback for line1 (most common issue)
        if ( isset( $args['address'] ) && empty( $args['address']['line1'] ) ) {
            $fallback_sources = [
                'billing_address_1',
                'shipping_address_1',
                'billing_city',
                'billing_postcode'
            ];
            
            foreach ( $fallback_sources as $source ) {
                if ( ! empty( $_POST[ $source ] ) ) {
                    $args['address']['line1'] = sanitize_text_field( $_POST[ $source ] );
                    error_log( "Emergency Address Fix: Used {$source} as line1: " . $args['address']['line1'] );
                    break;
                }
            }
            
            // Ultimate fallback
            if ( empty( $args['address']['line1'] ) ) {
                $args['address']['line1'] = 'Address Required';
                error_log( 'Emergency Address Fix: Using default line1' );
            }
        }
        
        return $args;
    }
}

// Initialize the plugin
new Custom_Stripe_Fixes();

// Debug: Check if plugin file is being loaded
error_log( 'Custom_Stripe_Fixes: Plugin file loaded at ' . __FILE__ );
