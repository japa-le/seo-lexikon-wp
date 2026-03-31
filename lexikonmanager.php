<?php
/*
Plugin Name: Lexikon Manager
Description: Verwaltet Lexikon-Einträge und zentrale Texte via Shortcodes.
Version: 1.3.0
Author: Janos
*/

if ( ! defined('ABSPATH') ) exit;

require_once plugin_dir_path( __FILE__ ) . 'includes/lexikon-renderer.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/lexikon-meta.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/lexikon-schema.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/lexikon-admin.php';

function lm_register_lexikon_block() {
    if ( function_exists( 'register_block_type' ) ) {
        register_block_type(
            plugin_dir_path( __FILE__ ) . 'block.json',
            array(
                'render_callback' => 'lm_render_lexikon',
            )
        );
    }
}
add_action( 'init', 'lm_register_lexikon_block' );

/**
 * Frontend: Lexikon JS auf der Lexikon-Seite laden
 */
function lexikon_enqueue_frontend_script() {
    if (is_admin()) return;

    wp_enqueue_script(
        'lexikon-script',
        plugin_dir_url(__FILE__) . 'build/frontend.js',
        array('jquery'),
        '1.3.0',
        true
    );
}
add_action('wp_enqueue_scripts', 'lexikon_enqueue_frontend_script');

/**
 * Admin: Alpine-basierte UI-Helfer nur auf Lexikon-Screens laden.
 */
function lexikon_enqueue_admin_script( $hook_suffix ) {
    if ( ! is_admin() ) {
        return;
    }

    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    $is_lexikon_post_screen = $screen && 'lexikon' === $screen->post_type && in_array( $screen->base, array( 'post', 'post-new' ), true );
    $is_snippet_status_page = ( 'lexikon_page_lm-lexikon-snippet-status' === $hook_suffix );

    if ( ! $is_lexikon_post_screen && ! $is_snippet_status_page ) {
        return;
    }

    wp_enqueue_script(
        'lexikon-admin-script',
        plugin_dir_url( __FILE__ ) . 'build/admin.js',
        array(),
        '1.3.0',
        true
    );
}
add_action( 'admin_enqueue_scripts', 'lexikon_enqueue_admin_script' );

/**
 * Kompatibilität: globales `ajaxurl` im Frontend.
 */
function lexikon_print_frontend_ajaxurl_fallback() {
    if ( is_admin() ) return;

    $ajax_url = admin_url( 'admin-ajax.php' );
    echo '<script>window.ajaxurl=window.ajaxurl||' . wp_json_encode( $ajax_url ) . ';</script>';
}
add_action( 'wp_head', 'lexikon_print_frontend_ajaxurl_fallback', 1 );

function lexikon_enqueue_styles() {
    wp_enqueue_style(
        'lexikon-style',
        plugin_dir_url(__FILE__) . 'assets/style.css',
        array(),
        '1.3.0'
    );
}
add_action('wp_enqueue_scripts', 'lexikon_enqueue_styles');
add_action('enqueue_block_assets', 'lexikon_enqueue_styles');

/**
 * Admin: CPT "lexikon" Liste immer alphabetisch
 */
function lexikon_orderby_title( $query ) {
    if ( is_admin() && $query->is_main_query() && 'lexikon' === $query->get('post_type') ) {
        $query->set( 'orderby', 'title' );
        $query->set( 'order', 'ASC' );
    }
}
add_action( 'pre_get_posts', 'lexikon_orderby_title' );


/**
 * 1) Custom Post Type registrieren
 */
function lexikon_register_post_type() {
    $labels = array(
        'name'               => 'Lexikon',
        'singular_name'      => 'Lexikon-Eintrag',
        'add_new'            => 'Neuen Eintrag hinzufügen',
        'add_new_item'       => 'Neuen Lexikon-Eintrag hinzufügen',
        'edit_item'          => 'Eintrag bearbeiten',
        'new_item'           => 'Neuer Eintrag',
        'view_item'          => 'Eintrag ansehen',
        'search_items'       => 'Einträge durchsuchen',
        'not_found'          => 'Keine Einträge gefunden',
        'not_found_in_trash' => 'Keine Einträge im Papierkorb gefunden',
    );

    $args = array(
        'labels'    => $labels,
        'public'    => false,
        'show_ui'   => true,
        'supports'  => array('title', 'editor'),
        'menu_icon' => 'dashicons-book',
    );

    register_post_type('lexikon', $args);
}
add_action('init', 'lexikon_register_post_type');


/**
 * 2) Meta-Boxen
 */
function lexikon_add_meta_boxes() {
    add_meta_box('lexikon_meta', 'Lexikon Einstellungen', 'lexikon_meta_box_callback', 'lexikon', 'normal', 'high');
}
add_action('add_meta_boxes', 'lexikon_add_meta_boxes');

function lexikon_get_allowed_embed_html() {
    return array(
        'iframe' => array(
            'src'             => true,
            'width'           => true,
            'height'          => true,
            'style'           => true,
            'title'           => true,
            'class'           => true,
            'id'              => true,
            'loading'         => true,
            'allow'           => true,
            'allowfullscreen' => true,
            'referrerpolicy'  => true,
            'frameborder'     => true,
        ),
        'div'  => array( 'class' => true, 'style' => true, 'id' => true ),
        'span' => array( 'class' => true, 'style' => true, 'id' => true ),
        'p'    => array( 'class' => true, 'style' => true, 'id' => true ),
        'a'    => array(
            'href'   => true,
            'target' => true,
            'rel'    => true,
            'class'  => true,
            'style'  => true,
            'id'     => true,
        ),
    );
}

function lexikon_sanitize_embed_html( $html ) {
    return wp_kses( (string) $html, lexikon_get_allowed_embed_html() );
}

/**
 * Tab-Aliases und Kanonisierung
 */
function lexikon_get_tab_aliases( $type ) {
    $type = sanitize_key( (string) $type );

    $map = array(
        'verbraucher' => array( 'verbraucher', 'verbraucherinsolvenz' ),
        'regel'       => array( 'regel', 'regelinsolvenz', 'regelinsoenz' ),
        'firmen'      => array( 'firmen', 'firmeninsolvenz' ),
    );

    return isset( $map[ $type ] ) ? $map[ $type ] : array( $type );
}

function lexikon_canonicalize_tab_token( $token ) {
    $token = sanitize_key( (string) $token );

    if ( '' === $token ) return '';

    if ( in_array( $token, lexikon_get_tab_aliases( 'verbraucher' ), true ) ) return 'verbraucher';
    if ( in_array( $token, lexikon_get_tab_aliases( 'regel' ), true ) )       return 'regel';
    if ( in_array( $token, lexikon_get_tab_aliases( 'firmen' ), true ) )      return 'firmen';

    if ( false !== strpos( $token, 'verbrauch' ) || false !== strpos( $token, 'privat' ) ) return 'verbraucher';
    if ( false !== strpos( $token, 'regel' ) )                                              return 'regel';
    if ( false !== strpos( $token, 'firma' ) || false !== strpos( $token, 'unternehm' ) || false !== strpos( $token, 'gewerb' ) ) return 'firmen';

    return $token;
}

function lexikon_normalize_tabs_meta_value( $raw_tabs ) {
    $tokens = array();

    if ( is_array( $raw_tabs ) ) {
        $tokens = $raw_tabs;
    } elseif ( is_string( $raw_tabs ) ) {
        $raw_tabs = trim( $raw_tabs );
        if ( '' !== $raw_tabs ) {
            $tokens = ( false !== strpos( $raw_tabs, ',' ) ) ? explode( ',', $raw_tabs ) : array( $raw_tabs );
        }
    }

    $normalized = array();
    foreach ( $tokens as $token ) {
        $token = lexikon_canonicalize_tab_token( $token );
        if ( '' !== $token ) {
            $normalized[] = $token;
        }
    }

    return array_values( array_unique( $normalized ) );
}

function lexikon_post_matches_type( $post_id, $type, $include_untagged = true ) {
    $type = sanitize_key( (string) $type );

    if ( '' === $type || in_array( $type, array( 'lexikon', 'all' ), true ) ) {
        return true;
    }

    $raw_tabs = get_post_meta( (int) $post_id, '_lexikon_tabs', true );
    $tabs     = lexikon_normalize_tabs_meta_value( $raw_tabs );

    if ( empty( $tabs ) ) {
        return (bool) $include_untagged;
    }

    $aliases_canon  = array_unique( array_map( 'lexikon_canonicalize_tab_token', lexikon_get_tab_aliases( $type ) ) );
    $tabs_canonical = array_unique( array_map( 'lexikon_canonicalize_tab_token', $tabs ) );

    foreach ( $aliases_canon as $alias ) {
        if ( in_array( sanitize_key( $alias ), $tabs_canonical, true ) ) {
            return true;
        }
    }

    return false;
}

function lexikon_build_tabs_meta_query( $type, $include_untagged = true ) {
    $aliases    = lexikon_get_tab_aliases( $type );
    $meta_query = array( 'relation' => 'OR' );

    foreach ( $aliases as $alias ) {
        $meta_query[] = array(
            'key'     => '_lexikon_tabs',
            'value'   => $alias,
            'compare' => 'LIKE',
        );
    }

    if ( $include_untagged ) {
        $meta_query[] = array( 'key' => '_lexikon_tabs', 'compare' => 'NOT EXISTS' );
        $meta_query[] = array( 'key' => '_lexikon_tabs', 'value' => '', 'compare' => '=' );
    }

    return $meta_query;
}

function lexikon_meta_box_callback($post) {
    $buchstabe     = get_post_meta($post->ID, '_lexikon_buchstabe', true);
    $tabs          = lexikon_normalize_tabs_meta_value( get_post_meta($post->ID, '_lexikon_tabs', true) );
    $video_url     = get_post_meta($post->ID, '_lexikon_video_url', true);
    $file_url      = get_post_meta($post->ID, '_lexikon_file_url', true);
    $diagram_embed = get_post_meta($post->ID, '_lexikon_diagram_embed', true);

    wp_nonce_field('lexikon_save_meta', 'lexikon_meta_nonce');
    ?>
    <p>
        <label for="lexikon_buchstabe"><strong>Buchstabe:</strong></label>
        <input type="text" id="lexikon_buchstabe" name="lexikon_buchstabe" value="<?php echo esc_attr($buchstabe); ?>" placeholder="z.B. A">
    </p>
    <p>
        <strong>Tabs auswählen:</strong><br>
        <label><input type="checkbox" name="lexikon_tabs[]" value="verbraucher" <?php checked(in_array('verbraucher', $tabs, true)); ?>> Verbraucherinsolvenz</label><br>
        <label><input type="checkbox" name="lexikon_tabs[]" value="regel" <?php checked(in_array('regel', $tabs, true)); ?>> Regelinsolvenz</label><br>
        <label><input type="checkbox" name="lexikon_tabs[]" value="firmen" <?php checked(in_array('firmen', $tabs, true)); ?>> Firmeninsolvenz</label>
    </p>
    <hr>
    <p>
        <label for="lexikon_video_url"><strong>Erklärvideo (URL, optional):</strong></label><br>
        <input type="text" id="lexikon_video_url" name="lexikon_video_url" class="large-text"
               placeholder="https://deine-seite.de/wp-content/uploads/video.mp4"
               value="<?php echo esc_attr($video_url); ?>">
        <small>Video in die Mediathek hochladen → URL kopieren → hier einfügen.</small>
    </p>
    <p>
        <label for="lexikon_file_url"><strong>Dokumentenvorlage (Download-URL, optional):</strong></label><br>
        <input type="text" id="lexikon_file_url" name="lexikon_file_url" class="large-text"
               placeholder="https://insolvenzo.eu/wp-content/uploads/template.pdf"
               value="<?php echo esc_attr($file_url); ?>">
        <small>PDF/DOCX in die Mediathek hochladen → URL kopieren → hier einfügen.</small>
    </p>
    <p>
        <label for="lexikon_diagram_embed"><strong>Prozessdiagramm (Embed HTML, optional):</strong></label><br>
        <textarea id="lexikon_diagram_embed" name="lexikon_diagram_embed" class="large-text" rows="5"
                  placeholder="<iframe src=&quot;https://lucid.app/...&quot;></iframe>"><?php echo esc_textarea($diagram_embed); ?></textarea>
        <small>Hier den kompletten Embed-Code einfügen (z.B. iFrame von Lucid). Wird im Frontend als Button „Prozessdiagramm" angezeigt und in einer Lightbox geöffnet.</small>
    </p>
    <?php
}

function lexikon_save_meta_box_data($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (get_post_type($post_id) !== 'lexikon') return;

    if ( ! isset($_POST['lexikon_meta_nonce']) || ! wp_verify_nonce($_POST['lexikon_meta_nonce'], 'lexikon_save_meta') ) {
        return;
    }

    if (isset($_POST['lexikon_buchstabe'])) {
        update_post_meta($post_id, '_lexikon_buchstabe', sanitize_text_field($_POST['lexikon_buchstabe']));
    }

    if (isset($_POST['lexikon_tabs'])) {
        $tabs = array_filter( array_map( 'lexikon_canonicalize_tab_token', (array) $_POST['lexikon_tabs'] ) );
        update_post_meta($post_id, '_lexikon_tabs', implode(',', $tabs));
    } else {
        delete_post_meta($post_id, '_lexikon_tabs');
    }

    if (isset($_POST['lexikon_video_url'])) {
        $video_url = esc_url_raw($_POST['lexikon_video_url']);
        if ($video_url) update_post_meta($post_id, '_lexikon_video_url', $video_url);
        else delete_post_meta($post_id, '_lexikon_video_url');
    }

    if (isset($_POST['lexikon_file_url'])) {
        $file_url = esc_url_raw($_POST['lexikon_file_url']);
        if ($file_url) update_post_meta($post_id, '_lexikon_file_url', $file_url);
        else delete_post_meta($post_id, '_lexikon_file_url');
    }

    if (isset($_POST['lexikon_diagram_embed'])) {
        $diagram_embed = lexikon_sanitize_embed_html( wp_unslash($_POST['lexikon_diagram_embed']) );
        if ($diagram_embed) update_post_meta($post_id, '_lexikon_diagram_embed', $diagram_embed);
        else delete_post_meta($post_id, '_lexikon_diagram_embed');
    }
}
add_action('save_post', 'lexikon_save_meta_box_data');


/**
 * Einmalige Datenmigration: _lexikon_tabs auf kanonische Werte bereinigen.
 */
function lexikon_migrate_tabs_to_canonical_once() {
    if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) return;

    $migration_option = 'lexikon_tabs_migration_20260325_done';
    if ( get_option( $migration_option ) ) return;

    $post_ids = get_posts( array(
        'post_type'              => 'lexikon',
        'post_status'            => 'any',
        'posts_per_page'         => -1,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ) );

    $allowed = array( 'verbraucher', 'regel', 'firmen' );
    $updated = 0;
    $deleted = 0;

    foreach ( $post_ids as $post_id ) {
        $tabs = array_values( array_unique( array_intersect(
            lexikon_normalize_tabs_meta_value( get_post_meta( (int) $post_id, '_lexikon_tabs', true ) ),
            $allowed
        ) ) );

        if ( empty( $tabs ) ) {
            delete_post_meta( (int) $post_id, '_lexikon_tabs' );
            $deleted++;
        } else {
            update_post_meta( (int) $post_id, '_lexikon_tabs', implode(',', $tabs) );
            $updated++;
        }
    }

    add_option(
        $migration_option,
        array(
            'migrated_at' => current_time( 'mysql' ),
            'total'       => count( $post_ids ),
            'updated'     => $updated,
            'deleted'     => $deleted,
        ),
        '',
        false
    );
}
add_action( 'admin_init', 'lexikon_migrate_tabs_to_canonical_once' );


/**
 * 3) Custom Columns in Admin
 */
function lexikon_custom_columns($columns) {
    $new_columns = array();
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['buchstabe']  = 'Buchstabe';
            $new_columns['insolvency'] = 'Insolvenz Typ';
        }
    }
    return $new_columns;
}
add_filter('manage_lexikon_posts_columns', 'lexikon_custom_columns');

function lexikon_custom_columns_content($column, $post_id) {
    if ($column === 'buchstabe') {
        $letter = get_post_meta($post_id, '_lexikon_buchstabe', true);
        echo ! empty($letter) ? esc_html($letter) : '—';
    }

    if ($column === 'insolvency') {
        $tabs = lexikon_normalize_tabs_meta_value( get_post_meta($post_id, '_lexikon_tabs', true) );
        if ( ! empty($tabs) ) {
            echo esc_html( implode(', ', $tabs) );
            echo '<div class="lexikon_tabs_data" style="display:none;">' . esc_attr( implode(',', $tabs) ) . '</div>';
        } else {
            echo '—';
        }
    }
}
add_action('manage_lexikon_posts_custom_column', 'lexikon_custom_columns_content', 10, 2);

/**
 * Frontend: Video-Button für Shortcodes (lexikon-* BEM-Schema).
 * Für den Block wird lm_get_video_html() in lexikon-renderer.php genutzt.
 */
function lexikon_get_video_placeholder_html( $video_url ) {
    ob_start();
    ?>
    <div class="lexikon-video">
        <button type="button" class="lexikon-video-btn" data-video-url="<?php echo esc_url($video_url); ?>">
            <i class="fa-solid fa-play" aria-hidden="true"></i>
            <span>Erklärvideo</span>
        </button>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Frontend: Lightbox Assets einmalig ausgeben (für Shortcodes).
 */
function lexikon_get_lightbox_assets_html() {
    static $printed = false;

    if ($printed) return '';
    $printed = true;

    return <<<HTML
<div id="lexikon-lightbox" style="display:none;position:fixed;inset:0;z-index:999999;background:rgba(0,0,0,.85);align-items:center;justify-content:center;">
  <button type="button" id="lexikon-lightbox-close" aria-label="Schließen" style="position:absolute;top:18px;right:24px;background:none;border:none;color:#fff;font-size:2.2rem;cursor:pointer;line-height:1;">&times;</button>
  <div id="lexikon-lightbox-content" style="background:#F2F3F5;width:min(95vw,1200px);height:min(85vh,800px);display:flex;align-items:center;justify-content:center;border:0;border-radius:6px;box-shadow:0 4px 40px rgba(0,0,0,.6);overflow:hidden;"></div>
</div>
<style>
  #lexikon-lightbox.is-open{display:flex!important;}
</style>
<script>
  (function(){
    var lb      = document.getElementById('lexikon-lightbox');
    var content = document.getElementById('lexikon-lightbox-content');
    var closeBtn= document.getElementById('lexikon-lightbox-close');
    if(!lb || !content || !closeBtn) return;

    if (lb.parentNode !== document.body) {
      document.body.appendChild(lb);
    }

    function openLightbox(embedHtml){
      if(!embedHtml) return;
      content.innerHTML = embedHtml;
      lb.style.display = 'flex';
      lb.classList.add('is-open');
      document.body.style.overflow = 'hidden';
    }

    function closeLightbox(){
      lb.style.display = 'none';
      lb.classList.remove('is-open');
      content.innerHTML = '';
      document.body.style.overflow = '';
    }

    document.addEventListener('click', function(e){
      var btn = e.target.closest('.lexikon-diagram-btn');
      if (btn) {
        var encoded = btn.getAttribute('data-embed');
        if (!encoded) return;
        openLightbox(decodeURIComponent(encoded));
        return;
      }
      if (e.target === lb || e.target === closeBtn) {
        closeLightbox();
      }
    });

    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape') closeLightbox();
    });
  })();
</script>
HTML;
}

/**
 * Frontend: Ressourcen-HTML für Shortcodes.
 */
function lexikon_get_resources_html( $video_url = '', $blog_post_id = 0, $file_url = '', $diagram_embed = '' ) {
    $blog_post_id = (int) $blog_post_id;

    if ( empty($video_url) && empty($blog_post_id) && empty($file_url) && empty($diagram_embed) ) {
        return '';
    }

    $out  = lexikon_get_lightbox_assets_html();
    $out .= '<div class="lexikon-resources-or lexikon-shortcode-resources">';
    $out .= '<div class="lexikon-resources">';

    if ( ! empty($video_url) ) {
        $out .= lexikon_get_video_placeholder_html( $video_url );
    }

    if ( $blog_post_id > 0 ) {
        $p = get_post($blog_post_id);
        if ( $p && $p->post_status === 'publish' ) {
            $out .= '<div class="lexikon-resource">';
            $out .= '<a class="lexikon-blog-btn" href="' . esc_url(get_permalink($p)) . '" target="_blank" rel="noopener">';
            $out .= '<i class="fa-solid fa-newspaper" aria-hidden="true"></i>';
            $out .= '<span>Blog Artikel</span>';
            $out .= '</a>';
            $out .= '</div>';
        }
    }

    $out .= '</div>';

    if ( ! empty($file_url) || ! empty($diagram_embed) ) {
        $out .= '<div class="lexikon-resource-file">';

        if ( ! empty($file_url) ) {
            $out .= '<a class="lexikon-download-btn" href="' . esc_url($file_url) . '" download>';
            $out .= '<i class="fa-solid fa-download" aria-hidden="true"></i>';
            $out .= '<span>Download Dokument</span>';
            $out .= '</a>';
        }

        if ( ! empty($diagram_embed) ) {
            $embed_html = lexikon_sanitize_embed_html($diagram_embed);
            $out .= '<button type="button" class="lexikon-diagram-btn" data-embed="' . esc_attr(rawurlencode($embed_html)) . '">';
            $out .= '<i class="fa-solid fa-image" aria-hidden="true"></i>';
            $out .= '<span>Prozessdiagramm</span>';
            $out .= '</button>';
        }

        $out .= '</div>';
    }

    $out .= '</div>';

    return $out;
}


/**
 * 5) Shortcodes: [lexikon_display type="verbraucher|regel|firmen"]
 */
function lexikon_get_grouped_posts( $type ) {
    $args = array(
        'post_type'      => 'lexikon',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    );

    $query  = new WP_Query($args);
    $groups = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();

            if ( ! lexikon_post_matches_type( $post_id, $type ) ) continue;

            $letter = strtoupper( get_post_meta($post_id, '_lexikon_buchstabe', true) );
            if ( empty($letter) ) {
                $letter = strtoupper( substr(get_the_title(), 0, 1) );
            }

            if ( ! isset($groups[$letter]) ) $groups[$letter] = array();

            $groups[$letter][] = array(
                'title'         => get_the_title(),
                'content'       => apply_filters('the_content', get_the_content()),
                'video_url'     => get_post_meta($post_id, '_lexikon_video_url', true),
                'blog_post_id'  => lm_get_blog_post_id( $post_id ),
                'file_url'      => get_post_meta($post_id, '_lexikon_file_url', true),
                'diagram_embed' => get_post_meta($post_id, '_lexikon_diagram_embed', true),
            );
        }
        wp_reset_postdata();
    }

    ksort($groups);
    return $groups;
}

function lexikon_display_shortcode($atts) {
    $atts = shortcode_atts(array(
        'type' => '',
        'tab'  => 'verbraucher',
    ), $atts, 'lexikon_display');

    $type   = lexikon_canonicalize_tab_token( '' !== trim((string) $atts['type']) ? $atts['type'] : $atts['tab'] );
    $groups = lexikon_get_grouped_posts( $type );

    $output = '';
    foreach ($groups as $letter => $posts) {
        $class   = strtolower($letter) . '-text';
        $output .= '<div class="alphabets">' . "\n";
        $output .= '<span class="' . esc_attr($class) . '"><strong>' . esc_html($letter) . '</strong></span>' . "\n";
        $output .= '<ul>' . "\n";

        foreach ($posts as $post) {
            $output .= '<li class="insvi"><div>';
            $output .= '<strong>' . esc_html($post['title']) . ':</strong> ' . $post['content'] . '</div>';
            $output .= lexikon_get_resources_html(
                $post['video_url'],
                $post['blog_post_id'],
                $post['file_url'],
                $post['diagram_embed']
            );
            $output .= '</li>' . "\n";
        }

        $output .= '</ul>' . "\n";
        $output .= '</div>' . "\n";
    }

    return $output;
}
add_shortcode('lexikon_display', 'lexikon_display_shortcode');

/**
 * Legacy-Kompatibilität: [lexikon tab="..."]
 */
function lexikon_legacy_shortcode( $atts ) {
    $atts = (array) $atts;
    if ( isset($atts['tab']) && ! isset($atts['type']) ) {
        $atts['type'] = $atts['tab'];
    }
    return lexikon_display_shortcode( $atts );
}
add_shortcode( 'lexikon', 'lexikon_legacy_shortcode' );


/**
 * Shortcode: [lexikon_search type="verbraucher|regel|firmen"]
 */
function lexikon_search_shortcode($atts) {
    $atts = shortcode_atts(array(
        'type' => '',
        'tab'  => 'verbraucher',
    ), $atts, 'lexikon_search');

    $type   = lexikon_canonicalize_tab_token( '' !== trim((string) $atts['type']) ? $atts['type'] : $atts['tab'] );
    $groups = lexikon_get_grouped_posts( $type );

    $output = '';
    foreach ($groups as $letter => $posts) {
        $output .= '<div class="alphabetsuche">' . "\n";
        $output .= '<strong>' . esc_html($letter) . '</strong>' . "\n";
        $output .= '<ul>' . "\n";

        foreach ($posts as $post) {
            $output .= '<li class="insvi"><div>';
            $output .= '<strong>' . esc_html($post['title']) . ':</strong> ' . $post['content'] . '</div>';
            $output .= lexikon_get_resources_html(
                $post['video_url'],
                $post['blog_post_id'],
                $post['file_url'],
                $post['diagram_embed']
            );
            $output .= '</li>' . "\n";
        }

        $output .= '</ul>' . "\n";
        $output .= '</div>' . "\n";
    }

    return $output;
}
add_shortcode('lexikon_search', 'lexikon_search_shortcode');


/**
 * JS einmalig im Footer ausgeben (alle Such-Shortcodes).
 */
function lexikon_print_search_js() {
    ?>
    <script>
    jQuery(document).ready(function($) {
      const ajaxUrl = '<?php echo esc_url( admin_url('admin-ajax.php') ); ?>';
      const searchTimeouts = new WeakMap();

      function ensureLexikonLightbox() {
        let lb = document.getElementById('lexikon-lightbox');
        if (lb) {
          if (lb.parentNode !== document.body) document.body.appendChild(lb);
          return lb;
        }

        lb = document.createElement('div');
        lb.id = 'lexikon-lightbox';
        lb.style.cssText = 'display:none;position:fixed;inset:0;z-index:999999;background:rgba(0,0,0,.85);align-items:center;justify-content:center;';
        lb.innerHTML = '<button type="button" id="lexikon-lightbox-close" aria-label="Schließen" style="position:absolute;top:18px;right:24px;background:none;border:none;color:#fff;font-size:2.2rem;cursor:pointer;line-height:1;">&times;</button>' +
                       '<div id="lexikon-lightbox-content" style="background:white;width:min(95vw,1200px);height:min(85vh,800px);display:block;border:0;border-radius:6px;box-shadow:0 4px 40px rgba(0,0,0,.6);overflow:hidden;"></div>';
        document.body.appendChild(lb);
        return lb;
      }

      function openLexikonLightbox(embedHtml) {
        if (!embedHtml) return;
        const lb      = ensureLexikonLightbox();
        const content = lb.querySelector('#lexikon-lightbox-content');
        if (!content) return;
        content.innerHTML = embedHtml;
        lb.style.display = 'flex';
        lb.classList.add('is-open');
        document.body.style.overflow = 'hidden';
      }

      function closeLexikonLightbox() {
        const lb = document.getElementById('lexikon-lightbox');
        if (!lb) return;
        const content = lb.querySelector('#lexikon-lightbox-content');
        if (content) content.innerHTML = '';
        lb.style.display = 'none';
        lb.classList.remove('is-open');
        document.body.style.overflow = '';
      }

      $(document).on('click', function(e) {
        const diagramBtn = $(e.target).closest('.lexikon-diagram-btn');
        if (diagramBtn.length) {
          e.preventDefault();
          const encoded = diagramBtn.attr('data-embed');
          if (!encoded) return;
          openLexikonLightbox(decodeURIComponent(encoded));
          return;
        }

        const lb = $('#lexikon-lightbox');
        if (!lb.length) return;

        if (e.target === lb[0] || e.target === document.getElementById('lexikon-lightbox-close')) {
          closeLexikonLightbox();
        }
      });

      $(document).on('keydown', function(e) {
        if (e.key === 'Escape') closeLexikonLightbox();
      });

      $(document).on('input', '.lexikon-search-input', function() {
        const input          = $(this);
        const container      = input.closest('.lexikon-search');
        if (!container.length) return;

        const resultsContainer = container.find('.lexikon-search-results');
        const action           = container.data('action');
        const nonce            = container.data('nonce');

        if (!resultsContainer.length || !action || !nonce) return;

        const existingTimeout = searchTimeouts.get(this);
        if (existingTimeout) clearTimeout(existingTimeout);

        const searchTerm = input.val().trim();
        if (searchTerm.length < 1) {
          resultsContainer.html('');
          return;
        }

        const self      = this;
        const timeoutId = setTimeout(function() {
          performSearch(action, nonce, searchTerm, resultsContainer);
        }, 200);

        searchTimeouts.set(self, timeoutId);
      });

      function performSearch(action, nonce, term, resultsContainer) {
        resultsContainer.html('<div class="search-loading">Suche läuft...</div>');

        fetch(ajaxUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ action: action, search_term: term, nonce: nonce })
        })
        .then(r => {
          if (!r.ok) throw new Error('HTTP ' + r.status);
          return r.json();
        })
        .then(data => displayResults(resultsContainer, data))
        .catch(() => resultsContainer.html('<div class="search-error">Fehler bei der Suche</div>'));
      }

      function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
      }

      function displayResults(resultsContainer, data) {
        if (!data.success || !Array.isArray(data.data) || data.data.length === 0) {
          resultsContainer.html('<div class="no-results">Keine Ergebnisse gefunden</div>');
          return;
        }

        let html = '<div class="search-count">' + data.data.length + ' Ergebnis' + (data.data.length !== 1 ? 'se' : '') + '</div>';
        html += '<ul class="results-list">';

        data.data.forEach(item => {
          html += '<li class="result-item">';
          html += '<div class="result-title">' + escapeHtml(item.title) + '</div>';
          if (item.excerpt)       html += '<div class="result-excerpt">' + item.excerpt + '</div>';
          if (item.resources_html) html += item.resources_html;
          html += '</li>';
        });

        html += '</ul>';
        resultsContainer.html(html);
      }

    });
    </script>
    <?php
}


/**
 * Gemeinsamer Renderer für die Lexikon-Suche (AJAX Inputs).
 */
function lexikon_render_search_form($verfahren, $placeholder) {
    $verfahren  = sanitize_key($verfahren);
    $action     = $verfahren . '_search';
    $nonce      = wp_create_nonce( $verfahren . '_search_nonce' );

    if ( ! has_action('wp_footer', 'lexikon_print_search_js') ) {
        add_action('wp_footer', 'lexikon_print_search_js', 20);
    }

    ob_start();
    ?>
    <div class="sidebar-search lexikon-search lexikon-search-<?php echo esc_attr($verfahren); ?>"
         data-action="<?php echo esc_attr($action); ?>"
         data-nonce="<?php echo esc_attr($nonce); ?>">
        <input
            type="text"
            class="sidebar-search-input lexikon-search-input"
            placeholder="<?php echo esc_attr($placeholder); ?>"
            autocomplete="off"
        />
        <div class="search-results lexikon-search-results"></div>
    </div>
    <?php
    return ob_get_clean();
}

function verbraucher_search_shortcode() {
    return lexikon_render_search_form('verbraucher', 'Verbraucherinsolvenz durchsuchen...');
}
add_shortcode('verbraucher_search', 'verbraucher_search_shortcode');

function regel_search_shortcode() {
    return lexikon_render_search_form('regel', 'Regelinsolvenz durchsuchen...');
}
add_shortcode('regel_search', 'regel_search_shortcode');

function firmen_search_shortcode() {
    return lexikon_render_search_form('firmen', 'Firmeninsolvenz durchsuchen...');
}
add_shortcode('firmen_search', 'firmen_search_shortcode');

function lexikon_global_search_shortcode() {
    return lexikon_render_search_form('lexikon', 'Gesamtes Lexikon durchsuchen...');
}
add_shortcode('lexikon_global_search', 'lexikon_global_search_shortcode');


/**
 * AJAX Handler
 */
function verbraucher_search_ajax() {
    check_ajax_referer('verbraucher_search_nonce', 'nonce');
    $search_term = isset($_POST['search_term']) ? sanitize_text_field(wp_unslash($_POST['search_term'])) : '';
    if ($search_term === '') wp_send_json_success(array());
    wp_send_json_success( search_by_verfahren($search_term, 'verbraucher') );
}
add_action('wp_ajax_verbraucher_search', 'verbraucher_search_ajax');
add_action('wp_ajax_nopriv_verbraucher_search', 'verbraucher_search_ajax');

function regel_search_ajax() {
    check_ajax_referer('regel_search_nonce', 'nonce');
    $search_term = isset($_POST['search_term']) ? sanitize_text_field(wp_unslash($_POST['search_term'])) : '';
    if ($search_term === '') wp_send_json_success(array());
    wp_send_json_success( search_by_verfahren($search_term, 'regel') );
}
add_action('wp_ajax_regel_search', 'regel_search_ajax');
add_action('wp_ajax_nopriv_regel_search', 'regel_search_ajax');

function firmen_search_ajax() {
    check_ajax_referer('firmen_search_nonce', 'nonce');
    $search_term = isset($_POST['search_term']) ? sanitize_text_field(wp_unslash($_POST['search_term'])) : '';
    if ($search_term === '') wp_send_json_success(array());
    wp_send_json_success( search_by_verfahren($search_term, 'firmen') );
}
add_action('wp_ajax_firmen_search', 'firmen_search_ajax');
add_action('wp_ajax_nopriv_firmen_search', 'firmen_search_ajax');

function lexikon_search_ajax() {
    check_ajax_referer('lexikon_search_nonce', 'nonce');
    $search_term = isset($_POST['search_term']) ? sanitize_text_field(wp_unslash($_POST['search_term'])) : '';
    if ($search_term === '') wp_send_json_success(array());
    wp_send_json_success( search_by_verfahren($search_term, 'lexikon') );
}
add_action('wp_ajax_lexikon_search', 'lexikon_search_ajax');
add_action('wp_ajax_nopriv_lexikon_search', 'lexikon_search_ajax');


/**
 * Core search function (DB Query)
 */
if ( ! function_exists( 'search_by_verfahren' ) ) {
    function search_by_verfahren($search_term, $verfahren) {
        $search_term = sanitize_text_field($search_term);
        $verfahren   = sanitize_text_field($verfahren);

        $query   = new WP_Query(array(
            'post_type'      => 'lexikon',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            's'              => $search_term,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        $results = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                if ( ! lexikon_post_matches_type( $post_id, $verfahren ) ) continue;

                $content_full = apply_filters('the_content', get_post_field('post_content', $post_id));

                if (!empty($search_term)) {
                    $content_full = preg_replace(
                        '/' . preg_quote($search_term, '/') . '/i',
                        '<strong>$0</strong>',
                        $content_full
                    );
                }

                $results[] = array(
                    'title'          => get_the_title($post_id),
                    'excerpt'        => $content_full,
                    'resources_html' => lexikon_get_resources_html(
                        get_post_meta( $post_id, '_lexikon_video_url', true ),
                        lm_get_blog_post_id( $post_id ),
                        get_post_meta( $post_id, '_lexikon_file_url', true ),
                        get_post_meta( $post_id, '_lexikon_diagram_embed', true )
                    ),
                );
            }
            wp_reset_postdata();
        }

        return $results;
    }
}