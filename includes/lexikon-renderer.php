<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function lm_normalize_anchor_slug( $text ) {
    $slug = remove_accents( mb_strtolower( trim( $text ) ) );
    $slug = preg_replace( '/[^a-z0-9\-\_]+/', '-', $slug );
    $slug = preg_replace( '/-+/', '-', $slug );
    $slug = trim( $slug, '-' );

    if ( '' === $slug ) {
        $slug = 'lexikon-entry-' . wp_generate_password( 8, false );
    }

    return $slug;
}

function lm_get_snippet_description( $post ) {
    $short = trim( get_post_meta( $post->ID, '_lm_kurzdefinition', true ) );

    if ( '' !== $short ) {
        return wp_strip_all_tags( $short );
    }

    $text  = strip_tags( apply_filters( 'the_content', $post->post_content ) );
    $words = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );

    if ( ! $words || count( $words ) < 1 ) {
        return '';
    }

    $snippet = implode( ' ', array_slice( $words, 0, 50 ) );

    if ( count( $words ) > 50 ) {
        $snippet .= '…';
    }

    return wp_strip_all_tags( $snippet );
}

function lm_get_blog_post_id( $post_id ) {
    $blog_post_id = intval( get_post_meta( $post_id, '_lm_blog_post_id', true ) );
    if ( $blog_post_id > 0 ) {
        return $blog_post_id;
    }

    // Legacy fallback.
    return intval( get_post_meta( $post_id, '_lexikon_blog_post_id', true ) );
}

function lm_get_schema_url( $post ) {
    $use_blog_url = filter_var( get_post_meta( $post->ID, '_lm_schema_blog_url', true ), FILTER_VALIDATE_BOOLEAN );
    $blog_post_id = lm_get_blog_post_id( $post->ID );

    if ( $use_blog_url && $blog_post_id > 0 ) {
        $blog_post = get_post( $blog_post_id );
        if ( $blog_post && 'publish' === get_post_status( $blog_post ) ) {
            return get_permalink( $blog_post );
        }
    }

    $slug = lm_normalize_anchor_slug( $post->post_title );
    return home_url( '/lexikon#' . $slug );
}

function lm_get_assignment_label( $post_id ) {
    $raw_tabs = get_post_meta( (int) $post_id, '_lexikon_tabs', true );

    if ( function_exists( 'lexikon_normalize_tabs_meta_value' ) ) {
        $tabs = lexikon_normalize_tabs_meta_value( $raw_tabs );
    } else {
        $tabs = array_filter( array_map( 'sanitize_key', explode( ',', (string) $raw_tabs ) ) );
    }

    if ( empty( $tabs ) ) {
        return '';
    }

    $label_map = array(
        'verbraucher' => 'Verbraucherinsolvenz',
        'regel'       => 'Regelinsolvenz',
        'firmen'      => 'Firmeninsolvenz',
    );

    $labels = array();
    foreach ( $tabs as $tab ) {
        $tab = sanitize_key( $tab );
        if ( isset( $label_map[ $tab ] ) ) {
            $labels[] = $label_map[ $tab ];
        }
    }

    $labels = array_values( array_unique( $labels ) );

    return implode( ', ', $labels );
}

/**
 * Video-Button für den Gutenberg-Block (lm-entry-* BEM-Schema).
 * Bewusst getrennt von lexikon_get_video_placeholder_html(),
 * das ausschließlich von den Shortcodes genutzt wird.
 */
function lm_get_video_html( $video_url ) {
    if ( empty( $video_url ) ) return '';

    $video_embed = '<video src="' . esc_url( $video_url ) . '" controls autoplay playsinline preload="metadata" style="width:100%;height:100%;"></video>';

    ob_start();
    ?>
    <div class="lm-entry-video">
        <button type="button" class="lm-entry-video-btn lm-entry-diagram-btn"
                data-embed="<?php echo esc_attr( rawurlencode( $video_embed ) ); ?>">
            <i class="fa-solid fa-play" aria-hidden="true"></i>
            <span>Erklärvideo</span>
        </button>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Lightbox für den Gutenberg-Block (einmalig pro Seitenaufruf).
 * Bewusst getrennt von lexikon_get_lightbox_assets_html() der Shortcodes.
 */
function lm_get_lightbox_assets_html() {
    static $printed = false;
    if ( $printed ) return '';
    $printed = true;

    return <<<HTML
<div id="lm-lightbox" style="display:none;position:fixed;inset:0;z-index:999999;background:rgba(0,0,0,.85);align-items:center;justify-content:center;">
  <button type="button" id="lm-lightbox-close" aria-label="Schließen" style="position:absolute;top:18px;right:24px;background:none;border:none;color:#fff;font-size:2.2rem;cursor:pointer;line-height:1;">&times;</button>
  <div id="lm-lightbox-content" style="background:#F2F3F5;width:min(95vw,1200px);height:min(85vh,800px);display:flex;align-items:center;justify-content:center;border:0;border-radius:6px;box-shadow:0 4px 40px rgba(0,0,0,.6);overflow:hidden;"></div>
</div>
<style>#lm-lightbox.is-open{display:flex!important;}</style>
<script>
(function(){
  var lb      = document.getElementById('lm-lightbox');
  var content = document.getElementById('lm-lightbox-content');
  var closeBtn= document.getElementById('lm-lightbox-close');
  if (!lb || !content || !closeBtn) return;

  if (lb.parentNode !== document.body) document.body.appendChild(lb);

  function open(embedHtml) {
    if (!embedHtml) return;
    content.innerHTML = embedHtml;
    lb.style.display = 'flex';
    lb.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  }

  function close() {
    lb.style.display = 'none';
    lb.classList.remove('is-open');
    content.innerHTML = '';
    document.body.style.overflow = '';
  }

  document.addEventListener('click', function(e) {
    var btn = e.target.closest('.lm-entry-diagram-btn');
    if (btn) {
      var encoded = btn.getAttribute('data-embed');
      if (encoded) open(decodeURIComponent(encoded));
      return;
    }
    if (e.target === lb || e.target === closeBtn) close();
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') close();
  });
})();
</script>
HTML;
}

function lm_get_lexikon_items( $type = '' ) {
    $args = array(
        'post_type'      => 'lexikon',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    );

    $posts = get_posts( $args );

    if ( '' === $type || ! function_exists( 'lexikon_post_matches_type' ) ) {
        return $posts;
    }

    $filtered = array();
    foreach ( $posts as $post ) {
        if ( lexikon_post_matches_type( $post->ID, $type ) ) {
            $filtered[] = $post;
        }
    }

    return $filtered;
}

function lm_render_lexikon( $attributes = array() ) {
    $attributes = wp_parse_args( $attributes, array(
        'show_search' => true,
        'show_tabs'   => true,
        'type'        => '',
    ) );

    $entries = lm_get_lexikon_items( sanitize_text_field( $attributes['type'] ) );

    if ( empty( $entries ) ) {
        return '<div class="lm-lexikon-empty">Keine Lexikon-Einträge gefunden.</div>';
    }

    $groups = array();
    foreach ( $entries as $post ) {
        $letter = mb_strtoupper( mb_substr( trim( $post->post_title ), 0, 1 ) );
        if ( '' === $letter ) {
            $letter = '#';
        }

        if ( ! isset( $groups[ $letter ] ) ) {
            $groups[ $letter ] = array();
        }

        $groups[ $letter ][] = array(
            'post'       => $post,
            'anchor'     => lm_normalize_anchor_slug( $post->post_title ),
            'snippet'    => lm_get_snippet_description( $post ),
            'schema_url' => lm_get_schema_url( $post ),
            'assignment' => lm_get_assignment_label( $post->ID ),
            'resource'   => array(
                'blog_post_id'  => lm_get_blog_post_id( $post->ID ),
                'video_url'     => esc_url( get_post_meta( $post->ID, '_lexikon_video_url', true ) ),
                'file_url'      => esc_url( get_post_meta( $post->ID, '_lexikon_file_url', true ) ),
                'diagram_embed' => get_post_meta( $post->ID, '_lexikon_diagram_embed', true ),
            ),
        );
    }

    ksort( $groups );

    $html  = '<div class="lm-lexikon" id="lm-lexikon">';
    $html .= '<div class="lm-lexikon-iwrap">';

    if ( $attributes['show_search'] ) {
        $html .= '<div class="lm-lexikon-search-wp"><input id="lm-lexikon-search" type="search" placeholder="Begriff suchen..." aria-label="Lexikon Suche" /></div>';
    }

    if ( $attributes['show_tabs'] ) {
        $html .= '<div class="lm-lexikon-tabs" role="tablist">';
        foreach ( array_keys( $groups ) as $letter ) {
            $html .= '<button type="button" class="lm-lexikon-tab" data-letter="' . esc_attr( $letter ) . '">' . esc_html( $letter ) . '</button>';
        }
        $html .= '</div>';
    }

    $html .= '</div>'; // .lm-lexikon-iwrap

    $html .= '<div class="lm-lexikon-entries">';

    foreach ( $groups as $letter => $items ) {
        $html .= '<section class="lm-lexikon-group" data-letter="' . esc_attr( $letter ) . '">';
        $html .= '<h2 class="lm-lexikon-group-title">' . esc_html( $letter ) . '</h2>';

        foreach ( $items as $item ) {
            $post  = $item['post'];
            $res   = $item['resource'];

            $html .= '<article id="' . esc_attr( $item['anchor'] ) . '" class="lm-entry">';
            $html .= '<h3 class="lm-entry-title">' . esc_html( $post->post_title ) . '</h3>';
            $html .= '<p class="lm-entry-snippet">' . esc_html( $item['snippet'] ) . '</p>';
            $html .= '<div class="lm-entry-content">' . apply_filters( 'the_content', $post->post_content ) . '</div>';

            $html .= '<div class="lm-entry-resources">';

            if ( $res['blog_post_id'] > 0 ) {
                $post_link = get_permalink( $res['blog_post_id'] );
                if ( $post_link ) {
                    $html .= '<a class="lm-entry-more" href="' . esc_url( $post_link ) . '">';
                    $html .= '<i class="fa-solid fa-newspaper" aria-hidden="true"></i>';
                    $html .= '<span>Mehr erfahren</span>';
                    $html .= '</a>';
                }
            }

            if ( $res['video_url'] ) {
                $html .= lm_get_video_html( $res['video_url'] );
            }

            if ( $res['file_url'] ) {
                $html .= '<a class="lm-entry-file" href="' . esc_url( $res['file_url'] ) . '" target="_blank" rel="noopener">';
                $html .= '<i class="fa-solid fa-download" aria-hidden="true"></i>';
                $html .= '<span>Datei herunterladen</span>';
                $html .= '</a>';
            }

            if ( ! empty( $res['diagram_embed'] ) ) {
                $html .= lm_get_lightbox_assets_html();
                $embed_html = lexikon_sanitize_embed_html( $res['diagram_embed'] );
                $html .= '<button type="button" class="lm-entry-diagram-btn" data-embed="' . esc_attr( rawurlencode( $embed_html ) ) . '">';
                $html .= '<i class="fa-solid fa-image" aria-hidden="true"></i>';
                $html .= '<span>Prozessdiagramm</span>';
                $html .= '</button>';
            }

            if ( ! empty( $item['assignment'] ) ) {
                $html .= '<div class="lm-entry-assignment">' . esc_html( $item['assignment'] ) . '</div>';
            }

            $html .= '</div>'; // .lm-entry-resources

            $html .= '</article>';
        }

        $html .= '</section>';
    }

    $html .= '</div>'; // .lm-lexikon-entries
    $html .= '</div>'; // .lm-lexikon

    return $html;
}
