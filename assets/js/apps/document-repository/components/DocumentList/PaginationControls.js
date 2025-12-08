import { Button, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * PaginationControls Component
 *
 * Renders pagination controls for navigating through document pages
 *
 * @param {Object}   props
 * @param {number}   props.currentPage  - Current page number
 * @param {number}   props.totalPages   - Total number of pages
 * @param {Function} props.onPageChange - Callback when page changes
 * @param {boolean}  props.isLoading    - Whether data is currently being loaded
 */
const PaginationControls = ( {
	currentPage,
	totalPages,
	onPageChange,
	isLoading = false,
} ) => {
	if ( totalPages <= 1 ) {
		return null;
	}

	// Generate page numbers to display
	const getPageNumbers = () => {
		const pages = [];
		const maxVisiblePages = 5; // Show up to 5 page numbers

		if ( totalPages <= maxVisiblePages ) {
			// Show all pages if total is small
			for ( let i = 1; i <= totalPages; i++ ) {
				pages.push( i );
			}
		} else {
			// Calculate start page - show pages around current page
			const half = Math.floor( maxVisiblePages / 2 );
			let start = Math.max( 1, currentPage - half );
			let end = Math.min( totalPages, start + maxVisiblePages - 1 );

			// If we're near the end, adjust start to show max pages
			if ( end === totalPages ) {
				start = Math.max( 1, end - maxVisiblePages + 1 );
			}

			// If we're near the beginning, adjust end to show max pages
			if ( start === 1 ) {
				end = Math.min( totalPages, start + maxVisiblePages - 1 );
			}

			for ( let i = start; i <= end; i++ ) {
				pages.push( i );
			}
		}

		return pages;
	};

	const pageNumbers = getPageNumbers();

	return (
		<div className="pagination">
			{ isLoading && (
				<div className="pagination-loading">
					<Spinner />
				</div>
			) }
			<Button
				onClick={ () => onPageChange( 1 ) }
				disabled={ currentPage === 1 || isLoading }
				className="pagination-button-first"
				aria-label={ __( 'First page', 'bcgov-design-system' ) }
			>
				«
			</Button>

			<Button
				onClick={ () => onPageChange( currentPage - 1 ) }
				disabled={ currentPage === 1 || isLoading }
				className="pagination-button-prev"
				aria-label={ __( 'Previous page', 'bcgov-design-system' ) }
			>
				‹
			</Button>

			{ pageNumbers.map( ( page ) => (
				<Button
					key={ page }
					onClick={ () => onPageChange( page ) }
					className={
						page === currentPage
							? 'current-page'
							: 'pagination-button-number'
					}
					aria-label={
						page === currentPage
							? `Current page, page ${ page }`
							: `Go to page ${ page }`
					}
					disabled={ page === currentPage || isLoading }
				>
					{ page }
				</Button>
			) ) }

			<span className="pagination-page-indicator">
				{ sprintf(
					/* translators: %1$d: current page, %2$d: total pages */
					__( '%1$d of %2$d', 'bcgov-design-system' ),
					currentPage,
					totalPages
				) }
			</span>

			<Button
				onClick={ () => onPageChange( currentPage + 1 ) }
				disabled={ currentPage === totalPages || isLoading }
				className="pagination-button-next"
				aria-label={ __( 'Next page', 'bcgov-design-system' ) }
			>
				›
			</Button>

			<Button
				onClick={ () => onPageChange( totalPages ) }
				disabled={ currentPage === totalPages || isLoading }
				className="pagination-button-last"
				aria-label={ __( 'Last page', 'bcgov-design-system' ) }
			>
				»
			</Button>
		</div>
	);
};

export default PaginationControls;
