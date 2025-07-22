import { useCallback, useMemo } from '@wordpress/element';
import {
	Button,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import ErrorBoundary from './ErrorBoundary';
import DocumentTable from './DocumentTable';
import UploadFeedback from './UploadFeedback';
import MetadataModal from '../../../shared/components/MetadataModal';
import UploadArea from './UploadArea';
import PaginationControls from './PaginationControls';
import RetryNotice from './RetryNotice';
import { isAllView, isTrashView } from '../../utils/documentStatus';

// Import custom hooks
import useNotifications from './hooks/useNotifications';
import useErrorHandling from './hooks/useErrorHandling';
import useMetadataManagement from './hooks/useMetadataManagement';
import useFileHandling from './hooks/useFileHandling';
import useDocumentManagement from './hooks/useDocumentManagement';

/**
 * DocumentList Component
 *
 * Main component for managing and displaying a list of documents with metadata.
 * Handles document uploads, metadata editing, bulk operations, and pagination.
 *
 * @param {Object}   props                      - Component props
 * @param {Array}    props.documents            - List of document objects to display
 * @param {number}   props.currentPage          - Current page number for pagination
 * @param {number}   props.totalPages           - Total number of pages
 * @param {Function} props.onPageChange         - Callback when page changes
 * @param {Function} props.onDelete             - Callback when a document is deleted
 * @param {Function} props.onTrash              - Callback when a document is moved to trash
 * @param {Function} props.onRestore            - Function to restore a document from the trash
 * @param {boolean}  props.isDeleting           - Flag indicating if a delete operation is in progress
 * @param {Array}    props.selectedDocuments    - Array of selected document IDs
 * @param {Function} props.onSelectDocument     - Callback when a document is selected
 * @param {Function} props.onSelectAll          - Callback when all documents are selected/deselected
 * @param {Function} props.onFileDrop           - Callback when files are dropped/uploaded
 * @param {Function} props.onDocumentsUpdate    - Callback when documents are updated
 * @param {Array}    props.metadataFields       - Array of metadata field definitions
 * @param {Array} 	 props.statusCounts 	    - Array of number of documents of each status
 * @param {string}   props.documentStatusFilter - Current status filter ('all', 'trash', etc.)
 * @param {Function} props.onStatusFilterChange - Callback when filter changes
 */
const DocumentList = ( {
	documents = [],
	currentPage = 1,
	totalPages = 1,
	onPageChange,
	onDelete,
	onTrash,
	onRestore,
	isDeleting = false,
	selectedDocuments = [],
	onSelectDocument,
	onSelectAll,
	onFileDrop,
	onDocumentsUpdate,
	metadataFields = [],
	statusCounts = {},
	documentStatusFilter = 'all',
  	onStatusFilterChange,
} ) => {
	// Memoize formatFileSize function
	const formatFileSize = useMemo(
		() => ( bytes ) => {
			if ( bytes === 0 ) {
				return '0 Bytes';
			}
			const k = 1024;
			const sizes = [ 'Bytes', 'KB', 'MB', 'GB' ];
			const i = Math.floor( Math.log( bytes ) / Math.log( k ) );
			return (
				parseFloat( ( bytes / Math.pow( k, i ) ).toFixed( 2 ) ) +
				' ' +
				sizes[ i ]
			);
		},
		[]
	);

	// Memoize API namespace to prevent recalculation
	const apiNamespace = useMemo( () => {
		const settings = window.documentRepositorySettings;

		return settings?.apiNamespace || 'wp/v2';
	}, [] );

	// Use notifications hook
	const { showNotification } = useNotifications();

	// Use error handling hook
	const { failedOperations, handleOperationError, retryAllOperations } =
		useErrorHandling( {
			onShowNotification: showNotification,
		} );

	// Use document management hook
	const {
		deleteDocument,
		restoreDocument,
		bulkDeleteConfirmOpen,
		bulkRestoreConfirmOpen,
		isMultiDeleting,
		isMultiRestoring,
		setDeleteDocument,
		setRestoreDocument,
		handleBulkDelete,
		handleBulkRestore,
		handleSingleDelete,
		handleSingleRestore,
		openBulkDeleteConfirm,
		closeBulkDeleteConfirm,
		openBulkRestoreConfirm,
		closeBulkRestoreConfirm,
	} = useDocumentManagement( {
		onDelete,
		onTrash,
		onRestore,
		documentStatusFilter,
		onSelectAll,
		onShowNotification: showNotification,
		onError: handleOperationError,
	} );

	// Use metadata management hook - critical for spreadsheet mode
	const {
		// Single document editing
		editingMetadata,
		editedValues,
		errors: metadataErrors,
		isSaving: isSavingMetadata,
		hasMetadataChanged,
		handleEditMetadata,
		updateEditedField,
		handleSaveMetadata,

		// Spreadsheet mode
		isSpreadsheetMode,
		hasMetadataChanges,
		bulkEditedMetadata,
		isSavingBulk,
		handleMetadataChange,
		toggleSpreadsheetMode,
		handleSaveBulkChanges,

		// Document state
		localDocuments,
	} = useMetadataManagement( {
		documents,
		metadataFields,
		apiNamespace,
		onUpdateDocuments: onDocumentsUpdate,
		onError: handleOperationError,
		onShowNotification: showNotification,
	} );

	// Use file handling hook
	const {
		uploadingFiles,
		showUploadFeedback,
		handleFiles,
		closeUploadFeedback,
	} = useFileHandling( {
		onFileDrop,
		onShowNotification: showNotification,
		onError: handleOperationError,
	} );

	// Handler to retry all failed operations
	const handleRetryAll = useCallback( () => {
		const operationHandlers = {
			delete: onDelete,
			metadata: handleSaveMetadata,
		};
		retryAllOperations( operationHandlers );
	}, [ onDelete, handleSaveMetadata, retryAllOperations ] );

	// Memoize the document table props to prevent unnecessary re-renders
	const documentTableProps = useMemo(
		() => ( {
			documents: localDocuments,
			selectedDocuments,
			onSelectDocument,
			onSelectAll,
			onDelete: setDeleteDocument,
			onEdit: handleEditMetadata,
			onRestore: setRestoreDocument,
			isDeleting,
			metadataFields,
			isSpreadsheetMode,
			bulkEditedMetadata,
			onMetadataChange: handleMetadataChange,
			formatFileSize,
			documentStatusFilter,
		} ),
		[
			localDocuments,
			selectedDocuments,
			onSelectDocument,
			onSelectAll,
			isDeleting,
			metadataFields,
			isSpreadsheetMode,
			bulkEditedMetadata,
			handleEditMetadata,
			handleMetadataChange,
			formatFileSize,
			setDeleteDocument,
			setRestoreDocument,
		]
	);

	const handleFilesWithLog = ( files ) => {
		handleFiles( files );
	};

	// Counts of all untrashed and trashed documents
	const { totalDocumentCount, trashedCount } = useMemo( () => {
		// Count all statuses except 'trash'
		const total = Object.entries( statusCounts ).reduce( ( acc, [ key, val ] ) => {
			if ( key !== 'trash' ) {
			return acc + Number( val || 0 );
			}
			return acc;
		}, 0);

		// Trash count
		const trash = Number( statusCounts.trash || 0 );

		return {
			totalDocumentCount: total,
			trashedCount: trash,
		};
	}, [ statusCounts ] );

	return (
		<ErrorBoundary>
			<div className="document-list">
				<RetryNotice
					failedOperations={ failedOperations }
					onRetryAll={ handleRetryAll }
				/>

				<div className="document-list__actions">
					<div className="document-list__left-actions">
						{ /* Delete button moved to table actions section */ }
					</div>
					<div className="document-list__right-actions">
						<UploadArea onFilesSelected={ handleFilesWithLog } />
					</div>
				</div>

				<ul className="subsubsub document-status-filters">
					<li className="all">
						<a
						href="#"
						className={ isAllView( documentStatusFilter ) ? 'current' : '' }
						onClick={ ( e ) => {
							e.preventDefault();
							onStatusFilterChange && onStatusFilterChange( 'all' );
						} }
						>
						{ __( 'All', 'bcgov-design-system' ) } <span className="count">( { totalDocumentCount } )</span>
						</a>
					</li>
					<li className="trash">
						{ ' | ' }
						<a
						href="#"
						className={ isTrashView( documentStatusFilter ) ? 'current' : '' }
						onClick={ ( e ) => {
							e.preventDefault();
							onStatusFilterChange && onStatusFilterChange( 'trash' );
						} }
						>
						{ __( 'Trash', 'bcgov-design-system ' ) } <span className="count">( { trashedCount } )</span>
						</a>
					</li>
				</ul>

				<div className="document-list__table-actions">
					<div className="action-buttons-container">
						<Button
							className={ `doc-repo-button spreadsheet-toggle${
								isSpreadsheetMode ? ' isPressed' : ''
							}` }
							onClick={ () =>
								toggleSpreadsheetMode( ! isSpreadsheetMode )
							}
							isPressed={ isSpreadsheetMode }
						>
							{ isSpreadsheetMode
								? __(
										'Exit Spreadsheet Mode',
										'bcgov-design-system'
								  )
								: __(
										'Enter Spreadsheet Mode',
										'bcgov-design-system'
								  ) }
						</Button>

						{ isSpreadsheetMode && hasMetadataChanges && (
							<Button
								className="doc-repo-button save-button"
								onClick={ handleSaveBulkChanges }
								isBusy={ isSavingBulk }
								disabled={ isSavingBulk }
							>
								{ isSavingBulk
									? __( 'Saving…', 'bcgov-design-system' )
									: __(
											'Save Changes',
											'bcgov-design-system'
									  ) }
							</Button>
						) }

						{ isTrashView( documentStatusFilter ) && selectedDocuments.length > 0 && (
							<Button
								className="doc-repo-button save-button bulk-restore-button"
								onClick={ openBulkRestoreConfirm }
								disabled={ isMultiRestoring }
							>
								{ sprintf(
								/* translators: %d: number of selected documents */
								__( 'Restore Selected (%d)', 'bcgov-design-system' ),
								selectedDocuments.length
								) }
							</Button>
							) }
	
						{ selectedDocuments.length > 0 && (
							<Button
								className="doc-repo-button delete-button bulk-delete-button"
								onClick={ openBulkDeleteConfirm }
								disabled={ isMultiDeleting }
							>
								{ sprintf(
								/* translators: %d: number of selected documents */
								__(
									isTrashView( documentStatusFilter )
									? 'Delete Selected Permanently (%d)'
									: 'Trash Selected (%d)',
									'bcgov-design-system'
								),
								selectedDocuments.length
								) }
							</Button>
						) }
					</div>
				</div>

				<DocumentTable { ...documentTableProps } />

				<PaginationControls
					currentPage={ currentPage }
					totalPages={ totalPages }
					onPageChange={ onPageChange }
				/>

				{ showUploadFeedback && (
					<UploadFeedback
						uploadingFiles={ uploadingFiles }
						showUploadFeedback={ showUploadFeedback }
						onClose={ closeUploadFeedback }
					/>
				) }

				{ deleteDocument && (
					<MetadataModal
						title={
							isTrashView( documentStatusFilter )
								? __( 'Delete Document Permanently', 'bcgov-design-system' )
								: __( 'Trash Document', 'bcgov-design-system' )
						}
						isOpen={ !! deleteDocument }
						onClose={ () => setDeleteDocument( null ) }
						onSave={ () => handleSingleDelete( deleteDocument.id ) }
						isSaving={ isDeleting }
						isDisabled={ false }
						saveButtonText={
							isDeleting
								? __(
									isTrashView( documentStatusFilter )
										? 'Deleting…'
										: 'Trashing…',
									'bcgov-design-system'
								)
								: __(
									isTrashView( documentStatusFilter )
										? 'Delete Permanently'
										: 'Trash',
									'bcgov-design-system'
								)
						}
						saveButtonClassName="doc-repo-button delete-button"
					>
						<div className="delete-confirmation-content">
							<div className="delete-warning">
								{ __(
									isTrashView( documentStatusFilter )
										? 'Are you sure you want to delete this document? This action cannot be undone.'
										: 'Are you sure you want to trash this document?',
									'bcgov-design-system'
								) }
							</div>
							<div className="documents-to-delete">
								<h4>
									{ __(
										isTrashView( documentStatusFilter )
											? 'Document to be deleted:'
											: 'Document to be trashed:',
										'bcgov-design-system'
									) }
								</h4>
								<ul>
									<li>{ deleteDocument.title }</li>
								</ul>
							</div>
						</div>
					</MetadataModal>
				) }

				{ restoreDocument && (
					<MetadataModal
						title={ __( 'Restore Document', 'bcgov-design-system' ) }
						isOpen={ !!restoreDocument }
						onClose={ () => setRestoreDocument( null ) }
						onSave={ () => handleSingleRestore( restoreDocument.id ) }
						isSaving={ isDeleting }
						isDisabled={ false }
						saveButtonText={ __( 'Restore', 'bcgov-design-system' ) }
						saveButtonClassName="doc-repo-button save-button"
					>
						<div className="restore-confirmation-content">
							<div className="restore-warning">
								{ __( 'Are you sure you want to restore this document?', 'bcgov-design-system' ) }
							</div>
							<div className="documents-to-restore">
								<h4>{ __( 'Document to be restored:', 'bcgov-design-system' ) }</h4>
								<ul>
									<li>{ restoreDocument.title }</li>
								</ul>
							</div>
						</div>
					</MetadataModal>
				) }

				{ /* Bulk Delete Confirmation Modal */ }
				{ bulkDeleteConfirmOpen && (
					<MetadataModal
						title={
							isTrashView( documentStatusFilter )
								? __( 'Delete Selected Documents Permanently', 'bcgov-design-system' )
								: __( 'Trash Selected Documents', 'bcgov-design-system' )
						}
						isOpen={ bulkDeleteConfirmOpen }
						onClose={ closeBulkDeleteConfirm }
						onSave={ () => handleBulkDelete( selectedDocuments ) }
						isSaving={ isMultiDeleting }
						isDisabled={ false }
						saveButtonText={
							isMultiDeleting
								? __(
									isTrashView( documentStatusFilter )
										? 'Deleting…'
										: 'Trashing…',
									'bcgov-design-system'
								)
								: __(
									isTrashView( documentStatusFilter )
										? 'Delete Selected Permanently'
										: 'Trash Selected',
									'bcgov-design-system'
								)
						}
						saveButtonClassName="doc-repo-button delete-button"
					>
						<div className="delete-confirmation-content">
							<div className="delete-warning">
								{ __(
									isTrashView( documentStatusFilter )
										? 'Are you sure you want to delete the selected documents? This action cannot be undone.'
										: 'Are you sure you want to trash the selected documents?',
									'bcgov-design-system'
								) }
							</div>
							<div className="documents-to-delete">
								<h4>
									{ sprintf(
										isTrashView( documentStatusFilter )
											? __( 'Documents to be deleted (%d):', 'bcgov-design-system' )
											: __( 'Documents to be trashed (%d):', 'bcgov-design-system' ),
										selectedDocuments.length
									) }
								</h4>
								<ul>
									{ localDocuments
										.filter( ( doc ) =>
											selectedDocuments.includes( doc.id )
										)
										.map( ( doc ) => (
											<li key={ doc.id }>
												{ doc.title }
											</li>
										) ) }
								</ul>
							</div>
						</div>
					</MetadataModal>
				) }

				{ bulkRestoreConfirmOpen && (
					<MetadataModal
						title={ __( 'Restore Selected Documents', 'bcgov-design-system' ) }
						isOpen={ bulkRestoreConfirmOpen }
						onClose={ closeBulkRestoreConfirm }
						onSave={ () => handleBulkRestore( selectedDocuments ) }
						isSaving={ isMultiRestoring }
						isDisabled={ false }
						saveButtonText={
							isMultiRestoring
								? __( 'Restoring…', 'bcgov-design-system' )
								: __( 'Restore Selected', 'bcgov-design-system' )
						}
						saveButtonClassName="doc-repo-button save-button"
					>
						<div className="restore-confirmation-content">
							<div className="restore-warning">
								{ __(
									'Are you sure you want to restore the selected documents?',
									'bcgov-design-system'
								) }
							</div>
							<div className="documents-to-restore">
								<h4>
									{ sprintf(
										__( 'Documents to be restored (%d):', 'bcgov-design-system' ),
										selectedDocuments.length
									) }
								</h4>
								<ul>
									{ localDocuments
										.filter( ( doc ) => selectedDocuments.includes( doc.id ) )
										.map( ( doc ) => (
											<li key={ doc.id }>{ doc.title }</li>
										) ) }
								</ul>
							</div>
						</div>
					</MetadataModal>
				) }

				{ editingMetadata && (
					<MetadataModal
						title={ __(
							'Edit Document Metadata',
							'bcgov-design-system'
						) }
						isOpen={ !! editingMetadata }
						onClose={ () => handleEditMetadata( null ) }
						onSave={ handleSaveMetadata }
						isSaving={ isSavingMetadata }
						isDisabled={ ! hasMetadataChanged }
						saveButtonText={
							isSavingMetadata
								? __( 'Saving…', 'bcgov-design-system' )
								: __( 'Save Changes', 'bcgov-design-system' )
						}
						saveButtonClassName="doc-repo-button save-button"
					>
						<div className="editable-metadata">
							{ /* Excerpt field */ }
							<div className="metadata-field">
								<label htmlFor="excerpt">
									{ __( 'Excerpt', 'bcgov-design-system' ) }
								</label>
								<TextareaControl
									id="excerpt"
									value={ editedValues.excerpt || '' }
									onChange={ ( value ) => {
										updateEditedField( 'excerpt', value );
										// Auto-resize the textarea
										setTimeout( () => {
											const textarea =
												document.getElementById(
													'excerpt'
												);
											if ( textarea ) {
												textarea.style.height = 'auto';
												textarea.style.height =
													textarea.scrollHeight +
													'px';
											}
										}, 0 );
									} }
									placeholder={ __(
										'Enter excerpt…',
										'bcgov-design-system'
									) }
									rows={ 3 }
									className="excerpt-textarea"
								/>
							</div>

							{ metadataFields.map( ( field ) => (
								<div
									key={ field.id }
									className="metadata-field"
								>
									<label htmlFor={ field.id }>
										{ field.label }
									</label>
									{ field.type === 'taxonomy' ? (
										<SelectControl
											id={ field.id }
											value={
												editedValues[ field.id ] || ''
											}
											options={ [
												{
													label: __(
														'Select…',
														'bcgov-design-system'
													),
													value: '',
												},
												...( field.options || [] ).map(
													( option ) => {
														// Handle both old format (string) and new format (object with id/name)
														if (
															typeof option ===
															'string'
														) {
															return {
																label: option,
																value: option,
															};
														}
														return {
															label:
																option.label ||
																option.name,
															value:
																option.value ||
																option.id,
														};
													}
												),
											] }
											onChange={ ( value ) =>
												updateEditedField(
													field.id,
													value
												)
											}
										/>
									) : (
										<TextControl
											id={ field.id }
											type={
												field.type === 'date'
													? 'date'
													: 'text'
											}
											value={
												editedValues[ field.id ] || ''
											}
											onChange={ ( value ) =>
												updateEditedField(
													field.id,
													value
												)
											}
										/>
									) }
									{ metadataErrors[ field.id ] && (
										<div className="metadata-error">
											{ metadataErrors[ field.id ] }
										</div>
									) }
								</div>
							) ) }
						</div>
						<div className="non-editable-metadata">
							<h3>
								{ __(
									'Document Information',
									'bcgov-design-system'
								) }
							</h3>
							<div className="metadata-field">
								<label htmlFor="document-filename">
									{ __( 'Filename', 'bcgov-design-system' ) }
								</label>
								<div
									id="document-filename"
									className="field-value"
								>
									{ (
										editingMetadata.metadata
											?.document_file_name ||
										editingMetadata.filename ||
										editingMetadata.title ||
										''
									).replace( /\.pdf$/i, '' ) ||
										__(
											'Not available',
											'bcgov-design-system'
										) }
								</div>
							</div>
							<div className="metadata-field">
								<label htmlFor="document-file-type">
									{ __( 'File Type', 'bcgov-design-system' ) }
								</label>
								<div
									id="document-file-type"
									className="field-value"
								>
									{ editingMetadata.metadata
										?.document_file_type || 'PDF' }
								</div>
							</div>
							<div className="metadata-field">
								<label htmlFor="document-file-size">
									{ __( 'File Size', 'bcgov-design-system' ) }
								</label>
								<div
									id="document-file-size"
									className="field-value"
								>
									{ editingMetadata.metadata
										?.document_file_size
										? formatFileSize(
												parseInt(
													editingMetadata.metadata
														.document_file_size
												)
										  )
										: __(
												'Not available',
												'bcgov-design-system'
										  ) }
								</div>
							</div>
						</div>
					</MetadataModal>
				) }
			</div>
		</ErrorBoundary>
	);
};

export default DocumentList;
