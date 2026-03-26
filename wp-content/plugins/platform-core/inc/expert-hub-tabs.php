<?php
// platform-core/inc/expert-hub-tabs.php
// ZERO page creation; safe to load on every request.
if (!defined('ABSPATH')) exit;
add_action('wp_enqueue_scripts', function () {
    $v = defined('WP_DEBUG') && WP_DEBUG ? time() : '1.0.0';
    wp_register_style('platform-expert-hub', plugins_url('../assets/expert-hub.css', __FILE__), [], $v);
    wp_register_script('platform-expert-hub', plugins_url('../assets/expert-hub.js', __FILE__), [], $v, true);
});
add_shortcode('platform_expert_hub', function ($atts) {
    if (!is_user_logged_in()) return '<p>Please sign in to access your expert panel.</p>';
    $atts = shortcode_atts(['default' => 'invites'], $atts, 'platform_expert_hub');
    $default = in_array($atts['default'], ['invites','tutorials','viva'], true) ? $atts['default'] : 'invites';
    wp_enqueue_style('platform-expert-hub');
    wp_enqueue_script('platform-expert-hub');
    $has_invites  = shortcode_exists('platform_expert_invites');
    $has_employee = shortcode_exists('ameliaemployeepanel');
    $has_viva     = shortcode_exists('platform_expert_college_classes');
    ob_start(); ?>
    <div class="peh">
      <nav class="peh-tabs" data-default="<?php echo esc_attr($default); ?>">
        <?php if ($has_invites):  ?><button class="peh-tab" data-tab="invites">Invites</button><?php endif; ?>
        <?php if ($has_employee): ?><button class="peh-tab" data-tab="tutorials">Manage tutorials</button><?php endif; ?>
        <?php if ($has_viva):     ?><button class="peh-tab" data-tab="viva">Viva</button><?php endif; ?>
      </nav>
      <?php if ($has_invites): ?>
        <section class="peh-panel" data-tab="invites"><?php echo do_shortcode('[platform_expert_invites]'); ?></section>
      <?php endif; ?>
      <?php if ($has_employee): ?>
        <section class="peh-panel" data-tab="tutorials"><?php echo do_shortcode('[ameliaemployeepanel]'); ?></section>
      <?php else: ?>
        <section class="peh-panel" data-tab="tutorials" hidden>
          <p><em>Amelia Employee Panel not detected. Ensure Amelia is active and the shortcode exists.</em></p>
        </section>
      <?php endif; ?>
      <?php if ($has_viva): ?>
        <section class="peh-panel" data-tab="viva"><?php echo do_shortcode('[platform_expert_college_classes]'); ?></section>
      <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
});