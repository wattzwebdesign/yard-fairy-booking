<?php
// Check if debug is enabled
error_log('YFB DEBUG TEST - If you see this, logging is working!');

// Check WordPress config
$wp_config = '/Users/donaldmcguinn/Documents/GitHub/yard-fairy-booking/../../wp-config.php';
if (file_exists($wp_config)) {
    $content = file_get_contents($wp_config);
    if (strpos($content, "define( 'WP_DEBUG_LOG', true )") !== false) {
        echo "WP_DEBUG_LOG is enabled\n";
    } else {
        echo "WP_DEBUG_LOG is NOT enabled - you need to enable it in wp-config.php\n";
        echo "Add these lines to wp-config.php:\n";
        echo "define( 'WP_DEBUG', true );\n";
        echo "define( 'WP_DEBUG_LOG', true );\n";
        echo "define( 'WP_DEBUG_DISPLAY', false );\n";
    }
    
    // Check for debug.log
    $debug_log = dirname($wp_config) . '/wp-content/debug.log';
    if (file_exists($debug_log)) {
        echo "\nDebug log location: $debug_log\n";
        echo "Last 20 lines:\n";
        echo "================\n";
        echo shell_exec("tail -20 '$debug_log'");
    } else {
        echo "\nDebug log does not exist yet at: $debug_log\n";
    }
} else {
    echo "wp-config.php not found\n";
}
