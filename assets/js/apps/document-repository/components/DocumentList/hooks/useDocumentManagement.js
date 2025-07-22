import { useState, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { isTrashView } from '../../../utils/documentStatus';

/**
 * Custom hook for document management operations
 *
 * @param {Object}   options                      - Options for the hook
 * @param {Function} options.onDelete             - Function to delete a document
 * @param {Function} options.onTrash              - Function to move a document to the trash
 * @param {Function} options.onRestore            - Function to restore a document from the trash
 * @param {string}   options.documentStatusFilter - Current document view status ('publish', 'trash', etc.)
 * @param {Function} options.onSelectAll          - Function to select/deselect all documents
 * @param {Function} options.onShowNotification   - Function to show notifications
 * @param {Function} options.onError              - Function to handle errors
 * @return {Object} Document management state and functions
 */
const useDocumentManagement = ( {
	onDelete,
	onTrash,
	onRestore,
	documentStatusFilter,
	onSelectAll,
	onShowNotification,
	onError,
} ) => {
	const [ deleteDocument, setDeleteDocument ] = useState( null );
	const [ restoreDocument, setRestoreDocument ] = useState( null );
	const [ bulkDeleteConfirmOpen, setBulkDeleteConfirmOpen ] = useState( false );
	const [ bulkRestoreConfirmOpen, setBulkRestoreConfirmOpen ] = useState( false );
	const [ isMultiDeleting, setIsMultiDeleting ] = useState( false );
	const [ isMultiRestoring, setIsMultiRestoring ] = useState( false );

	/**
	 * Handle bulk deletion of documents
	 * @param {Array} selectedDocuments Array of document IDs to delete
	 */
	const handleBulkDelete = useCallback(
		async ( selectedDocuments ) => {
			if ( ! selectedDocuments || selectedDocuments.length === 0 ) {
				return;
			}

			setIsMultiDeleting( true );

			try {
				await Promise.all(
                    selectedDocuments.map( ( docId ) =>
                        isTrashView( documentStatusFilter )
                            ? onDelete( docId )
                            : onTrash( docId )
                    )
                );
				setBulkDeleteConfirmOpen( false );
				onSelectAll( false );

				if ( onShowNotification ) {
                    onShowNotification(
                        'success',
                        __(
                            isTrashView( documentStatusFilter )
                                ? 'Selected documents were deleted successfully.'
                                : 'Selected documents were trashed successfully.',
                            'bcgov-design-system'
                        )
                    );
                }
			} catch ( error ) {
                if ( onError ) {
                    onError( 'bulk-delete', null, error, {
                        addToRetryQueue: false,
                        customMessage: __(
                            isTrashView( documentStatusFilter )
                                ? 'Error deleting one or more documents.'
                                : 'Error trashing one or more documents.',
                            'bcgov-design-system'
                        ),
                    } );
                }
            } finally {
				setIsMultiDeleting( false );
			}
		},
		[ onDelete, onTrash, documentStatusFilter, onSelectAll, onShowNotification, onError ]
	);

	/**
	 * Trash or Delete single document
	 * @param {number} documentId Document ID to delete
	 */
	const handleSingleDelete = useCallback(
		async ( documentId ) => {
			try {
				if ( isTrashView( documentStatusFilter ) ) {
                    await onDelete( documentId );
                } else {
                    await onTrash( documentId );
                }
				setDeleteDocument( null );

				if ( onShowNotification ) {
                    onShowNotification(
                        'success',
                        __(
                            isTrashView( documentStatusFilter )
                                ? 'Document deleted successfully.'
                                : 'Document trashed successfully.',
                            'bcgov-design-system'
                        )
                    );
                }
			} catch ( error ) {
				if ( onError ) {
					onError( 'delete', documentId, error, {
						customMessage: sprintf(
							/* translators: %1$d: document ID, %2$s: error message */
							__(
                                isTrashView( documentStatusFilter )
                                    ? 'Error deleting document %1$d: %2$s'
                                    : 'Error trashing document %1$d: %2$s',
                                'bcgov-design-system'
                            ),
							documentId,
							error.message ||
								__(
									'An unknown error occurred',
									'bcgov-design-system'
								)
						),
					} );
				}
			}
		},
		[ onDelete, onTrash, documentStatusFilter, onShowNotification, onError ]
	);

	/**
	 * Handle bulk restore of trashed documents
	 * @param {Array} selectedDocuments Array of document IDs to restore
	 */
	const handleBulkRestore = useCallback(
		async ( selectedDocuments ) => {
			if ( ! selectedDocuments || selectedDocuments.length === 0 ) {
				return;
			}

			setIsMultiRestoring( true );

			try {
				await Promise.all( selectedDocuments.map( onRestore ) );

				setBulkRestoreConfirmOpen( false );
				onSelectAll( false );

				if ( onShowNotification ) {
					onShowNotification(
						'success',
						__(
							'Selected documents were restored successfully.',
							'bcgov-design-system'
						)
					);
				}
			} catch ( error ) {
				if ( onError ) {
					onError( 'bulk-restore', null, error, {
						addToRetryQueue: false,
						customMessage: __(
							'Error restoring one or more documents.',
							'bcgov-design-system'
						),
					} );
				}
			} finally {
				setIsMultiRestoring( false );
			}
		},
		[ onRestore, onSelectAll, onShowNotification, onError ]
	);

	/**
	 * Restore single document
	 * @param {number} documentId Document ID to restore
	 */
	const handleSingleRestore = useCallback(
		async ( documentId ) => {
			try {
				

				await onRestore( documentId );

				setRestoreDocument( null );

				if ( onShowNotification ) {
                    onShowNotification(
                        'success',
                        __( 'Document restored successfully.', 'bcgov-design-system' )
                    );
                }
			} catch ( error ) {
				if ( onError ) {
					onError( 'restore', documentId, error, {
						customMessage: sprintf(
							/* translators: %1$d: document ID, %2$s: error message */
							__( 'Error restoring document %1$d: %2$s', 'bcgov-design-system' ),
							documentId,
							error.message ||
								__(
									'An unknown error occurred',
									'bcgov-design-system'
								)
						),
					} );
				}
			}
		},
		[ onRestore, onShowNotification, onError ]
	);

	/**
	 * Open bulk delete confirmation dialog
	 */
	const openBulkDeleteConfirm = useCallback( () => {
		setBulkDeleteConfirmOpen( true );
	}, [] );

	/**
	 * Close bulk delete confirmation dialog
	 */
	const closeBulkDeleteConfirm = useCallback( () => {
		setBulkDeleteConfirmOpen( false );
	}, [] );

	/**
	 * Open bulk restore confirmation dialog
	 */
	const openBulkRestoreConfirm = useCallback( () => {
		setBulkRestoreConfirmOpen( true );
	}, [] );

	/**
	 * Close bulk restore confirmation dialog
	 */
	const closeBulkRestoreConfirm = useCallback( () => {
		setBulkRestoreConfirmOpen( false );
	}, [] );

	return {
		// State
		deleteDocument,
		restoreDocument,
		bulkDeleteConfirmOpen,
		bulkRestoreConfirmOpen,
		isMultiDeleting,
		isMultiRestoring,

		// Actions
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
	};
};

export default useDocumentManagement;
