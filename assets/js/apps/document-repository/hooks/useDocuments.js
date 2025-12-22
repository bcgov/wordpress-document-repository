/**
 * useDocuments
 *
 * React hook for the Document Repository admin interface.
 * Features paginated loading, manual search, accurate tab counts,
 * and support for trash/restore/permanent delete operations.
 *
 * @return {Object} Hook return value
 * @return {Array} return.documents - Current page of documents
 * @return {number} return.totalDocuments - Total number of documents
 * @return {number} return.currentPage - Current page number (1-indexed)
 * @return {number} return.totalPages - Total number of pages
 * @return {Object} return.statusCounts - Status counts for tabs: { all: number, trash: number, ... }
 * @return {boolean} return.isLoading - Whether documents are currently being fetched
 * @return {boolean} return.isDeleting - Whether a delete/trash/restore operation is in progress
 * @return {string|null} return.error - Error message if an operation failed
 * @return {string} return.searchTerm - Current active search term
 * @return {string} return.searchInput - Current search input value
 * @return {Function} return.setSearchInput - Setter for search input
 * @return {Function} return.performSearch - Execute search
 * @return {Function} return.handleSearchKeyPress - Handle Enter key for search
 * @return {Function} return.setSearchParams - Setter for search parameters
 * @return {Function} return.fetchDocuments - Refresh documents and status counts
 * @return {Function} return.deleteDocument - Permanently delete a document
 * @return {Function} return.trashDocument - Move a document to trash
 * @return {Function} return.restoreDocument - Restore a document from trash
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

export const useDocuments = () => {
	// ===================================================================
	// Visible table data
	// ===================================================================
	const [ documents, setDocuments ] = useState( [] ); // Current page of documents
	const [ totalDocuments, setTotalDocuments ] = useState( 0 ); // Total after filtering
	const [ currentPage, setCurrentPage ] = useState( 1 );
	const [ totalPages, setTotalPages ] = useState( 1 );
	const [ statusCounts, setStatusCounts ] = useState( {} ); // { all: 123, trash: 5, ... }

	// ===================================================================
	// UI state
	// ===================================================================
	const [ searchTerm, setSearchTerm ] = useState( '' ); // Active search term
	const [ searchInput, setSearchInput ] = useState( '' ); // Search input value
	const [ isLoading, setIsLoading ] = useState( false );
	const [ isDeleting, setIsDeleting ] = useState( false );
	const [ error, setError ] = useState( null );

	// ===================================================================
	// Table parameters
	// ===================================================================
	const [ searchParams, setSearchParams ] = useState( {
		page: 1,
		per_page: 100, // Fixed at 100 documents per page
		orderby: 'date',
		order: 'DESC',
		status: 'all',
		search: '', // Manual search term
	} );

	const { apiNamespace } = window.documentRepositorySettings || {};

	// ===================================================================
	// Load documents for current page
	// ===================================================================
	const loadDocuments = useCallback( async () => {
		if ( ! apiNamespace ) {
			setError( 'API namespace missing' );
			return;
		}

		setIsLoading( true );
		setError( null );

		try {
			const params = new URLSearchParams( {
				per_page: String( searchParams.per_page ),
				page: String( searchParams.page ),
				orderby: searchParams.orderby,
				order: searchParams.order,
				status: searchParams.status,
			} );

			// Only add search if it's set (manual search)
			if ( searchParams.search && searchParams.search.trim() ) {
				params.append( 'search', searchParams.search.trim() );
			}

			const response = await apiFetch( {
				path: `/${ apiNamespace }/documents?${ params }`,
			} );

			setDocuments( response?.documents || [] );
			setTotalDocuments( response?.total || 0 );
			setTotalPages( response?.total_pages || 1 );

			// Update status counts from the response
			if ( response?.status_counts ) {
				setStatusCounts( response.status_counts );
			}
		} catch ( err ) {
			setError( err.message || 'Failed to load documents' );
			setDocuments( [] );
			setTotalDocuments( 0 );
			setTotalPages( 1 );
			// Don't change currentPage on error - keep it synchronized with searchParams.page
		} finally {
			setIsLoading( false );
		}
	}, [ apiNamespace, searchParams ] );

	// ===================================================================
	// Load documents when searchParams change
	// ===================================================================
	useEffect( () => {
		loadDocuments();
	}, [ loadDocuments ] );

	// ===================================================================
	// Keep currentPage synchronized with searchParams.page
	// ===================================================================
	useEffect( () => {
		setCurrentPage( searchParams.page );
	}, [ searchParams.page ] );

	// ===================================================================
	// Keep searchTerm synchronized with searchParams.search
	// ===================================================================
	useEffect( () => {
		setSearchTerm( searchParams.search || '' );
	}, [ searchParams.search ] );

	// ===================================================================
	// Initialize search input from searchParams
	// ===================================================================
	useEffect( () => {
		if ( searchParams.search ) {
			setSearchInput( searchParams.search );
		}
	}, [ searchParams.search ] );

	// ===================================================================
	// Manual search function
	// ===================================================================
	const performSearch = useCallback( () => {
		const trimmedSearch = searchInput.trim();
		setSearchTerm( trimmedSearch );
		setSearchParams( ( prev ) => ( {
			...prev,
			search: trimmedSearch,
			page: 1, // Reset to first page on new search
		} ) );
	}, [ searchInput ] );

	// ===================================================================
	// Handle search input changes (just update input, don't search yet)
	// ===================================================================
	const handleSearchInputChange = useCallback( ( value ) => {
		setSearchInput( value );
	}, [] );

	// ===================================================================
	// Handle search on Enter key
	// ===================================================================
	const handleSearchKeyPress = useCallback(
		( event ) => {
			if ( event.key === 'Enter' ) {
				performSearch();
			}
		},
		[ performSearch ]
	);

	// ===================================================================
	// Refresh documents and status counts
	// ===================================================================
	const fetchDocuments = useCallback( async () => {
		setSearchParams( ( prev ) => ( { ...prev } ) ); // Trigger reload
	}, [] );

	// ===================================================================
	// Document actions
	// ===================================================================

	/**
	 * Permanently delete a document
	 * @param {number|string} id Document ID
	 * @return {Promise<boolean>} Success
	 */
	const deleteDocument = async ( id ) => {
		setIsDeleting( true );
		try {
			await apiFetch( {
				path: `/${ apiNamespace }/documents/${ id }?force=true`,
				method: 'DELETE',
			} );
			await fetchDocuments();
			return true;
		} catch {
			setError( 'Failed to permanently delete document' );
			return false;
		} finally {
			setIsDeleting( false );
		}
	};

	/**
	 * Move document to trash
	 * @param {number|string} id Document ID
	 * @return {Promise<boolean>} Success
	 */
	const trashDocument = async ( id ) => {
		setIsDeleting( true );
		try {
			await apiFetch( {
				path: `/${ apiNamespace }/documents/${ id }?force=false`,
				method: 'DELETE',
			} );
			await fetchDocuments();
			return true;
		} catch {
			setError( 'Failed to move document to trash' );
			return false;
		} finally {
			setIsDeleting( false );
		}
	};

	/**
	 * Restore document from trash
	 * @param {number|string} id Document ID
	 * @return {Promise<boolean>} Success
	 */
	const restoreDocument = async ( id ) => {
		setIsDeleting( true );
		try {
			await apiFetch( {
				path: `/${ apiNamespace }/documents/${ id }/restore`,
				method: 'POST',
			} );
			await fetchDocuments();
			return true;
		} catch {
			setError( 'Failed to restore document' );
			return false;
		} finally {
			setIsDeleting( false );
		}
	};

	// ===================================================================
	// Return hook API
	// ===================================================================
	return {
		// Document data
		documents,
		totalDocuments,
		currentPage,
		totalPages,
		statusCounts,

		// Loading & error states
		isLoading,
		isDeleting,
		error,

		// Search controls
		searchTerm,
		searchInput,
		setSearchInput: handleSearchInputChange,
		performSearch,
		handleSearchKeyPress,

		// Actions
		setSearchParams,
		fetchDocuments,
		deleteDocument,
		trashDocument,
		restoreDocument,
	};
};
