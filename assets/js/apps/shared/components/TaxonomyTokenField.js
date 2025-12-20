import { useMemo } from '@wordpress/element';
import { FormTokenField } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Normalize option format to consistent structure
 * @param {string|Object} option - Option in various formats
 * @return {Object} Normalized option with label and value
 */
const normalizeOption = ( option ) => {
	if ( typeof option === 'string' ) {
		return { label: option, value: option };
	}
	return {
		label: option.label || option.name || '',
		value: option.value || option.id || option.label || option.name || '',
	};
};

/**
 * TaxonomyTokenField Component
 *
 * A WordPress Gutenberg FormTokenField-based multi-select component for taxonomy fields.
 * Uses __experimentalExpandOnFocus to expand the suggestions dropdown on focus.
 *
 * @param {Object}   props             - Component props
 * @param {Array}    props.value       - Array of selected values (can be IDs or labels)
 * @param {Array}    props.options     - Array of options (can be strings or objects with label/value)
 * @param {Function} props.onChange    - Callback when selection changes, receives array of values
 * @param {string}   props.id          - ID for the field
 * @param {string}   props.label       - Label for the field
 * @param {string}   props.placeholder - Placeholder text
 * @return {JSX.Element} Rendered FormTokenField component
 */
const TaxonomyTokenField = ( {
	value = [],
	options = [],
	onChange,
	id,
	label,
	placeholder,
} ) => {
	// Normalize options once using useMemo for performance
	const normalizedOptions = useMemo(
		() => options.map( normalizeOption ),
		[ options ]
	);

	// Create suggestions array and lookup maps in a single pass for efficiency
	const { suggestions, labelToValueMap, valueToLabelMap } = useMemo( () => {
		const suggestionsArray = [];
		const labelToValue = new Map();
		const valueToLabel = new Map();

		normalizedOptions.forEach( ( opt ) => {
			const optLabel = opt.label;
			const optValue = opt.value;
			const valueStr = String( optValue );

			suggestionsArray.push( optLabel );
			labelToValue.set( optLabel, optValue );
			valueToLabel.set( valueStr, optLabel );
		} );

		return {
			suggestions: suggestionsArray,
			labelToValueMap: labelToValue,
			valueToLabelMap: valueToLabel,
		};
	}, [ normalizedOptions ] );

	// Convert value (which may be IDs) to display labels (tokens)
	const tokenValues = useMemo( () => {
		if ( ! Array.isArray( value ) ) {
			if ( ! value ) {
				return [];
			}
			// Single value - try to find label
			const valueStr = String( value );
			return [ valueToLabelMap.get( valueStr ) || valueStr ];
		}

		// Array of values - convert IDs to labels
		return value
			.map( ( val ) => {
				const valStr = String( val );
				// Return label if found in map, otherwise return value as-is
				return valueToLabelMap.get( valStr ) || valStr;
			} )
			.filter( Boolean ); // Remove any undefined/null values
	}, [ value, valueToLabelMap ] );

	// Handle token change - convert labels back to values
	const handleChange = ( tokens ) => {
		// Convert token labels back to values (IDs if available)
		const values = tokens.map( ( token ) => {
			const tokenValue = labelToValueMap.get( token );
			// If no value found, the token might be a new value or already a value
			return tokenValue !== undefined ? tokenValue : token;
		} );

		onChange( values );
	};

	// Memoize placeholder to avoid recreating on every render
	const fieldPlaceholder = useMemo(
		() =>
			placeholder ||
			__( 'Type to search or select…', 'bcgov-design-system' ),
		[ placeholder ]
	);

	return (
		<FormTokenField
			id={ id }
			value={ tokenValues }
			suggestions={ suggestions }
			onChange={ handleChange }
			placeholder={ fieldPlaceholder }
			__experimentalExpandOnFocus={ true }
			label={ label }
		/>
	);
};

export default TaxonomyTokenField;
