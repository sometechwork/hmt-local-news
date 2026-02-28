<?php

/**
 * Plugin Name: Local News
 * Description: Adds an Announcements post type and School taxonomy for per-school news.
 * Version: 0.1.0
 * Author: Your Name
 */

if (! defined('ABSPATH')) {
    exit;
}

function stw_sa_register_cpt_and_tax()
{

    // 1) Custom Post Type: Local News
    $labels = array(
        'name'               => 'Local News',
        'singular_name'      => 'Local News Item',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Local News',
        'edit_item'          => 'Edit Local News',
        'new_item'           => 'New Local News',
        'view_item'          => 'View Local News',
        'search_items'       => 'Search Local News',
        'not_found'          => 'No local news found',
        'not_found_in_trash' => 'No local news found in Trash',
        'menu_name'          => 'Local News',
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'show_in_menu'       => true,
        'show_in_rest'       => true,
        'menu_position'      => 20,
        'menu_icon'          => 'dashicons-megaphone',
        'supports'           => array('title', 'editor', 'excerpt', 'author', 'revisions'),
        'has_archive'        => false,
        'rewrite' => array('slug' => 'local-news'),
    );

    register_post_type('announcement', array_merge($args, array(
        'capability_type' => array('announcement', 'announcements'),
        'map_meta_cap'    => true,
    )));

    // 2) Taxonomy: school (Berlin, etc.)
    $tax_labels = array(
        'name'          => 'Schools',
        'singular_name' => 'School',
        'search_items'  => 'Search Schools',
        'all_items'     => 'All Schools',
        'edit_item'     => 'Edit School',
        'update_item'   => 'Update School',
        'add_new_item'  => 'Add New School',
        'new_item_name' => 'New School Name',
        'menu_name'     => 'Schools',
    );

    $tax_args = array(
        'labels'            => $tax_labels,
        'public'            => true,
        'hierarchical'      => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'rewrite'           => array('slug' => 'school'),
    );

    register_taxonomy('school', array('announcement'), $tax_args);
}
add_action('init', 'stw_sa_register_cpt_and_tax');

/**
 * Ensure permalinks flush once on activation so routes work.
 */
// function stw_sa_activate() {
// 	stw_sa_register_cpt_and_tax();
// 	flush_rewrite_rules();
// }
// register_activation_hook( __FILE__, 'stw_sa_activate' );

function stw_sa_deactivate()
{
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'stw_sa_deactivate');

function stw_sa_add_roles()
{

    // Principal (approver)
    $principal_caps = array(
        'read'                         => true,
        'upload_files'                 => true,

        'edit_announcements'           => true,
        'edit_announcement'            => true,
        'publish_announcements'        => true,
        'read_announcement'            => true,
        'delete_announcement'          => true,

        // Needed to approve (edit/publish teacher-created posts)
        'edit_others_announcements'    => true,
        'edit_published_announcements' => true,
        'delete_published_announcements' => true,
    );

    // Teacher (draft-only)
    $teacher_caps = array(
        'read'                => true,
        'upload_files'        => true,

        'edit_announcements'  => true,
        'edit_announcement'   => true,
        'read_announcement'   => true,
        'delete_announcement' => true,

        // No publish caps for teachers
        // 'publish_announcements' => false
    );

    $principal = get_role('school_principal');
    if (! $principal) {
        add_role('school_principal', 'School Principal', $principal_caps);
    } else {
        foreach ($principal_caps as $cap => $grant) {
            $principal->add_cap($cap, $grant);
        }
    }

    $teacher = get_role('school_teacher');
    if (! $teacher) {
        add_role('school_teacher', 'School Teacher', $teacher_caps);
    } else {
        foreach ($teacher_caps as $cap => $grant) {
            $teacher->add_cap($cap, $grant);
        }
    }
}
register_activation_hook(__FILE__, 'stw_sa_add_roles');

/**
 * Tell WordPress to use custom capabilities for the CPT.
 * (Update CPT registration to include these.)
 */

function stw_sa_user_profile_school_field($user)
{
    if (! current_user_can('manage_options')) {
        return;
    }

    $selected = (int) get_user_meta($user->ID, 'stw_sa_school_term_id', true);
    $terms    = get_terms(array(
        'taxonomy'   => 'school',
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
                    <?php foreach ($terms as $t) : ?>
                        <option value="<?php echo (int) $t->term_id; ?>" <?php selected($selected, (int) $t->term_id); ?>>
                            <?php echo esc_html($t->name); ?>
                        </option>
                    <?php endforeach; ?>
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
    if (! current_user_can('manage_options')) {
        return;
    }
    $term_id = isset($_POST['stw_sa_school_term_id']) ? (int) $_POST['stw_sa_school_term_id'] : 0;
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
    if (! $user || empty($user->ID)) {
        return;
    }

    $roles = (array) $user->roles;
    if (! in_array('school_principal', $roles, true) && ! in_array('school_teacher', $roles, true)) {
        return;
    }

    $term_id = (int) get_user_meta($user->ID, 'stw_sa_school_term_id', true);
    if ($term_id <= 0) {
        // Hard block: principal cannot publish without an assigned school.
        // Revert status if they tried to publish.
        if ($post->post_status === 'publish') {
            remove_action('save_post', 'stw_sa_enforce_school_term_on_save', 10);
            wp_update_post(array(
                'ID'          => $post_id,
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
    if (! $user || ! in_array('school_principal', (array) $user->roles, true)) {
        return;
    }

    $term_id = (int) get_user_meta($user->ID, 'stw_sa_school_term_id', true);
    if ($term_id > 0) {
        wp_set_object_terms($post_id, array($term_id), 'school', false);
    }
}
add_action('save_post', 'stw_sa_default_school_term_for_principal', 9, 3);

function stw_sa_hide_school_metabox_for_principal()
{
    $user = wp_get_current_user();
    if (! $user || ! in_array('school_principal', (array) $user->roles, true)) {
        return;
    }

    remove_meta_box('tagsdiv-school', 'announcement', 'side');
    remove_meta_box('schooldiv', 'announcement', 'side'); // hierarchical tax uses this id
}
add_action('admin_head', 'stw_sa_hide_school_metabox_for_principal');

function stw_sa_filter_admin_list_to_school($query)
{
    if (! is_admin() || ! $query->is_main_query()) {
        return;
    }

    $user = wp_get_current_user();
    if (! $user || ! in_array('school_principal', (array) $user->roles, true)) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (! $screen || $screen->id !== 'edit-announcement') {
        return;
    }

    $term_id = (int) get_user_meta($user->ID, 'stw_sa_school_term_id', true);
    if ($term_id > 0) {
        $query->set('tax_query', array(
            array(
                'taxonomy' => 'school',
                'field'    => 'term_id',
                'terms'    => array($term_id),
            ),
        ));
    } else {
        // No assigned school: show none.
        $query->set('post__in', array(0));
    }
}
add_action('pre_get_posts', 'stw_sa_filter_admin_list_to_school');

function stw_sa_grant_caps_to_administrator()
{
    $role = get_role('administrator');
    if (! $role) {
        return;
    }

    $caps = array(
        'edit_announcements',
        'edit_announcement',
        'publish_announcements',
        'read_announcement',
        'delete_announcement',
        'edit_published_announcements',
        'delete_published_announcements',
        'delete_announcements',
        'read_private_announcements',
        'edit_private_announcements',
        'delete_private_announcements',
        'delete_others_announcements',
        'edit_others_announcements',
    );

    foreach ($caps as $cap) {
        $role->add_cap($cap);
    }
}

function stw_sa_activate()
{
    stw_sa_register_cpt_and_tax();
    stw_sa_add_roles();
    stw_sa_grant_caps_to_administrator();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'stw_sa_activate');

function stw_sa_shortcode_school_announcements($atts)
{
    $atts = shortcode_atts(
        array(
            'school' => '',   // slug, eg "munich"
            'limit'  => 5,
        ),
        $atts,
        'school_announcements'
    );

    $school_slug = sanitize_title($atts['school']);
    $limit       = max(1, min(20, (int) $atts['limit']));

    if (empty($school_slug)) {
        return '<div class="stw-sa stw-sa-error">Missing school attribute, example: [school_announcements school="munich"]</div>';
    }

    $term = get_term_by('slug', $school_slug, 'school');
    if (! $term) {
        return '<div class="stw-sa stw-sa-error">Invalid school: ' . esc_html($school_slug) . '</div>';
    }

    $q = new WP_Query(array(
        'post_type'      => 'announcement',
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'no_found_rows'  => true,
        'tax_query'      => array(
            array(
                'taxonomy' => 'school',
                'field'    => 'term_id',
                'terms'    => array((int) $term->term_id),
            ),
        ),
    ));

    ob_start();

    echo '<div class="stw-sa" data-school="' . esc_attr($school_slug) . '">';

    if ($q->have_posts()) {
        echo '<div class="stw-sa-list">';
        while ($q->have_posts()) {
            $q->the_post();
            $post_id = get_the_ID();

            echo '<article class="stw-sa-item">';
            echo '<h3 class="stw-sa-title">' . esc_html(get_the_title()) . '</h3>';
            echo '<div class="stw-sa-meta">' . esc_html(get_the_date()) . '</div>';

            $ex = get_the_excerpt();
            if (empty($ex)) {
                $ex = wp_trim_words(wp_strip_all_tags(get_the_content()), 28);
            }
            echo '<p class="stw-sa-excerpt">' . esc_html($ex) . '</p>';

            echo '<button type="button" class="stw-sa-readmore" data-post-id="' . (int) $post_id . '">Read more</button>';
            echo '</article>';
        }
        echo '</div>';
        wp_reset_postdata();
    } else {
        echo '<div class="stw-sa-empty">No announcements yet.</div>';
    }

    // Modal shell (single instance)
    echo '
		<div class="stw-sa-modal" aria-hidden="true">
			<div class="stw-sa-modal__overlay" data-close="1"></div>
			<div class="stw-sa-modal__panel" role="dialog" aria-modal="true" aria-label="Announcement">
				<button type="button" class="stw-sa-modal__close" data-close="1">Close</button>
				<div class="stw-sa-modal__body">
					<div class="stw-sa-modal__loading" style="display:none;">Loading...</div>
					<h3 class="stw-sa-modal__title"></h3>
					<div class="stw-sa-modal__meta"></div>
					<div class="stw-sa-modal__content"></div>
				</div>
			</div>
		</div>
	';

    echo '</div>';

    return ob_get_clean();
}
add_shortcode('school_announcements', 'stw_sa_shortcode_school_announcements');

function stw_sa_register_rest_routes()
{
    register_rest_route('stw-sa/v1', '/announcement/(?P<id>\d+)', array(
        'methods'             => 'GET',
        'callback'            => 'stw_sa_rest_get_announcement',
        'permission_callback' => '__return_true',
        'args'                => array(
            'id' => array(
                'validate_callback' => function ($param) {
                    return is_numeric($param) && (int) $param > 0;
                },
            ),
        ),
    ));
}
add_action('rest_api_init', 'stw_sa_register_rest_routes');

function stw_sa_rest_get_announcement(WP_REST_Request $request)
{
    $post_id = (int) $request['id'];
    $post    = get_post($post_id);

    if (! $post || $post->post_type !== 'announcement' || $post->post_status !== 'publish') {
        return new WP_REST_Response(array('message' => 'Not found'), 404);
    }

    return array(
        'id'      => $post_id,
        'title'   => get_the_title($post_id),
        'date'    => get_the_date('', $post_id),
        'content' => apply_filters('the_content', $post->post_content),
    );
}

function stw_sa_enqueue_assets()
{
    // Only load on pages where shortcode is used.
    if (! is_singular()) {
        return;
    }

    global $post;
    if (! $post || stripos($post->post_content, '[school_announcements') === false) {
        return;
    }

    $ver = '0.1.0';

    wp_register_script(
        'stw-sa',
        plugins_url('assets/stw-sa.js', __FILE__),
        array(),
        $ver,
        true
    );

    wp_localize_script('stw-sa', 'STW_SA', array(
        'restUrl' => esc_url_raw(rest_url('stw-sa/v1/announcement/')),
    ));

    wp_enqueue_script('stw-sa');

    wp_register_style(
        'stw-sa',
        plugins_url('assets/stw-sa.css', __FILE__),
        array(),
        $ver
    );
    wp_enqueue_style('stw-sa');
}
add_action('wp_enqueue_scripts', 'stw_sa_enqueue_assets');

function stw_sa_force_teacher_draft_only($data, $postarr)
{
    if (empty($postarr['post_type']) || $postarr['post_type'] !== 'announcement') {
        return $data;
    }

    $user = wp_get_current_user();
    if (! $user || ! in_array('school_teacher', (array) $user->roles, true)) {
        return $data;
    }

    // Teachers can only ever save as draft.
    if (isset($data['post_status']) && $data['post_status'] !== 'draft') {
        $data['post_status'] = 'draft';
    }

    return $data;
}
add_filter('wp_insert_post_data', 'stw_sa_force_teacher_draft_only', 10, 2);

function stw_sa_get_notify_email() {
	if ( defined( 'STW_SA_NOTIFY_EMAIL' ) && STW_SA_NOTIFY_EMAIL ) {
		return sanitize_email( STW_SA_NOTIFY_EMAIL );
	}
	return sanitize_email( get_option( 'admin_email' ) );
}

function stw_sa_notify_on_teacher_draft_create($new_status, $old_status, $post)
{
    if (! $post || $post->post_type !== 'announcement') {
        return;
    }

    // Only when it becomes a draft from auto-draft (first real save).
    if ($new_status !== 'draft' || $old_status !== 'auto-draft') {
        return;
    }

    $author = get_user_by('id', (int) $post->post_author);
    if (! $author || ! in_array('school_teacher', (array) $author->roles, true)) {
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
    $edit_link = admin_url('post.php?post=' . (int) $post->ID . '&action=edit');

    $message =
        "A school teacher created a new draft.\n\n" .
        "Title: " . wp_strip_all_tags(get_the_title($post->ID)) . "\n" .
        "Author: " . $author->display_name . " (" . $author->user_email . ")\n" .
        "Edit: " . $edit_link . "\n";

    wp_mail($to, $subject, $message);
}
add_action('transition_post_status', 'stw_sa_notify_on_teacher_draft_create', 10, 3);
