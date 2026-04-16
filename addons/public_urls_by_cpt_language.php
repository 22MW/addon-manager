<?php
/**
 * Plugin Name: URLs públicas por CPT e idioma
 * Description: Shortcode para listar URLs publicadas por idioma y CPT, con filtros y botón de exportación a Markdown.
 * Version: 1.0.0
 * Author: 22MW
 * Long Description: Shortcode [public_urls_by_cpt_language]. Parametros: post_types="page,product" (filtra CPT), show_empty="0|1" (muestra u oculta grupos vacios), show_titles="0|1" (muestra u oculta el titulo junto a cada URL), languages="es,en" (incluye solo esos idiomas), exclude_languages="fr,de" (excluye idiomas), current_language_only="0|1" (solo idioma actual). Incluye boton para exportar el listado a .md.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Detecta idiomas activos (Polylang/WPML) o devuelve un idioma único por defecto.
 *
 * @return array<int,array<string,string>>
 */
function am_public_urls_detect_languages() {
    if (function_exists('pll_languages_list')) {
        $codes = pll_languages_list(array('fields' => 'slug'));
        $names = pll_languages_list(array('fields' => 'name'));
        $list  = array();

        foreach ((array) $codes as $index => $code) {
            $name = isset($names[$index]) ? (string) $names[$index] : strtoupper((string) $code);
            $list[] = array(
                'code' => (string) $code,
                'name' => $name !== '' ? $name : strtoupper((string) $code),
                'source' => 'polylang',
            );
        }

        if (!empty($list)) {
            return $list;
        }
    }

    if (defined('ICL_SITEPRESS_VERSION')) {
        $active = apply_filters(
            'wpml_active_languages',
            null,
            array(
                'skip_missing' => 0,
                'orderby' => 'code',
            )
        );

        if (is_array($active) && !empty($active)) {
            $list = array();
            foreach ($active as $lang) {
                $code = isset($lang['code']) ? (string) $lang['code'] : '';
                if ($code === '') {
                    continue;
                }
                $name = isset($lang['translated_name']) ? (string) $lang['translated_name'] : strtoupper($code);
                $list[] = array(
                    'code' => $code,
                    'name' => $name !== '' ? $name : strtoupper($code),
                    'source' => 'wpml',
                );
            }

            if (!empty($list)) {
                return $list;
            }
        }
    }

    return array(
        array(
            'code' => 'default',
            'name' => __('Idioma principal', 'default'),
            'source' => 'single',
        ),
    );
}

/**
 * Devuelve post types públicos consultables.
 *
 * @return array<string,WP_Post_Type>
 */
function am_public_urls_get_public_post_types() {
    $types = get_post_types(
        array(
            'public' => true,
            'publicly_queryable' => true,
        ),
        'objects'
    );

    if (isset($types['attachment'])) {
        unset($types['attachment']);
    }

    return is_array($types) ? $types : array();
}

/**
 * Parsea lista CSV para atributos de shortcode.
 *
 * @param string $value
 * @return array<int,string>
 */
function am_public_urls_parse_csv($value) {
    $items = array_map('trim', explode(',', (string) $value));
    $items = array_filter($items, static function ($item) {
        return $item !== '';
    });

    return array_values(array_unique(array_map(static function ($item) {
        return strtolower((string) $item);
    }, $items)));
}

/**
 * Escapa texto para salida Markdown básica.
 *
 * @param string $text
 * @return string
 */
function am_public_urls_markdown_escape($text) {
    $text = (string) $text;
    $map = array(
        '\\' => '\\\\',
        '*'  => '\*',
        '_'  => '\_',
        '['  => '\[',
        ']'  => '\]',
        '`'  => '\`',
    );

    return strtr($text, $map);
}

/**
 * Render del shortcode principal.
 *
 * @param array<string,mixed> $atts
 * @return string
 */
function am_public_urls_by_cpt_language_shortcode($atts) {
    $atts = shortcode_atts(
        array(
            'post_types' => '',
            'show_empty' => '0',
            'show_titles' => '1',
            'languages' => '',
            'exclude_languages' => '',
            'current_language_only' => '0',
        ),
        (array) $atts,
        'public_urls_by_cpt_language'
    );

    $show_empty = in_array((string) $atts['show_empty'], array('1', 'true', 'yes'), true);
    $show_titles = in_array((string) $atts['show_titles'], array('1', 'true', 'yes'), true);
    $current_language_only = in_array((string) $atts['current_language_only'], array('1', 'true', 'yes'), true);

    $all_types = am_public_urls_get_public_post_types();
    if (empty($all_types)) {
        return '<p>No se encontraron tipos de contenido públicos.</p>';
    }

    $requested_types = array();
    if ((string) $atts['post_types'] !== '') {
        $requested_types = array_filter(
            array_map('sanitize_key', array_map('trim', explode(',', (string) $atts['post_types'])))
        );
    }

    if (!empty($requested_types)) {
        $all_types = array_intersect_key($all_types, array_flip($requested_types));
        if (empty($all_types)) {
            return '<p>No hay coincidencias en <code>post_types</code>.</p>';
        }
    }

    $languages = am_public_urls_detect_languages();

    $include_langs = am_public_urls_parse_csv((string) $atts['languages']);
    $exclude_langs = am_public_urls_parse_csv((string) $atts['exclude_languages']);

    if ($current_language_only) {
        $current_lang = '';
        if (function_exists('pll_current_language')) {
            $current_lang = (string) pll_current_language('slug');
        } elseif (defined('ICL_SITEPRESS_VERSION')) {
            $current_lang = (string) apply_filters('wpml_current_language', null);
        }

        if ($current_lang !== '') {
            $current_lang = strtolower($current_lang);
            $languages = array_values(array_filter($languages, static function ($lang) use ($current_lang) {
                $code = isset($lang['code']) ? strtolower((string) $lang['code']) : '';
                return $code === $current_lang;
            }));
        }
    }

    if (!empty($include_langs)) {
        $languages = array_values(array_filter($languages, static function ($lang) use ($include_langs) {
            $code = isset($lang['code']) ? strtolower((string) $lang['code']) : '';
            return in_array($code, $include_langs, true);
        }));
    }

    if (!empty($exclude_langs)) {
        $languages = array_values(array_filter($languages, static function ($lang) use ($exclude_langs) {
            $code = isset($lang['code']) ? strtolower((string) $lang['code']) : '';
            return !in_array($code, $exclude_langs, true);
        }));
    }

    if (empty($languages)) {
        return '<p>No hay idiomas que coincidan con los filtros aplicados.</p>';
    }

    $original_wpml_lang = null;
    if (defined('ICL_SITEPRESS_VERSION')) {
        $original_wpml_lang = apply_filters('wpml_current_language', null);
    }

    $container_id = 'am-public-urls-' . wp_rand(1000, 999999);
    $markdown_id = $container_id . '-markdown';
    $export_filename = 'public-urls-' . gmdate('Y-m-d-H-i-s') . '.md';
    $markdown_lines = array(
        '# URLs públicas por idioma y CPT',
        '',
    );

    ob_start();
    echo '<div id="' . esc_attr($container_id) . '" class="am-public-urls-report">';
    echo '<h3>URLs públicas por idioma y CPT</h3>';
    echo '<p><button type="button" class="button am-public-urls-export" data-source-id="' . esc_attr($markdown_id) . '" data-filename="' . esc_attr($export_filename) . '">Exportar .md</button></p>';

    foreach ($languages as $lang) {
        $lang_code = isset($lang['code']) ? (string) $lang['code'] : 'default';
        $lang_name = isset($lang['name']) ? (string) $lang['name'] : strtoupper($lang_code);
        $source    = isset($lang['source']) ? (string) $lang['source'] : 'single';
        $markdown_lines[] = '## ' . am_public_urls_markdown_escape($lang_name) . ' (' . am_public_urls_markdown_escape($lang_code) . ')';
        $markdown_lines[] = '';

        if ($source === 'wpml' && $lang_code !== '') {
            do_action('wpml_switch_language', $lang_code);
        }

        echo '<div class="am-public-urls-language">';
        echo '<h4>' . esc_html($lang_name) . ' (' . esc_html($lang_code) . ')</h4>';

        foreach ($all_types as $post_type => $obj) {
            $args = array(
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
                'fields' => 'ids',
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'suppress_filters' => false,
            );

            if ($source === 'polylang' && $lang_code !== 'default') {
                $args['lang'] = $lang_code;
            }

            $ids = get_posts($args);
            $count = is_array($ids) ? count($ids) : 0;

            if (!$show_empty && $count === 0) {
                continue;
            }

            $label = isset($obj->labels->name) ? (string) $obj->labels->name : $post_type;
            $markdown_lines[] = '### ' . am_public_urls_markdown_escape($label) . ' (' . am_public_urls_markdown_escape($post_type) . ': ' . $count . ')';
            echo '<section class="am-public-urls-cpt">';
            echo '<h3>' . esc_html($label) . ' <small>(' . esc_html($post_type) . ': ' . esc_html((string) $count) . ')</small></h3>';

            if ($count === 0) {
                echo '<p><em>Sin URLs publicadas.</em></p>';
                $markdown_lines[] = '- _Sin URLs publicadas._';
                $markdown_lines[] = '';
                echo '</section>';
                continue;
            }

            echo '<ul>';
            foreach ($ids as $post_id) {
                $post_id = (int) $post_id;
                $url = get_permalink($post_id);
                if (!is_string($url) || $url === '') {
                    continue;
                }

                $title = get_the_title($post_id);
                if (!is_string($title) || $title === '') {
                    $title = '(sin título)';
                }

                if ($show_titles) {
                    $markdown_lines[] = '- **' . am_public_urls_markdown_escape($title) . '**: ' . $url;
                } else {
                    $markdown_lines[] = '- ' . $url;
                }

                echo '<li>';
                if ($show_titles) {
                    echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($title) . '</a> ';
                }
                echo '<code>' . esc_html($url) . '</code>';
                echo '</li>';
            }
            echo '</ul>';
            $markdown_lines[] = '';
            echo '</section>';
        }

        echo '</div>';
        $markdown_lines[] = '';
    }

    if (defined('ICL_SITEPRESS_VERSION')) {
        do_action('wpml_switch_language', $original_wpml_lang);
    }

    $markdown_output = implode("\n", $markdown_lines);
    echo '<textarea id="' . esc_attr($markdown_id) . '" hidden>' . esc_textarea($markdown_output) . '</textarea>';
    echo '<script>(function(){'
        . 'var root=document.getElementById("' . esc_js($container_id) . '");'
        . 'if(!root){return;}'
        . 'var btn=root.querySelector(".am-public-urls-export");'
        . 'if(!btn){return;}'
        . 'btn.addEventListener("click",function(){'
            . 'var sourceId=btn.getAttribute("data-source-id");'
            . 'var filename=btn.getAttribute("data-filename")||"public-urls.md";'
            . 'var source=document.getElementById(sourceId);'
            . 'if(!source){return;}'
            . 'var content=source.value||"";'
            . 'var blob=new Blob([content],{type:"text/markdown;charset=utf-8"});'
            . 'var url=URL.createObjectURL(blob);'
            . 'var a=document.createElement("a");'
            . 'a.href=url;'
            . 'a.download=filename;'
            . 'document.body.appendChild(a);'
            . 'a.click();'
            . 'a.remove();'
            . 'setTimeout(function(){URL.revokeObjectURL(url);},1000);'
        . '});'
    . '})();</script>';

    echo '</div>';
    return (string) ob_get_clean();
}

add_shortcode('public_urls_by_cpt_language', 'am_public_urls_by_cpt_language_shortcode');
