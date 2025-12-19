import { useMemo } from '@wordpress/element';
import { ThemeProvider, createTheme } from '@mui/material/styles';
import {
	Select,
	MenuItem,
	Chip,
	FormControl,
	OutlinedInput,
	Box,
} from '@mui/material';

/**
 * Z-index constant for Material-UI menus in WordPress modals
 * WordPress modals use z-index 100000, so we need to be higher
 */
const MODAL_MENU_Z_INDEX = 100001;

/**
 * Create a Material-UI theme that matches WordPress admin styles
 * Theme is created once and reused across all instances
 */
const wordpressTheme = createTheme( {
	palette: {
		mode: 'light',
		primary: {
			main: '#2271b1', // WordPress blue
		},
	},
	components: {
		MuiOutlinedInput: {
			styleOverrides: {
				root: {
					'&:hover .MuiOutlinedInput-notchedOutline': {
						borderColor: '#2271b1',
					},
				},
			},
		},
		MuiSelect: {
			styleOverrides: {
				root: {
					fontSize: '14px',
					lineHeight: '1.4',
				},
			},
		},
		MuiChip: {
			styleOverrides: {
				root: {
					height: '24px',
					fontSize: '12px',
				},
			},
		},
		MuiPopover: {
			styleOverrides: {
				root: {
					zIndex: `${ MODAL_MENU_Z_INDEX } !important`,
				},
				paper: {
					zIndex: `${ MODAL_MENU_Z_INDEX } !important`,
				},
			},
		},
		MuiMenu: {
			styleOverrides: {
				root: {
					zIndex: `${ MODAL_MENU_Z_INDEX } !important`,
				},
				paper: {
					zIndex: `${ MODAL_MENU_Z_INDEX } !important`,
				},
			},
		},
	},
} );

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
 * Normalize a value to match option values
 * Handles cases where values come from database as names but options use IDs
 * @param {string|number} val               - Value to normalize
 * @param {Array}         normalizedOptions - Array of normalized options
 * @return {string} Normalized value as string
 */
const normalizeValue = ( val, normalizedOptions ) => {
	const valStr = String( val );
	// First, try direct match with option values
	const directMatch = normalizedOptions.find(
		( opt ) => String( opt.value ) === valStr
	);
	if ( directMatch ) {
		return String( directMatch.value );
	}
	// If no direct match, try matching by label/name
	const labelMatch = normalizedOptions.find(
		( opt ) =>
			String( opt.label ) === valStr || String( opt.name ) === valStr
	);
	if ( labelMatch ) {
		return String( labelMatch.value );
	}
	// If still no match, return the original value as string
	return valStr;
};

/**
 * MultipleSelectChip Component
 *
 * A Material-UI based multi-select dropdown that displays selected items as chips.
 * Based on Material-UI's MultipleSelectChip example.
 *
 * @param {Object}   props             - Component props
 * @param {Array}    props.value       - Array of selected values
 * @param {Array}    props.options     - Array of options (can be strings or objects with label/value)
 * @param {Function} props.onChange    - Callback when selection changes
 * @param {string}   props.id          - ID for the select field
 * @param {string}   props.placeholder - Placeholder text
 * @param {boolean}  props.required    - Whether the field is required
 * @param {string}   props.className   - Additional CSS class name
 * @return {JSX.Element} Rendered multi-select component
 */
const MultipleSelectChip = ( {
	value = [],
	options = [],
	onChange,
	id,
	placeholder,
	required = false,
	className = '',
} ) => {
	// Normalize options once using useMemo for performance
	const normalizedOptions = useMemo(
		() => options.map( normalizeOption ),
		[ options ]
	);

	// Normalize and prepare selected values
	const selectedValues = useMemo( () => {
		if ( Array.isArray( value ) ) {
			return value.map( ( v ) => normalizeValue( v, normalizedOptions ) );
		}
		if ( value ) {
			return [ normalizeValue( value, normalizedOptions ) ];
		}
		return [];
	}, [ value, normalizedOptions ] );

	const handleChange = ( event ) => {
		const newValue = event.target.value;
		// Material-UI Select with multiple returns an array
		const valueArray =
			typeof newValue === 'string' ? newValue.split( ',' ) : newValue;
		// Remove duplicates by converting to Set and back to array
		const uniqueValues = Array.from(
			new Set( valueArray.map( ( v ) => String( v ) ) )
		);
		onChange( uniqueValues );
	};

	// Handle chip delete with proper event handling
	const handleChipDelete = ( e, selectedValue, selectedArray ) => {
		// Stop event propagation to prevent dropdown from opening
		e?.preventDefault();
		e?.stopPropagation();
		// Use the selected array from renderValue parameter to ensure current state
		const newSelection = selectedArray.filter(
			( v ) => String( v ) !== String( selectedValue )
		);
		onChange( newSelection );
	};

	// Prevent chip clicks from opening dropdown
	const handleChipClick = ( e ) => {
		e?.preventDefault();
		e?.stopPropagation();
	};

	return (
		<ThemeProvider theme={ wordpressTheme }>
			<Box className={ className }>
				<FormControl
					fullWidth
					required={ required }
					sx={ {
						'& .MuiOutlinedInput-root': {
							minHeight: '36px', // Match WordPress component height
						},
					} }
				>
					<Select
						id={ id }
						multiple
						value={ selectedValues }
						onChange={ handleChange }
						displayEmpty
						input={ <OutlinedInput id={ `${ id }-input` } /> }
						MenuProps={ {
							PaperProps: {
								style: {
									maxHeight: 300,
								},
							},
							anchorOrigin: {
								vertical: 'bottom',
								horizontal: 'left',
							},
							transformOrigin: {
								vertical: 'top',
								horizontal: 'left',
							},
							disablePortal: false,
							disableScrollLock: true,
							container:
								typeof document !== 'undefined'
									? document.body
									: null,
						} }
						renderValue={ ( selected ) => {
							if ( selected.length === 0 ) {
								return (
									<span
										style={ {
											color: '#999',
											fontSize: '14px',
											fontStyle: 'italic',
										} }
									>
										{ placeholder || 'Select options...' }
									</span>
								);
							}
							return (
								<Box
									sx={ {
										display: 'flex',
										flexWrap: 'wrap',
										gap: 0.5,
									} }
								>
									{ selected.map( ( selectedValue ) => {
										// Find matching option by comparing string values
										const option = normalizedOptions.find(
											( opt ) =>
												String( opt.value ) ===
												String( selectedValue )
										);
										const displayLabel = option
											? option.label
											: selectedValue;
										return (
											<Chip
												key={ selectedValue }
												label={ displayLabel }
												size="small"
												onDelete={ ( e ) =>
													handleChipDelete(
														e,
														selectedValue,
														selected
													)
												}
												onClick={ handleChipClick }
												onMouseDown={ handleChipClick }
											/>
										);
									} ) }
								</Box>
							);
						} }
					>
						{ normalizedOptions.length > 0 ? (
							normalizedOptions.map( ( option ) => {
								const optionValue = String( option.value );
								const isSelected =
									selectedValues.includes( optionValue );
								return (
									<MenuItem
										key={ optionValue }
										value={ optionValue }
										disabled={ isSelected }
										sx={ {
											opacity: isSelected ? 0.5 : 1,
										} }
									>
										{ option.label }
									</MenuItem>
								);
							} )
						) : (
							<MenuItem disabled>No options available</MenuItem>
						) }
					</Select>
				</FormControl>
			</Box>
		</ThemeProvider>
	);
};

export default MultipleSelectChip;
