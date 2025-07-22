import {
	useState,
	useEffect,
	useCallback,
	useReducer,
} from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Metadata reducer for handling metadata state
 * @param {Object}  state                      - The current state
 * @param {Object}  action                     - The action to perform
 * @param {string}  action.type                - The type of action
 * @param {*}       action.payload             - The payload for the action
 * @param {Object}  [action.initialValues]     - Initial values for editing
 * @param {Object}  [action.initialBulkValues] - Initial values for bulk editing
 * @param {string}  [action.documentId]        - Document ID for bulk updates
 * @param {string}  [action.fieldId]           - Field ID for bulk updates
 * @param {*}       [action.value]             - New value for bulk updates
 * @param {boolean} [action.hasChanges]        - Whether there are changes
 * @return {Object} The new state
 */
const metadataReducer = ( state, action ) => {
	switch ( action.type ) {
		case 'SET_EDITING_DOCUMENT':
			return {
				...state,
				editingMetadata: action.payload,
				editedValues: action.initialValues || {},
				errors: {},
			};
		case 'CLEAR_EDITING_DOCUMENT':
			return {
				...state,
				editingMetadata: null,
				editedValues: {},
				errors: {},
			};
		case 'UPDATE_EDITED_VALUES':
			return {
				...state,
				editedValues: {
					...state.editedValues,
					...action.payload,
				},
			};
		case 'SET_ERRORS':
			return {
				...state,
				errors: action.payload,
			};
		case 'SET_IS_SAVING':
			return {
				...state,
				isSaving: action.payload,
			};
		case 'ENTER_SPREADSHEET_MODE':
			return {
				...state,
				isSpreadsheetMode: true,
				bulkEditedMetadata: action.initialBulkValues || {},
				hasMetadataChanges: false,
			};
		case 'EXIT_SPREADSHEET_MODE':
			return {
				...state,
				isSpreadsheetMode: false,
				bulkEditedMetadata: {},
				hasMetadataChanges: false,
			};
		case 'UPDATE_BULK_METADATA':
			return {
				...state,
				bulkEditedMetadata: {
					...state.bulkEditedMetadata,
					[ action.documentId ]: {
						...state.bulkEditedMetadata[ action.documentId ],
						[ action.fieldId ]: action.value,
					},
				},
				hasMetadataChanges: action.hasChanges,
			};
		case 'SET_IS_SAVING_BULK':
			return {
				...state,
				isSavingBulk: action.payload,
			};
		case 'CLEAR_BULK_CHANGES':
			return {
				...state,
				bulkEditedMetadata: {},
				hasMetadataChanges: false,
			};
		default:
			return state;
	}
};

/**
 * Custom hook for managing document metadata
 *
 * @param {Object}   options                    Options for the hook
 * @param {Array}    options.documents          Current documents array
 * @param {Array}    options.metadataFields     Metadata field definitions
 * @param {string}   options.apiNamespace       API namespace for metadata operations
 * @param {Function} options.onUpdateDocuments  Callback when documents are updated
 * @param {Function} options.onError            Callback for error handling
 * @param {Function} options.onShowNotification Callback for showing notifications
 * @return {Object} Metadata management state and functions
 */
const useMetadataManagement = ( {
	documents = [],
	metadataFields = [],
	apiNamespace,
	onUpdateDocuments,
	onError,
	onShowNotification,
} ) => {
	// Use reducer for all metadata-related state
	const [ metadataState, dispatch ] = useReducer( metadataReducer, {
		// Single document editing
		editingMetadata: null,
		editedValues: {},
		errors: {},
		isSaving: false,

		// Spreadsheet mode - critical for keeping the save button functionality
		isSpreadsheetMode: false,
		bulkEditedMetadata: {},
		hasMetadataChanges: false,
		isSavingBulk: false,
	} );

	// Maintain local copy of documents
	const [ localDocuments, setLocalDocuments ] = useState( documents );

	// Keep local documents in sync with documents prop
	useEffect( () => {
		setLocalDocuments( documents );
	}, [ documents ] );

	/**
	 * Check if metadata values have changed
	 */
	const hasMetadataChanged = useCallback( () => {
		const { editingMetadata, editedValues } = metadataState;

		if ( ! editingMetadata ) {
			return false;
		}

		// Check metadata fields
		const metadataChanged = metadataFields.some( ( field ) => {
			const currentValue = editingMetadata.metadata?.[ field.id ] || '';
			const editedValue = editedValues[ field.id ] || '';
			return currentValue !== editedValue;
		} );

		// Check excerpt
		const currentExcerpt = editingMetadata.excerpt || '';
		const editedExcerpt = editedValues.excerpt || '';
		const excerptChanged = currentExcerpt !== editedExcerpt;

		return metadataChanged || excerptChanged;
	}, [ metadataState, metadataFields ] );

	/**
	 * Start editing a document's metadata
	 * @param {Object} document The document to edit
	 */
	const handleEditMetadata = useCallback(
		( document ) => {
			// If document is null, clear the editing state
			if ( ! document ) {
				dispatch( { type: 'CLEAR_EDITING_DOCUMENT' } );
				return;
			}

			const documentToEdit = {
				...document,
				upload_date:
					document.date ||
					document.upload_date ||
					document.metadata?.upload_date,
			};

			// Initialize edited values with current metadata, preserving case
			const initialValues = {};
			metadataFields.forEach( ( field ) => {
				initialValues[ field.id ] =
					document.metadata?.[ field.id ] ?? '';
			} );

			// Add excerpt to initial values
			initialValues.excerpt = document.excerpt ?? '';

			dispatch( {
				type: 'SET_EDITING_DOCUMENT',
				payload: documentToEdit,
				initialValues,
			} );
		},
		[ metadataFields ]
	);

	/**
	 * Handle metadata field value change
	 * @param {number} documentId Document ID
	 * @param {string} fieldId    Field ID
	 * @param {string} value      New value
	 */
	const handleMetadataChange = useCallback(
		( documentId, fieldId, value ) => {
			// Update bulk edited metadata
			const prevDoc =
				metadataState.bulkEditedMetadata[ documentId ] || {};
			let newDoc;
			if ( fieldId === 'excerpt' ) {
				newDoc = {
					...prevDoc,
					excerpt: value,
				};
			} else {
				newDoc = {
					...prevDoc,
					[ fieldId ]: value,
				};
			}
			const newBulkMetadata = {
				...metadataState.bulkEditedMetadata,
				[ documentId ]: newDoc,
			};

			// Check if any metadata or excerpt has changed
			const hasChanges = Object.entries( newBulkMetadata ).some(
				( [ docId, editedMetadata ] ) => {
					const currentDoc = localDocuments.find(
						( doc ) => doc.id === parseInt( docId )
					);
					if ( ! currentDoc ) {
						return false;
					}
					// Check metadata fields
					const metadataChanged = metadataFields.some( ( field ) => {
						const originalValue =
							currentDoc.metadata?.[ field.id ] || '';
						const isChanged =
							String( originalValue ) !==
							String( editedMetadata[ field.id ] || '' );
						return isChanged;
					} );
					// Check excerpt
					const originalExcerpt = currentDoc.excerpt || '';
					const isExcerptChanged =
						String( originalExcerpt ) !==
						String( editedMetadata.excerpt || '' );
					return metadataChanged || isExcerptChanged;
				}
			);

			// Update state with changed value and hasChanges flag
			dispatch( {
				type: 'UPDATE_BULK_METADATA',
				documentId,
				fieldId,
				value,
				hasChanges,
			} );

			// Do NOT update localDocuments here in spreadsheet mode
		},
		[
			localDocuments,
			metadataState.bulkEditedMetadata,
			dispatch,
			metadataFields,
		]
	);

	/**
	 * Update a single field value when editing a single document
	 * @param {string} fieldId Field ID
	 * @param {string} value   New value
	 */
	const updateEditedField = useCallback(
		( fieldId, value ) => {
			dispatch( {
				type: 'UPDATE_EDITED_VALUES',
				payload: { [ fieldId ]: value },
			} );
		},
		[ dispatch ]
	);

	/**
	 * Save metadata changes for a single document
	 */
	const handleSaveMetadata = useCallback( async () => {
		const { editingMetadata, editedValues } = metadataState;

		if ( ! editingMetadata ) {
			return;
		}

		dispatch( { type: 'SET_IS_SAVING', payload: true } );

		try {
			// Separate metadata and excerpt for different endpoints
			const metadataToUpdate = {};
			const excerptChanged =
				String( editingMetadata.excerpt || '' ) !==
				String( editedValues.excerpt || '' );

			// Only include metadata fields (not excerpt)
			metadataFields.forEach( ( field ) => {
				const currentValue =
					editingMetadata.metadata?.[ field.id ] || '';
				const editedValue = editedValues[ field.id ] || '';
				if ( String( currentValue ) !== String( editedValue ) ) {
					metadataToUpdate[ field.id ] = editedValue;
				}
			} );

			// Prepare API calls
			const calls = [];

			// Update metadata if there are changes
			if ( Object.keys( metadataToUpdate ).length > 0 ) {
				calls.push(
					apiFetch( {
						path: `/${ apiNamespace }/documents/${ editingMetadata.id }/metadata`,
						method: 'POST',
						data: metadataToUpdate,
					} )
				);
			}

			// Update excerpt if it changed
			if ( excerptChanged ) {
				calls.push(
					apiFetch( {
						path: `/${ apiNamespace }/documents/${ editingMetadata.id }`,
						method: 'PUT',
						data: { excerpt: editedValues.excerpt },
					} )
				);
			}

			// Execute all API calls
			await Promise.all( calls );

			// Update local documents
			setLocalDocuments( ( prev ) =>
				prev.map( ( doc ) =>
					doc.id === editingMetadata.id
						? {
								...doc,
								metadata: {
									...doc.metadata,
									...metadataToUpdate,
								},
								excerpt: excerptChanged
									? editedValues.excerpt
									: doc.excerpt,
						  }
						: doc
				)
			);

			// Update parent component if needed
			if ( typeof onUpdateDocuments === 'function' ) {
				onUpdateDocuments(
					localDocuments.map( ( doc ) =>
						doc.id === editingMetadata.id
							? {
									...doc,
									metadata: {
										...doc.metadata,
										...metadataToUpdate,
									},
									excerpt: excerptChanged
										? editedValues.excerpt
										: doc.excerpt,
							  }
							: doc
					)
				);
			}

			// Reset editing state
			dispatch( { type: 'CLEAR_EDITING_DOCUMENT' } );

			// Show success notification
			if ( onShowNotification ) {
				onShowNotification(
					'success',
					__(
						'Document metadata updated successfully',
						'bcgov-design-system'
					)
				);
			}
		} catch ( error ) {
			if ( error.data?.errors ) {
				dispatch( { type: 'SET_ERRORS', payload: error.data.errors } );
			}

			if ( onError ) {
				onError( 'metadata', editingMetadata.id, error, {
					customMessage:
						error.data?.message ||
						__(
							'Failed to update metadata',
							'bcgov-design-system'
						),
				} );
			}
		} finally {
			dispatch( { type: 'SET_IS_SAVING', payload: false } );
		}
	}, [
		metadataState,
		apiNamespace,
		localDocuments,
		metadataFields,
		onUpdateDocuments,
		onShowNotification,
		onError,
		dispatch,
	] );

	/**
	 * Toggle spreadsheet mode on/off
	 * @param {boolean} enabled Whether to enable spreadsheet mode
	 */
	const toggleSpreadsheetMode = useCallback(
		( enabled ) => {
			if ( enabled ) {
				// Initialize bulk edit metadata when entering spreadsheet mode
				const initialBulkMetadata = {};
				localDocuments.forEach( ( doc ) => {
					initialBulkMetadata[ doc.id ] = {
						...( doc.metadata || {} ),
						excerpt: doc.excerpt || '', // Include excerpt in initial state
					};
				} );

				dispatch( {
					type: 'ENTER_SPREADSHEET_MODE',
					initialBulkValues: initialBulkMetadata,
				} );
			} else {
				dispatch( { type: 'EXIT_SPREADSHEET_MODE' } );
			}
		},
		[ localDocuments ]
	);

	/**
	 * Save all bulk metadata changes
	 */
	const handleSaveBulkChanges = useCallback( async () => {
		const { bulkEditedMetadata } = metadataState;

		dispatch( { type: 'SET_IS_SAVING_BULK', payload: true } );

		try {
			// Only send updates for documents with actual changes (metadata or excerpt)
			const docsToUpdate = Object.entries( bulkEditedMetadata ).filter(
				( [ docId, edited ] ) => {
					const original = localDocuments.find(
						( doc ) => doc.id.toString() === docId
					);
					if ( ! original ) return false;
					// Check metadata fields
					const metadataChanged = metadataFields.some( ( field ) => {
						const origVal = original.metadata?.[ field.id ] ?? '';
						const editVal = edited[ field.id ] ?? '';
						return String( origVal ) !== String( editVal );
					} );
					// Check excerpt
					const origExcerpt = original.excerpt ?? '';
					const editExcerpt = edited.excerpt ?? '';
					const excerptChanged =
						String( origExcerpt ) !== String( editExcerpt );
					return metadataChanged || excerptChanged;
				}
			);

			const updatePromises = docsToUpdate.map( ( [ docId, edited ] ) => {
				const original = localDocuments.find(
					( doc ) => doc.id.toString() === docId
				);

				const metaDataToUpdate = {};
				metadataFields.forEach( ( field ) => {
					const origVal = original.metadata?.[ field.id ] ?? '';
					const editVal = edited[ field.id ] ?? '';
					if ( String( origVal ) !== String( editVal ) ) {
						metaDataToUpdate[ field.id ] = editVal;
					}
				} );

				const excerptChanged =
					String( original.excerpt ?? '' ) !==
					String( edited.excerpt ?? '' );

				// Prepare API calls with document ID tracking
				const calls = [];

				if ( Object.keys( metaDataToUpdate ).length > 0 ) {
					calls.push(
						apiFetch( {
							path: `/${ apiNamespace }/documents/${ docId }/metadata`,
							method: 'POST',
							data: metaDataToUpdate,
						} ).then( ( result ) => ( {
							type: 'metadata',
							docId,
							result,
						} ) )
					);
				}

				if ( excerptChanged ) {
					calls.push(
						apiFetch( {
							path: `/${ apiNamespace }/documents/${ docId }`,
							method: 'PUT',
							data: { excerpt: edited.excerpt },
						} ).then( ( result ) => ( {
							type: 'excerpt',
							docId,
							result,
						} ) )
					);
				}

				// Run all needed calls for this document in parallel
				return Promise.all( calls );
			} );

			const results = await Promise.allSettled( updatePromises.flat() );

			// Process results with proper document tracking
			const failed = results
				.filter( ( result ) => result.status === 'rejected' )
				.map( ( result ) => ( {
					result: result.reason,
					docId: result.value?.docId || 'unknown',
				} ) );

			// Flatten the results since updatePromises.flat() creates nested arrays
			const successful = results
				.filter( ( result ) => result.status === 'fulfilled' )
				.flatMap( ( result ) => {
					// Handle both single results and nested arrays
					if ( Array.isArray( result.value ) ) {
						return result.value;
					}
					return [ result.value ];
				} )
				.filter( ( result ) => result ); // Filter out any undefined results

			if ( failed.length > 0 ) {
				// Handle failed operations
				failed.forEach( ( { result, docId } ) => {
					if ( onError ) {
						onError( 'metadata', docId, result.reason, {
							showNotice: false, // Don't show individual notices
						} );
					}
				} );

				// Show summary notification
				if ( onShowNotification ) {
					onShowNotification(
						'warning',
						sprintf(
							/* translators: %1$d: number of failed updates, %2$d: total number of updates */
							__(
								'%1$d of %2$d metadata updates failed. You can retry the failed operations.',
								'bcgov-design-system'
							),
							failed.length,
							Object.keys( bulkEditedMetadata ).length
						),
						0 // Don't auto-dismiss
					);
				}
			}

			// Update local documents with successful API responses (contains properly formatted data)
			if ( successful.length > 0 ) {
				const updatedDocuments = localDocuments.map( ( doc ) => {
					const successfulUpdates = successful.filter(
						( update ) => update.docId === doc.id.toString()
					);

					if ( successfulUpdates.length > 0 ) {
						// Find the most recent update (should be the same document data)
						const latestUpdate =
							successfulUpdates[ successfulUpdates.length - 1 ];
						// Use the fresh data from API response which has properly formatted taxonomy values
						return latestUpdate.result;
					}
					return doc;
				} );

				// Update local state first
				setLocalDocuments( updatedDocuments );

				// Update parent component with refreshed data
				if ( typeof onUpdateDocuments === 'function' ) {
					onUpdateDocuments( updatedDocuments );
				}
			}

			if ( failed.length === 0 ) {
				// All updates successful
				if ( onShowNotification ) {
					onShowNotification(
						'success',
						__(
							'All metadata changes saved successfully.',
							'bcgov-design-system'
						)
					);
				}

				// Clear bulk changes state
				dispatch( { type: 'CLEAR_BULK_CHANGES' } );

				// Exit spreadsheet mode
				dispatch( { type: 'EXIT_SPREADSHEET_MODE' } );
			}
		} catch ( error ) {
			if ( onError ) {
				onError( 'bulk-metadata', null, error );
			}
		} finally {
			dispatch( { type: 'SET_IS_SAVING_BULK', payload: false } );
		}
	}, [
		metadataState,
		apiNamespace,
		localDocuments,
		metadataFields,
		onUpdateDocuments,
		onError,
		onShowNotification,
	] );

	return {
		// Single document editing
		editingMetadata: metadataState.editingMetadata,
		editedValues: metadataState.editedValues,
		errors: metadataState.errors,
		isSaving: metadataState.isSaving,
		hasMetadataChanged,
		handleEditMetadata,
		updateEditedField,
		handleSaveMetadata,

		// Spreadsheet mode
		isSpreadsheetMode: metadataState.isSpreadsheetMode,
		hasMetadataChanges: metadataState.hasMetadataChanges,
		bulkEditedMetadata: metadataState.bulkEditedMetadata,
		isSavingBulk: metadataState.isSavingBulk,
		handleMetadataChange,
		toggleSpreadsheetMode,
		handleSaveBulkChanges,

		// Document state
		localDocuments,
	};
};

export default useMetadataManagement;
