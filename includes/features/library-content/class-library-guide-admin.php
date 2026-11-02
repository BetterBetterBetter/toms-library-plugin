<?php
/**
 * Library Guide — an in-plugin, task-oriented help screen for the
 * non-technical administrators who manage the member Library day to day.
 *
 * The guide is intentionally recipe-first: it explains the mental model in
 * one short section, then answers the concrete "how do I...?" questions an
 * operator actually asks. Every recipe points at the real admin screens and
 * boxes that ship with this plugin, and most pair with a screencast script in
 * docs/screencasts/ so the written steps and the recorded walkthroughs stay
 * in lockstep.
 *
 * Content is held as a structured array and rendered in one loop so a
 * non-developer editor can adjust wording without touching markup.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MemberLibrary_Guide_Admin implements MemberLibrary_Feature {

    const PAGE_SLUG = 'tsol-library-guide';

    /** @var string */
    private $page_hook = '';

    public function init() {
        add_action('admin_menu', array($this, 'add_menu_page'), 30);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function add_menu_page() {
        $this->page_hook = (string) add_submenu_page(
            MemberLibrary_Admin_Navigation::MENU_SLUG,
            __('Library Guide', 'member-library'),
            __('Guide &amp; Help', 'member-library'),
            'edit_pages',
            self::PAGE_SLUG,
            array($this, 'render')
        );
    }

    public function enqueue_assets($hook) {
        if ('' === $this->page_hook || (string) $hook !== $this->page_hook) {
            return;
        }

        // The shared admin stylesheet loads automatically for every
        // tsol-library-* screen; this adds only the guide-specific layout.
        wp_enqueue_style(
            'tsol-library-guide-admin',
            MEMBER_LIBRARY_PLUGIN_URL . 'assets/features/library-content/library-guide-admin.css',
            array('tsol-library-content-admin'),
            MEMBER_LIBRARY_PLUGIN_VERSION
        );
    }

    public function render() {
        if (!current_user_can('edit_pages')) {
            return;
        }

        $app_name = MemberLibrary_Brand::app_name();
        $sections = $this->sections($app_name);
        ?>
        <div class="wrap tsol-library-admin-page tsol-library-guide">
            <h1><?php esc_html_e('Library Guide &amp; Help', 'member-library'); ?></h1>
            <p class="tsol-library-admin-page__lead">
                <?php esc_html_e('Plain-language, step-by-step answers for managing the member Library. Start with “How the Library works”, then jump to the task you need.', 'member-library'); ?>
            </p>

            <div class="tsol-guide-layout">
                <nav class="tsol-guide-toc" aria-label="<?php esc_attr_e('Guide contents', 'member-library'); ?>">
                    <strong class="tsol-guide-toc__title"><?php esc_html_e('On this page', 'member-library'); ?></strong>
                    <ul>
                        <?php foreach ($sections as $section) : ?>
                            <li><a href="#<?php echo esc_attr($section['id']); ?>"><?php echo esc_html($section['title']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </nav>

                <div class="tsol-guide-body">
                    <?php foreach ($sections as $section) : ?>
                        <section id="<?php echo esc_attr($section['id']); ?>" class="card tsol-guide-section">
                            <h2><?php echo esc_html($section['title']); ?></h2>
                            <?php if (!empty($section['intro'])) : ?>
                                <p class="tsol-guide-section__intro"><?php echo esc_html($section['intro']); ?></p>
                            <?php endif; ?>

                            <?php if (!empty($section['model'])) : ?>
                                <?php $this->render_model($section['model']); ?>
                            <?php endif; ?>

                            <?php if (!empty($section['recipes'])) : ?>
                                <?php foreach ($section['recipes'] as $recipe) : ?>
                                    <?php $this->render_recipe($recipe); ?>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <?php if (!empty($section['items'])) : ?>
                                <dl class="tsol-guide-faq">
                                    <?php foreach ($section['items'] as $item) : ?>
                                        <dt><?php echo esc_html($item['q']); ?></dt>
                                        <dd>
                                            <?php foreach ($item['a'] as $line) : ?>
                                                <p><?php echo esc_html($line); ?></p>
                                            <?php endforeach; ?>
                                            <?php if (!empty($item['link'])) : ?>
                                                <p><a class="button button-small" href="<?php echo esc_url($item['link']['url']); ?>"><?php echo esc_html($item['link']['label']); ?></a></p>
                                            <?php endif; ?>
                                        </dd>
                                    <?php endforeach; ?>
                                </dl>
                            <?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_model(array $model) {
        ?>
        <div class="tsol-guide-model">
            <?php foreach ($model as $index => $box) : ?>
                <?php if ($index > 0) : ?><span class="tsol-guide-model__arrow" aria-hidden="true">→</span><?php endif; ?>
                <div class="tsol-guide-model__box">
                    <strong><?php echo esc_html($box['name']); ?></strong>
                    <span><?php echo esc_html($box['role']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function render_recipe(array $recipe) {
        ?>
        <details class="tsol-guide-recipe">
            <summary>
                <span class="tsol-guide-recipe__title"><?php echo esc_html($recipe['title']); ?></span>
                <?php if (!empty($recipe['screencast'])) : ?>
                    <span class="tsol-status-chip tsol-status-chip--good tsol-guide-recipe__chip"><?php esc_html_e('Screencast available', 'member-library'); ?></span>
                <?php endif; ?>
            </summary>
            <div class="tsol-guide-recipe__body">
                <?php if (!empty($recipe['intro'])) : ?>
                    <p><?php echo esc_html($recipe['intro']); ?></p>
                <?php endif; ?>
                <ol>
                    <?php foreach ($recipe['steps'] as $step) : ?>
                        <li><?php echo esc_html($step); ?></li>
                    <?php endforeach; ?>
                </ol>
                <?php if (!empty($recipe['tip'])) : ?>
                    <p class="tsol-guide-recipe__tip"><strong><?php esc_html_e('Watch out:', 'member-library'); ?></strong> <?php echo esc_html($recipe['tip']); ?></p>
                <?php endif; ?>
                <?php if (!empty($recipe['screencast'])) : ?>
                    <p class="description"><?php printf(esc_html__('Screencast script: %s', 'member-library'), esc_html($recipe['screencast'])); ?></p>
                <?php endif; ?>
            </div>
        </details>
        <?php
    }

    /**
     * All guide content. Strings stay here, in one place, so wording can be
     * edited without hunting through markup. Menu paths match the real
     * Library admin menu exactly.
     *
     * @param string $app_name The member-facing app's display name.
     * @return array<int,array<string,mixed>>
     */
    private function sections($app_name) {
        $rules_url = admin_url('edit.php?post_type=memberpressrule');

        return array(
            array(
                'id'    => 'how-it-works',
                'title' => __('How the Library works', 'member-library'),
                'intro' => __('Four moving parts work together. You spend almost all of your time in the first one.', 'member-library'),
                'model' => array(
                    array('name' => __('WordPress (here)', 'member-library'), 'role' => __('Where you add and organise content, and set who is allowed in.', 'member-library')),
                    array('name' => __('MemberPress', 'member-library'), 'role' => __('The lock. Its rules decide which memberships unlock which content.', 'member-library')),
                    array('name' => __('Catalogue feed', 'member-library'), 'role' => __('The conveyor belt. It carries your changes over to the app automatically.', 'member-library')),
                    array('name' => $app_name, 'role' => __('The storefront members actually see and watch in.', 'member-library')),
                ),
                'items' => array(
                    array(
                        'q' => __('The one rule to remember', 'member-library'),
                        'a' => array(
                            __('You do not set access on a Library course or video directly. You create a MemberPress rule for it, and the Library content inherits that rule.', 'member-library'),
                            __('The “Library access” box on the edit screen only reports what MemberPress currently decides. It is a mirror, not a switch.', 'member-library'),
                        ),
                    ),
                    array(
                        'q' => __('You almost never touch the app directly', 'member-library'),
                        'a' => array(
                            sprintf(__('WordPress is the source of truth. When you publish or edit here, the change is delivered to %s on its own, usually within a minute.', 'member-library'), $app_name),
                            __('If something looks wrong in the app, the fix is almost always here in WordPress, not in the app.', 'member-library'),
                        ),
                    ),
                ),
            ),
            array(
                'id'    => 'everyday-tasks',
                'title' => __('Everyday tasks', 'member-library'),
                'intro' => __('Click any task to expand its steps.', 'member-library'),
                'recipes' => array(
                    array(
                        'title' => __('Add a new course', 'member-library'),
                        'intro' => __('A course is a container with lessons grouped into sections.', 'member-library'),
                        'steps' => array(
                            __('In the left menu, go to Library → Courses, then click Add New Library Course.', 'member-library'),
                            __('Give the course a title and a short Excerpt; members see both on the course card.', 'member-library'),
                            __('Add outcomes in “What you’ll learn” and tick a Collection in the sidebar so it files onto the right shelf. Click Save Draft.', 'member-library'),
                            __('In the “Course curriculum” box, click Open structure builder. Add a section, name it, and click Save structure.', 'member-library'),
                            __('Click Add lesson here under the section, type the lesson title, paste the video link into the “Media” box, and Publish the lesson. Use Back to course structure to return.', 'member-library'),
                            __('Check the “Library access” box (see “Change who can access something”), then Publish the course. It appears in the app automatically, usually within a minute.', 'member-library'),
                        ),
                        'tip' => __('A brand-new course that no Access Group or MemberPress rule covers is open to every signed-in member. Set access before publishing, not after.', 'member-library'),
                        'screencast' => 'docs/screencasts/01-add-a-course.md',
                    ),
                    array(
                        'title' => __('Add episodes to a series', 'member-library'),
                        'intro' => __('A series is an ongoing collection of episodes, such as member calls or live events.', 'member-library'),
                        'steps' => array(
                            __('Go to Library → Series and open the series you want, or click Add New to start one.', 'member-library'),
                            __('Check the “Series settings” box for what each episode is called and the order episodes appear in.', 'member-library'),
                            __('In the “Series episodes” box, use the add link to start a new episode. Type its title and paste the video link into the “Media” box.', 'member-library'),
                            __('Publish the episode, then return to the series: it is listed in “Series episodes” and flows to the app automatically.', 'member-library'),
                        ),
                        'tip' => __('A series inherits its access from the series itself. Episodes do not each need their own rule.', 'member-library'),
                        'screencast' => 'docs/screencasts/02-add-series-episodes.md',
                    ),
                    array(
                        'title' => __('Add a single video or resource', 'member-library'),
                        'steps' => array(
                            __('Go to Library → Content and click Add New.', 'member-library'),
                            __('Add the title and paste the video link into the “Media” box. Add downloads such as worksheets or slides in the “Library resources” box, each with a label and a link.', 'member-library'),
                            __('The “Library placement” box shows where it appears; a one-off stays as Standalone content.', 'member-library'),
                            __('Check the “Library access” box, then Publish.', 'member-library'),
                        ),
                        'screencast' => 'docs/screencasts/03-add-single-content.md',
                    ),
                    array(
                        'title' => __('Change who can access something', 'member-library'),
                        'intro' => __('This is the most important task, and the one most often done wrong. Access is not set on the Library item; it comes from Access Groups, which MemberPress enforces.', 'member-library'),
                        'steps' => array(
                            __('Go to Library → Access Groups. “Live” is what members have now; “Draft” is where your changes wait until you publish.', 'member-library'),
                            __('Open a group and, under “What this group unlocks”, tick the content. The group lists the memberships that include it. Click Save draft — saving never changes member access.', 'member-library'),
                            __('You can also tick groups from a MemberPress membership, in its “Library Access Groups” box. That saves to the same draft.', 'member-library'),
                            __('When ready, use “Preview the check first”, then Publish. Publishing checks every current member; if anyone would lose access, nothing is published and you see exactly who.', 'member-library'),
                            __('Re-open the Library item and confirm the “Library access” box reads “Protected by MemberPress” and names the group.', 'member-library'),
                        ),
                        'tip' => __('If the “Library access” box says “No MemberPress rule applies”, the content is open to every signed-in member. That is almost never what you want for paid content.', 'member-library'),
                        'screencast' => 'docs/screencasts/04-change-access.md',
                    ),
                    array(
                        'title' => __('Check who can access something', 'member-library'),
                        'steps' => array(
                            __('Open the Library item and read the “Library access” box in the sidebar; it lists the live rules that apply.', 'member-library'),
                            __('For a wider view, go to Library → Settings and open the Access tab to see protection across the catalogue.', 'member-library'),
                            __('The Access column on the Courses, Series, and Content lists shows protected vs open at a glance.', 'member-library'),
                        ),
                        'screencast' => 'docs/screencasts/05-check-access.md',
                    ),
                    array(
                        'title' => __('Feature content on the Library homepage', 'member-library'),
                        'steps' => array(
                            __('Go to Library → Homepage.', 'member-library'),
                            __('Search for the item and choose the row it belongs in, for example Featured.', 'member-library'),
                            __('Use the Move up and Move down arrows to set its order within the row.', 'member-library'),
                            __('Click Save homepage. The homepage in the app updates automatically.', 'member-library'),
                        ),
                        'tip' => __('Featuring changes where something appears, not who can open it. Access still comes from its Access Group.', 'member-library'),
                        'screencast' => 'docs/screencasts/06-feature-homepage.md',
                    ),
                    array(
                        'title' => __('Post an announcement to members', 'member-library'),
                        'intro' => __('Announcements follow a numbered flow down the edit screen. Sending is not switched on yet: for now, announcements are saved as drafts only.', 'member-library'),
                        'steps' => array(
                            __('Go to Library → Announcements and click Add New.', 'member-library'),
                            __('Work down the numbered boxes: 1. Message, 2. Destination (where it sends members), 3. Audience (who gets it).', 'member-library'),
                            __('Box 4. Delivery shows the channel. Click Save draft — your destination and audience are kept, and nothing is sent.', 'member-library'),
                            __('In box 5. Review, click Preview audience to see who would receive it.', 'member-library'),
                        ),
                        'tip' => __('Once sending is switched on, delivery will reach real members. Always preview the audience in the Review box first.', 'member-library'),
                        'screencast' => 'docs/screencasts/07-post-announcement.md',
                    ),
                    array(
                        'title' => __('Organise with Collections and Topics', 'member-library'),
                        'steps' => array(
                            __('Collections are the shelves content sits on; manage them under Library → Collections.', 'member-library'),
                            __('Topics are cross-cutting tags; manage them under Library → Topics.', 'member-library'),
                            __('Assign both from the edit screen of any course, series, or content item.', 'member-library'),
                        ),
                        'screencast' => 'docs/screencasts/08-collections-topics.md',
                    ),
                    array(
                        'title' => __('Schedule or unpublish content', 'member-library'),
                        'steps' => array(
                            __('To schedule: on the edit screen, set the Publish date to the future and the item goes live automatically.', 'member-library'),
                            __('To hide something already live: change its status to Draft and Update. It leaves the app on the next sync.', 'member-library'),
                        ),
                        'tip' => __('Deleting is permanent. Prefer Draft if you may want the content back.', 'member-library'),
                        'screencast' => 'docs/screencasts/09-schedule-unpublish.md',
                    ),
                ),
            ),
            array(
                'id'    => 'make-it-show',
                'title' => __('Make sure it shows in the app', 'member-library'),
                'intro' => sprintf(__('Your changes travel to %s on their own. This page confirms the pipe is flowing.', 'member-library'), $app_name),
                'items' => array(
                    array(
                        'q' => __('Confirm a change was delivered', 'member-library'),
                        'a' => array(
                            __('Go to Library → Settings → Sync Status.', 'member-library'),
                            __('When the “WordPress cursor” and the “Library app cursor” match and pending deliveries are zero, everything is delivered.', 'member-library'),
                            __('If a delivery is stuck, use the Retry button on that page.', 'member-library'),
                        ),
                    ),
                    array(
                        'q' => __('A change still is not appearing', 'member-library'),
                        'a' => array(
                            __('On Sync Status, confirm the Library URL and the catalogue synchronization secret exactly match the app’s settings.', 'member-library'),
                            __('Then wait a minute and refresh; the app processes deliveries shortly after they are sent.', 'member-library'),
                        ),
                    ),
                ),
            ),
            array(
                'id'    => 'troubleshooting',
                'title' => __('Troubleshooting', 'member-library'),
                'items' => array(
                    array(
                        'q' => __('A member says they cannot open content they paid for', 'member-library'),
                        'a' => array(
                            __('First confirm their membership is active in MemberPress (MemberPress → Members).', 'member-library'),
                            __('Then open the content’s “Library access” box and confirm the rule includes their membership.', 'member-library'),
                            __('If the membership is active and listed on the rule but access still fails, note the content and member and escalate to the technical team.', 'member-library'),
                        ),
                        'link' => array('url' => $rules_url, 'label' => __('Open MemberPress Rules', 'member-library')),
                    ),
                    array(
                        'q' => __('Everyone can see paid content for free', 'member-library'),
                        'a' => array(
                            __('This means no MemberPress rule is attached. Open the item; the “Library access” box will say “No MemberPress rule applies”.', 'member-library'),
                            __('Add a rule (see “Change who can access something”) and the content locks down immediately.', 'member-library'),
                        ),
                    ),
                    array(
                        'q' => __('The app shows “Membership required” on a video', 'member-library'),
                        'a' => array(
                            __('That is the normal message when the signed-in member’s plan does not unlock that content. Confirm it is expected for that member.', 'member-library'),
                            __('If it is wrong, check the rule’s memberships and the member’s active membership, as above.', 'member-library'),
                        ),
                    ),
                    array(
                        'q' => __('A video will not play, but the page loads', 'member-library'),
                        'a' => array(
                            __('Re-open the item and confirm the “Media” box has the correct video attached.', 'member-library'),
                            __('If the video is missing or points at the wrong file, re-attach it and Update.', 'member-library'),
                        ),
                    ),
                ),
            ),
            array(
                'id'    => 'updating',
                'title' => __('Updating the plugin', 'member-library'),
                'items' => array(
                    array(
                        'q' => __('How updates arrive', 'member-library'),
                        'a' => array(
                            __('This plugin updates itself from its official release source, the same way other WordPress plugins do.', 'member-library'),
                            __('When an update is available you will see it on Plugins → Installed Plugins, with a notice to update.', 'member-library'),
                            __('Updating does not change your content, your rules, or your settings. If you are unsure about timing, check with the technical team first.', 'member-library'),
                        ),
                        'link' => array('url' => admin_url('plugins.php'), 'label' => __('Open Installed Plugins', 'member-library')),
                    ),
                ),
            ),
            array(
                'id'    => 'screencasts',
                'title' => __('Video walkthroughs', 'member-library'),
                'intro' => __('Each everyday task above has a matching screencast script in the plugin’s docs/screencasts folder, so the recorded videos and these written steps always cover the same tasks.', 'member-library'),
            ),
        );
    }
}
