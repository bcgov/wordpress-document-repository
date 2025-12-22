import { CheckboxControl, TextControl, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import SafeRender from './SafeRender';
import DocumentTableRow from './DocumentTableRow';

/**
 * DocumentTable Component
 *
 * A table component that displays a list of documents with their metadata and actions.
 * Supports both regular and spreadsheet modes for metadata editing.
 *
 * @param {Object}   props                       - Component props
 * @param {Array}    props.documents             - List of document objects to display
 * @param {Array}    props.selectedDocuments     - Array of selected document IDs
 * @param {Function} props.onSelectDocument      - Callback when a document is selected
 * @param {Function} props.onSelectAll           - Callback when all documents are selected/deselected
 * @param {Function} props.onDelete              - Callback when a document is deleted
 * @param {Function} props.onEdit                - Callback when a document is edited
 * @param {Function} props.onRestore             - Callback when the document is restored from trash
 * @param {boolean}  props.isDeleting            - Flag indicating if a delete operation is in progress
 * @param {Array}    props.metadataFields        - Array of metadata field definitions
 * @param {boolean}  props.isSpreadsheetMode     - Flag indicating if table is in spreadsheet mode
 * @param {Object}   props.bulkEditedMetadata    - Object containing bulk edited metadata values
 * @param {Function} props.onMetadataChange      - Callback when metadata is changed in spreadsheet mode
 * @param {Function} props.formatFileSize        - Function to format file size for display
 * @param {string}   props.documentStatusFilter  - Current status filter ('all', 'trash', etc.)
 * @param {string}   props.searchTerm            - Current active search term
 * @param {string}   props.searchInput           - Current search input value
 * @param {Function} props.setSearchInput        - Setter for search input
 * @param {Function} props.performSearch         - Execute search function
 * @param {Function} props.handleSearchKeyPress  - Handle Enter key for search
 * @param {Function} props.setSearchParams       - Setter for search parameters
 * @param {Function} props.toggleSpreadsheetMode - Callback to toggle spreadsheet mode
 * @param {boolean}  props.hasMetadataChanges    - Flag indicating if there are unsaved metadata changes
 * @param {Function} props.handleSaveBulkChanges - Callback to save bulk changes
 * @param {boolean}  props.isSavingBulk          - Flag indicating if bulk save is in progress
 * @return {JSX.Element} Rendered document table
 */
function DocumentTable( {
	documents,
	selectedDocuments,
	onSelectDocument,
	onSelectAll,
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
	searchTerm = '',
	searchInput = '',
	setSearchInput,
	performSearch,
	handleSearchKeyPress,
	setSearchParams,
	toggleSpreadsheetMode,
	hasMetadataChanges,
	handleSaveBulkChanges,
	isSavingBulk,
} ) {
	// Check if all documents are currently selected
	const allSelected =
		documents.length > 0 && selectedDocuments.length === documents.length;

	return (
		<div className="document-table-wrapper">
			{ /* Search bar and spreadsheet button at top of table */ }
			<div className="document-table__search-header">
				<div className="document-table__search-header-left">
					{ toggleSpreadsheetMode && (
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
					) }
					{ isSpreadsheetMode &&
						hasMetadataChanges &&
						handleSaveBulkChanges && (
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
				</div>
				<div className="document-table__search-header-right">
					<div className="document-search-wrapper">
						<div className="document-search-input-wrapper">
							<TextControl
								placeholder={ __(
									'Search documents…',
									'bcgov-design-system'
								) }
								value={ searchInput }
								onChange={ setSearchInput }
								onKeyPress={ handleSearchKeyPress }
								className="document-search-input"
							/>
							{ ( searchTerm || searchInput ) && (
								<button
									className="document-search-clear"
									onClick={ () => {
										// Always reset search completely
										setSearchParams( ( prev ) => ( {
											...prev,
											search: '',
											page: 1,
										} ) );

										setSearchInput( '' );
									} }
									aria-label={ __(
										'Clear search',
										'bcgov-design-system'
									) }
									title={ __(
										'Clear search',
										'bcgov-design-system'
									) }
								>
									<svg
										xmlns="http://www.w3.org/2000/svg"
										viewBox="0 0 24 24"
										width="16"
										height="16"
										fill="none"
										stroke="currentColor"
										strokeWidth="2"
										strokeLinecap="round"
										strokeLinejoin="round"
									>
										<line
											x1="18"
											y1="6"
											x2="6"
											y2="18"
										></line>
										<line
											x1="6"
											y1="6"
											x2="18"
											y2="18"
										></line>
									</svg>
								</button>
							) }
						</div>
						<Button
							className="document-search-button"
							variant="primary"
							onClick={ performSearch }
							disabled={ ! searchInput.trim() }
						>
							{ __( 'Search', 'bcgov-design-system' ) }
						</Button>
					</div>
				</div>
			</div>
			<div className="document-table" role="table">
				{ /* Table header with column titles and select all checkbox */ }
				<div className="document-table-header" role="rowgroup">
					<div className="document-table-row" role="row">
						{ /* Select all checkbox column */ }
						<div
							className="document-table-cell header"
							role="columnheader"
						>
							<CheckboxControl
								checked={ allSelected }
								onChange={ onSelectAll }
							/>
						</div>
						{ /* Document title column */ }
						<div
							className="document-table-cell header"
							role="columnheader"
						>
							{ __( 'Title', 'bcgov-design-system' ) }
						</div>
						{ /* Excerpt column */ }
						<div
							className="document-table-cell header"
							role="columnheader"
						>
							{ __( 'Excerpt', 'bcgov-design-system' ) }
						</div>
						{ /* Metadata columns */ }
						{ metadataFields.map( ( field ) => (
							<div
								key={ field.id }
								className="document-table-cell header metadata-column"
								role="columnheader"
							>
								{ field.label }
							</div>
						) ) }
						{ /* File size column */ }
						<div
							className="document-table-cell header"
							role="columnheader"
						>
							{ __( 'Size', 'bcgov-design-system' ) }
						</div>
						{ /* File type column */ }
						<div
							className="document-table-cell header"
							role="columnheader"
						>
							{ __( 'Type', 'bcgov-design-system' ) }
						</div>
						{ /* Revisions column */ }
						<div
							className="document-table-cell header"
							role="columnheader"
						>
							{ __( 'Revisions', 'bcgov-design-system' ) }
						</div>
						{ /* Actions column */ }
						<div
							className="document-table-cell header"
							role="columnheader"
						>
							{ __( 'Actions', 'bcgov-design-system' ) }
						</div>
					</div>
				</div>

				{ /* Table body containing document rows */ }
				<div className="document-table-body" role="rowgroup">
					{ documents.map( ( document ) => (
						<SafeRender key={ document.id }>
							<DocumentTableRow
								document={ document }
								isSelected={ selectedDocuments.includes(
									document.id
								) }
								onSelect={ onSelectDocument }
								onDelete={ onDelete }
								onEdit={ onEdit }
								onRestore={ onRestore }
								isDeleting={ isDeleting }
								metadataFields={ metadataFields }
								isSpreadsheetMode={ isSpreadsheetMode }
								bulkEditedMetadata={ bulkEditedMetadata }
								onMetadataChange={ onMetadataChange }
								formatFileSize={ formatFileSize }
								documentStatusFilter={ documentStatusFilter }
								excerpt={ document.excerpt }
								searchTerm={ searchTerm }
							/>
						</SafeRender>
					) ) }
				</div>
			</div>
		</div>
	);
}

export { DocumentTable as default };
