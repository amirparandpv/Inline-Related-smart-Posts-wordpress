<?php
/**
 * Plugin Name: Inline Related Posts (IRP)
 * Plugin URI: https://github.com/amirparandpv/inline-related-posts
 * Description: نمایش مطالب مرتبط به‌صورت درون‌متن با تنظیمات کامل (پست‌تایپ، دسته‌ها، استایل، تعداد، الگوریتم، محل درج و دکمه ویرایشگر).
 * Version: 1.0.0
 * Author: amirparand.ir
 * Author URI: https://amirparand.ir
 * Text Domain: irp
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */


if (!defined('ABSPATH')) exit;

// Constants
define('IRP_VERSION', '1.0.0');
define('IRP_OPTION_KEY', 'irp_settings');
define('IRP_PLUGIN_FILE', __FILE__);
define('IRP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IRP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Defaults
function irp_default_settings() {
    return array(
        'after_paragraph' => 5,
        'post_types'      => array('post'),
        'category_ids'    => array(), // خالی یعنی همه دسته‌ها
        'num_posts'       => 4,
        'order_strategy'  => 'related', // related|random
        'style_variant'   => 'card',    // card|list
        'custom_css_class'=> '',
        'color_theme'     => '#0ea5e9', // آبی
        'auto_insert'     => 1,         // 1: خودکار فعال، اگر شورتکد بود، خودکار غیرفعاله
    );
}

// Activation: set defaults if not set
register_activation_hook(IRP_PLUGIN_FILE, function() {
    $opts = get_option(IRP_OPTION_KEY);
    if (!is_array($opts)) {
        update_option(IRP_OPTION_KEY, irp_default_settings());
    } else {
        update_option(IRP_OPTION_KEY, wp_parse_args($opts, irp_default_settings()));
    }
});

// Admin menu
add_action('admin_menu', function() {
    add_options_page(
        __('Inline Related Posts', 'irp'),
        __('Inline Related Posts', 'irp'),
        'manage_options',
        'irp-settings',
        'irp_render_settings_page'
    );
});

// Register settings
add_action('admin_init', function() {
    register_setting('irp_settings_group', IRP_OPTION_KEY, array(
        'sanitize_callback' => 'irp_sanitize_settings'
    ));

    add_settings_section('irp_main', __('General', 'irp'), '__return_false', 'irp-settings');

    add_settings_field('after_paragraph', 'نمایش بعد از چندمین پاراگراف', 'irp_field_after_paragraph', 'irp-settings', 'irp_main');
    add_settings_field('post_types', 'پست‌تایپ‌ها', 'irp_field_post_types', 'irp-settings', 'irp_main');
    add_settings_field('category_ids', 'دسته‌ها (اختیاری)', 'irp_field_categories', 'irp-settings', 'irp_main');
    add_settings_field('num_posts', 'تعداد پست‌ها', 'irp_field_num_posts', 'irp-settings', 'irp_main');
    add_settings_field('order_strategy', 'الگوریتم نمایش', 'irp_field_order_strategy', 'irp-settings', 'irp_main');
    add_settings_field('style_variant', 'استایل', 'irp_field_style_variant', 'irp-settings', 'irp_main');
    add_settings_field('color_theme', 'رنگ تم', 'irp_field_color_theme', 'irp-settings', 'irp_main');
    add_settings_field('custom_css_class', 'کلاس CSS سفارشی', 'irp_field_custom_css_class', 'irp-settings', 'irp_main');
    add_settings_field('auto_insert', 'درج خودکار', 'irp_field_auto_insert', 'irp-settings', 'irp_main');
});

// Sanitize
function irp_sanitize_settings($input) {
    $defaults = irp_default_settings();
    $out = array();

    $out['after_paragraph'] = max(0, intval($input['after_paragraph'] ?? $defaults['after_paragraph']));
    $out['num_posts']       = max(1, min(12, intval($input['num_posts'] ?? $defaults['num_posts'])));

    $out['order_strategy']  = in_array(($input['order_strategy'] ?? ''), array('related','random'), true) ? $input['order_strategy'] : $defaults['order_strategy'];
    $out['style_variant']   = in_array(($input['style_variant'] ?? ''), array('card','list'), true) ? $input['style_variant'] : $defaults['style_variant'];

    $out['custom_css_class']= sanitize_html_class($input['custom_css_class'] ?? '');
    $color = trim($input['color_theme'] ?? $defaults['color_theme']);
    $out['color_theme']     = preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color) ? $color : $defaults['color_theme'];

    $out['auto_insert']     = !empty($input['auto_insert']) ? 1 : 0;

    // Post types
    $available = get_post_types(array('public' => true), 'names');
    $selected  = isset($input['post_types']) && is_array($input['post_types']) ? array_values(array_intersect($input['post_types'], $available)) : array();
    $out['post_types'] = !empty($selected) ? $selected : $defaults['post_types'];

    // Categories
    $cats_in = isset($input['category_ids']) && is_array($input['category_ids']) ? array_map('intval', $input['category_ids']) : array();
    $out['category_ids'] = array_values(array_filter($cats_in));

    return wp_parse_args($out, $defaults);
}

// Fields
function irp_get_settings() {
    return wp_parse_args(get_option(IRP_OPTION_KEY, array()), irp_default_settings());
}

function irp_field_after_paragraph() {
    $s = irp_get_settings();
    echo '<input type="number" min="0" step="1" name="'.esc_attr(IRP_OPTION_KEY).'[after_paragraph]" value="'.esc_attr($s['after_paragraph']).'" /> ';
    echo '<p class="description">0 یعنی انتهای محتوا. اگر کاربر با دکمه یا شورتکد درج کند، این مقدار نادیده گرفته می‌شود.</p>';
}

function irp_field_post_types() {
    $s = irp_get_settings();
    $types = get_post_types(array('public'=>true),'objects');
    foreach ($types as $type) {
        $checked = in_array($type->name, $s['post_types'], true) ? 'checked' : '';
        echo '<label style="display:inline-block;margin:0 12px 8px 0">';
        echo '<input type="checkbox" name="'.esc_attr(IRP_OPTION_KEY).'[post_types][]" value="'.esc_attr($type->name).'" '.$checked.'> '.esc_html($type->labels->singular_name);
        echo '</label>';
    }
}

function irp_field_categories() {
    $s = irp_get_settings();
    $terms = get_terms(array('taxonomy'=>'category','hide_empty'=>false));
    echo '<div style="max-height:220px;overflow:auto;border:1px solid #ddd;padding:8px;border-radius:6px">';
    foreach ($terms as $t) {
        $checked = in_array($t->term_id, $s['category_ids'], true) ? 'checked' : '';
        echo '<label style="display:block;margin:4px 0"><input type="checkbox" name="'.esc_attr(IRP_OPTION_KEY).'[category_ids][]" value="'.esc_attr($t->term_id).'" '.$checked.'> '.esc_html($t->name).'</label>';
    }
    echo '</div>';
    echo '<p class="description">اگر چیزی انتخاب نشه، توی همه دسته‌ها نمایش داده می‌شود.</p>';
}

function irp_field_num_posts() {
    $s = irp_get_settings();
    echo '<input type="number" min="1" max="12" step="1" name="'.esc_attr(IRP_OPTION_KEY).'[num_posts]" value="'.esc_attr($s['num_posts']).'" />';
}

function irp_field_order_strategy() {
    $s = irp_get_settings();
    $opts = array('related'=>'مطالب مشابه (بر اساس دسته/برچسب)','random'=>'تصادفی');
    foreach ($opts as $val=>$label) {
        $checked = $s['order_strategy']===$val ? 'checked' : '';
        echo '<label style="margin-right:16px"><input type="radio" name="'.esc_attr(IRP_OPTION_KEY).'[order_strategy]" value="'.esc_attr($val).'" '.$checked.'> '.esc_html($label).'</label>';
    }
}

function irp_field_style_variant() {
    $s = irp_get_settings();
    $opts = array('card'=>'کارت‌ها (شبکه‌ای)','list'=>'فهرستی (ساده)');
    foreach ($opts as $val=>$label) {
        $checked = $s['style_variant']===$val ? 'checked' : '';
        echo '<label style="margin-right:16px"><input type="radio" name="'.esc_attr(IRP_OPTION_KEY).'[style_variant]" value="'.esc_attr($val).'" '.$checked.'> '.esc_html($label).'</label>';
    }
}

function irp_field_color_theme() {
    $s = irp_get_settings();
    echo '<input type="text" name="'.esc_attr(IRP_OPTION_KEY).'[color_theme]" value="'.esc_attr($s['color_theme']).'" class="regular-text" /> ';
    echo '<span class="description">مثل #0ea5e9</span>';
}

function irp_field_custom_css_class() {
    $s = irp_get_settings();
    echo '<input type="text" name="'.esc_attr(IRP_OPTION_KEY).'[custom_css_class]" value="'.esc_attr($s['custom_css_class']).'" class="regular-text" />';
}

function irp_field_auto_insert() {
    $s = irp_get_settings();
    $checked = $s['auto_insert'] ? 'checked' : '';
    echo '<label><input type="checkbox" name="'.esc_attr(IRP_OPTION_KEY).'[auto_insert]" value="1" '.$checked.'> درج خودکار داخل محتوا (اگر شورتکد [irp_related] باشد، خودکار درج نمی‌شود)</label>';
}

// Render settings page
function irp_render_settings_page() {
    if (!current_user_can('manage_options')) return;
    ?>
    <div class="wrap">
        <h1>Inline Related Posts (IRP)</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('irp_settings_group');
            do_settings_sections('irp-settings');
            submit_button('ذخیره تنظیمات');
            ?>
        </form>
    </div>
    <?php
}

// Shortcode
add_shortcode('irp_related', function($atts = array()) {
    return irp_render_related_box(get_the_ID());
});

// Content injection
add_filter('the_content', function($content) {
    if (!is_singular()) return $content;
    if (!in_the_loop() || !is_main_query()) return $content;

    $post = get_post();
    $s = irp_get_settings();

    // Post type gate
    if (!in_array($post->post_type, $s['post_types'], true)) return $content;

    // Category gate (if selected)
    if (!empty($s['category_ids'])) {
        $post_cats = wp_get_post_categories($post->ID);
        if (!array_intersect($s['category_ids'], $post_cats)) {
            return $content;
        }
    }

    // If shortcode exists, don't auto-insert
    if (has_shortcode($content, 'irp_related')) return $content;

    // Auto insert
    if (!$s['auto_insert']) return $content;

    $html = irp_render_related_box($post->ID);
    if (!$html) return $content;

    $n = intval($s['after_paragraph']);
    return irp_insert_after_paragraphs($content, $html, $n);
}, 12);

// Insert helper
function irp_insert_after_paragraphs($content, $insertion, $paragraph_number) {
    if ($paragraph_number <= 0) {
        return $content . $insertion;
    }
    $closing_p = '</p>';
    $parts = explode($closing_p, $content);
    if (count($parts) < 2) {
        return $content . $insertion;
    }
    $out = '';
    foreach ($parts as $index => $part) {
        if (trim($part) === '' && $index === array_key_last($parts)) continue;
        $out .= $part . $closing_p;
        if (($index + 1) === $paragraph_number) {
            $out .= $insertion;
        }
    }
    if (strpos($out, $insertion) === false) {
        $out .= $insertion;
    }
    return $out;
}

// Query related posts
function irp_get_related_posts($post_id, $limit, $strategy) {
    $post = get_post($post_id);
    if (!$post) return array();

    $args = array(
        'post_type'      => $post->post_type,
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'post__not_in'   => array($post_id),
        'ignore_sticky_posts' => true,
        'no_found_rows'  => true,
    );

    if ($strategy === 'random') {
        $args['orderby'] = 'rand';
    } else {
        // related: by categories and tags
        $cats = wp_get_post_categories($post_id);
        $tags = wp_get_post_tags($post_id, array('fields' => 'ids'));
        $tax_query = array('relation' => 'OR');
        if (!empty($cats)) {
            $tax_query[] = array(
                'taxonomy' => 'category',
                'field'    => 'term_id',
                'terms'    => $cats,
            );
        }
        if (!empty($tags)) {
            $tax_query[] = array(
                'taxonomy' => 'post_tag',
                'field'    => 'term_id',
                'terms'    => $tags,
            );
        }
        if (count($tax_query) > 1) {
            $args['tax_query'] = $tax_query;
        }
        $args['orderby'] = 'date';
        $args['order']   = 'DESC';
    }

    return get_posts($args);
}

// Render box
function irp_render_related_box($post_id = null) {
    $post_id = $post_id ?: get_the_ID();
    if (!$post_id) return '';

    $s = irp_get_settings();
    $posts = irp_get_related_posts($post_id, $s['num_posts'], $s['order_strategy']);
    if (empty($posts)) return '';

    $classes = array('irp-box', 'irp-variant-'.$s['style_variant']);
    if (!empty($s['custom_css_class'])) $classes[] = $s['custom_css_class'];

    ob_start();
    ?>
    <div class="<?php echo esc_attr(implode(' ', $classes)); ?>" style="--irp-accent: <?php echo esc_attr($s['color_theme']); ?>">
        <div class="irp-header">
            <span class="irp-badge">مطالب مرتبط</span>
        </div>
        <?php if ($s['style_variant'] === 'card'): ?>
            <div class="irp-grid">
                <?php foreach ($posts as $p): ?>
                    <a class="irp-card" href="<?php echo esc_url(get_permalink($p)); ?>">
                        <div class="irp-thumb">
                            <?php if (has_post_thumbnail($p)): ?>
                                <?php echo get_the_post_thumbnail($p, 'medium'); ?>
                            <?php else: ?>
                                <div class="irp-no-thumb"></div>
                            <?php endif; ?>
                        </div>
                        <div class="irp-meta">
                            <h4 class="irp-title"><?php echo esc_html(get_the_title($p)); ?></h4>
                            <div class="irp-date"><?php echo esc_html(get_the_date('', $p)); ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <ul class="irp-list">
                <?php foreach ($posts as $p): ?>
                    <li>
                        <a href="<?php echo esc_url(get_permalink($p)); ?>" class="irp-link">
                            <?php echo esc_html(get_the_title($p)); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// Styles enqueue
add_action('wp_enqueue_scripts', function() {
    wp_register_style('irp-style', IRP_PLUGIN_URL . 'assets/style.css', array(), IRP_VERSION);
    wp_enqueue_style('irp-style');
});

// Editor assets: Gutenberg block + Quicktag button
add_action('enqueue_block_editor_assets', function() {
    wp_enqueue_script(
        'irp-editor',
        IRP_PLUGIN_URL . 'assets/editor.js',
        array('wp-blocks','wp-element','wp-i18n','wp-editor','wp-blockEditor','wp-data'),
        IRP_VERSION,
        true
    );
    wp_localize_script('irp-editor', 'IRP_BLOCK', array(
        'title' => 'مطالب مرتبط درون‌متن',
        'description' => 'درج باکس مطالب مرتبط در محل دلخواه.',
        'icon' => 'admin-post'
    ));
});

// Classic editor Quicktag (Text mode)
add_action('admin_print_footer_scripts', function($hook = '') {
    $screen = get_current_screen();
    if (!in_array($screen->base, array('post','post-new'), true)) return;
    if (wp_script_is('quicktags')): ?>
        <script>
        QTags.addButton('irp_related','مطالب مرتبط','[irp_related]','','i','درج مطالب مرتبط', 1);
        </script>
    <?php endif;
});

