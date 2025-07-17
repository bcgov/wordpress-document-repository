/**
 * Returns true if the current status filter is 'trash'.
 * @param {string} status
 * @returns {boolean}
 */
export function isTrashView( status ) {
    return 'trash' === status;
}

/**
 * Returns true if the current status filter is 'all'.
 * @param {string} status
 * @returns {boolean}
 */
export function isAllView( status ) {
    return 'all' === status;
}
