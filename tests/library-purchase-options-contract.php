<?php
/**
 * Course/Series purchase options contract (admin repeater → catalogue).
 *
 * Admins author 1–3 purchase options per Course or Series: name, price, price
 * note, what's included, button text, optional button price and a Stripe
 * checkout link. The catalogue exports only complete options, and the legacy
 * single `purchase_offer` is derived from the first one.
 *
 * Run: wp eval-file tests/library-purchase-options-contract.php --skip-themes
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

$model = 'MemberLibrary_Content_Model';
$catalogue = 'MemberLibrary_Content_Catalogue';
$assert(defined($model . '::META_PURCHASE_OPTIONS'), 'Purchase options meta key is not defined.');
$assert(method_exists($model, 'sanitize_purchase_options'), 'Purchase options sanitizer is missing.');
$assert(method_exists($model, 'purchase_option_problems'), 'Purchase option completeness check is missing.');
$assert(method_exists($catalogue, 'purchase_options_for_post'), 'Catalogue purchase options export is missing.');
if ($failures) {
    foreach ($failures as $failure) {
        WP_CLI::warning($failure);
    }
    WP_CLI::error('Purchase options contract failed before behavior checks.');
}

$complete = array(
    'name' => '  Lifetime access  ',
    'price' => '$197',
    'price_note' => 'one-time',
    'includes' => "Lifetime access to this course\n\n  1 month of The School free  \n",
    'button_label' => 'Buy lifetime access',
    'button_price' => '',
    'checkout_url' => 'https://buy.stripe.com/test_abc123',
);

// Sanitizing: trims, splits includes, keeps order, caps at three.
$saved = MemberLibrary_Content_Model::sanitize_purchase_options(array(
    $complete,
    array_merge($complete, array('name' => 'The School membership', 'checkout_url' => 'https://checkout.stripe.com/c/pay/cs_test_1')),
    array_merge($complete, array('name' => 'Third')),
    array_merge($complete, array('name' => 'Fourth is dropped')),
));
$assert(count($saved) === 3, 'More than three purchase options were kept.');
$assert(($saved[0]['name'] ?? '') === 'Lifetime access', 'Option name was not trimmed.');
$assert(($saved[0]['includes'] ?? null) === array('Lifetime access to this course', '1 month of The School free'), 'Included items were not split into trimmed, non-empty lines.');
$assert(($saved[1]['name'] ?? '') === 'The School membership', 'Option order was not preserved.');

// Rows the admin left completely empty disappear; partly filled rows are kept so typed work is never lost.
$saved = MemberLibrary_Content_Model::sanitize_purchase_options(array(
    array('name' => '', 'price' => '', 'includes' => "\n", 'checkout_url' => ''),
    array('name' => 'Draft option'),
    'not-a-row',
));
$assert(count($saved) === 1 && $saved[0]['name'] === 'Draft option', 'Empty rows were kept or partly filled rows were lost.');

// Markup and over-long text are stripped and bounded.
$saved = MemberLibrary_Content_Model::sanitize_purchase_options(array(
    array_merge($complete, array('name' => '<script>x</script><b>Bold</b> ' . str_repeat('a', 200))),
));
$assert(strpos($saved[0]['name'], '<') === false && function_exists('mb_strlen') && mb_strlen($saved[0]['name']) <= 60, 'Option name kept markup or exceeded 60 characters.');

// Completeness: name, price, button text and an https Stripe checkout link are required.
$assert(MemberLibrary_Content_Model::purchase_option_problems(MemberLibrary_Content_Model::sanitize_purchase_options(array($complete))[0]) === array(), 'A complete option was reported as incomplete.');
foreach (array(
    'name' => array('name' => ''),
    'price' => array('price' => ''),
    'button text' => array('button_label' => ''),
    'checkout link' => array('checkout_url' => ''),
    'non-Stripe link' => array('checkout_url' => 'https://example.com/pay'),
    'plain http link' => array('checkout_url' => 'http://buy.stripe.com/test_abc123'),
    'look-alike host' => array('checkout_url' => 'https://buy.stripe.com.evil.test/x'),
    'credentials in link' => array('checkout_url' => 'https://user:pass@buy.stripe.com/x'),
) as $case => $override) {
    $rows = MemberLibrary_Content_Model::sanitize_purchase_options(array(array_merge($complete, $override)));
    $assert(!empty($rows) && MemberLibrary_Content_Model::purchase_option_problems($rows[0]) !== array(), "An option with a missing/invalid {$case} was reported as complete.");
}

// Catalogue export on a disposable Course.
$course_id = wp_insert_post(array(
    'post_type' => MemberLibrary_Content_Model::COURSE_POST_TYPE,
    'post_status' => 'draft',
    'post_title' => 'Purchase options contract fixture',
), true);
$assert(!is_wp_error($course_id), 'Could not create the disposable Course fixture.');

if (!is_wp_error($course_id)) {
    $course = get_post($course_id);
    update_post_meta($course_id, MemberLibrary_Content_Model::META_PURCHASE_OPTIONS, MemberLibrary_Content_Model::sanitize_purchase_options(array(
        array_merge($complete, array('name' => 'Incomplete', 'checkout_url' => 'https://example.com/pay')),
        $complete,
        array_merge($complete, array('name' => 'The School membership', 'price' => '$97', 'price_note' => 'per month', 'button_label' => 'Join The School', 'button_price' => '$97/mo', 'checkout_url' => 'https://buy.stripe.com/test_school')),
    )));

    $exported = MemberLibrary_Content_Catalogue::purchase_options_for_post($course);
    $assert(count($exported) === 2, 'The catalogue exported an incomplete purchase option.');
    $assert(array_keys($exported[0] ?? array()) === array('name', 'price', 'price_note', 'includes', 'button_label', 'button_price', 'checkout_url'), 'Exported purchase option keys do not match the contract.');
    $assert(($exported[0]['button_price'] ?? '') === '$197', 'An empty button price did not fall back to the option price.');
    $assert(($exported[1]['button_price'] ?? '') === '$97/mo', 'An explicit button price was not exported.');

    $legacy = MemberLibrary_Content_Catalogue::purchase_offer_for_post($course);
    $assert(
        is_array($legacy)
        && $legacy['url'] === 'https://buy.stripe.com/test_abc123'
        && $legacy['button_label'] === 'Buy lifetime access'
        && $legacy['price'] === '$197',
        'The legacy purchase_offer was not derived from the first complete option.'
    );

    // Lessons never carry purchase options.
    $lesson_id = wp_insert_post(array(
        'post_type' => MemberLibrary_Content_Model::ITEM_POST_TYPE,
        'post_status' => 'draft',
        'post_title' => 'Purchase options lesson fixture',
    ), true);
    if (!is_wp_error($lesson_id)) {
        update_post_meta($lesson_id, MemberLibrary_Content_Model::META_PURCHASE_OPTIONS, array($complete));
        $assert(MemberLibrary_Content_Catalogue::purchase_options_for_post(get_post($lesson_id)) === array(), 'A lesson exported purchase options.');
        wp_delete_post($lesson_id, true);
    }

    wp_delete_post($course_id, true);
}

if ($failures) {
    foreach ($failures as $failure) {
        WP_CLI::warning($failure);
    }
    WP_CLI::error('Purchase options contract failed with ' . count($failures) . ' issue(s).');
}

WP_CLI::success('Purchase options contract passed.');
