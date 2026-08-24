<?php

/**
 * Plugin Name: Local News
 * Description: Adds an Announcements post type and School taxonomy for per-school news.
 * Version: 0.1.3
 * Author: Vishal Sanap(STW)
 */

if (!defined('ABSPATH')) {
    exit;
}

function stw_sa_register_cpt_and_tax()
{

    // 1) Custom Post Type: Local News
    $labels = array(
        'name' => 'Local News',
        'singular_name' => 'Local News Item',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New Local News',
        'edit_item' => 'Edit Local News',
        'new_item' => 'New Local News',
        'view_item' => 'View Local News',
        'search_items' => 'Search Local News',
        'not_found' => 'No local news found',
        'not_found_in_trash' => 'No local news found in Trash',
        'menu_name' => 'Local News',
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'menu_position' => 20,
        'menu_icon' => 'dashicons-megaphone',
        'supports' => array('title', 'editor', 'excerpt', 'author', 'revisions', 'thumbnail'),
        'has_archive' => false,
        'rewrite' => array('slug' => 'local-news'),
    );

    register_post_type('announcement', array_merge($args, array(
        'capability_type' => array('announcement', 'announcements'),
        'map_meta_cap' => true,
    )));

    // 2) Taxonomy: school (Berlin, etc.)
    $tax_labels = array(
        'name' => 'Schools',
        'singular_name' => 'School',
        'search_items' => 'Search Schools',
        'all_items' => 'All Schools',
        'edit_item' => 'Edit School',
        'update_item' => 'Update School',
        'add_new_item' => 'Add New School',
        'new_item_name' => 'New School Name',
        'menu_name' => 'Schools',
    );

    $tax_args = array(
        'labels' => $tax_labels,
        'public' => true,
        'hierarchical' => true,
        'show_in_rest' => true,
        'show_admin_column' => true,
        'rewrite' => array('slug' => 'school'),
    );

    register_taxonomy('school', array('announcement'), $tax_args);
}
add_action('init', 'stw_sa_register_cpt_and_tax');

function stw_sa_activate()
{
    stw_sa_register_cpt_and_tax();
    stw_sa_ensure_roles_and_caps(true);
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'stw_sa_activate');

function stw_sa_deactivate()
{
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'stw_sa_deactivate');

function stw_sa_get_role_cap_maps()
{
    // Principal (approver)
    $principal_caps = array(
        'read' => true,
        'upload_files' => true,

        'edit_announcements' => true,
        'edit_announcement' => true,
        'publish_announcements' => true,
        'read_announcement' => true,
        'delete_announcement' => true,

        // Needed to approve (edit/publish teacher-created posts)
        'edit_others_announcements' => true,
        'edit_published_announcements' => true,
        'delete_published_announcements' => true,
    );

    // Teacher (draft-only)
    $teacher_caps = array(
        'read' => true,
        'upload_files' => true,

        'edit_announcements' => true,
        'edit_announcement' => true,
        'read_announcement' => true,
        'delete_announcement' => true,
    );

    // Full CPT cap map for administrators on each subsite.
    $admin_caps = array(
        'edit_announcements' => true,
        'edit_others_announcements' => true,
        'edit_published_announcements' => true,
        'edit_private_announcements' => true,
        'edit_announcement' => true,
        'publish_announcements' => true,
        'read_announcement' => true,
        'read_private_announcements' => true,
        'delete_announcements' => true,
        'delete_others_announcements' => true,
        'delete_published_announcements' => true,
        'delete_private_announcements' => true,
        'delete_announcement' => true,
    );

    return array(
        'school_principal' => array('label' => 'School Principal', 'caps' => $principal_caps),
        'school_teacher' => array('label' => 'School Teacher', 'caps' => $teacher_caps),
        'administrator' => array('label' => 'Administrator', 'caps' => $admin_caps),
    );
}

function stw_sa_apply_caps_to_role($role, $caps)
{
    if (!$role || !is_object($role) || !method_exists($role, 'add_cap')) {
        return;
    }

    foreach ($caps as $cap => $grant) {
        if ($grant && $role->has_cap($cap)) {
            continue;
        }
        if (!$grant && !$role->has_cap($cap)) {
            continue;
        }
        $role->add_cap($cap, $grant);
    }
}

function stw_sa_ensure_roles_and_caps($force = false)
{
    if (!function_exists('get_role') || !function_exists('add_role')) {
        return;
    }

    if (defined('WP_INSTALLING') && WP_INSTALLING) {
        return;
    }

    $roles_version = '1';
    if (!$force && get_option('stw_sa_roles_version') === $roles_version) {
        return;
    }

    foreach (stw_sa_get_role_cap_maps() as $role_key => $role_config) {
        $role = get_role($role_key);

        if ($role_key === 'administrator') {
            if ($role) {
                stw_sa_apply_caps_to_role($role, $role_config['caps']);
            }
            continue;
        }

        if (!$role) {
            add_role($role_key, $role_config['label'], $role_config['caps']);
            continue;
        }

        stw_sa_apply_caps_to_role($role, $role_config['caps']);
    }

    update_option('stw_sa_roles_version', $roles_version, false);
}
add_action('init', 'stw_sa_ensure_roles_and_caps', 20);

function stw_sa_on_new_site($new_site)
{
    if (!is_multisite() || !function_exists('switch_to_blog')) {
        return;
    }

    $blog_id = 0;
    if (is_object($new_site) && isset($new_site->blog_id)) {
        $blog_id = (int)$new_site->blog_id;
    }
    elseif (is_numeric($new_site)) {
        $blog_id = (int)$new_site;
    }

    if ($blog_id <= 0) {
        return;
    }

    switch_to_blog($blog_id);
    stw_sa_register_cpt_and_tax();
    stw_sa_ensure_roles_and_caps(true);
    restore_current_blog();
}
add_action('wp_initialize_site', 'stw_sa_on_new_site', 20);

function stw_sa_is_school_only_user($user = null)
{
    $user = $user ?: wp_get_current_user();
    if (!$user || empty($user->ID)) {
        return false;
    }

    $roles = (array)$user->roles;
    $is_school_user = in_array('school_principal', $roles, true) || in_array('school_teacher', $roles, true);

    return $is_school_user && !in_array('administrator', $roles, true);
}

function stw_sa_redirect_school_users_from_dashboard()
{
    if (!is_admin() || !stw_sa_is_school_only_user()) {
        return;
    }

    global $pagenow;
    if ($pagenow !== 'index.php') {
        return;
    }

    wp_safe_redirect(admin_url('edit.php?post_type=announcement'));
    exit;
}
add_action('load-index.php', 'stw_sa_redirect_school_users_from_dashboard');

function stw_sa_restrict_admin_menu()
{
    if (!stw_sa_is_school_only_user()) {
        return;
    }

    remove_menu_page('index.php');
    remove_menu_page('edit.php');
    remove_menu_page('upload.php');
    remove_menu_page('edit.php?post_type=page');
    remove_menu_page('edit-comments.php');
    remove_menu_page('themes.php');
    remove_menu_page('plugins.php');
    remove_menu_page('users.php');
    remove_menu_page('tools.php');
    remove_menu_page('options-general.php');
}
add_action('admin_menu', 'stw_sa_restrict_admin_menu', 999);

/**
 * Tell WordPress to use custom capabilities for the CPT.
 * (Update CPT registration to include these.)
 */

function stw_sa_user_profile_school_field($user)
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $selected = (int)get_user_meta($user->ID, 'stw_sa_school_term_id', true);
    $terms = get_terms(array(
        'taxonomy' => 'school',
        'hide_empty' => false,
    ));
?>
    <h2>School Announcements</h2>
    <table class="form-table" role="presentation">
        <tr>
            <th><label for="stw_sa_school_term_id">Assigned school</label></th>
            <td>
                <select name="stw_sa_school_term_id" id="stw_sa_school_term_id">
                    <option value="0">None</option>
                    <?php foreach ($terms as $t): ?>
                        <option value="<?php echo (int)$t->term_id; ?>" <?php selected($selected, (int)$t->term_id); ?>>
                            <?php echo esc_html($t->name); ?>
                        </option>
                    <?php
    endforeach; ?>
                </select>
                <p class="description">Principals will only be able to post announcements for this school.</p>
            </td>
        </tr>
    </table>
<?php
}
add_action('show_user_profile', 'stw_sa_user_profile_school_field');
add_action('edit_user_profile', 'stw_sa_user_profile_school_field');

function stw_sa_save_user_profile_school_field($user_id)
{
    if (!current_user_can('manage_options')) {
        return;
    }
    $term_id = isset($_POST['stw_sa_school_term_id']) ? (int)$_POST['stw_sa_school_term_id'] : 0;
    update_user_meta($user_id, 'stw_sa_school_term_id', $term_id);
}
add_action('personal_options_update', 'stw_sa_save_user_profile_school_field');
add_action('edit_user_profile_update', 'stw_sa_save_user_profile_school_field');

function stw_sa_enforce_school_term_on_save($post_id, $post, $update)
{
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if ($post->post_type !== 'announcement') {
        return;
    }

    $user = wp_get_current_user();
    if (!$user || empty($user->ID)) {
        return;
    }

    $roles = (array)$user->roles;
    if (!in_array('school_principal', $roles, true) && !in_array('school_teacher', $roles, true)) {
        return;
    }

    $term_id = (int)get_user_meta($user->ID, 'stw_sa_school_term_id', true);
    if ($term_id <= 0) {
        // Hard block: principal cannot publish without an assigned school.
        // Revert status if they tried to publish.
        if ($post->post_status === 'publish') {
            remove_action('save_post', 'stw_sa_enforce_school_term_on_save', 10);
            wp_update_post(array(
                'ID' => $post_id,
                'post_status' => 'draft',
            ));
            add_action('save_post', 'stw_sa_enforce_school_term_on_save', 10, 3);
        }
        return;
    }

    // Force exactly this school term.
    wp_set_object_terms($post_id, array($term_id), 'school', false);
}
add_action('save_post', 'stw_sa_enforce_school_term_on_save', 10, 3);

function stw_sa_default_school_term_for_principal($post_id, $post, $update)
{
    if ($update) {
        return;
    }
    if ($post->post_type !== 'announcement') {
        return;
    }

    $user = wp_get_current_user();
    if (!$user || !in_array('school_principal', (array)$user->roles, true)) {
        return;
    }

    $term_id = (int)get_user_meta($user->ID, 'stw_sa_school_term_id', true);
    if ($term_id > 0) {
        wp_set_object_terms($post_id, array($term_id), 'school', false);
    }
}
add_action('save_post', 'stw_sa_default_school_term_for_principal', 9, 3);

function stw_sa_hide_school_metabox_for_principal()
{
    $user = wp_get_current_user();
    if (!$user || !in_array('school_principal', (array)$user->roles, true)) {
        return;
    }

    remove_meta_box('tagsdiv-school', 'announcement', 'side');
    remove_meta_box('schooldiv', 'announcement', 'side'); // hierarchical tax uses this id
}
add_action('admin_head', 'stw_sa_hide_school_metabox_for_principal');

function stw_sa_filter_admin_list_to_school($query)
{
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    $user = wp_get_current_user();
    if (!$user || !in_array('school_principal', (array)$user->roles, true)) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->id !== 'edit-announcement') {
        return;
    }

    $term_id = (int)get_user_meta($user->ID, 'stw_sa_school_term_id', true);
    if ($term_id > 0) {
        $query->set('tax_query', array(
                array(
                'taxonomy' => 'school',
                'field' => 'term_id',
                'terms' => array($term_id),
            ),
        ));
    }
    else {
        // No assigned school: show none.
        $query->set('post__in', array(0));
    }
}
add_action('pre_get_posts', 'stw_sa_filter_admin_list_to_school');

function stw_sa_shortcode_school_announcements($atts)
{

    wp_enqueue_script('stw-sa');
    wp_enqueue_style('stw-sa');

    $atts = shortcode_atts(
        array(
        'school' => '', // slug, eg "munich"
        'desktop_limit' => 3,
        'mobile_limit' => 2,
    ),
        $atts,
        'school_announcements'
    );

    $school_slug = sanitize_title($atts['school']);
    $desktop_limit = max(1, min(20, (int)$atts['desktop_limit']));
    $mobile_limit  = max(1, min(20, (int)$atts['mobile_limit']));
    $query_limit   = max($desktop_limit, $mobile_limit);

    if (empty($school_slug)) {
        return '<div class="stw-sa stw-sa-error">Missing school attribute, example: [school_announcements school="munich"]</div>';
    }

    $term = get_term_by('slug', $school_slug, 'school');
    if (!$term) {
        return '<div class="stw-sa stw-sa-error">Invalid school: ' . esc_html($school_slug) . '</div>';
    }

    $q = new WP_Query(array(
        'post_type' => 'announcement',
        'post_status' => 'publish',
        'posts_per_page' => $query_limit,
        'no_found_rows' => true,
        'tax_query' => array(
                array(
                'taxonomy' => 'school',
                'field' => 'term_id',
                'terms' => array((int)$term->term_id),
            ),
        ),
    ));

    if (!$q->have_posts()) {
        return '';
    }

    ob_start();

    $instance_id = uniqid('stw_sa_');
    echo '<div id="' . esc_attr($instance_id) . '" class="stw-sa" data-school="' . esc_attr($school_slug) . '">';

    // Scoped styles for responsive display limit
    echo '<style>
        @media (max-width: 767px) {
            #' . esc_attr($instance_id) . ' .stw-sa-item:nth-child(n+' . ($mobile_limit + 1) . ') { display: none; }
        }
        @media (min-width: 768px) {
            #' . esc_attr($instance_id) . ' .stw-sa-item:nth-child(n+' . ($desktop_limit + 1) . ') { display: none; }
        }
    </style>';

    echo '<h2 class="stw-sa-heading">' . esc_html__('Neuigkeiten', 'school-announcements') . '</h2>';

    echo '<div class="stw-sa-list">';
        while ($q->have_posts()) {
            $q->the_post();
            $post_id = get_the_ID();

            $has_thumb = has_post_thumbnail();
            $classes = $has_thumb ? 'stw-sa-item stw-sa-item--has-thumb' : 'stw-sa-item';

            echo '<article class="' . esc_attr($classes) . '">';
            
            if ($has_thumb) {
                echo '<div class="stw-sa-thumb">' . get_the_post_thumbnail($post_id, 'medium') . '</div>';
            }
            
            echo '<div class="stw-sa-content">';
            echo '<h3 class="stw-sa-title">' . esc_html(get_the_title()) . '</h3>';
            echo '<div class="stw-sa-meta">' . esc_html(get_the_date()) . '</div>';

            $ex = get_the_excerpt();
            if (empty($ex)) {
                $ex = wp_trim_words(wp_strip_all_tags(get_the_content()), 28);
            }
            echo '<p class="stw-sa-excerpt">' . esc_html($ex) . '</p>';

            echo '<button type="button" class="stw-sa-readmore" data-post-id="' . (int)$post_id . '">' . esc_html__( 'Mehr Lesen', 'school-announcements' ) . '</button>';
            echo '</div>';
            echo '</article>';
        }
        echo '</div>';
        
        // JSON-LD Structured Data for SEO (Option 3)
        // Fetch all announcements to ensure they are indexable even if not currently visible
        $all_q = new WP_Query(array(
            'post_type' => 'announcement',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'tax_query' => array(
                    array(
                    'taxonomy' => 'school',
                    'field' => 'term_id',
                    'terms' => array((int)$term->term_id),
                ),
            ),
        ));

        if ($all_q->have_posts()) {
            $schema = array(
                '@context' => 'https://schema.org',
                '@type'    => 'ItemList',
                'itemListElement' => array()
            );
            $position = 1;
            while ($all_q->have_posts()) {
                $all_q->the_post();
                
                $article_id = get_the_ID();
                $article = array(
                    '@type' => 'NewsArticle',
                    'headline' => get_the_title(),
                    'datePublished' => get_the_date('c'),
                    'dateModified' => get_the_modified_date('c'),
                    'text' => wp_strip_all_tags(get_post_field('post_content', $article_id)),
                );
                
                if (has_post_thumbnail($article_id)) {
                    $article['image'] = get_the_post_thumbnail_url($article_id, 'large');
                }
                
                $schema['itemListElement'][] = array(
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'item' => $article
                );
            }
            wp_reset_postdata();
            echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
        }

        wp_reset_postdata();

    echo '</div>'; // close .stw-sa

    return ob_get_clean();
}
add_shortcode('school_announcements', 'stw_sa_shortcode_school_announcements');

// Inject the modal precisely once in the footer if the shortcode was used.
function stw_sa_render_modal_in_footer()
{
    if (wp_script_is('stw-sa', 'enqueued')) {
        echo '
		<div class="stw-sa-modal" aria-hidden="true">
			<div class="stw-sa-modal__overlay" data-close="1"></div>
			<div class="stw-sa-modal__panel" role="dialog" aria-modal="true" aria-label="Announcement">
				<button type="button" class="stw-sa-modal__close" data-close="1">Close</button>
				<div class="stw-sa-modal__body">
					<div class="stw-sa-modal__loading" style="display:none;">Loading...</div>
					<h3 class="stw-sa-modal__title"></h3>
					<div class="stw-sa-modal__meta"></div>
					<div class="stw-sa-modal__thumb"></div>
					<div class="stw-sa-modal__content"></div>
				</div>
			</div>
		</div>
		';
    }
}
add_action('wp_footer', 'stw_sa_render_modal_in_footer');

function stw_sa_register_rest_routes()
{
    register_rest_route('stw-sa/v1', '/announcement/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'stw_sa_rest_get_announcement',
        'permission_callback' => '__return_true',
        'args' => array(
            'id' => array(
                'validate_callback' => function ($param) {
                return is_numeric($param) && (int)$param > 0;
            },
            ),
        ),
    ));
}
add_action('rest_api_init', 'stw_sa_register_rest_routes');

function stw_sa_rest_get_announcement(WP_REST_Request $request)
{
    $post_id = (int)$request['id'];
    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'announcement' || $post->post_status !== 'publish') {
        return new WP_REST_Response(array('message' => 'Not found'), 404);
    }

    return array(
        'id' => $post_id,
        'title' => get_the_title($post_id),
        'date' => get_the_date('', $post_id),
        'content' => apply_filters('the_content', $post->post_content),
        'thumbnail' => has_post_thumbnail($post_id) ? get_the_post_thumbnail($post_id, 'large') : '',
    );
}

function stw_sa_register_assets()
{
    $ver = '0.1.3';

    wp_register_script(
        'stw-sa',
        plugins_url('assets/stw-sa.js', __FILE__),
        array('jquery'),
        $ver,
        true
    );

    wp_localize_script('stw-sa', 'STW_SA', array(
        'restUrl' => esc_url_raw(rest_url('stw-sa/v1/announcement/')),
    ));

    wp_register_style(
        'stw-sa',
        plugins_url('assets/stw-sa.css', __FILE__),
        array(),
        $ver
    );
}
add_action('wp_enqueue_scripts', 'stw_sa_register_assets');

function stw_sa_force_teacher_draft_only($data, $postarr)
{
    if (empty($postarr['post_type']) || $postarr['post_type'] !== 'announcement') {
        return $data;
    }

    $user = wp_get_current_user();
    if (!$user || !in_array('school_teacher', (array)$user->roles, true)) {
        return $data;
    }

    // Teachers can only ever save as draft.
    if (isset($data['post_status']) && $data['post_status'] !== 'draft') {
        $data['post_status'] = 'draft';
    }

    return $data;
}
add_filter('wp_insert_post_data', 'stw_sa_force_teacher_draft_only', 10, 2);

function stw_sa_get_notify_email()
{
    if (defined('STW_SA_NOTIFY_EMAIL') && STW_SA_NOTIFY_EMAIL) {
        return sanitize_email(STW_SA_NOTIFY_EMAIL);
    }
    return sanitize_email(get_option('admin_email'));
}

function stw_sa_notify_on_teacher_draft_create($new_status, $old_status, $post)
{
    if (!$post || $post->post_type !== 'announcement') {
        return;
    }

    // Only when it becomes a draft from auto-draft (first real save).
    if ($new_status !== 'draft' || $old_status !== 'auto-draft') {
        return;
    }

    $author = get_user_by('id', (int)$post->post_author);
    if (!$author || !in_array('school_teacher', (array)$author->roles, true)) {
        return;
    }

    // Send only once per post.
    if (get_post_meta($post->ID, '_stw_sa_teacher_draft_notified', true)) {
        return;
    }
    update_post_meta($post->ID, '_stw_sa_teacher_draft_notified', 1);

    $to = stw_sa_get_notify_email();
    if (empty($to)) {
        return;
    }

    $subject = 'New teacher draft: ' . wp_strip_all_tags(get_the_title($post->ID));
    $edit_link = admin_url('post.php?post=' . (int)$post->ID . '&action=edit');

    $message =
        "A school teacher created a new draft.\n\n" .
        "Title: " . wp_strip_all_tags(get_the_title($post->ID)) . "\n" .
        "Author: " . $author->display_name . " (" . $author->user_email . ")\n" .
        "Edit: " . $edit_link . "\n";

    wp_mail($to, $subject, $message);
}
add_action('transition_post_status', 'stw_sa_notify_on_teacher_draft_create', 10, 3);

function stw_sa_autoptimize_exclude($exclude)
{
    // Exclude our handle/file from Autoptimize aggregation.
    $exclude .= ',stw-sa.js,stw-sa';
    return $exclude;
}
add_filter('autoptimize_filter_js_exclude', 'stw_sa_autoptimize_exclude');
