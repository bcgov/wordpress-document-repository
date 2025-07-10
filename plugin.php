<?php
/**
 * Plugin Name: {Plugin Name}
 * Plugin URI: https://github.com/bcgov/{plugin-name}
 * Author: govwordpress@gov.bc.ca
 * Author URI: {JIRA Epic URL}
 * Description: WordPress {plugin-name} plugin is a plugin that adds custom functionality to your WordPress site.
 * Requires at least: 6.4.4
 * Tested up to: 6.5
 * Requires PHP: 7.4
 * Version: 1.0.0
 * License: Apache License Version 2.0
 * License URI: LICENSE
 * Text Domain: {plugin-name}
 * Tags:
 *
 * @package {PluginName}
 */



/**
 * The function register_plugin_blocks registers block types from metadata in block.json files
 * found in subdirectories of the Blocks/build folder.
 */
function register_plugin_blocks() {
    // Define the path to the build directory.
    $build_dir = plugin_dir_path( __FILE__ ) . 'Blocks/build/';

    // Use glob to find all block.json files in the subdirectories of the build folder.
    $block_files = glob( $build_dir . '*/block.json' );
    // Loop through each block.json file.
    foreach ( $block_files as $block_file ) {
        // Register the block type from the metadata in block.json.
        register_block_type_from_metadata( $block_file );
    }
}
// Hook the function into the 'init' action.
add_action( 'init', 'register_plugin_blocks' );


/**  // Example.
* use Bcgov\{PluginName}\{
**     {ClassName},
** };
** //Initialize
** ${feature_name} = new {ClassName}();
** ${feature_name}->init();
*/
