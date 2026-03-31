<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function lm_add_lexikon_meta_boxes() {
    add_meta_box(
        'lm_lexikon_meta',
        'Lexikon SEO & Verlinkung',
        'lm_lexikon_meta_box_callback',
        'lexikon',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'lm_add_lexikon_meta_boxes' );

function lm_lexikon_meta_box_callback( $post ) {
    wp_nonce_field( 'lm_save_lexikon_meta', 'lm_lexikon_meta_nonce' );

    $kurzdefinition = get_post_meta( $post->ID, '_lm_kurzdefinition', true );
    $blog_post_id   = function_exists( 'lm_get_blog_post_id' )
        ? lm_get_blog_post_id( $post->ID )
        : get_post_meta( $post->ID, '_lm_blog_post_id', true );
    $schema_blog    = get_post_meta( $post->ID, '_lm_schema_blog_url', true );

    echo '<div x-data="lmLexikonMetaHelper(' . esc_attr( wp_json_encode( (string) $kurzdefinition ) ) . ')">';
    echo '<p><label for="lm_kurzdefinition"><strong>Kurzdefinition</strong></label><br/><textarea style="width:100%;" id="lm_kurzdefinition" name="lm_kurzdefinition" rows="3" x-model="kurzdefinition">' . esc_textarea( $kurzdefinition ) . '</textarea><br/><small x-text="kurzdefinition.length ? `Zeichen: ${kurzdefinition.length}` : `Noch keine Kurzdefinition gesetzt.`"></small></p>';

    echo '<p><label for="lm_blog_post_id"><strong>Blog Post ID</strong></label><br/><input type="text" id="lm_blog_post_id" name="lm_blog_post_id" value="' . esc_attr( $blog_post_id ) . '" style="width:220px;" /><br/><small>Falls gesetzt, wird die Blog-URL für „Mehr erfahren“ verwendet; für die Schema-URL nur bei aktivierter Checkbox.</small></p>';

    echo '<p><label for="lm_schema_blog_url"><input type="checkbox" id="lm_schema_blog_url" name="lm_schema_blog_url" value="1" ' . checked( $schema_blog, 1, false ) . ' /> Schema-URL auf Blog-Post zeigen (falls Post existiert)</label></p>';
    echo '</div>';
}

function lm_save_lexikon_meta( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! isset( $_POST['lm_lexikon_meta_nonce'] ) || ! wp_verify_nonce( $_POST['lm_lexikon_meta_nonce'], 'lm_save_lexikon_meta' ) ) {
        return;
    }

    if ( isset( $_POST['post_type'] ) && 'lexikon' === $_POST['post_type'] ) {
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
    }

    if ( isset( $_POST['lm_kurzdefinition'] ) ) {
        update_post_meta( $post_id, '_lm_kurzdefinition', sanitize_textarea_field( $_POST['lm_kurzdefinition'] ) );
    }

    if ( isset( $_POST['lm_blog_post_id'] ) ) {
        $blog_post_id = intval( $_POST['lm_blog_post_id'] );

        if ( $blog_post_id > 0 ) {
            update_post_meta( $post_id, '_lm_blog_post_id', $blog_post_id );
        } else {
            delete_post_meta( $post_id, '_lm_blog_post_id' );
        }

        // Legacy cleanup after successful write.
        delete_post_meta( $post_id, '_lexikon_blog_post_id' );
    }

    $schema_blog = isset( $_POST['lm_schema_blog_url'] ) ? 1 : 0;
    update_post_meta( $post_id, '_lm_schema_blog_url', $schema_blog );
}
add_action( 'save_post', 'lm_save_lexikon_meta' );
