<?php
/**
 * Flow 6 – Publisher uploads & monetizes
 * Tutor + Woo + Memberships + platform-core
 * - Front-end "Pricing & Distribution" form (proposed price, bundles, optional certificate template)
 * - Admin Moderation: Approve => create Woo product + link to course + apply Membership rules
 * - Draft -> Approved -> Listed in catalog; one-off purchase works; subscription grants access
 */
if (!defined('ABSPATH')) exit;

/* -----------------------------------------------
 * 0) Pages guard: /publish (Tutor Dashboard)
 * ----------------------------------------------*/
add_action('init', function () {
    if (!get_page_by_path('publish')) {
        wp_insert_post([
            'post_type'   => 'page',
            'post_status' => 'publish',
            'post_title'  => 'Publish',
            'post_name'   => 'publish',
            'post_content'=> '[tutor_dashboard]'
        ]);
    }
});

/* -------------------------------------------------------
 * 1) Course meta: pricing & distribution (front-end form)
 *   Meta keys we manage on the "courses" post type:
 *   - _platform_proposed_price (float)
 *   - _platform_selected_bundles (array of plan IDs)
 *   - _platform_certificate_template (string|int, optional)
 *   - _platform_moderation_status ('draft'|'pending'|'approved'|'rejected')
 * ------------------------------------------------------*/
add_shortcode('platform_course_monetize', function ($atts) {
    if (!is_user_logged_in()) return '<p>Please sign in.</p>';
    $uid  = get_current_user_id();
    $atts = shortcode_atts(['course' => 0], $atts);
    $course_id = (int)$atts['course'];

    // If no course passed, show a list of user courses pending/draft
    if (!$course_id) {
        $q = new WP_Query([
            'post_type' => 'courses',
            'author'    => $uid,
            'post_status' => ['draft','pending'],
            'posts_per_page' => 20,
        ]);
        if (!$q->have_posts()) return '<p>No draft/pending courses found. Create one from your Tutor dashboard.</p>';
        ob_start();
        echo '<div class="pcm-list"><h3>Your draft/pending courses</h3><ul>';
        while ($q->have_posts()) { $q->the_post();
            $link = add_query_arg(['course'=>get_the_ID()], get_permalink(get_page_by_path('publish'))); // open dashboard; or keep this page
            echo '<li><a href="'.esc_url(add_query_arg(['course_id'=>get_the_ID()], get_permalink())).'">'.esc_html(get_the_title()).'</a></li>';
        }
        echo '</ul></div>';
        wp_reset_postdata();
        return ob_get_clean();
    }

    // Security: author only
    $post = get_post($course_id);
    if (!$post || $post->post_type !== 'courses' || (int)$post->post_author !== $uid) {
        return '<p>Course not found.</p>';
    }

    // Fetch plans for the bundles checklist
    $plans = get_posts(['post_type'=>'wc_membership_plan','numberposts'=>-1,'post_status'=>'publish']);
    $price = get_post_meta($course_id, '_platform_proposed_price', true);
    $bunds = (array) get_post_meta($course_id, '_platform_selected_bundles', true);
    $cert  = get_post_meta($course_id, '_platform_certificate_template', true);

    ob_start(); ?>
    <form id="pcm-form" class="pcm-form" data-course="<?php echo (int)$course_id; ?>">
      <h3>Pricing & Distribution</h3>
      <p><label>Proposed price (one‑off) <input type="number" min="0" step="0.01" name="price" value="<?php echo esc_attr($price); ?>"></label></p>
      <fieldset><legend>Include in subscription bundles</legend>
        <?php foreach ($plans as $plan): ?>
          <label style="display:block"><input type="checkbox" name="bundles[]" value="<?php echo (int)$plan->ID; ?>" <?php checked(in_array((int)$plan->ID, $bunds)); ?>> <?php echo esc_html($plan->post_title); ?></label>
        <?php endforeach; ?>
      </fieldset>
      <p><label>Certificate template ID (optional) <input type="text" name="certificate" value="<?php echo esc_attr($cert); ?>"></label></p>
      <p><em>Submitting updates the course meta and marks it <strong>Pending Review</strong> for moderation.</em></p>
      <button class="button button-primary">Save & Submit for Review</button>
      <div id="pcm-status" aria-live="polite" style="margin-top:.5rem;"></div>
    </form>
    <script>
      (function(){
        const f = document.getElementById('pcm-form');
        f && f.addEventListener('submit', function(e){
          e.preventDefault();
          const status = document.getElementById('pcm-status'); status.textContent='Saving…';
          const data = new FormData(f);
          fetch('<?php echo esc_url_raw( rest_url('platform-core/v1/publish/course/'.$course_id.'/pricing') ); ?>',{
            method:'POST',
            headers:{'X-WP-Nonce':'<?php echo wp_create_nonce('wp_rest'); ?>'},
            body:data
          }).then(r=>r.json()).then(j=>{
            status.textContent = j && j.ok ? 'Saved. Sent to moderation.' : (j.error || 'Error');
          }).catch(()=>{status.textContent='Network error';});
        });
      })();
    </script>
    <style>
      .pcm-form { max-width: 720px; padding: 1rem; border:1px solid #e5e5e5; border-radius:10px; background:#fff; }
      .pcm-form label { display:block; margin:.35rem 0; }
    </style>
    <?php
    return ob_get_clean();
});

// REST: save pricing meta & mark pending
add_action('rest_api_init', function () {
    register_rest_route('platform-core/v1', '/publish/course/(?P<id>\d+)/pricing', [
        'methods' => 'POST',
        'permission_callback' => function($req){ return is_user_logged_in(); },
        'callback' => function(\WP_REST_Request $req){
            $course_id = (int) $req['id'];
            $post = get_post($course_id);
            if (!$post || $post->post_type !== 'courses' || (int)$post->post_author !== get_current_user_id()) {
                return new \WP_REST_Response(['error'=>'Not allowed'], 403);
            }
            $price = (float) ($req->get_param('price') ?? 0);
            $bunds = array_map('intval', (array) $req->get_param('bundles'));
            $cert  = sanitize_text_field($req->get_param('certificate') ?? '');

            update_post_meta($course_id, '_platform_proposed_price', $price);
            update_post_meta($course_id, '_platform_selected_bundles', $bunds);
            if ($cert !== '') update_post_meta($course_id, '_platform_certificate_template', $cert);
            update_post_meta($course_id, '_platform_moderation_status', 'pending');

            // set WP status to 'pending' so it enters moderation queue
            if ($post->post_status !== 'pending') {
                wp_update_post(['ID'=>$course_id, 'post_status'=>'pending']);
            }
            return new \WP_REST_Response(['ok'=>true], 200);
        }
    ]);
});

/* -------------------------------------------------------
 * 2) Admin Moderation screen: Approve / Reject
 *    Approve => Create Woo product + link to course + apply Membership rules
 * ------------------------------------------------------*/
add_action('admin_menu', function () {
    add_menu_page('Publishing Moderation', 'Publishing', 'manage_options', 'platform-publishing', 'platform_core_publishing_screen', 'dashicons-yes-alt', 56);
});

function platform_core_publishing_screen() {
    if (!current_user_can('edit_others_posts')) return;
    // Approve action
    if (isset($_POST['platform_approve']) && check_admin_referer('platform_publishing')) {
        $course_id = (int) $_POST['course_id'];
        $price     = (float) $_POST['final_price'];
        $bundles   = array_map('intval', (array)($_POST['bundles'] ?? []));
        $cert      = sanitize_text_field($_POST['certificate'] ?? '');
        $res = platform_core_approve_course($course_id, $price, $bundles, $cert);
        echo $res === true
            ? '<div class="updated"><p>Approved & published.</p></div>'
            : '<div class="error"><p>'.esc_html($res).'</p></div>';
    }
    // Reject action
    if (isset($_POST['platform_reject']) && check_admin_referer('platform_publishing')) {
        $course_id = (int) $_POST['course_id'];
        update_post_meta($course_id, '_platform_moderation_status', 'rejected');
        wp_update_post(['ID'=>$course_id, 'post_status'=>'draft']);
        echo '<div class="updated"><p>Rejected & returned to draft.</p></div>';
    }

    // Queue: pending courses
    $q = new WP_Query([
        'post_type' => 'courses',
        'post_status' => ['pending'],
        'posts_per_page' => 25,
    ]);

    echo '<div class="wrap"><h1>Publishing Moderation</h1>';
    if (!$q->have_posts()) {
        echo '<p>No courses awaiting approval.</p></div>'; return;
    }
    echo '<table class="widefat striped"><thead><tr><th>Course</th><th>Publisher</th><th>Proposed price</th><th>Bundles</th><th>Certificate</th><th>Actions</th></tr></thead><tbody>';
    while ($q->have_posts()) { $q->the_post();
        $course_id = get_the_ID();
        $publisher = get_userdata(get_post_field('post_author', $course_id));
        $price = (float) get_post_meta($course_id, '_platform_proposed_price', true);
        $bunds = (array) get_post_meta($course_id, '_platform_selected_bundles', true);
        $cert  = get_post_meta($course_id, '_platform_certificate_template', true);
        $plans = get_posts(['post_type'=>'wc_membership_plan','numberposts'=>-1]);
        echo '<tr><td><a href="'.esc_url(get_edit_post_link($course_id)).'">'.esc_html(get_the_title()).'</a></td>';
        echo '<td>'.esc_html($publisher ? $publisher->display_name : '—').'</td>';
        echo '<td>'.esc_html(wc_price($price)).'</td>';
        echo '<td>'.esc_html(implode(', ', array_map(function($id){ return get_the_title($id); }, $bunds))).'</td>';
        echo '<td>'.esc_html($cert ?: '—').'</td>';
        echo '<td><form method="post">';
        wp_nonce_field('platform_publishing');
        echo '<input type="hidden" name="course_id" value="'.(int)$course_id.'">';
        echo '<label>Final price <input type="number" name="final_price" step="0.01" min="0" value="'.esc_attr($price).'"></label><br>';
        echo '<details><summary>Bundles</summary>';
        foreach ($plans as $p) {
            printf('<label style="display:block"><input type="checkbox" name="bundles[]" value="%d" %s> %s</label>',
                (int)$p->ID, checked(in_array((int)$p->ID, $bunds), true, false), esc_html($p->post_title));
        }
        echo '</details>';
        echo '<label>Certificate template ID <input type="text" name="certificate" value="'.esc_attr($cert).'"></label><br>';
        echo '<button class="button button-primary" name="platform_approve" value="1">Approve → publish</button> ';
        echo '<button class="button" name="platform_reject" value="1">Reject</button>';
        echo '</form></td></tr>';
    }
    wp_reset_postdata();
    echo '</tbody></table></div>';
}

/* -------------------------------------------------------
 * 3) Approve helper: create Woo product + link + membership
 * ------------------------------------------------------*/
function platform_core_approve_course($course_id, $price, $bundle_plan_ids, $certificate_template) {
    if (!current_user_can('edit_post', $course_id)) return 'No permission';
    $post = get_post($course_id);
    if (!$post || $post->post_type !== 'courses') return 'Bad course';

    // 1) Create/Update product
    if (!class_exists('WC_Product_Simple')) return 'WooCommerce missing';
    $product_id = (int) get_post_meta($course_id, '_platform_product_id', true);
    if ($product_id && ($p = wc_get_product($product_id))) {
        $p->set_regular_price($price);
        $p->set_price($price);
        $p->set_catalog_visibility('catalog');
        $p->set_status('publish');
        $p->set_virtual(true);
        $p->save();
    } else {
        $p = new WC_Product_Simple();
        $p->set_name('Course: '.get_the_title($course_id));
        $p->set_regular_price($price);
        $p->set_price($price);
        $p->set_catalog_visibility('catalog');
        $p->set_status('publish');
        $p->set_virtual(true);
        $product_id = $p->save();
        update_post_meta($product_id, '_platform_course_id', $course_id);
        update_post_meta($course_id, '_platform_product_id', $product_id);
    }

    // 2) Link product to course (Tutor Woo integration metas; safe defaults)
    update_post_meta($course_id, '_tutor_course_price_type', $price > 0 ? 'paid' : 'free');
    update_post_meta($course_id, '_tutor_course_price', $price);
    update_post_meta($course_id, '_tutor_course_product_id', $product_id);

    // 3) Apply Membership rules: include this course in selected plans (content restriction)
    platform_core_memberships_include_course_in_plans($course_id, (array) $bundle_plan_ids);

    // 4) Optional: certificate template
    if ($certificate_template !== '') {
        update_post_meta($course_id, '_platform_certificate_template', $certificate_template);
        // If Tutor certificate addon is present, keep a compatible key too:
        update_post_meta($course_id, '_tutor_course_certificate_template_id', $certificate_template);
    }

    // 5) Publish the course; mark approved
    update_post_meta($course_id, '_platform_moderation_status', 'approved');
    wp_update_post(['ID'=>$course_id, 'post_status'=>'publish']);

    return true;
}

function platform_core_memberships_include_course_in_plans($course_id, array $plan_ids) {
    // If Woo Memberships rules API is available, create a rule per plan
    if (function_exists('wc_memberships')) {
        $rules_instance = wc_memberships()->get_rules_instance();
        if (method_exists($rules_instance, 'create_rule')) {
            foreach ($plan_ids as $plan_id) {
                // Rule: "Members of PLAN can view post_type=courses object_id=COURSE immediately"
                $args = [
                    'plan_id'         => (int)$plan_id,
                    'rule_type'       => 'content_restriction',
                    'content_type'    => 'post_type',
                    'object_ids'      => [ (int)$course_id ],
                    'content_restriction_type' => 'view',
                    'access_schedule' => 'immediate',
                ];
                // Avoid duplicates: try to find existing rule first
                $existing = $rules_instance->get_rules( [
                    'plan_id'      => (int)$plan_id,
                    'rule_type'    => 'content_restriction',
                    'content_type' => 'post_type',
                    'object_id'    => (int)$course_id
                ] );
                if (empty($existing)) {
                    try { $rules_instance->create_rule($args); } catch (\Throwable $e) { /* ignore */ }
                }
            }
        } else {
            // Fallback store (so our access filter can use it)
            update_post_meta($course_id, '_platform_membership_plans', array_map('intval', $plan_ids));
        }
    } else {
        update_post_meta($course_id, '_platform_membership_plans', array_map('intval', $plan_ids));
    }
}

/* -----------------------------------------------------------------
 * 4) Access helper (fallback): if a member hits a course page, allow
 *    enrollment without purchase. This complements Membership rules.
 * ----------------------------------------------------------------*/
add_action('template_redirect', function () {
    if (!is_singular('courses') || !is_user_logged_in()) return;
    $course_id = get_the_ID();
    // Skip if course is free
    $price_type = get_post_meta($course_id, '_tutor_course_price_type', true);
    if ($price_type === 'free') return;

    // Check if user already purchased (Tutor/Woo normally handles this)
    $user_id = get_current_user_id();
    if (apply_filters('platform_core_user_has_course', false, $user_id, $course_id)) return;

    // If user is active member of one of the selected plans, grant access (enroll)
    $plan_ids = (array) get_post_meta($course_id, '_platform_membership_plans', true);
    if (!empty($plan_ids) && function_exists('wc_memberships_is_user_active_member')) {
        foreach ($plan_ids as $plan_id) {
            if (wc_memberships_is_user_active_member($user_id, $plan_id)) {
                // Tutor LMS enrollment helper varies by version;
                // We set a generic enrollment meta as a safe fallback:
                add_user_meta($user_id, '_platform_enrolled_course_'.$course_id, current_time('mysql'), true);
                do_action('platform_core_course_enrolled', $user_id, $course_id); // hook to wire deeper with Tutor if needed
                break;
            }
        }
    }
});
