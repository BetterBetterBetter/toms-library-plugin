<?php
/**
 * WP-CLI contract checks for administrator Library access testing.
 *
 * Run: wp eval-file tests/library-access-testing-contract.php --skip-themes
 */

if (!defined('WP_CLI') || !WP_CLI) {
    throw new RuntimeException('Run this contract check through WP-CLI.');
}

$failures = array();
$assert = static function ($condition, $message) use (&$failures) {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(class_exists('MemberLibrary_Auth_Access_Testing'), 'Library access-testing integration is not loaded.');
$assert(has_action('admin_post_tsol_library_open_access_test') !== false, 'Access-test bridge action is not registered.');
$assert(has_action('admin_post_tsol_library_finish_access_test') !== false, 'Access-test finish action is not registered.');
$assert(has_action('admin_post_nopriv_tsol_library_open_access_test') === false, 'Anonymous users can reach the access-test bridge.');
$assert(has_action('admin_post_nopriv_tsol_library_finish_access_test') === false, 'Anonymous users can finish an access test.');
$assert(has_filter('user_row_actions') !== false, 'The Users screen access-test action is not registered.');
$assert(has_filter('mepr-admin-members-cols') !== false, 'The MemberPress access-test column is not registered.');
$assert(has_filter('mepr_members_list_table_row') !== false, 'The MemberPress access-test cell is not registered.');

if (class_exists('MemberLibrary_Auth_Access_Testing')) {
    $assert(
        MemberLibrary_Auth_Access_Testing::valid_token(str_repeat('a', 48)),
        'A generated access-test token is rejected.'
    );
    $assert(
        !MemberLibrary_Auth_Access_Testing::valid_token(str_repeat('a', 47)),
        'A short access-test token is accepted.'
    );
    $assert(
        !MemberLibrary_Auth_Access_Testing::valid_token(str_repeat('g', 48)),
        'A non-hex access-test token is accepted.'
    );
    $key = MemberLibrary_Auth_Access_Testing::transient_key(str_repeat('b', 48));
    $assert(
        preg_match('/^tsol_library_access_test_[a-f0-9]{64}$/', $key) === 1,
        'Access-test transient keys must contain only a hash of the bearer token.'
    );
    $assert(
        strpos($key, str_repeat('b', 48)) === false,
        'The raw access-test bearer token leaked into its transient key.'
    );
    $start_url = MemberLibrary_Auth_Access_Testing::library_login_url('start', str_repeat('c', 48));
    $start_query = array();
    parse_str((string) wp_parse_url($start_url, PHP_URL_QUERY), $start_query);
    $assert(
        isset($start_query['return_to']) && $start_query['return_to'] === '/?access_test=1&access_test_token=' . str_repeat('c', 48),
        'The access-test login does not preserve its exact Library return path.'
    );
    $stop_url = MemberLibrary_Auth_Access_Testing::library_login_url('stop');
    $stop_query = array();
    parse_str((string) wp_parse_url($stop_url, PHP_URL_QUERY), $stop_query);
    $assert(
        isset($stop_query['return_to']) && $stop_query['return_to'] === '/?access_test=0',
        'The administrator login does not clear the Library access-test state.'
    );

    $assert(
        method_exists('MemberLibrary_Auth_Access_Testing', 'switch_back_url'),
        'The access test does not provide a normalized Login as User switch-back URL.'
    );
    if (method_exists('MemberLibrary_Auth_Access_Testing', 'switch_back_url')) {
        $old_user = new WP_User();
        $old_user->ID = 123;
        $return_bridge = add_query_arg(array(
            'action' => 'tsol_library_open_access_test',
            'mode' => 'admin',
        ), admin_url('admin-post.php'));
        $switch_back_url = MemberLibrary_Auth_Access_Testing::switch_back_url($old_user, $return_bridge);
        $switch_back_query = array();
        parse_str((string) wp_parse_url($switch_back_url, PHP_URL_QUERY), $switch_back_query);
        $assert(
            isset($switch_back_query['_wpnonce']) && !isset($switch_back_query['amp;_wpnonce']),
            'The Login as User nonce was left HTML-escaped and cannot be verified by WordPress.'
        );
        $assert(
            isset($switch_back_query['redirect_to']) && $switch_back_query['redirect_to'] === $return_bridge,
            'The Login as User switch-back does not preserve the administrator Library bridge.'
        );
    }
}

if ($failures) {
    foreach ($failures as $failure) {
        WP_CLI::warning($failure);
    }
    WP_CLI::error(count($failures) . ' Library access-testing contract check(s) failed.');
}

WP_CLI::success('Library access-testing contract checks passed.');
