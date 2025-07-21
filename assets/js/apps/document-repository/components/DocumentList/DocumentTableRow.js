import {
	Button,
	CheckboxControl,
	TextControl,
	TextareaControl,
	SelectControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { isTrashView } from '../../utils/documentStatus';

/**
 * DocumentTableRow Component
 *
 * A row component that displays a single document's information and actions.
 * Handles both display and editing of document metadata in spreadsheet mode.
 *
 * @param {Object}   props                      - Component props
 * @param {Object}   props.document             - The document object containing all document data
 * @param {boolean}  props.isSelected           - Whether this document is currently selected
 * @param {Function} props.onSelect             - Callback when the document's selection state changes
 * @param {Function} props.onDelete             - Callback when the document is deleted
 * @param {Function} props.onRestore            - Callback when the document is restored from trash
 * @param {Function} props.onEdit               - Callback when the document is edited
 * @param {boolean}  props.isDeleting           - Flag indicating if a delete operation is in progress
 * @param {Array}    props.metadataFields       - Array of metadata field definitions
 * @param {boolean}  props.isSpreadsheetMode    - Flag indicating if table is in spreadsheet mode
 * @param {Object}   props.bulkEditedMetadata   - Object containing bulk edited metadata values
 * @param {Function} props.onMetadataChange     - Callback when metadata is changed in spreadsheet mode
 * @param {Function} props.formatFileSize       - Function to format file size for display
 * @param {string}   props.documentStatusFilter - Current status filter ('all', 'trash', etc.)
 * @return {JSX.Element} Rendered document table row
 */
function DocumentTableRow( {
	document,
	isSelected,
	onSelect,
	onDelete,
	onEdit,
	onRestore,
	isDeleting,
	metadataFields,
	isSpreadsheetMode,
	bulkEditedMetadata,
	onMetadataChange,
	formatFileSize,
	documentStatusFilter,
} ) {
	const renderMetadataField = ( field ) => {
		if ( ! isSpreadsheetMode ) {
			const fieldValue =
				document.metadata && document.metadata[ field.id ]
					? document.metadata[ field.id ]
					: '';

			// For taxonomy fields, display the name values directly
			if ( field.type === 'taxonomy' && fieldValue ) {
				// Handle arrays (for multiple selections) and single values
				const values = Array.isArray( fieldValue )
					? fieldValue
					: [ fieldValue ];
				// Return single value or comma-separated for multiple
				return values.length === 1 ? values[ 0 ] : values.join( ', ' );
			}

			return fieldValue || '—';
		}

		const fieldValue =
			bulkEditedMetadata[ document.id ]?.[ field.id ] || '';

		if ( field.type === 'taxonomy' ) {
			const options = ( field.options || [] ).map( ( option ) => {
				// Handle both old format (string) and new format (object with id/name)
				if ( typeof option === 'string' ) {
					return {
						label: option,
						value: option,
					};
				}
				return {
					label: option.label || option.name,
					value: option.label || option.name,
				};
			} );

			return (
				<SelectControl
					value={ fieldValue }
					options={ [
						{
							label: __( 'Select…', 'bcgov-design-system' ),
							value: '',
						},
						...options,
					] }
					onChange={ ( newValue ) =>
						onMetadataChange( document.id, field.id, newValue )
					}
				/>
			);
		}

		return (
			<TextControl
				type={ field.type === 'date' ? 'date' : 'text' }
				value={ fieldValue }
				onChange={ ( newValue ) =>
					onMetadataChange( document.id, field.id, newValue )
				}
				className="metadata-input"
			/>
		);
	};

	/**
	 * Render the action buttons contextually.
	 * If viewing trashed documents, show the restore and delete permanently buttons
	 * If viewing other documents, show the download, edit and trash buttons
	 */
	const renderActions = () => {
		if ( isTrashView( documentStatusFilter ) ) {
			return (
				<>
				{ /* Restore button */ }
				<Button
					onClick={ () => onRestore( document ) }
					className="doc-repo-button icon-button table-action-button"
					title={ __( 'Restore', 'bcgov-design-system' ) }
					aria-label={ __( 'Restore', 'bcgov-design-system' ) }
					disabled={ isDeleting }
				>
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="16px" height="16px" >
						<path
							d="M440-320h80v-166l64 62 56-56-160-160-160 160 56 56 64-62v166ZM280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Z"
							fill="currentColor"
						/>
					</svg>

				</Button>

				{ /* Delete Permanently button */ }
				<Button
					onClick={ () => onDelete( document ) }
					className="doc-repo-button icon-button table-action-button"
					title={ __( 'Delete Permanently', 'bcgov-design-system' ) }
					aria-label={ __( 'Delete Permanently', 'bcgov-design-system' ) }
					disabled={ isDeleting }
				>
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="16px" height="16px">
						<path
						d="m376-300 104-104 104 104 56-56-104-104 104-104-56-56-104 104-104-104-56 56 104 104-104 104 56 56Zm-96 180q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Z"
						fill="currentColor"
						/>
					</svg>
					
				</Button>
				</>
			);
		} else return (
			<>
				{ /* Download button */ }
				<Button
					onClick={ () =>
						window.open(
							document.metadata.document_file_url,
							'_blank'
						)
					}
					className="doc-repo-button icon-button table-action-button"
					title={ __( 'Download', 'bcgov-design-system' ) }
					aria-label={ __(
						'Download',
						'bcgov-design-system'
					) }
				>
					<svg viewBox="0 0 24 24" width="16" height="16">
						<path
							d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"
							fill="currentColor"
						/>
					</svg>
				</Button>

				{ /* Edit button */ }
				<Button
					onClick={ () => onEdit( document ) }
					className="doc-repo-button icon-button table-action-button"
					title={ __(
						'Edit Metadata',
						'bcgov-design-system'
					) }
					aria-label={ __(
						'Edit Metadata',
						'bcgov-design-system'
					) }
				>
					<svg viewBox="0 0 24 24" width="16" height="16">
						<path
							d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"
							fill="currentColor"
						/>
					</svg>
				</Button>

				{ /* Trash button */ }
				<Button
					onClick={ () => onDelete( document ) }
					className="doc-repo-button icon-button table-action-button"
					title={ __( 'Trash', 'bcgov-design-system' ) }
					aria-label={ __( 'Trash', 'bcgov-design-system' ) }
					disabled={ isDeleting }
				>
					<svg viewBox="0 0 24 24" width="16" height="16">
						<path
							d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"
							fill="currentColor"
						/>
					</svg>
				</Button>
			</>
		);
	}
	
	return (
		<div
			className="document-table-row"
			role="row"
			data-document-id={ document.id }
		>
			{ /* Selection checkbox cell */ }
			<div
				className="document-table-cell"
				role="cell"
				aria-label={ __( 'Select document', 'bcgov-design-system' ) }
			>
				<CheckboxControl
					checked={ isSelected }
					onChange={ () => onSelect( document.id ) }
				/>
			</div>

			{ /* Document title cell */ }
			<div className="document-table-cell" role="cell">
				{ document.title || document.filename }
			</div>

			{ /* Excerpt cell */ }
			<div className="document-table-cell" role="cell">
				{ isSpreadsheetMode ? (
					<TextareaControl
						value={
							typeof bulkEditedMetadata?.[ document.id ]
								?.excerpt !== 'undefined'
								? bulkEditedMetadata[ document.id ].excerpt
								: document.excerpt || ''
						}
						onChange={ ( newValue ) => {
							onMetadataChange(
								document.id,
								'excerpt',
								newValue
							);
							// Auto-resize the textarea
							setTimeout( () => {
								const textarea = document.querySelector(
									`[data-document-id="${ document.id }"] textarea`
								);
								if ( textarea ) {
									textarea.style.height = 'auto';
									textarea.style.height =
										textarea.scrollHeight + 'px';
								}
							}, 0 );
						} }
						placeholder={ __(
							'Enter excerpt…',
							'bcgov-design-system'
						) }
						rows={ 2 }
						className="excerpt-textarea"
					/>
				) : (
					document.excerpt || '—'
				) }
			</div>

			{ /* Metadata cells - dynamically rendered based on metadata fields */ }
			{ metadataFields.map( ( field ) => (
				<div
					key={ field.id }
					className="document-table-cell metadata-column"
					role="cell"
					aria-label={ sprintf(
						/* translators: %s: field label */
						__( 'Edit %s', 'bcgov-design-system' ),
						field.label
					) }
				>
					{ renderMetadataField( field ) }
				</div>
			) ) }

			{ /* File size cell */ }
			<div className="document-table-cell" role="cell">
				{ document.metadata && document.metadata.document_file_size
					? formatFileSize( document.metadata.document_file_size )
					: '—' }
			</div>

			{ /* File type cell */ }
			<div className="document-table-cell" role="cell">
				{ document.metadata && document.metadata.document_file_type
					? document.metadata.document_file_type
					: '—' }
			</div>

			{ /* Actions cell - only shown in regular mode */ }
			<div
				className="document-table-cell actions"
				role="cell"
				aria-label={ __( 'Document actions', 'bcgov-design-system' ) }
			>
				{ ! isSpreadsheetMode && (
					<div className="action-buttons">
						{ renderActions() }
					</div>
				) }
			</div>
		</div>
	);
}

export { DocumentTableRow as default };
