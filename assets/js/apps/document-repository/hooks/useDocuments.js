/**
 * useDocuments
 *
 * High-performance React hook for the Document Repository admin interface.
 * Features instant client-side search, accurate tab counts (All / Trash / etc.),
 * full support for trash/restore/permanent delete, and zero unnecessary API calls.
 *
 * @return {Object} Hook return value
 * @return {Array} return.documents - Current page of documents after filtering, sorting, and pagination
 * @return {number} return.totalDocuments - Total number of documents after filtering
 * @return {number} return.currentPage - Current page number (1-indexed)
 * @return {number} return.totalPages - Total number of pages
 * @return {Object} return.statusCounts - Status counts for tabs: { all: number, trash: number, ... }
 * @return {boolean} return.isLoading - Whether documents are currently being fetched
 * @return {boolean} return.isDeleting - Whether a delete/trash/restore operation is in progress
 * @return {string|null} return.error - Error message if an operation failed
 * @return {string} return.searchTerm - Current search term for client-side filtering
 * @return {Function} return.setSearchTerm - Setter for search term
 * @return {Object} return.searchParams - Search parameters: { page, per_page, orderby, order, status }
 * @return {Function} return.setSearchParams - Setter for search parameters
 * @return {Function} return.fetchDocuments - Refresh documents and status counts
 * @return {Function} return.deleteDocument - Permanently delete a document
 * @return {Function} return.trashDocument - Move a document to trash
 * @return {Function} return.restoreDocument - Restore a document from trash
 */
import {
	useState,
	useEffect,
	useCallback,
	useMemo,
	useRef,
} from '@wordpress/element';
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
	// Internal cache & loading state
	// ===================================================================
	const [ allDocumentsCache, setAllDocumentsCache ] = useState( [] );
	const [ isCacheLoaded, setIsCacheLoaded ] = useState( false );

	// ===================================================================
	// UI state
	// ===================================================================
	const [ searchTerm, setSearchTerm ] = useState( '' );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ isDeleting, setIsDeleting ] = useState( false );
	const [ error, setError ] = useState( null );

	// ===================================================================
	// Table parameters
	// ===================================================================
	const [ searchParams, setSearchParams ] = useState( {
		page: 1,
		per_page: window.documentRepositorySettings?.perPage || 20,
		orderby: 'date',
		order: 'DESC',
		status: 'all',
	} );

	const { apiNamespace } = window.documentRepositorySettings || {};
	const isFirstRender = useRef( true );

	// ===================================================================
	// Refresh global status counts (used for tabs) – fetched from status=all
	// ===================================================================
	/**
	 * Refresh global status counts for tabs (All, Trash, etc.)
	 * Fetches a minimal request to get status_counts from the API
	 * @return {Promise<void>}
	 */
	const refreshStatusCounts = useCallback( async () => {
		if ( ! apiNamespace ) return;

		try {
			const response = await apiFetch( {
				path: `/${ apiNamespace }/documents?per_page=1&page=1&status=all`,
			} );
			if ( response?.status_counts ) {
				setStatusCounts( response.status_counts );
			}
		} catch {
			// Fail silently – counts are not critical
		}
	}, [ apiNamespace ] );

	// ===================================================================
	// Fetch all documents for current status filter (batched)
	// ===================================================================
	/**
	 * Fetch all documents for the current status filter in batches of 100
	 * Updates the internal cache and status counts
	 * @param {string|null} statusOverride Optional status to fetch instead of current filter
	 * @return {Promise<void>}
	 */
	const fetchAllDocuments = useCallback(
		async ( statusOverride = null ) => {
			if ( ! apiNamespace ) {
				setError( 'API namespace missing' );
				return;
			}

			setIsLoading( true );
			setError( null );

			// Determine status to fetch
			let statusToFetch = null;
			if ( statusOverride !== null ) {
				statusToFetch = statusOverride;
			} else if ( searchParams.status && searchParams.status !== 'all' ) {
				statusToFetch = searchParams.status;
			}

			try {
				const allDocs = [];
				let page = 1;
				let counts = {};

				while ( true ) {
					const params = new URLSearchParams( {
						per_page: '100',
						page: String( page ),
					} );
					if ( statusToFetch )
						params.append( 'status', statusToFetch );

					const response = await apiFetch( {
						path: `/${ apiNamespace }/documents?${ params }`,
					} );

					if ( ! response?.documents?.length ) break;

					allDocs.push( ...response.documents );

					if ( page === 1 && response.status_counts ) {
						counts = response.status_counts;
					}

					if ( response.documents.length < 100 ) break;
					page++;
				}

				setAllDocumentsCache( allDocs );
				setStatusCounts( counts );
				setIsCacheLoaded( true );
			} catch ( err ) {
				setError( err.message || 'Failed to load documents' );
				setAllDocumentsCache( [] );
				setIsCacheLoaded( false );
			} finally {
				setIsLoading( false );
			}
		},
		[ apiNamespace, searchParams.status ]
	);

	// ===================================================================
	// Initial load – only once
	// ===================================================================
	useEffect( () => {
		if ( isFirstRender.current ) {
			isFirstRender.current = false;
			fetchAllDocuments();
		}
	}, [ fetchAllDocuments ] );

	// ===================================================================
	// Reload cache when status filter changes (All → Trash, etc.)
	// ===================================================================
	useEffect( () => {
		if ( ! isFirstRender.current ) {
			setIsCacheLoaded( false );
			fetchAllDocuments();
		}
	}, [ searchParams.status, fetchAllDocuments ] );

	// ===================================================================
	// Client-side search → sort → paginate (instant, no API calls)
	// ===================================================================
	const processed = useMemo( () => {
		if ( ! isCacheLoaded || ! allDocumentsCache.length ) {
			return { docs: [], total: 0, totalPages: 1 };
		}

		let list = allDocumentsCache;

		// Search
		if ( searchTerm.trim() ) {
			const term = searchTerm.toLowerCase().trim();
			list = allDocumentsCache.filter( ( doc ) => {
				const titleMatch = ( doc.title || '' )
					.toLowerCase()
					.includes( term );
				const excerptMatch = ( doc.excerpt || '' )
					.toLowerCase()
					.includes( term );
				const metaMatch = doc.metadata
					? Object.values( doc.metadata ).some( ( v ) =>
							String( v || '' )
								.toLowerCase()
								.includes( term )
					  )
					: false;
				return titleMatch || excerptMatch || metaMatch;
			} );
		}

		// Sort
		const sorted = [ ...list ].sort( ( a, b ) => {
			let aVal =
				searchParams.orderby === 'title' ? a.title || '' : a.date || 0;
			let bVal =
				searchParams.orderby === 'title' ? b.title || '' : b.date || 0;

			if ( searchParams.orderby === 'title' ) {
				aVal = aVal.toLowerCase();
				bVal = bVal.toLowerCase();
			} else {
				aVal = new Date( aVal );
				bVal = new Date( bVal );
			}

			const order = searchParams.order === 'ASC' ? 1 : -1;
			return ( aVal > bVal ? 1 : -1 ) * order;
		} );

		// Paginate
		const perPage = searchParams.per_page || 20;
		const page = searchParams.page || 1;
		const start = ( page - 1 ) * perPage;
		const docs = sorted.slice( start, start + perPage );

		return {
			docs,
			total: sorted.length,
			totalPages: Math.ceil( sorted.length / perPage ) || 1,
		};
	}, [ allDocumentsCache, searchTerm, searchParams, isCacheLoaded ] );

	// Apply processed data to visible state
	useEffect( () => {
		setDocuments( processed.docs );
		setTotalDocuments( processed.total );
		setTotalPages( processed.totalPages );
		setCurrentPage( searchParams.page || 1 );

		if ( processed.docs.length === 0 && searchParams.page > 1 ) {
			setSearchParams( ( prev ) => ( { ...prev, page: 1 } ) );
		}
	}, [ processed, searchParams.page ] );

	// Reset to page 1 on search
	useEffect( () => {
		setSearchParams( ( prev ) => ( { ...prev, page: 1 } ) );
	}, [ searchTerm ] );

	// ===================================================================
	// Universal refresh: reload current view + update tab counts
	// ===================================================================
	/**
	 * Refresh the document cache and status counts
	 * Reloads all documents for the current status filter and updates tab counts
	 * @return {Promise<void>}
	 */
	const refresh = useCallback( async () => {
		setIsCacheLoaded( false );
		await fetchAllDocuments();
		await refreshStatusCounts();
	}, [ fetchAllDocuments, refreshStatusCounts ] );

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
			await refresh();
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
			await refresh();
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
			await refresh();
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
		/** @type {Array} Current page of documents after filtering, sorting, and pagination */
		documents,
		/** @type {number} Total number of documents after filtering (before pagination) */
		totalDocuments,
		/** @type {number} Current page number (1-indexed) */
		currentPage,
		/** @type {number} Total number of pages */
		totalPages,
		/** @type {Object} Status counts for tabs: { all: number, trash: number, ... } */
		statusCounts,

		// Loading & error states
		/** @type {boolean} Whether documents are currently being fetched */
		isLoading,
		/** @type {boolean} Whether a delete/trash/restore operation is in progress */
		isDeleting,
		/** @type {string|null} Error message if an operation failed */
		error,

		// Search & filter controls
		/** @type {string} Current search term for client-side filtering */
		searchTerm,
		/** @type {Function} Setter for search term */
		setSearchTerm,
		/** @type {Object} Search parameters: { page, per_page, orderby, order, status } */
		searchParams,
		/** @type {Function} Setter for search parameters */
		setSearchParams,

		// Actions
		/** @type {Function} Refresh documents and status counts */
		fetchDocuments: refresh,
		/** @type {Function} Permanently delete a document */
		deleteDocument,
		/** @type {Function} Move a document to trash */
		trashDocument,
		/** @type {Function} Restore a document from trash */
		restoreDocument,
	};
};
