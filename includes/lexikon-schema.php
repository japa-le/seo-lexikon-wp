<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Generate DefinedTermSet JSON-LD for lexikon entries.
if ( ! function_exists( 'lm_render_lexikon_schema' ) ) {
    function lm_render_lexikon_schema() {
        if ( ! is_page() && ! is_singular( 'lexikon' ) && ! is_singular( 'page' ) ) {
            return;
        }

        global $post;
        if ( ! $post ) {
            return;
        }

        if ( 'lexikon' !== $post->post_type && 'page' !== $post->post_type ) {
            return;
        }

        // Schema nur dort ausgeben, wo der Lexikon Gutenberg-Block eingebunden ist.
        if ( ! function_exists( 'has_block' ) || ! has_block( 'lm/lexikon', $post ) ) {
            return;
        }

        if ( ! function_exists( 'lm_get_lexikon_items' ) ) {
            return;
        }

        $terms = lm_get_lexikon_items();
        if ( empty( $terms ) ) {
            return;
        }

        $defined_terms = array();
        foreach ( $terms as $term ) {
            $description = '';
            if ( function_exists( 'lm_get_snippet_description' ) ) {
                $description = lm_get_snippet_description( $term );
            }
            $url = function_exists( 'lm_get_schema_url' )
                ? lm_get_schema_url( $term )
                : home_url( '/lexikon#' . lm_normalize_anchor_slug( $term->post_title ) );

            if ( ! empty( $description ) ) {
                $defined_terms[] = array(
                    '@type'       => 'DefinedTerm',
                    'name'        => $term->post_title,
                    'description' => $description,
                    'url'         => $url,
                );
            }
        }

        if ( empty( $defined_terms ) ) {
            return;
        }

        $payload = array(
            '@context'        => 'https://schema.org',
            '@type'           => 'DefinedTermSet',
            'name'            => 'Insolvenz Lexikon',
            'hasDefinedTerm'  => $defined_terms,
        );

        echo '<script type="application/ld+json">' . wp_json_encode( $payload ) . '</script>';
    }
    add_action( 'wp_head', 'lm_render_lexikon_schema' );
}
