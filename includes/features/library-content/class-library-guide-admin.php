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

    /** Attachment meta key that marks a Media Library video as a guide walkthrough. */
    const VIDEO_META = '_member_library_guide_clip';

    /** @var string */
    private $assets_url = '';

    public function init() {
        add_action('admin_menu', array($this, 'add_menu_page'), 30);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('current_screen', array($this, 'quiet_notices'));
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

    /**
     * This is a reading page for non-technical editors; a stack of unrelated
     * plugin nags above it buries the guide. Notices still show on every
     * other admin screen, so nothing is lost by quieting them here.
     */
    public function quiet_notices($screen) {
        if ('' === $this->page_hook || !is_object($screen) || $screen->id !== $this->page_hook) {
            return;
        }
        foreach (array('admin_notices', 'all_admin_notices', 'network_admin_notices', 'user_admin_notices') as $hook) {
            remove_all_actions($hook);
        }
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
        wp_enqueue_script(
            'tsol-library-guide-admin',
            MEMBER_LIBRARY_PLUGIN_URL . 'assets/features/library-content/library-guide-admin.js',
            array(),
            MEMBER_LIBRARY_PLUGIN_VERSION,
            true
        );
    }

    public function render() {
        if (!current_user_can('edit_pages')) {
            return;
        }

        $this->assets_url = MEMBER_LIBRARY_PLUGIN_URL . 'assets/features/library-content/guide/';
        $app_name = MemberLibrary_Brand::app_name();
        $sections = $this->sections($app_name);
        $by_id = array();
        foreach ($sections as $section) {
            $by_id[$section['id']] = $section;
        }
        $tasks = $by_id['everyday-tasks']['recipes'];
        $videos = $this->videos();
        $seconds = 0;
        foreach ($tasks as $task) {
            $seconds += (int) $task['seconds'];
        }
        ?>
        <div class="wrap tsol-library-admin-page tsol-library-guide">
            <header class="tsol-guide-hero">
                <div class="tsol-guide-hero__text">
                    <p class="tsol-guide-hero__eyebrow"><span class="dashicons dashicons-book-alt" aria-hidden="true"></span><?php esc_html_e('Library help centre', 'member-library'); ?></p>
                    <h1><?php esc_html_e('Guide &amp; Help', 'member-library'); ?></h1>
                    <p class="tsol-guide-hero__lead"><?php esc_html_e('Short narrated videos and plain-language steps for everything you do in the member Library.', 'member-library'); ?></p>
                    <ul class="tsol-guide-hero__facts">
                        <li><span class="dashicons dashicons-video-alt3" aria-hidden="true"></span><?php printf(esc_html__('%d walkthroughs', 'member-library'), count($tasks)); ?></li>
                        <li><span class="dashicons dashicons-clock" aria-hidden="true"></span><?php printf(esc_html__('About %d minutes', 'member-library'), (int) round($seconds / 60)); ?></li>
                        <li><span class="dashicons dashicons-format-chat" aria-hidden="true"></span><?php esc_html_e('Captions &amp; transcripts', 'member-library'); ?></li>
                    </ul>
                </div>
                <div class="tsol-guide-search" role="search">
                    <label for="tsol-guide-search"><?php esc_html_e('What do you want to do?', 'member-library'); ?></label>
                    <div class="tsol-guide-search__field">
                        <span class="dashicons dashicons-search" aria-hidden="true"></span>
                        <input type="search" id="tsol-guide-search" placeholder="<?php esc_attr_e('Try “add a course” or “access”', 'member-library'); ?>" autocomplete="off">
                    </div>
                    <p class="tsol-guide-quick">
                        <span><?php esc_html_e('Popular:', 'member-library'); ?></span>
                        <?php foreach (array(0 => __('Add a course', 'member-library'), 3 => __('Change access', 'member-library'), 8 => __('Unpublish', 'member-library')) as $i => $label) : ?>
                            <?php if (isset($tasks[$i])) : ?><a href="#task-<?php echo esc_attr($tasks[$i]['clip']); ?>" data-guide-open="<?php echo esc_attr($tasks[$i]['clip']); ?>"><?php echo esc_html($label); ?></a><?php endif; ?>
                        <?php endforeach; ?>
                    </p>
                </div>
            </header>

            <nav class="tsol-guide-jump" aria-label="<?php esc_attr_e('Guide contents', 'member-library'); ?>">
                <?php
                $jump_icons = array('screencasts' => 'dashicons-video-alt3', 'how-it-works' => 'dashicons-networking', 'troubleshooting' => 'dashicons-sos', 'make-it-show' => 'dashicons-yes-alt', 'updating' => 'dashicons-update-alt');
                foreach ($jump_icons as $id => $icon) :
                    ?>
                    <a href="#<?php echo esc_attr($id); ?>"><span class="dashicons <?php echo esc_attr($icon); ?>" aria-hidden="true"></span><?php echo esc_html($by_id[$id]['title']); ?></a>
                <?php endforeach; ?>
            </nav>

            <section id="screencasts" class="tsol-guide-section tsol-guide-theatre">
                <div class="tsol-guide-theatre__grid">
                    <div id="everyday-tasks" class="tsol-guide-stage" role="region" aria-label="<?php echo esc_attr($by_id['everyday-tasks']['title']); ?>">
                        <h2 class="screen-reader-text"><?php echo esc_html($by_id['screencasts']['title']); ?></h2>
                        <?php foreach ($tasks as $index => $task) : ?>
                            <?php $this->render_task($task, $index, count($tasks), isset($videos[$task['clip']]) ? $videos[$task['clip']] : '', $tasks); ?>
                        <?php endforeach; ?>
                    </div>

                    <aside class="tsol-guide-playlist" aria-label="<?php esc_attr_e('All walkthroughs', 'member-library'); ?>">
                        <div class="tsol-guide-playlist__head">
                            <div>
                                <h3><?php echo esc_html($by_id['screencasts']['title']); ?></h3>
                                <p><?php printf(esc_html__('%1$d videos · about %2$d min', 'member-library'), count($tasks), (int) round($seconds / 60)); ?></p>
                            </div>
                            <span class="tsol-guide-playlist__progress" data-guide-progress hidden>
                                <span data-guide-progress-text></span>
                                <span class="tsol-guide-playlist__bar"><span data-guide-progress-bar></span></span>
                            </span>
                        </div>
                        <ol class="tsol-guide-playlist__list">
                            <?php foreach ($tasks as $index => $task) : ?>
                                <li class="tsol-guide-playlist__item" data-guide-filter="<?php echo esc_attr($this->keywords($task)); ?>" data-clip="<?php echo esc_attr($task['clip']); ?>">
                                    <a href="#task-<?php echo esc_attr($task['clip']); ?>" data-guide-open="<?php echo esc_attr($task['clip']); ?>" data-guide-autoplay>
                                        <span class="tsol-guide-playlist__index" aria-hidden="true"><span class="tsol-guide-playlist__n"><?php echo (int) ($index + 1); ?></span><span class="dashicons dashicons-controls-play"></span><span class="dashicons dashicons-yes"></span></span>
                                        <span class="tsol-guide-playlist__text">
                                            <span class="tsol-guide-playlist__title"><?php echo esc_html($task['title']); ?></span>
                                            <span class="tsol-guide-playlist__summary"><?php echo esc_html($task['summary']); ?></span>
                                            <span class="tsol-guide-playlist__state">
                                                <span class="tsol-guide-playlist__state--now"><?php esc_html_e('Now playing', 'member-library'); ?></span>
                                                <span class="tsol-guide-playlist__state--done"><?php esc_html_e('Watched', 'member-library'); ?></span>
                                            </span>
                                        </span>
                                        <span class="tsol-guide-playlist__time"><?php echo esc_html($this->clock($task['seconds'])); ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                        <p class="tsol-guide-search__empty" hidden><span class="dashicons dashicons-search" aria-hidden="true"></span><?php esc_html_e('No walkthrough matches that search. Try one word, such as “course”, “access” or “homepage”.', 'member-library'); ?></p>
                        <p class="tsol-guide-player-note"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><?php echo esc_html($by_id['screencasts']['intro']); ?></p>
                    </aside>
                </div>
                <?php if (count($videos) < count($tasks) && current_user_can('manage_options')) : ?>
                    <p class="tsol-guide-gallery__admin">
                        <?php
                        printf(
                            /* translators: 1: installed count, 2: total, 3: WP-CLI command. */
                            esc_html__('Administrators: %1$d of %2$d videos are installed on this site. Install the rest into the Media Library with %3$s.', 'member-library'),
                            count($videos),
                            count($tasks),
                            '<code>wp member-library guide-videos import &lt;folder&gt;</code>'
                        );
                        ?>
                    </p>
                <?php endif; ?>
            </section>

            <?php $this->render_overview($by_id['how-it-works']); ?>

            <div class="tsol-guide-help-grid">
                <div class="tsol-guide-help-col">
                    <?php $this->render_faq($by_id['troubleshooting']); ?>
                </div>
                <div class="tsol-guide-help-col">
                    <?php $this->render_faq($by_id['make-it-show']); ?>
                    <?php $this->render_faq($by_id['updating']); ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_overview(array $section) {
        ?>
        <section id="<?php echo esc_attr($section['id']); ?>" class="tsol-guide-section tsol-guide-overview">
            <div class="tsol-guide-section__head">
                <h2><?php echo esc_html($section['title']); ?></h2>
                <p><?php echo esc_html($section['intro']); ?></p>
            </div>
            <ol class="tsol-guide-flow">
                <?php foreach ($section['model'] as $index => $box) : ?>
                    <li class="tsol-guide-flow__step<?php echo 0 === $index ? ' is-you' : ''; ?>">
                        <span class="tsol-guide-flow__icon dashicons <?php echo esc_attr($box['icon']); ?>" aria-hidden="true"></span>
                        <strong><?php echo esc_html($box['name']); ?></strong>
                        <span><?php echo esc_html($box['role']); ?></span>
                        <?php if (0 === $index) : ?><em class="tsol-guide-flow__badge"><?php esc_html_e('You work here', 'member-library'); ?></em><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
            <div class="tsol-guide-rules">
                <?php foreach ($section['items'] as $index => $item) : ?>
                    <div class="tsol-guide-rule<?php echo 0 === $index ? ' tsol-guide-rule--key' : ''; ?>">
                        <span class="dashicons <?php echo 0 === $index ? 'dashicons-lock' : 'dashicons-wordpress'; ?>" aria-hidden="true"></span>
                        <div>
                            <h3><?php echo esc_html($item['q']); ?></h3>
                            <?php foreach ($item['a'] as $line) : ?>
                                <p><?php echo $this->rich($line); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in rich(). ?></p>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }

    private function render_task(array $task, $index, $total, $video_url, array $tasks) {
        $clip = $task['clip'];
        $cues = $this->cues($clip);
        $poster = $this->assets_url . 'posters/' . $clip . '.jpg';
        $next = $index + 1 < $total ? $tasks[$index + 1] : null;
        $prev = $index > 0 ? $tasks[$index - 1] : null;
        ?>
        <article id="task-<?php echo esc_attr($clip); ?>" class="tsol-guide-task" data-guide-task="<?php echo esc_attr($clip); ?>" data-script="<?php echo esc_attr($task['screencast']); ?>" aria-labelledby="task-<?php echo esc_attr($clip); ?>-title">
            <div class="tsol-guide-task__watch">
                <?php if ('' !== $video_url) : ?>
                    <video class="tsol-guide-video" controls preload="metadata" playsinline poster="<?php echo esc_url($poster); ?>">
                        <source src="<?php echo esc_url($video_url); ?>" type="video/mp4">
                        <track kind="captions" srclang="en" label="<?php esc_attr_e('English', 'member-library'); ?>" src="<?php echo esc_url($this->assets_url . 'captions/' . $clip . '.vtt'); ?>" default>
                    </video>
                    <button type="button" class="tsol-guide-cover" data-guide-play hidden aria-label="<?php echo esc_attr(sprintf(__('Play the walkthrough: %1$s (%2$s)', 'member-library'), $task['title'], $this->clock($task['seconds']))); ?>">
                        <img src="<?php echo esc_url($poster); ?>" alt="" loading="lazy" width="1280" height="800">
                        <span class="tsol-guide-cover__play" aria-hidden="true"><span class="dashicons dashicons-controls-play"></span><span class="tsol-guide-cover__label"><?php echo esc_html(sprintf(__('Play · %s', 'member-library'), $this->clock($task['seconds']))); ?></span></span>
                    </button>
                    <div class="tsol-guide-endcard" data-guide-endcard hidden>
                        <p class="tsol-guide-endcard__done"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><?php esc_html_e('Nice — that’s this walkthrough done.', 'member-library'); ?></p>
                        <div class="tsol-guide-endcard__actions">
                            <button type="button" class="tsol-guide-endcard__replay" data-guide-replay><span class="dashicons dashicons-controls-repeat" aria-hidden="true"></span><?php esc_html_e('Watch again', 'member-library'); ?></button>
                            <?php if ($next) : ?>
                                <a class="tsol-guide-endcard__next" href="#task-<?php echo esc_attr($next['clip']); ?>" data-guide-open="<?php echo esc_attr($next['clip']); ?>" data-guide-autoplay><small><?php esc_html_e('Up next', 'member-library'); ?></small><?php echo esc_html($next['title']); ?><span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="tsol-guide-video tsol-guide-video--missing">
                        <img src="<?php echo esc_url($poster); ?>" alt="" loading="lazy">
                        <p><span class="dashicons dashicons-video-alt3" aria-hidden="true"></span><?php esc_html_e('This video has not been installed on this site yet. The written steps below cover the same task.', 'member-library'); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <header class="tsol-guide-task__head">
                <div class="tsol-guide-task__heading">
                    <p class="tsol-guide-task__meta">
                        <span class="tsol-guide-task__num"><?php echo esc_html(sprintf(__('Walkthrough %1$d of %2$d', 'member-library'), $index + 1, $total)); ?></span>
                        <span><span class="dashicons dashicons-clock" aria-hidden="true"></span><?php echo esc_html($this->clock($task['seconds'])); ?></span>
                        <span><span class="dashicons dashicons-editor-ol" aria-hidden="true"></span><?php echo esc_html(sprintf(_n('%d step', '%d steps', count($task['steps']), 'member-library'), count($task['steps']))); ?></span>
                    </p>
                    <h3 id="task-<?php echo esc_attr($clip); ?>-title"><?php echo esc_html($task['title']); ?></h3>
                    <p class="tsol-guide-task__intro"><?php echo !empty($task['intro']) ? $this->rich($task['intro']) : esc_html($task['summary']); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
                </div>
                <nav class="tsol-guide-task__nav" aria-label="<?php esc_attr_e('Other walkthroughs', 'member-library'); ?>">
                    <?php if ($prev) : ?>
                        <a class="tsol-guide-task__step tsol-guide-task__step--prev" href="#task-<?php echo esc_attr($prev['clip']); ?>" data-guide-open="<?php echo esc_attr($prev['clip']); ?>" title="<?php echo esc_attr($prev['title']); ?>"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span><span><?php esc_html_e('Previous', 'member-library'); ?></span></a>
                    <?php endif; ?>
                    <?php if ($next) : ?>
                        <a class="tsol-guide-task__step tsol-guide-task__step--next" href="#task-<?php echo esc_attr($next['clip']); ?>" data-guide-open="<?php echo esc_attr($next['clip']); ?>" title="<?php echo esc_attr($next['title']); ?>"><span><?php esc_html_e('Next video', 'member-library'); ?></span><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></a>
                    <?php endif; ?>
                </nav>
            </header>

            <?php if (!empty($task['tip'])) : ?>
                <div class="tsol-guide-tip">
                    <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                    <p><strong><?php esc_html_e('Watch out', 'member-library'); ?></strong> <?php echo $this->rich($task['tip']); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
                </div>
            <?php endif; ?>

            <div class="tsol-guide-task__body">
                <div class="tsol-guide-tabs" role="tablist" aria-label="<?php esc_attr_e('Steps or narration', 'member-library'); ?>" hidden>
                    <button type="button" role="tab" id="tab-steps-<?php echo esc_attr($clip); ?>" aria-controls="panel-steps-<?php echo esc_attr($clip); ?>" aria-selected="true"><span class="dashicons dashicons-editor-ol" aria-hidden="true"></span><?php esc_html_e('Step by step', 'member-library'); ?></button>
                    <?php if (!empty($cues)) : ?>
                        <button type="button" role="tab" id="tab-script-<?php echo esc_attr($clip); ?>" aria-controls="panel-script-<?php echo esc_attr($clip); ?>" aria-selected="false" tabindex="-1"><span class="dashicons dashicons-text" aria-hidden="true"></span><?php esc_html_e('Transcript', 'member-library'); ?></button>
                    <?php endif; ?>
                </div>

                <div class="tsol-guide-panel" role="tabpanel" id="panel-steps-<?php echo esc_attr($clip); ?>" aria-labelledby="tab-steps-<?php echo esc_attr($clip); ?>">
                    <h4 class="tsol-guide-panel__title"><?php esc_html_e('Step by step', 'member-library'); ?></h4>
                    <ol class="tsol-guide-steps">
                        <?php foreach ($task['steps'] as $step) : ?>
                            <li><?php echo $this->rich($step); // phpcs:ignore WordPress.Security.EscapeOutput ?></li>
                        <?php endforeach; ?>
                    </ol>
                </div>

                <?php if (!empty($cues)) : ?>
                    <div class="tsol-guide-panel" role="tabpanel" id="panel-script-<?php echo esc_attr($clip); ?>" aria-labelledby="tab-script-<?php echo esc_attr($clip); ?>">
                        <h4 class="tsol-guide-panel__title"><?php esc_html_e('Transcript', 'member-library'); ?></h4>
                        <?php if ('' !== $video_url) : ?><p class="tsol-guide-panel__hint"><?php esc_html_e('Click any line to jump the video to that moment.', 'member-library'); ?></p><?php endif; ?>
                        <ol class="tsol-guide-transcript">
                            <?php foreach ($cues as $cue) : ?>
                                <li><button type="button" class="tsol-guide-transcript__cue" data-seek="<?php echo esc_attr(number_format($cue['at'], 2, '.', '')); ?>"<?php disabled('', $video_url); ?>><span class="tsol-guide-transcript__at"><?php echo esc_html($this->clock($cue['at'])); ?></span> <span><?php echo esc_html($cue['text']); ?></span></button></li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                <?php endif; ?>
            </div>
        </article>
        <?php
    }

    private function render_faq(array $section) {
        ?>
        <section id="<?php echo esc_attr($section['id']); ?>" class="tsol-guide-section tsol-guide-faq" data-guide-faq>
            <div class="tsol-guide-section__head">
                <h2><span class="dashicons <?php echo esc_attr($section['icon']); ?>" aria-hidden="true"></span><?php echo esc_html($section['title']); ?></h2>
                <?php if (!empty($section['intro'])) : ?><p><?php echo esc_html($section['intro']); ?></p><?php endif; ?>
            </div>
            <?php foreach ($section['items'] as $item) : ?>
                <details class="tsol-guide-faq__item" data-guide-filter="<?php echo esc_attr(strtolower($item['q'] . ' ' . implode(' ', $item['a']))); ?>">
                    <summary><?php echo esc_html($item['q']); ?></summary>
                    <div class="tsol-guide-faq__answer">
                        <?php foreach ($item['a'] as $line) : ?>
                            <p><?php echo $this->rich($line); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
                        <?php endforeach; ?>
                        <?php if (!empty($item['link'])) : ?>
                            <p><a class="button" href="<?php echo esc_url($item['link']['url']); ?>"><?php echo esc_html($item['link']['label']); ?></a></p>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </section>
        <?php
    }

    /**
     * Escape a line of guide copy, then highlight menu paths ("Library → Courses")
     * and the names of on-screen boxes in “quotes” so they are easy to scan.
     */
    private function rich($text) {
        $html = esc_html($text);
        $html = preg_replace(
            '/\b((?:Library|MemberPress|Plugins)(?: → [A-Z][\w-]*(?: [A-Z][\w-]*)*)+)/u',
            '<span class="tsol-guide-path">$1</span>',
            $html
        );
        return (string) preg_replace('/“([^”]{2,60})”/u', '<span class="tsol-guide-ui">$1</span>', $html);
    }

    private function keywords(array $task) {
        return strtolower($task['title'] . ' ' . $task['summary'] . ' ' . implode(' ', $task['steps']) . ' ' . (isset($task['also']) ? $task['also'] : ''));
    }

    private function clock($seconds) {
        $seconds = (int) round($seconds);
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    /**
     * Narration cues from the caption file that ships with the plugin.
     *
     * @return array<int,array{at:float,text:string}>
     */
    private function cues($clip) {
        $file = MEMBER_LIBRARY_PLUGIN_DIR . 'assets/features/library-content/guide/captions/' . $clip . '.vtt';
        if (!is_readable($file)) {
            return array();
        }
        $cues = array();
        $blocks = preg_split('/\R\R+/', trim((string) file_get_contents($file)));
        foreach ($blocks as $block) {
            $lines = preg_split('/\R/', trim($block));
            foreach ($lines as $i => $line) {
                if (preg_match('/^(\d+):(\d{2}):(\d{2}(?:\.\d+)?) -->/', $line, $m)) {
                    $text = trim(implode(' ', array_slice($lines, $i + 1)));
                    if ('' !== $text) {
                        $cues[] = array('at' => $m[1] * 3600 + $m[2] * 60 + (float) $m[3], 'text' => $text);
                    }
                    break;
                }
            }
        }
        return $cues;
    }

    /**
     * Walkthrough videos live in the Media Library (they are too large to ship
     * in the plugin ZIP). Each is tagged with VIDEO_META = its clip slug.
     *
     * @return array<string,string> clip slug => video URL
     */
    private function videos() {
        $ids = get_posts(array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 50,
            'fields'         => 'ids',
            'meta_key'       => self::VIDEO_META,
            'no_found_rows'  => true,
        ));
        $videos = array();
        foreach ($ids as $id) {
            $clip = (string) get_post_meta($id, self::VIDEO_META, true);
            $url = wp_get_attachment_url($id);
            if ('' !== $clip && $url) {
                $videos[$clip] = $url;
            }
        }
        /** Filter: override or supply guide video URLs, keyed by clip slug. */
        return (array) apply_filters('member_library_guide_videos', $videos);
    }

    /**
     * WP-CLI: wp member-library guide-videos import <folder> [--force]
     * Copies NN-*.mp4 walkthroughs from <folder> into the Media Library and
     * tags them for the guide. Re-running skips clips already installed.
     */
    public static function cli_import_videos($args, $assoc_args) {
        $dir = isset($args[0]) ? rtrim($args[0], '/') : '';
        if ('' === $dir || !is_dir($dir)) {
            WP_CLI::error('Pass the folder holding the narrated NN-*.mp4 files.');
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $guide = new self();
        $known = array();
        foreach ($guide->sections('') as $section) {
            foreach (isset($section['recipes']) ? $section['recipes'] : array() as $recipe) {
                $known[$recipe['clip']] = $recipe['title'];
            }
        }
        $installed = $guide->videos();
        $force = !empty($assoc_args['force']);
        $added = 0;
        foreach ($known as $clip => $title) {
            $source = $dir . '/' . $clip . '.mp4';
            if (!is_readable($source)) {
                WP_CLI::warning("Missing {$clip}.mp4 in {$dir}; skipped.");
                continue;
            }
            if (isset($installed[$clip]) && !$force) {
                WP_CLI::log("{$clip}: already installed.");
                continue;
            }
            $tmp = wp_tempnam($clip . '.mp4');
            copy($source, $tmp);
            $id = media_handle_sideload(
                array('name' => 'library-guide-' . $clip . '.mp4', 'tmp_name' => $tmp),
                0,
                sprintf('Library guide: %s', $title)
            );
            if (is_wp_error($id)) {
                @unlink($tmp);
                WP_CLI::warning("{$clip}: " . $id->get_error_message());
                continue;
            }
            if ($force) {
                foreach (get_posts(array('post_type' => 'attachment', 'post_status' => 'inherit', 'fields' => 'ids', 'meta_key' => self::VIDEO_META, 'meta_value' => $clip, 'exclude' => array($id))) as $old) {
                    delete_post_meta($old, self::VIDEO_META);
                }
            }
            update_post_meta($id, self::VIDEO_META, $clip);
            $added++;
            WP_CLI::log("{$clip}: installed as attachment {$id}.");
        }
        WP_CLI::success("{$added} guide video(s) installed.");
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
                    array('name' => __('WordPress (here)', 'member-library'), 'role' => __('Where you add and organise content, and set who is allowed in.', 'member-library'), 'icon' => 'dashicons-wordpress'),
                    array('name' => __('MemberPress', 'member-library'), 'role' => __('The lock. Its rules decide which memberships unlock which content.', 'member-library'), 'icon' => 'dashicons-lock'),
                    array('name' => __('Catalogue feed', 'member-library'), 'role' => __('The conveyor belt. It carries your changes over to the app automatically.', 'member-library'), 'icon' => 'dashicons-update'),
                    array('name' => $app_name, 'role' => __('The storefront members actually see and watch in.', 'member-library'), 'icon' => 'dashicons-smartphone'),
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
                'intro' => __('Pick a task to watch its walkthrough and follow the steps. Each video is one to two minutes long.', 'member-library'),
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
                        'clip' => '01-add-a-course',
                        'also' => 'create new course lesson lessons module modules curriculum section build',
                        'seconds' => 129,
                        'summary' => __('Create a course, build its sections and lessons, and publish it.', 'member-library'),
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
                        'clip' => '02-add-series-episodes',
                        'also' => 'episode new episode member call calls live event recording podcast',
                        'seconds' => 67,
                        'summary' => __('Add a new episode to an ongoing series such as member calls.', 'member-library'),
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
                        'clip' => '03-add-single-content',
                        'also' => 'upload video pdf worksheet slides download downloads resource file one-off standalone',
                        'seconds' => 61,
                        'summary' => __('Publish a one-off video or downloadable resource.', 'member-library'),
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
                        'clip' => '04-change-access',
                        'also' => 'permission permissions members only paid paywall restrict restricted lock locked protect membership memberships unlock give access remove access',
                        'seconds' => 82,
                        'summary' => __('Choose which memberships unlock a piece of content.', 'member-library'),
                    ),
                    array(
                        'title' => __('Check who can access something', 'member-library'),
                        'steps' => array(
                            __('Open the Library item and read the “Library access” box in the sidebar; it lists the live rules that apply.', 'member-library'),
                            __('For a wider view, go to Library → Settings and open the Access tab to see protection across the catalogue.', 'member-library'),
                            __('The Access column on the Courses, Series, and Content lists shows protected vs open at a glance.', 'member-library'),
                        ),
                        'screencast' => 'docs/screencasts/05-check-access.md',
                        'clip' => '05-check-access',
                        'also' => 'who can see who can open protected open permissions audit visible public free',
                        'seconds' => 40,
                        'summary' => __('See at a glance who can open something today.', 'member-library'),
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
                        'clip' => '06-feature-homepage',
                        'also' => 'feature featured highlight homepage home page row rows carousel reorder order promote',
                        'seconds' => 33,
                        'summary' => __('Put content in a homepage row and set its order.', 'member-library'),
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
                        'clip' => '07-post-announcement',
                        'also' => 'announce announcement notify notification email push message news broadcast',
                        'seconds' => 71,
                        'summary' => __('Draft a message for members and preview who would get it.', 'member-library'),
                    ),
                    array(
                        'title' => __('Organise with Collections and Topics', 'member-library'),
                        'steps' => array(
                            __('Collections are the shelves content sits on; manage them under Library → Collections.', 'member-library'),
                            __('Topics are cross-cutting tags; manage them under Library → Topics.', 'member-library'),
                            __('Assign both from the edit screen of any course, series, or content item.', 'member-library'),
                        ),
                        'screencast' => 'docs/screencasts/08-collections-topics.md',
                        'clip' => '08-collections-topics',
                        'also' => 'category categories tag tags shelf shelves organize organise sort group',
                        'seconds' => 37,
                        'summary' => __('File content onto shelves and tag it by topic.', 'member-library'),
                    ),
                    array(
                        'title' => __('Schedule or unpublish content', 'member-library'),
                        'steps' => array(
                            __('To schedule: on the edit screen, set the Publish date to the future and the item goes live automatically.', 'member-library'),
                            __('To hide something already live: change its status to Draft and Update. It leaves the app on the next sync.', 'member-library'),
                        ),
                        'tip' => __('Deleting is permanent. Prefer Draft if you may want the content back.', 'member-library'),
                        'screencast' => 'docs/screencasts/09-schedule-unpublish.md',
                        'clip' => '09-schedule-unpublish',
                        'also' => 'expire expiry expiration end date schedule scheduled publish later future hide take down remove unpublish draft delete',
                        'seconds' => 42,
                        'summary' => __('Go live later, or take something down without deleting it.', 'member-library'),
                    ),
                ),
            ),
            array(
                'id'    => 'make-it-show',
                'icon'  => 'dashicons-yes-alt',
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
                'icon'  => 'dashicons-sos',
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
                'icon'  => 'dashicons-update-alt',
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
                'intro' => __('Every video is recorded from the real Library screens and narrated from the same script as the written steps, so the two always match. Captions are on by default; use the CC button in the player to turn them off.', 'member-library'),
            ),
        );
    }
}
