# Rasedi WooCommerce Payment Gateway

Accept payments on your WooCommerce store using the Rasedi Payment Gateway.

## Installation

1.  **Download**: Clone or download this repository.
2.  **Zip**: Compress the `rasedi-woocommerce-sdk` folder into a ZIP file.
3.  **Upload**: Go to WordPress Admin > Plugins > Add New > Upload Plugin.
4.  **Activate**: Activate "Rasedi Payment Gateway".

## Configuration

1.  Go to **WooCommerce > Settings > Payments > Rasedi**.
2.  **Enable** the gateway.
3.  Enter your **Key ID** and **Private Key** provided by Rasedi.
4.  Optionally enable **Test Mode** to use the sandbox environment.

## Usage
Once configured, "Rasedi Payment" will appear as a payment option on the checkout page. Customers will be redirected to the secure Rasedi payment page to complete their transaction.

## Troubleshooting / Local Development

### "There are no payment methods available" Error
This error usually occurs because the Checkout page is using **WooCommerce Blocks**, which this gateway does not natively support. 

**Automatic Fix:**
The plugin now includes a **built-in fix** that automatically detects if the checkout page is using Blocks and forces it to render the Classic Shortcode instead. You do not need to do anything manually.

If you still see issues, ensure you are using the latest version of the plugin.

### Debugging & Logs
To see detailed error logs for this plugin:
1. Go to **WooCommerce > Status > Logs**.
2. Select `rasedi-payment-gateway-...` from the dropdown.
3. Click **View**.

### Order Stuck in "Pending Payment" (Localhost)
On `localhost`, the Rasedi server cannot send Webhooks to your machine. You must manually simulate the webhook to complete the order.

**Manual Webhook Command:**
```bash
curl -X POST -H "Content-Type: application/json" \
     -d '{"referenceCode":"<YOUR_REFERENCE_CODE>", "status":"SUCCESS"}' \
     "http://localhost:8080/?wc-api=WC_Gateway_Rasedi"
```
Replace `<YOUR_REFERENCE_CODE>` with the code from the payment success page.
