<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function lm_lexikon_admin_menu() {
    add_submenu_page(
        'edit.php?post_type=lexikon',
        'Snippet-Status',
        'Snippet-Status',
        'manage_options',
        'lm-lexikon-snippet-status',
        'lm_lexikon_snippet_status_page'
    );
}
add_action( 'admin_menu', 'lm_lexikon_admin_menu' );

function lm_lexikon_snippet_status_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $args = array(
        'post_type'      => 'lexikon',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    );

    $posts = get_posts( $args );

    echo '<div class="wrap" x-data="lmSnippetStatus"><h1>Lexikon Snippet-Status</h1>';
    echo '<p>Folgende Lexikon-Einträge haben noch keine Kurzdefinition:</p>';
    echo '<p><input type="search" class="regular-text" placeholder="Nach Titel oder ID filtern..." x-model="searchTerm" /></p>';

    echo '<table class="widefat striped"><thead><tr><th>Titel</th><th>ID</th><th>Link</th></tr></thead><tbody>';

    foreach ( $posts as $post ) {
        $snippet = get_post_meta( $post->ID, '_lm_kurzdefinition', true );
        if ( trim( $snippet ) === '' ) {
            $search_value = strtolower( get_the_title( $post ) . ' ' . $post->ID );
            echo '<tr data-search="' . esc_attr( $search_value ) . '" x-show="matches($el.dataset.search)">';
            echo '<td>' . esc_html( get_the_title( $post ) ) . '</td>';
            echo '<td>' . esc_html( $post->ID ) . '</td>';
            echo '<td><a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">Bearbeiten</a></td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table></div>';
}
