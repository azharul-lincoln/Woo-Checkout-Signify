<?php
/*
 * Plugin Name:       Woo Checkout Signify
 * Plugin URI:        https://github.com/azharul-lincoln/woo-checkout-signify
 * Description:       Add signature functionality to WooCommerce checkout.
 * Version:           1.0.0
 * Author:            Azharul Lincoln
 * Author URI:        https://github.com/azharul-lincoln
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woo-checkout-signify
 */

class Woo_Checkout_Signify
{
    /**
     * The unique instance of the plugin.
     * @Woo_Checkout_Signify
     */
    private static $instance;

    /**
     * Gets an instance of our plugin.
     *
     * @return Woo_Checkout_Signify
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct()
    {
        // Actions
        add_action('woocommerce_checkout_after_terms_and_conditions', array($this, 'add_signature_field_to_checkout'));
        add_action('woocommerce_checkout_process', array($this, 'validate_signature_field'));
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_signature_to_order_meta'));
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_signature_on_order'));
        add_filter('woocommerce_settings_tabs_array', array($this, 'add_settings_tab'), 50);
        add_action('woocommerce_settings_tabs_woocss_settings_tab', array($this, 'settings_tab'));
        add_action('woocommerce_update_options_woocss_settings_tab', array($this, 'update_settings'));

        // Load text domain
        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));
    }

    /**
     * Load plugin text domain for translation.
     */
    public function load_plugin_textdomain()
    {
        load_plugin_textdomain('woo-checkout-signify', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    /**
     * Adds signature field to WooCommerce checkout.
     */
    public function add_signature_field_to_checkout()
    {
?>
        <style>
            #signature_field {
                border: 1px solid #ddd;
                padding: 10px;
                margin-bottom: 20px;
                background: #fff;
                display: flex;
                flex-direction: column;
                justify-items: center;
                align-items: center;
                padding-bottom: 30px;
                border-radius: 5px;
            }

            #signature_canvas_container {
                width: 80%;
                /* Set the width of the container */
                max-width: 600px;
                /* Set a maximum width if needed */
                margin: 0 auto;
                /* Center the container */
            }

            #signature_canvas {
                border: 2px dashed #bbb;
                width: 100%;
                border-radius: 5px;
                /* Set the canvas width to 100% of its container */
                height: auto;
                /* Allow the height to adjust according to the aspect ratio */

            }

            #clear_signature {
                margin-top: 20px;
                display: block;
                cursor: pointer;
                padding: 15px 30px;
            }

            .signature-error {
                color: red;
            }
        </style>
        <div id="signature_field">
            <h3><?php echo esc_html(get_option('woocss_sign_below_text', __('Please sign below:', 'woo-checkout-signify'))); ?></h3>
            <div id="signature_canvas_container">
                <canvas id="signature_canvas"></canvas>
            </div>
            <button id="clear_signature"><?php echo esc_html(get_option('woocss_clear_text', __('Clear Signature', 'woo-checkout-signify'))); ?></button>
            <input type="hidden" name="signature" id="signature" <?php echo get_option('woocss_signature_required', 'no') === 'yes' ? 'required' : ''; ?>>
            <div class="signature-error" style="display: none;"><?php echo esc_html(get_option('woocss_error_message', __('Please sign before proceeding.', 'woo-checkout-signify'))); ?></div>
        </div>

        <script>
            jQuery(function($) {
                var canvas = document.getElementById("signature_canvas");
                var context = canvas.getContext("2d");
                var signatureInput = document.getElementById("signature");
                var signatureError = document.querySelector(".signature-error");
                var canvasContainer = document.getElementById("signature_canvas_container");

                var isDrawing = false;
                var lastX = 0;
                var lastY = 0;

                // Set canvas width and height based on the container size
                function setCanvasSize() {
                    canvas.width = canvasContainer.offsetWidth; // Set canvas width to container width
                    canvas.height = canvasContainer.offsetWidth * 0.5; // Set canvas height to half of container width (adjust as needed)
                }

                // Call setCanvasSize() initially and on window resize
                setCanvasSize();
                window.addEventListener('resize', setCanvasSize);

                canvas.addEventListener("mousedown", function(event) {
                    isDrawing = true;
                    [lastX, lastY] = [event.offsetX, event.offsetY];
                });

                canvas.addEventListener("mousemove", function(event) {
                    if (isDrawing) {
                        context.beginPath();
                        context.moveTo(lastX, lastY);
                        context.lineTo(event.offsetX, event.offsetY);
                        context.stroke();
                        [lastX, lastY] = [event.offsetX, event.offsetY];
                    }
                });

                canvas.addEventListener("mouseup", function() {
                    isDrawing = false;
                    signatureInput.value = canvas.toDataURL();
                    signatureError.style.display = "none";
                });

                canvas.addEventListener("mouseout", function() {
                    isDrawing = false;
                });

                // Clear signature
                $("#clear_signature").click(function(event) {
                    event.preventDefault(); // Prevent default behavior
                    context.clearRect(0, 0, canvas.width, canvas.height);
                    signatureInput.value = "";
                    signatureError.style.display = "none";
                });

                // Validate signature
                $("form.checkout").submit(function(event) {
                    if (!signatureInput.value && $("#signature").prop("required")) {
                        signatureError.style.display = "block";
                        event.preventDefault();
                    }
                });

                // Reflect admin settings for signature requirement
                var isSignatureRequired = '<?php echo get_option('woocss_signature_required', 'no'); ?>';
                if (isSignatureRequired === 'yes') {
                    $('#signature').prop('required', true);
                } else {
                    $('#signature').prop('required', false);
                }
            });
        </script>
<?php
    }

    /**
     * Validate signature field.
     */
    public function validate_signature_field()
    {
        $isSignatureRequired = get_option('woocss_signature_required', 'no');
        if ($isSignatureRequired === 'yes' && empty($_POST['signature'])) {
            wc_add_notice(get_option('woocss_error_message', __('Please sign before proceeding.', 'woo-checkout-signify')), 'error');
        }
    }

    /**
     * Save signature data to order meta.
     */
    public function save_signature_to_order_meta($order_id)
    {
        if ($_POST['signature']) {
            update_post_meta($order_id, 'signature', sanitize_text_field($_POST['signature']));
        }
    }

    /**
     * Display signature on order details page.
     */
    public function display_signature_on_order($order)
    {
        $signature = get_post_meta($order->get_id(), 'signature', true);
        if ($signature) {
            echo '<h4>' . __('Signature', 'woo-checkout-signify') . '</h4>';
            echo '<img style="max-width:100%; width: 300px;" src="' . $signature . '" />';
        }
    }

    /**
     * Add a new tab to the WooCommerce settings.
     */
    public function add_settings_tab($tabs)
    {
        $tabs['woocss_settings_tab'] = __('Signature Pad', 'woo-checkout-signify');
        return $tabs;
    }

    /**
     * Display the settings content for the new tab.
     */
    /**
     * Display the settings content for the new tab.
     */
    public function settings_tab()
    {
        woocommerce_admin_fields(array(
            array(
                'name' => __('Signature Field Settings', 'woo-checkout-signify'),
                'type' => 'title',
                'desc' => '',
                'id'   => 'woocss_settings_tab_title'
            ),
            array(
                'name'    => __('Require Signature Field', 'woo-checkout-signify'),
                'desc'    => __('Require customers to sign before proceeding.', 'woo-checkout-signify'),
                'id'      => 'woocss_signature_required',
                'type'    => 'checkbox',
                'default' => 'no',
            ),
            array(
                'name'    => __('Text for "Please sign below:"', 'woo-checkout-signify'),
                'desc'    => __('Customize the text for "Please sign below:"', 'woo-checkout-signify'),
                'id'      => 'woocss_sign_below_text',
                'type'    => 'text',
                'default' => __('Please sign below:', 'woo-checkout-signify'),
            ),
            array(
                'name'    => __('Text for "Clear Signature"', 'woo-checkout-signify'),
                'desc'    => __('Customize the text for "Clear Signature"', 'woo-checkout-signify'),
                'id'      => 'woocss_clear_text',
                'type'    => 'text',
                'default' => __('Clear Signature', 'woo-checkout-signify'),
            ),
            array(
                'name'    => __('Error Message', 'woo-checkout-signify'),
                'desc'    => __('Error message displayed when signature field is empty.', 'woo-checkout-signify'),
                'id'      => 'woocss_error_message',
                'type'    => 'text',
                'default' => __('Please sign before proceeding.', 'woo-checkout-signify'),
            ),
            array('type' => 'sectionend', 'id' => 'woocss_settings_tab_sectionend'),
        ));
    }

    /**
     * Update the settings when the form is saved.
     */
    public function update_settings()
    {
        update_option('woocss_signature_required', isset($_POST['woocss_signature_required']) ? 'yes' : 'no');
        update_option('woocss_sign_below_text', sanitize_text_field($_POST['woocss_sign_below_text']));
        update_option('woocss_clear_text', sanitize_text_field($_POST['woocss_clear_text']));
        update_option('woocss_error_message', sanitize_text_field($_POST['woocss_error_message']));
    }
}

Woo_Checkout_Signify::get_instance();
