<?php
/**
 * Lexikon Import/Export Helper
 *
 * Modes:
 *  - Import (default): reads data.txt and creates/updates CPT lexikon entries.
 *  - Export: writes all existing CPT lexikon entries into data.txt format.
 *
 * Browser usage (admin only):
 *  import: /wp-content/plugins/lexikonmanager/import-lexikon-data.php?lm_lexikon_import=1&confirm=1
 *  dry run: /wp-content/plugins/lexikonmanager/import-lexikon-data.php?lm_lexikon_import=1&confirm=1&dry_run=1
 *  export: /wp-content/plugins/lexikonmanager/import-lexikon-data.php?lm_lexikon_export=1&confirm=1
 *
 * CLI usage:
 *  php import-lexikon-data.php --import
 *  php import-lexikon-data.php --import --dry-run
 *  php import-lexikon-data.php --export
 */

if ( ! defined( 'ABSPATH' ) ) {
    $search_dir = __DIR__;
    $attempts   = 0;

    while ( $attempts < 8 ) {
        $candidate = rtrim( $search_dir, '/\\' ) . '/wp-load.php';
        if ( file_exists( $candidate ) ) {
            require_once $candidate;
            break;
        }

        $parent = dirname( $search_dir );
        if ( $parent === $search_dir ) {
            break;
        }

        $search_dir = $parent;
        $attempts++;
    }
}

if ( ! defined( 'ABSPATH' ) ) {
    echo 'WordPress bootstrap failed.' . PHP_EOL;
    exit(1);
}

if ( ! function_exists( 'post_exists' ) ) {
    require_once ABSPATH . 'wp-admin/includes/post.php';
}

function lm_ie_is_cli() {
    return ( defined( 'WP_CLI' ) && WP_CLI ) || ( PHP_SAPI === 'cli' );
}

function lm_ie_out( $text ) {
    if ( lm_ie_is_cli() ) {
        echo $text . PHP_EOL;
        return;
    }

    echo '<pre style="white-space:pre-wrap;word-break:break-word;">' . esc_html( $text ) . '</pre>';
}

function lm_ie_parse_tabs( $raw ) {
    $allowed = array( 'verbraucher', 'regel', 'firmen' );

    if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
        return array( 'verbraucher' );
    }

    $parts = array_map( 'trim', explode( ',', strtolower( $raw ) ) );
    $parts = array_values( array_unique( array_filter( $parts ) ) );
    $parts = array_values( array_intersect( $parts, $allowed ) );

    return ! empty( $parts ) ? $parts : array( 'verbraucher' );
}

function lm_ie_parse_title_and_tabs( $line ) {
    $line = trim( (string) $line );

    // Format: Titel [verbraucher,regel]
    if ( preg_match( '/^(.*)\[\s*([^\]]+)\s*\]$/u', $line, $m ) ) {
        $title = trim( $m[1] );
        $tabs  = lm_ie_parse_tabs( $m[2] );
        if ( '' !== $title ) {
            return array( $title, $tabs );
        }
    }

    return array( $line, array( 'verbraucher' ) );
}

function lm_ie_read_entries_from_data_file( $data_file ) {
    if ( ! file_exists( $data_file ) ) {
        return new WP_Error( 'lm_data_missing', 'Data file not found: ' . $data_file );
    }

    $data = (string) file_get_contents( $data_file );
    $data = str_replace( array( "\r\n", "\r" ), "\n", $data );
    $lines = explode( "\n", $data );

    $current_letter = '';
    $entries        = array();
    $current_entry  = null;
    $state          = 'waiting_for_letter';

    foreach ( $lines as $line ) {
        $line = trim( (string) $line );

        // Allow comments in data.txt.
        if ( '' !== $line && 0 === strpos( $line, '#' ) ) {
            continue;
        }

        if ( '' === $line ) {
            if ( 'in_entry' === $state && $current_entry && '' !== $current_entry['content'] ) {
                $current_entry['content'] .= "\n";
            }
            continue;
        }

        if ( preg_match( '/^[A-ZÄÖÜ]$/u', $line ) ) {
            if ( $current_entry ) {
                $entries[] = $current_entry;
                $current_entry = null;
            }
            $current_letter = $line;
            $state          = 'waiting_for_entry';
            continue;
        }

        if ( 'waiting_for_entry' === $state ) {
            list( $title, $tabs ) = lm_ie_parse_title_and_tabs( $line );

            if ( '' === $title ) {
                continue;
            }

            $current_entry = array(
                'letter'  => $current_letter,
                'title'   => $title,
                'tabs'    => $tabs,
                'content' => '',
            );
            $state = 'in_entry';
            continue;
        }

        if ( 'in_entry' === $state && $current_entry ) {
            $current_entry['content'] = '' === $current_entry['content']
                ? $line
                : $current_entry['content'] . "\n" . $line;
        }
    }

    if ( $current_entry ) {
        $entries[] = $current_entry;
    }

    return $entries;
}

function lm_ie_import_data( $data_file, $dry_run = false ) {
    $entries = lm_ie_read_entries_from_data_file( $data_file );
    if ( is_wp_error( $entries ) ) {
        lm_ie_out( $entries->get_error_message() );
        return;
    }

    $created = 0;
    $updated = 0;
    $failed  = 0;

    foreach ( $entries as $entry ) {
        $title   = wp_strip_all_tags( $entry['title'] );
        $content = (string) $entry['content'];
        $letter  = strtoupper( (string) $entry['letter'] );
        $tabs    = lm_ie_parse_tabs( implode( ',', (array) $entry['tabs'] ) );

        $existing_id = (int) post_exists( $title, '', '', 'lexikon' );
        $is_update   = $existing_id > 0;

        if ( $dry_run ) {
            lm_ie_out( sprintf( '[DRY RUN] %s: %s', $is_update ? 'Update' : 'Create', $title ) );
            continue;
        }

        $post_data = array(
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_type'    => 'lexikon',
        );

        if ( $is_update ) {
            $post_data['ID'] = $existing_id;
            $post_id = wp_update_post( $post_data, true );
        } else {
            $post_id = wp_insert_post( $post_data, true );
        }

        if ( is_wp_error( $post_id ) ) {
            $failed++;
            lm_ie_out( 'Error for "' . $title . '": ' . $post_id->get_error_message() );
            continue;
        }

        update_post_meta( $post_id, '_lexikon_buchstabe', $letter );
        update_post_meta( $post_id, '_lexikon_tabs', implode( ',', $tabs ) );

        if ( $is_update ) {
            $updated++;
            lm_ie_out( 'Updated: ' . $title );
        } else {
            $created++;
            lm_ie_out( 'Created: ' . $title );
        }
    }

    if ( $dry_run ) {
        lm_ie_out( sprintf( 'Dry run complete. Parsed entries: %d', count( $entries ) ) );
        return;
    }

    lm_ie_out( sprintf( 'Import complete. Created: %d, Updated: %d, Failed: %d', $created, $updated, $failed ) );
}

function lm_ie_export_data( $data_file ) {
    $posts = get_posts(
        array(
            'post_type'      => 'lexikon',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        )
    );

    $output_lines = array();
    $last_letter  = '';

    foreach ( $posts as $post ) {
        $title   = (string) get_the_title( $post );
        $content = (string) get_post_field( 'post_content', $post->ID );
        $letter  = strtoupper( (string) get_post_meta( $post->ID, '_lexikon_buchstabe', true ) );
        $tabs    = (string) get_post_meta( $post->ID, '_lexikon_tabs', true );

        if ( '' === $letter ) {
            if ( function_exists( 'mb_substr' ) ) {
                $letter = strtoupper( mb_substr( $title, 0, 1 ) );
            } else {
                $letter = strtoupper( substr( $title, 0, 1 ) );
            }
        }

        if ( $letter !== $last_letter ) {
            if ( ! empty( $output_lines ) ) {
                $output_lines[] = '';
            }
            $output_lines[] = $letter;
            $output_lines[] = '';
            $last_letter = $letter;
        }

        $title_line = $title;
        if ( '' !== trim( $tabs ) ) {
            $title_line .= ' [' . $tabs . ']';
        }

        $output_lines[] = $title_line;
        $output_lines[] = trim( $content );
        $output_lines[] = '';
    }

    $payload = implode( PHP_EOL, $output_lines );
    file_put_contents( $data_file, $payload );

    lm_ie_out( 'Export complete: ' . $data_file );
    lm_ie_out( 'Entries exported: ' . count( $posts ) );
}

function lm_ie_is_allowed_browser_run() {
    return is_user_logged_in() && current_user_can( 'manage_options' ) && isset( $_GET['confirm'] ) && '1' === $_GET['confirm'];
}

$data_file = plugin_dir_path( __FILE__ ) . 'data.txt';

if ( lm_ie_is_cli() ) {
    $args = getopt( '', array( 'import', 'export', 'dry-run' ) );
    $do_export = isset( $args['export'] );
    $do_import = isset( $args['import'] ) || ! $do_export;
    $dry_run   = isset( $args['dry-run'] );

    if ( $do_export ) {
        lm_ie_export_data( $data_file );
        exit;
    }

    if ( $do_import ) {
        lm_ie_import_data( $data_file, $dry_run );
        exit;
    }
}

if ( isset( $_GET['lm_lexikon_export'] ) && '1' === $_GET['lm_lexikon_export'] ) {
    if ( ! lm_ie_is_allowed_browser_run() ) {
        wp_die( 'Not allowed. Admin login + confirm=1 required.' );
    }
    lm_ie_export_data( $data_file );
    exit;
}

if ( isset( $_GET['lm_lexikon_import'] ) && '1' === $_GET['lm_lexikon_import'] ) {
    if ( ! lm_ie_is_allowed_browser_run() ) {
        wp_die( 'Not allowed. Admin login + confirm=1 required.' );
    }
    $dry_run = isset( $_GET['dry_run'] ) && '1' === $_GET['dry_run'];
    lm_ie_import_data( $data_file, $dry_run );
    exit;
}

lm_ie_out( 'No action requested.' );
lm_ie_out( 'Use ?lm_lexikon_import=1&confirm=1 or ?lm_lexikon_export=1&confirm=1 (admin only).' );
