<?php

/**
 * Plugin Name: FAQ Interactive
 * Description: FAQ interactiva responsive con AJAX, filtros por categorías y personalización visual mediante shortcode.
 * Version: 3.1.22
 * Author: 22MW
 * Author URI: https://22mw.online
 */

// Variable global para AJAX
add_action('wp_head', 'faq_ajax_var');
function faq_ajax_var()
{
    echo '<script>var faqAjax="' . admin_url('admin-ajax.php') . '";</script>';
}

// CSS inline
add_action('wp_head', 'faq_inline_css');
function faq_inline_css()
{
?>
    <style>
        :root {
            --faq-active-bg-color: #7f8bec;
            --faq-active-text-color: #ffffff;
            --faq-active-title-color: #1a1a1a;
            --faq-text-color: #1a1a1a;
            --faq-bg-color: #f6f5f3;

        }

        .faq-interactive {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin: 0;
            max-height: 80vh;
            height: 500px;
            overflow: hidden;
            padding: 20px;

        }

        .faq-interactive.faq-position-right {
            direction: rtl;
        }

        .faq-interactive.faq-position-right>* {
            direction: ltr;
        }

        .faq-questions {
            display: flex;
            flex-direction: column;
            gap: 5px;
            overflow-y: auto;
            padding-right: 0px;
            height: 100%;
            padding: 30px 0
        }

        .faq-questions::-webkit-scrollbar,
        .faq-answer-content::-webkit-scrollbar {
            width: 8px;
        }

        .faq-questions::-webkit-scrollbar-track,
        .faq-answer-content::-webkit-scrollbar-track {
            background: transparent;
            border-radius: 4px
        }

        .faq-questions::-webkit-scrollbar-thumb,
        .faq-answer-content::-webkit-scrollbar-thumb {
            background: var(--faq-bg-color);
            border-radius: 4px;
            border-color: var(--faq-bg-color);
        }

        a.faq-question-link {
            padding: 15px;
            background: var(--faq-bg-color);
            border-radius: 10px;
            text-decoration: none;
            color: var(--link-color, var(--faq-text-color));
            transition: all .3s;
            flex-shrink: 0;
            display: block;
            margin-right: 10px;
            font-weight: 500;
        }

        a.faq-question-link:hover {
            background: var(--link-color, var(--faq-active-bg-color));
            color: var(--faq-active-text-color);
        }

        a.faq-question-link.active,
        .faq-question-link.active:hover {
            background: var(--link-color, var(--faq-active-bg-color));
            color: var(--faq-bg-color);
            margin-right: 10px;
        }

        .faq-answer-content p a {
            color: var(--faq-text-color);
            text-decoration: underline;
        }

        .faq-answer-content p a:hover {
            color: var(--faq-text-color);
            text-decoration: none;
        }

        .faq-answer-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            max-height: 100%;
            overflow: hidden
        }

        .faq-answer {
            width: 100%;
            height: 100%;
            padding: 20px;
            background: var(--faq-active-bg-color);
            border-radius: 22px;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            transition: background .3s ease;
        }

        .faq-close {
            display: none
        }

        .faq-answer-content {
            line-height: 1.6;
            overflow-y: auto;
            padding-right: 10px;
            flex: 1;
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            align-content: flex-start;
            padding: 20px 0;
            opacity: 1;
            transition: opacity .3s ease;
        }

        .faq-answer-content.faq-fade-out {
            opacity: 0;
        }

        .faq-answer-content h3 {
            font-weight: 400;
            line-height: 32px;
            font-size: 29px;
            width: 100%;
            text-align: center;
            color: var(--faq-active-title-color);
        }

        .faq-answer-content p {
            color: #fff;
            letter-spacing: 0px;
            line-height: 23px;
            font-weight: 500;
            padding: 0px 20px
        }

        .faq-loading {
            display: none;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--faq-bg-color);
            align-items: center;
            justify-content: center;
            z-index: 10
        }

        .faq-loading.active {
            display: flex
        }

        .faq-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--faq-bg-color);
            border-top: 4px solid var(--faq-active-bg-color);
            border-radius: 50%;
            animation: faq-spin 1s linear infinite
        }

        @keyframes faq-spin {
            0% {
                transform: rotate(0deg)
            }

            100% {
                transform: rotate(360deg)
            }
        }

        @media (max-width:768px) {
            .faq-interactive {
                grid-template-columns: 1fr;
                height: auto;
                max-height: none;
                overflow: visible;
                padding:0px;
            }

            .faq-questions {
                max-height: none;
                overflow-y: visible;
                padding: 10px;
                margin: 0px
            }

            .faq-answer-wrapper {
                display: none
            }

            .faq-answer-wrapper.active {
                display: block;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 9999
            }

            .faq-answer {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 90%;
                max-width: 500px;
                height: auto;
                max-height: 80vh;
            }

            .faq-close {
                display: block;
                position: absolute;
                top: 10px;
                left: 10px;
                background: transparent;
                border: none;
                font-size: 30px;
                cursor: pointer;
                color: var(--faq-active-text-color);
                line-height: 1;
                padding: 0;
                width: 30px;
                height: 30px;
                z-index: 10
            }

            .faq-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: #ffffff8e;
                z-index: 9998
            }

            .faq-overlay.active {
                display: block
            }

            a.faq-question-link.active,
            .faq-question-link.active:hover {
                border-radius: 10px;
                margin-right: 10px
            }

            .faq-answer-content h3 {
                font-weight: 400;
                font-size: 24px
            }

            .faq-answer-content p {
                padding: 0
            }
        }
    </style>
<?php
}

// JS inline
add_action('wp_footer', 'faq_inline_js');
function faq_inline_js()
{
?>
    <script>
        (function() {
            if (window.faqInit) return;
            window.faqInit = true;

            function init() {
                document.querySelectorAll('.faq-interactive').forEach(function(container) {
                    if (container.dataset.init) return;
                    container.dataset.init = '1';

                    var answerDiv = container.querySelector('.faq-answer-content');
                    var answerWrapper = container.querySelector('.faq-answer-wrapper');
                    var answerBox = container.querySelector('.faq-answer');
                    var closeBtn = container.querySelector('.faq-close');
                    var links = container.querySelectorAll('.faq-question-link');
                    var loading = container.querySelector('.faq-loading');

                    var faqData = JSON.parse(container.getAttribute('data-faqs'));

                    var overlay = document.querySelector('.faq-overlay');
                    if (!overlay) {
                        overlay = document.createElement('div');
                        overlay.className = 'faq-overlay';
                        document.body.appendChild(overlay);
                    }

                    function loadFAQ(id) {
                        var faq = faqData[id];
                        if (!faq) {
                            history.replaceState('', document.title, window.location.pathname);
                            return;
                        }

                        links.forEach(function(l) {
                            l.classList.remove('active')
                        });
                        var curr = container.querySelector('[data-faq-id="' + id + '"]');
                        if (curr) {
                            curr.classList.add('active');
                        }

                        answerDiv.classList.add('faq-fade-out');

                        setTimeout(function() {
                            answerDiv.innerHTML = '<h3>' + faq.title + '</h3>' + faq.content;
                            answerDiv.scrollTop = 0;
                            answerDiv.classList.remove('faq-fade-out');

                            if (window.innerWidth <= 768) {
                                answerWrapper.classList.add('active');
                                overlay.classList.add('active');
                                document.body.style.overflow = 'hidden';
                            }
                        }, 300);
                    }

                    function close() {
                        answerWrapper.classList.remove('active');
                        overlay.classList.remove('active');
                        document.body.style.overflow = '';
                        history.pushState('', document.title, window.location.pathname);
                    }

                    links.forEach(function(link) {
                        link.addEventListener('click', function(e) {
                            e.preventDefault();
                            var id = this.getAttribute('data-faq-id');
                            loadFAQ(id);
                            history.pushState(null, '', '#faq_' + id);
                        });
                    });

                    if (closeBtn) closeBtn.addEventListener('click', close);
                    overlay.addEventListener('click', close);

                    var hash = window.location.hash;
                    if (hash.indexOf('#faq_') === 0) {
                        var hashId = hash.replace('#faq_', '');
                        if (faqData[hashId]) {
                            loadFAQ(hashId);
                        } else if (links.length > 0 && window.innerWidth > 768) {
                            loadFAQ(links[0].getAttribute('data-faq-id'));
                        }
                    } else if (links.length > 0 && window.innerWidth > 768) {
                        loadFAQ(links[0].getAttribute('data-faq-id'));
                    }

                    window.addEventListener('hashchange', function() {
                        var h = window.location.hash;
                        if (h.indexOf('#faq_') === 0) {
                            loadFAQ(h.replace('#faq_', ''));
                        }
                    });
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }

            setTimeout(init, 500);
        })();
    </script>
<?php
}

// Shortcode
add_shortcode('faq_interactive', 'faq_shortcode');
function faq_shortcode($atts)
{
    $atts = shortcode_atts(array(
        'post_type' => 'faq',
        'categories' => '',
        'position' => 'left',
        'random' => '',
        'active_bg_color' => '',
        'active_text_color' => '',
        'active_title_color' => '',
        'text_color' => '',
        'bg_color' => ''
    ), $atts);

    $post_types = array_map('trim', explode(',', $atts['post_type']));

    $all_posts = array();

    foreach ($post_types as $type) {
        $args = array(
            'post_type' => $type,
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        );

        if (count($post_types) === 1 && !empty($atts['categories'])) {
            $taxonomies = get_object_taxonomies($type);
            if (!empty($taxonomies)) {
                $cats = array_map('trim', explode(',', $atts['categories']));
                $first = $cats[0];
                $field = is_numeric($first) ? 'term_id' : 'slug';
                $terms = $field === 'term_id' ? array_map('intval', $cats) : $cats;

                $args['tax_query'] = array(array(
                    'taxonomy' => $taxonomies[0],
                    'field' => $field,
                    'terms' => $terms
                ));
            }
        } elseif (is_tax()) {
            $term = get_queried_object();
            $args['tax_query'] = array(array(
                'taxonomy' => $term->taxonomy,
                'field' => 'term_id',
                'terms' => $term->term_id
            ));
        }

        $query = new WP_Query($args);
        if ($query->have_posts()) {
            $all_posts = array_merge($all_posts, $query->posts);
        }
    }

    if ($atts['random'] === '1') {
        shuffle($all_posts);
    }

    if (empty($all_posts)) return '<p>No hay preguntas disponibles.</p>';

    $faqs = (object) array('posts' => $all_posts);

    // Preparar datos JSON con todas las respuestas
    $faq_data = array();
    foreach ($all_posts as $post) {
        setup_postdata($post);
        $faq_data[$post->ID] = array(
            'title' => get_the_title($post->ID),
            'content' => apply_filters('the_content', $post->post_content)
        );
    }

    $position_class = $atts['position'] === 'right' ? ' faq-position-right' : '';
    $faq_id = 'faq-' . uniqid();

    $custom_css = '';
    if (!empty($atts['active_bg_color']) || !empty($atts['active_text_color']) || !empty($atts['active_title_color']) || !empty($atts['text_color']) || !empty($atts['bg_color'])) {
        $custom_css = '<style>';
        $custom_css .= '#' . $faq_id . ' {';
        if (!empty($atts['active_bg_color'])) $custom_css .= '--faq-active-bg-color:' . esc_attr($atts['active_bg_color']) . ';';
        if (!empty($atts['active_text_color'])) $custom_css .= '--faq-active-text-color:' . esc_attr($atts['active_text_color']) . ';';
        if (!empty($atts['active_title_color'])) $custom_css .= '--faq-active-title-color:' . esc_attr($atts['active_title_color']) . ';';
        if (!empty($atts['text_color'])) $custom_css .= '--faq-text-color:' . esc_attr($atts['text_color']) . ';';
        if (!empty($atts['bg_color'])) $custom_css .= '--faq-bg-color:' . esc_attr($atts['bg_color']) . ';';
        $custom_css .= '}</style>';
    }

    ob_start();
    echo $custom_css;
?>
    <div id="<?php echo $faq_id; ?>" class="faq-interactive<?php echo $position_class; ?>" data-faqs='<?php echo esc_attr(json_encode($faq_data)); ?>'>
        <div class="faq-questions">
            <?php foreach ($all_posts as $post):
                setup_postdata($post);
            ?>
                <a href="#faq_<?php echo $post->ID; ?>" class="faq-question-link" data-faq-id="<?php echo $post->ID; ?>"><?php echo get_the_title($post->ID); ?></a>
            <?php endforeach; ?>
        </div>
        <div class="faq-answer-wrapper">
            <div class="faq-answer">
                <div class="faq-loading">
                    <div class="faq-spinner"></div>
                </div>
                <button class="faq-close">&times;</button>
                <div class="faq-answer-content">
                    <p>Selecciona una pregunta</p>
                </div>
            </div>
        </div>
    </div>
<?php
    wp_reset_postdata();
    return ob_get_clean();
}

// AJAX (mantenido por compatibilidad)
add_action('wp_ajax_get_faq_content', 'faq_ajax');
add_action('wp_ajax_nopriv_get_faq_content', 'faq_ajax');
function faq_ajax()
{
    $post_id = intval($_POST['post_id']);
    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'faq') {
        wp_send_json_error();
    }

    $content = '<h3>' . get_the_title($post_id) . '</h3>';
    $content .= apply_filters('the_content', $post->post_content);

    wp_send_json_success(array('content' => $content));
}
