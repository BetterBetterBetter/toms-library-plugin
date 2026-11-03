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
    $assert(9 === substr_count($html, '<article id="task-'), 'The guide does not render the nine everyday task walkthroughs.');
    $assert(9 === substr_count($html, 'class="tsol-guide-playlist__item"'), 'The playlist does not list the nine walkthroughs.');

    // 3. Each task's screencast script, captions, and poster ship with the plugin.
    preg_match_all('#data-guide-task="([0-9a-z\-]+)" data-script="(docs/screencasts/[0-9a-z\-]+\.md)"#', $html, $tasks);
    $assert(9 === count($tasks[2]), 'Not every task names its screencast script.');
    foreach ($tasks[2] as $i => $script) {
        $clip = $tasks[1][$i];
        $assert(is_readable(MEMBER_LIBRARY_PLUGIN_DIR . $script), 'The guide names a missing screencast script: ' . $script);
        $assert(false !== strpos($script, $clip), 'Task ' . $clip . ' points at another clip\'s script.');
        foreach (array('captions/' . $clip . '.vtt', 'posters/' . $clip . '.jpg') as $asset) {
            $assert(is_readable(MEMBER_LIBRARY_PLUGIN_DIR . 'assets/features/library-content/guide/' . $asset), 'Missing guide asset: ' . $asset);
        }
        $assert(false !== strpos($html, 'id="panel-script-' . $clip . '"'), 'Task ' . $clip . ' has no narration transcript.');
    }

    // Every task shows either a captioned video or the written-steps fallback.
    $videos = substr_count($html, '<video class="tsol-guide-video"');
    $missing = substr_count($html, 'tsol-guide-video--missing');
    $assert(9 === $videos + $missing, 'A task renders neither its video nor the fallback.');
    $assert($videos === substr_count($html, '<track kind="captions"'), 'A guide video has no captions track.');

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
