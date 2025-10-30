<?php
namespace Bcgov\WordpressDocumentRepository;

/**
 * Class DocumentRevisionManager
 *
 * Tracks document file metadata in WordPress revisions. Currently
 * limited to document attachments (file ID + versions) but can be
 * expanded to custom fields and taxonomies later.
 */
class DocumentRevisionManager {

    /**
     * Initialize hooks for revision tracking.
     *
     * @return void
     */
    public function init(): void {
        add_filter( 'wp_post_revision_meta_keys', [ $this, 'register_meta_keys_for_revisions' ] );
        add_action( 'updated_post_meta', [ $this, 'maybe_create_revision_on_meta' ], 10, 4 );
        add_action( 'wp_restore_post_revision', [ $this, 'restore_revision_meta' ], 10, 2 );
    }

    /**
     * Only track the document file meta keys for now.
     *
     * @param array $keys Existing revision meta keys.
     * @return array Modified revision meta keys including document fields.
     */
    public function register_meta_keys_for_revisions( array $keys ): array {
        $keys[] = 'document_file_id';
        $keys[] = 'document_file_versions';

        return $keys;
    }

    /**
     * Trigger a revision when the tracked meta changes.
     *
     * @param int    $meta_id     Meta ID.
     * @param int    $post_id     Post ID.
     * @param string $meta_key    Meta key.
     * @return void
     */
    public function maybe_create_revision_on_meta( $meta_id, $post_id, $meta_key ): void {
        if ( 'document' !== get_post_type( $post_id ) ) { return;
        }

        $tracked_keys = [ 'document_file_id', 'document_file_versions' ];
        if ( ! in_array( $meta_key, $tracked_keys, true ) ) { return;
        }

        $this->trigger_revision( $post_id );
    }

    /**
     * Build a diffable string of the document attachment for the revision.
     *
     * @param int $post_id Post ID.
     * @return string Human-readable diff string for the attachment.
     */
    public function build_diff_string( int $post_id ): string {
        $file_id   = get_post_meta( $post_id, 'document_file_id', true );
        $file_info = 'No file';

        if ( $file_id ) {
            $file_name = get_the_title( $file_id );
            $file_url  = wp_get_attachment_url( $file_id );
            $file_path = get_attached_file( $file_id );
            $size_str  = '';
            if ( $file_path && file_exists( $file_path ) ) {
                $size_bytes = filesize( $file_path );
                $size_str   = $size_bytes >= 1048576
                    ? number_format_i18n( $size_bytes / 1048576, 1 ) . 'MB'
                    : number_format_i18n( $size_bytes / 1024, 1 ) . 'KB';
            }
            $file_info = sprintf(
                '%s (%s) <%s>',
                $file_name,
                $size_str ? $size_str : '0KB',
                $file_url
            );

        }

        return $file_info;
    }

    /**
     * Update post_content to trigger a revision for the document attachment.
     *
     * @param int $post_id Post ID.
     * @return void
     */
    private function trigger_revision( int $post_id ): void {
        $diff_string  = $this->build_diff_string( $post_id );
        $prev_content = get_post_field( 'post_content', $post_id );
        if ( $prev_content === $diff_string ) { return;
        }

        wp_update_post(
            [
				'ID'           => $post_id,
				'post_content' => $diff_string,
			]
        );
        wp_save_post_revision( $post_id );
    }

    /**
     * Restore document attachment metadata from a revision.
     *
     * @param int $post_id     Post ID to restore.
     * @param int $revision_id Revision ID to restore from.
     * @return void
     */
    public function restore_revision_meta( int $post_id, int $revision_id ): void {
        foreach ( [ 'document_file_id', 'document_file_versions' ] as $key ) {
            $val = get_post_meta( $revision_id, $key, true );
            if ( ! is_null( $val ) ) { update_post_meta( $post_id, $key, $val );
            }
        }
    }
}
