import { useState, useRef, useCallback } from '@wordpress/element';
import {
	Button,
	FormFileUpload,
	Notice,
	Card,
	CardHeader,
	CardBody,
	CardFooter,
	Spinner,
	TextareaControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * CSV Bulk Uploader Component
 *
 * Allows users to upload CSV files to bulk tag existing documents
 * with metadata based on CSV content.
 */
const CsvBulkUploader = ({ onUploadSuccess, modalMode = false }) => {
	const [ csvFile, setCsvFile ] = useState( null );
	const [ isProcessing, setIsProcessing ] = useState( false );
	const [ results, setResults ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ processingOptions, setProcessingOptions ] = useState( {
		overwriteExisting: false,
		createMissingTerms: true,
	} );

	const fileInputRef = useRef( null );

	// Get settings from WordPress
	const { apiNamespace } = window.documentRepositorySettings;

	/**
	 * Handle CSV file selection
	 */
	const handleFileChange = ( event ) => {
		const file = event.target.files[ 0 ];
		if ( file && file.type === 'text/csv' ) {
			setCsvFile( file );
			setError( null );
			setResults( null );
		} else {
			setError( 'Please select a valid CSV file.' );
			setCsvFile( null );
		}
	};

	/**
	 * Download CSV template
	 */
	const downloadTemplate = async () => {
		try {
			const response = await fetch(
				`${ window.documentRepositorySettings.apiRoot }${ apiNamespace }/csv-bulk-upload/template`,
				{
					headers: {
						'X-WP-Nonce': window.documentRepositorySettings.nonce,
					},
				}
			);

			if ( response.ok ) {
				const blob = await response.blob();
				const url = window.URL.createObjectURL( blob );
				const a = document.createElement( 'a' );
				a.href = url;
				a.download = 'document-tagging-template.csv';
				document.body.appendChild( a );
				a.click();
				window.URL.revokeObjectURL( url );
				document.body.removeChild( a );
			} else {
				setError( 'Failed to download template.' );
			}
		} catch ( err ) {
			setError( 'Failed to download template: ' + err.message );
		}
	};

	/**
	 * Process CSV upload
	 */
	const processCsvUpload = async () => {
		if ( ! csvFile ) {
			setError( 'Please select a CSV file first.' );
			return;
		}

		setIsProcessing( true );
		setError( null );

		const formData = new FormData();
		formData.append( 'csv_file', csvFile );
		formData.append( 'options', JSON.stringify( processingOptions ) );
		formData.append( '_wpnonce', window.documentRepositorySettings.nonce );

		try {
			// Create AbortController for timeout
			const controller = new AbortController();
			const timeoutId = setTimeout( () => controller.abort(), 300000 ); // 5 minutes timeout

			const response = await fetch(
				`${ window.documentRepositorySettings.apiRoot }${ apiNamespace }/csv-bulk-upload`,
				{
					method: 'POST',
					headers: {
						'X-WP-Nonce': window.documentRepositorySettings.nonce,
					},
					body: formData,
					signal: controller.signal,
				}
			);

			clearTimeout( timeoutId );

			// Check if response is OK
			if ( ! response.ok ) {
				// Handle 504 Gateway Timeout specifically
				if ( response.status === 504 ) {
					throw new Error(
						'Request timed out. The CSV file may be too large or the server is taking too long to process. Please try processing a smaller batch or contact your administrator.'
					);
				}

				// Try to parse error as JSON, but handle HTML error pages
				let errorData;
				const contentType = response.headers.get( 'content-type' );
				if ( contentType && contentType.includes( 'application/json' ) ) {
					try {
						errorData = await response.json();
					} catch ( e ) {
						errorData = { message: `Server error (${ response.status })` };
					}
				} else {
					// Response is HTML (like a 504 error page)
					const text = await response.text();
					if ( response.status === 504 ) {
						errorData = {
							message:
								'Gateway timeout. The server took too long to process your CSV file. Please try processing a smaller batch or contact your administrator.',
						};
					} else {
						errorData = {
							message: `Server error (${ response.status }). Please try again or contact your administrator.`,
						};
					}
				}
				throw new Error( errorData.message || 'Upload failed' );
			}

			const data = await response.json();
			setResults( data );

			if ( onUploadSuccess ) {
				onUploadSuccess( data );
			}
		} catch ( err ) {
			if ( err.name === 'AbortError' ) {
				setError(
					'Request timed out after 5 minutes. The CSV file may be too large. Please try processing a smaller batch or contact your administrator.'
				);
			} else {
				setError( err.message || 'Failed to process CSV file.' );
			}
		} finally {
			setIsProcessing( false );
		}
	};

	/**
	 * Reset form
	 */
	const resetForm = () => {
		setCsvFile( null );
		setResults( null );
		setError( null );
		if ( fileInputRef.current ) {
			fileInputRef.current.value = '';
		}
	};

	/**
	 * Render results summary
	 */
	const renderResults = () => {
		if ( ! results ) return null;

		return (
			<Notice status="success" isDismissible={ false }>
				<h4>{ __( 'CSV Processing Complete!', 'wordpress-document-repository' ) }</h4>
				<ul>
					<li>
						{ sprintf(
							/* translators: %d: number of rows */
							__( 'Total rows processed: %d', 'wordpress-document-repository' ),
							results.total_rows
						) }
					</li>
					<li>
						{ sprintf(
							/* translators: %d: number of documents */
							__( 'Documents found and tagged: %d', 'wordpress-document-repository' ),
							results.documents_found
						) }
					</li>
					<li>
						{ sprintf(
							/* translators: %d: number of tags */
							__( 'Successful tags applied: %d', 'wordpress-document-reliable' ),
							results.successful_tags
						) }
					</li>
					{ results.documents_not_found > 0 && (
						<li>
							{ sprintf(
								/* translators: %d: number of documents */
								__( 'Documents not found: %d', 'wordpress-document-repository' ),
								results.documents_not_found
							) }
						</li>
					) }
					{ results.failed_tags > 0 && (
						<li>
							{ sprintf(
								/* translators: %d: number of failed tags */
								__( 'Failed tags: %d', 'wordpress-document-repository' ),
								results.failed_tags
							) }
						</li>
					) }
				</ul>
				{ results.errors && results.errors.length > 0 && (
					<div>
						<h5>{ __( 'Errors:', 'wordpress-document-repository' ) }</h5>
						<ul>
							{ results.errors.map( ( error, index ) => (
								<li key={ index }>
									{ sprintf(
										/* translators: 1: row number, 2: error message */
										__( 'Row %1$d: %2$s', 'wordpress-document-repository' ),
										error.row,
										error.message
									) }
								</li>
							) ) }
						</ul>
					</div>
				) }
			</Notice>
		);
	};

	// Modal mode layout
	if ( modalMode ) {
		return (
			<div className="csv-bulk-uploader-modal">
				{ error && (
					<Notice
						status="error"
						isDismissible={ true }
						onRemove={ () => setError( null ) }
					>
						{ error }
					</Notice>
				) }

				{ results && renderResults() }

				<div className="csv-upload-section">
					<h4>{ __( 'Upload CSV File', 'wordpress-document-repository' ) }</h4>
					<FormFileUpload
						accept=".csv,text/csv"
						onChange={ handleFileChange }
						ref={ fileInputRef }
					>
						{ __( 'Choose CSV File', 'wordpress-document-repository' ) }
					</FormFileUpload>
					{ csvFile && (
						<p className="selected-file">
							{ __( 'Selected:', 'wordpress-document-repository' ) } { csvFile.name }
						</p>
					) }
				</div>

				<div className="csv-options-section">
					<h4>{ __( 'Processing Options', 'wordpress-document-repository' ) }</h4>
					<label>
						<input
							type="checkbox"
							checked={ processingOptions.createMissingTerms }
							onChange={ ( e ) =>
								setProcessingOptions( ( prev ) => ( {
									...prev,
									createMissingTerms: e.target.checked,
								} ) )
							}
						/>
						{ __( 'Create missing taxonomy terms automatically', 'wordpress-document-repository' ) }
					</label>
				</div>

				<div className="modal-actions">
					<Button
						isPrimary
						onClick={ processCsvUpload }
						disabled={ ! csvFile || isProcessing }
					>
						{ isProcessing ? (
							<>
								<Spinner />
								{ __( 'Processing...', 'wordpress-document-repository' ) }
							</>
						) : (
							__( 'Process CSV', 'wordpress-document-repository' )
						) }
					</Button>
					{ results && (
						<Button onClick={ resetForm }>
							{ __( 'Upload Another', 'wordpress-document-repository' ) }
						</Button>
					) }
				</div>
			</div>
		);
	}

	// Full card layout
	return (
		<Card className="csv-bulk-uploader">
			<CardHeader>
				<h3>{ __( 'CSV Bulk Upload & Tagging', 'wordpress-document-repository' ) }</h3>
			</CardHeader>

			<CardBody>
				{ error && (
					<Notice
						status="error"
						isDismissible={ true }
						onRemove={ () => setError( null ) }
					>
						{ error }
					</Notice>
				) }

				{ results && renderResults() }

				<div className="csv-info">
					<p>
						{ __(
							'Upload a CSV file to automatically tag existing documents with metadata. The CSV should have a column for document titles/names and columns for each metadata field you want to apply.',
							'wordpress-document-repository'
						) }
					</p>
					<Button isSecondary onClick={ downloadTemplate }>
						{ __( 'Download CSV Template', 'wordpress-document-repository' ) }
					</Button>
				</div>

				<div className="csv-upload-section">
					<h4>{ __( 'Upload CSV File', 'wordpress-document-repository' ) }</h4>
					<FormFileUpload
						accept=".csv,text/csv"
						onChange={ handleFileChange }
						ref={ fileInputRef }
					>
						{ __( 'Choose CSV File', 'wordpress-document-repository' ) }
					</FormFileUpload>
					{ csvFile && (
						<p className="selected-file">
							{ __( 'Selected:', 'wordpress-document-repository' ) } { csvFile.name }
						</p>
					) }
				</div>

				<div className="csv-options-section">
					<h4>{ __( 'Processing Options', 'wordpress-document-repository' ) }</h4>
					<label>
						<input
							type="checkbox"
							checked={ processingOptions.createMissingTerms }
							onChange={ ( e ) =>
								setProcessingOptions( ( prev ) => ( {
									...prev,
									createMissingTerms: e.target.checked,
								} ) )
							}
						/>
						{ __( 'Create missing taxonomy terms automatically', 'wordpress-document-repository' ) }
					</label>
				</div>
			</CardBody>

			<CardFooter>
				<div className="card-actions">
					<Button
						isPrimary
						onClick={ processCsvUpload }
						disabled={ ! csvFile || isProcessing }
					>
						{ isProcessing ? (
							<>
								<Spinner />
								{ __( 'Processing...', 'wordpress-document-repository' ) }
							</>
						) : (
							__( 'Process CSV', 'wordpress-document-repository' )
						) }
					</Button>
					{ results && (
						<Button onClick={ resetForm }>
							{ __( 'Upload Another', 'wordpress-document-repository' ) }
						</Button>
					) }
				</div>
			</CardFooter>
		</Card>
	);
};

export default CsvBulkUploader; 