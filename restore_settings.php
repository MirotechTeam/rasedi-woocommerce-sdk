<?php
require_once '/var/www/html/wp-load.php';

$settings = array(
    'enabled' => 'yes',
    'title' => 'Rasedi Payment',
    'description' => 'Pay securely using Rasedi.',
    'testmode' => 'no',
    'key_id' => 'live_lais4GLfbqmY7hTyRsSs_aEMJ-oMnQk2BtyvCtcprZDhBMh6zTttXUROaTH9ajXnL0r3hIESJ1nRTxUO12jeL-Ay',
    'private_key' => "-----BEGIN PRIVATE KEY-----\nMC4CAQAwBQYDK2VwBCIEIIw8bEIM1U1FpNWRJETIzfN7DD9o0oswJEbbekYTDimk\n-----END PRIVATE KEY-----",
    'secret_key' => 'live_lais4GLfbqmY7hTyRsSs_aEMJ-oMnQk2BtyvCtcprZDhBMh6zTttXUROaTH9ajXnL0r3hIESJ1nRTxUO12jeL-Ay'
);

update_option('woocommerce_rasedi_settings', $settings);
echo "Settings updated successfully.\n";
