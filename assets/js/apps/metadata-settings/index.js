/**
 * Metadata Settings Application Entry Point
 *
 * This module serves as the main entry point for the metadata settings application.
 * It handles the initialization and mounting of the React application to the DOM.
 *
 * @module metadata-settings/index
 */

import { render } from '@wordpress/element';
import MetadataApp from './App';

/**
 * Initializes and mounts the metadata settings application
 *
 * This function:
 * 1. Waits for the DOM to be fully loaded
 * 2. Locates the container element for the application
 * 3. Mounts the MetadataApp component to the container
 * 4. Handles any initialization errors gracefully
 *
 * @function initializeMetadataApp
 * @throws {Error} If the container element is not found
 * @throws {Error} If there's an error during React rendering
 */
const initializeMetadataApp = () => {
	// Find the container element where the app will be mounted
	const container = document.getElementById(
		'wordpress-document-repository-metadata-app'
	);

	if ( ! container ) {
		return;
	}

	try {
		// Mount the MetadataApp component to the container
		render( <MetadataApp />, container );
	} catch ( error ) {
		// Handle any errors during initialization
		container.innerHTML =
			'<div class="notice notice-error"><p>Error initializing metadata settings. Please check the console for details.</p></div>';
	}
};

// Initialize the application when the DOM is ready
document.addEventListener( 'DOMContentLoaded', initializeMetadataApp );
