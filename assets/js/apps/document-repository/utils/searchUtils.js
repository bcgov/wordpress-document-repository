/**
 * Search Utilities
 *
 * Provides functions for searching and highlighting text in documents.
 * These utilities are used for client-side search functionality.
 *
 * @module searchUtils
 */

import { Fragment } from '@wordpress/element';

/**
 * Highlights all occurrences of a search term in text
 *
 * @param {string} text       - The text to highlight
 * @param {string} searchTerm - The search term to highlight
 * @return {JSX.Element|string} Text with highlighted matches wrapped in <mark> tags
 */
export const highlightSearchTerm = ( text, searchTerm ) => {
	if ( ! searchTerm || ! text ) {
		return text || '';
	}

	const searchTrimmed = searchTerm.trim();
	const searchLower = searchTrimmed.toLowerCase();
	const textStr = String( text );
	const textLower = textStr.toLowerCase();

	// If no match, return original text
	if ( ! textLower.includes( searchLower ) ) {
		return textStr;
	}

	// Find all matches and create highlighted segments
	const parts = [];
	let lastIndex = 0;
	let index = textLower.indexOf( searchLower, lastIndex );

	while ( index !== -1 ) {
		// Add text before match
		if ( index > lastIndex ) {
			parts.push( textStr.substring( lastIndex, index ) );
		}

		// Add highlighted match (preserve original case, use trimmed length)
		parts.push(
			<mark key={ index } className="search-highlight">
				{ textStr.substring( index, index + searchTrimmed.length ) }
			</mark>
		);

		lastIndex = index + searchTrimmed.length;
		index = textLower.indexOf( searchLower, lastIndex );
	}

	// Add remaining text
	if ( lastIndex < textStr.length ) {
		parts.push( textStr.substring( lastIndex ) );
	}

	// Ensure we have at least one part
	if ( parts.length === 0 ) {
		return textStr;
	}

	// If we only have one part and it's a string (no highlights), return it directly
	if ( parts.length === 1 && typeof parts[ 0 ] === 'string' ) {
		return parts[ 0 ];
	}

	return <Fragment>{ parts }</Fragment>;
};
