<?php
/** Contract for Library → Guide & Help: registered, gated, and accurate to the shipped screens. */

if (!defined('WP_CLI') || !WP_CLI) {
    throw new RuntimeException('Run this contract through WP-CLI.');
}

$failures = array();
$assert = static function ($condition, $message) use (&$failures) {
    if (!$condition) {
        $failures[] = $message;
    }
};

$guide = new MemberLibrary_Guide_Admin();
$render = static function ($user_id) use ($guide) {
    $previous_user = get_current_user_id();
    wp_set_current_user((int) $user_id);
    ob_start();
    $guide->render();
    $html = (string) ob_get_clean();
    wp_set_current_user($previous_user);
    return $html;
};

// 1. Only editors and above see the guide.
$assert('' === $render(0), 'The guide renders for a signed-out visitor.');

$admin_ids = get_users(array('role' => 'administrator', 'fields' => 'ID', 'number' => 1));
$assert(!empty($admin_ids), 'No administrator user exists to render the guide.');
if (!empty($admin_ids)) {
    $html = $render((int) $admin_ids[0]);

    // 2. Every section is present and every recipe is a native disclosure.
    foreach (array('how-it-works', 'everyday-tasks', 'make-it-show', 'troubleshooting', 'updating', 'screencasts') as $section) {
        $assert(false !== strpos($html, 'id="' . $section . '"'), 'The guide has no ' . $section . ' section.');
    }
    $assert(9 === substr_count($html, '<details class="tsol-guide-recipe">'), 'The guide does not list the nine everyday recipes.');

    // 3. Each recipe's screencast script ships with the plugin.
    preg_match_all('#Screencast script: (docs/screencasts/[0-9a-z\-]+\.md)#', $html, $scripts);
    $assert(9 === count($scripts[1]), 'Not every recipe names its screencast script.');
    foreach ($scripts[1] as $script) {
        $assert(is_readable(MEMBER_LIBRARY_PLUGIN_DIR . $script), 'The guide names a missing screencast script: ' . $script);
    }

    // 4. The guide never promises actions the screens do not offer: announcements
    // are draft-only, and homepage rows are ordered with arrows, not dragging.
    foreach (array('send or schedule', 'Drag items') as $claim) {
        $assert(false === stripos($html, $claim), 'The guide describes an action the admin does not offer: ' . $claim);
    }
}

if (!empty($failures)) {
    WP_CLI::error(implode("\n", array_values(array_unique($failures))));
}
WP_CLI::success('The Library guide is gated, complete, and matches the shipped admin screens.');
