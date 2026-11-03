/* Library Guide — progressive enhancement. Without JavaScript every
   walkthrough is shown in full, one after another, with its steps and
   narration. With it, the page becomes a player: a gallery of every video,
   one walkthrough on stage below it, Steps / Narration tabs, watched
   progress, a transcript that seeks the video, and search across tasks
   and help. */
(function () {
    'use strict';

    var root = document.querySelector('.tsol-library-guide');
    if (!root) {
        return;
    }
    root.classList.add('is-enhanced');

    var STORE = 'tsolLibraryGuideWatched';
    var tasks = [].slice.call(root.querySelectorAll('[data-guide-task]'));
    var items = [].slice.call(root.querySelectorAll('.tsol-guide-playlist__item'));
    var faqs = [].slice.call(root.querySelectorAll('.tsol-guide-faq__item'));
    var stage = root.querySelector('.tsol-guide-stage');

    // Watched progress is a per-viewer convenience; it must never break the page.
    var watched = {};
    try { watched = JSON.parse(window.localStorage.getItem(STORE) || '{}') || {}; } catch (e) { watched = {}; }

    function saveWatched() {
        try { window.localStorage.setItem(STORE, JSON.stringify(watched)); } catch (e) { /* ignore */ }
    }

    function paintProgress() {
        var done = 0;
        items.forEach(function (li) {
            var on = !!watched[li.getAttribute('data-clip')];
            li.classList.toggle('is-watched', on);
            if (on) { done++; }
        });
        var box = root.querySelector('[data-guide-progress]');
        if (!box) { return; }
        box.hidden = false;
        root.querySelector('[data-guide-progress-text]').textContent = done + ' of ' + items.length + ' watched';
        root.querySelector('[data-guide-progress-bar]').style.width = Math.round(done / items.length * 100) + '%';
    }

    function pauseAll() {
        root.querySelectorAll('video').forEach(function (v) { v.pause(); });
    }

    function current() {
        for (var i = 0; i < tasks.length; i++) {
            if (tasks[i].classList.contains('is-open')) { return tasks[i]; }
        }
        return null;
    }

    function open(clip, opts) {
        opts = opts || {};
        var found = null;
        tasks.forEach(function (t) {
            if (t.getAttribute('data-guide-task') === clip) { found = t; }
        });
        if (!found) { return null; }
        if (found !== current()) { pauseAll(); }
        tasks.forEach(function (t) { t.classList.toggle('is-open', t === found); });
        items.forEach(function (li) {
            var on = li.getAttribute('data-clip') === clip;
            li.classList.toggle('is-current', on);
            var a = li.querySelector('a');
            if (on) { a.setAttribute('aria-current', 'true'); } else { a.removeAttribute('aria-current'); }
        });
        if (opts.scroll && stage) {
            var top = stage.getBoundingClientRect().top;
            if (top < 40 || top > window.innerHeight * 0.3) {
                var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                stage.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
            }
        }
        if (opts.focus) {
            var h = found.querySelector('h3');
            if (h) { h.setAttribute('tabindex', '-1'); h.focus({ preventScroll: true }); }
        }
        if (opts.history && window.history.replaceState) {
            window.history.replaceState(null, '', '#task-' + clip);
        }
        return found;
    }

    // Tabs: Steps / Narration.
    function selectTab(tab, focus) {
        var list = tab.closest('[role="tablist"]');
        list.querySelectorAll('[role="tab"]').forEach(function (t) {
            var on = t === tab;
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            t.tabIndex = on ? 0 : -1;
            var panel = document.getElementById(t.getAttribute('aria-controls'));
            if (panel) { panel.hidden = !on; }
        });
        if (focus) { tab.focus(); }
    }

    root.querySelectorAll('.tsol-guide-tabs').forEach(function (list) {
        list.hidden = false;
        var first = list.querySelector('[role="tab"]');
        if (first) { selectTab(first, false); }
        list.addEventListener('keydown', function (e) {
            var tabs = [].slice.call(list.querySelectorAll('[role="tab"]'));
            var i = tabs.indexOf(document.activeElement);
            if (i < 0) { return; }
            var next = null;
            if (e.key === 'ArrowRight') { next = tabs[(i + 1) % tabs.length]; }
            if (e.key === 'ArrowLeft') { next = tabs[(i - 1 + tabs.length) % tabs.length]; }
            if (e.key === 'Home') { next = tabs[0]; }
            if (e.key === 'End') { next = tabs[tabs.length - 1]; }
            if (next) { e.preventDefault(); selectTab(next, true); }
        });
    });

    // Title cards: the first frame of an admin screencast is busy, so each
    // video starts behind a calm cover that names the task.
    // While the cover is up, the native controls underneath must not take
    // Tab stops ahead of it; the video becomes interactive once started.
    root.querySelectorAll('[data-guide-play]').forEach(function (b) {
        b.hidden = false;
        var v = b.closest('.tsol-guide-task').querySelector('video');
        if (v) { v.inert = true; v.setAttribute('tabindex', '-1'); }
    });

    function start(host, focusVideo) {
        var clipVideo = host && host.querySelector('video');
        if (!clipVideo) { return; }
        host.classList.add('is-started');
        clipVideo.inert = false;
        clipVideo.removeAttribute('tabindex');
        if (focusVideo) { clipVideo.focus({ preventScroll: true }); }
        var started = clipVideo.play();
        if (started && started.catch) { started.catch(function () {}); }
    }

    root.addEventListener('click', function (e) {
        var replay = e.target.closest('[data-guide-replay]');
        if (replay) {
            var rv = replay.closest('.tsol-guide-task').querySelector('video');
            if (rv) { rv.currentTime = 0; rv.focus({ preventScroll: true }); var rp = rv.play(); if (rp && rp.catch) { rp.catch(function () {}); } }
            return;
        }
        var cover = e.target.closest('[data-guide-play]');
        if (cover) {
            start(cover.closest('.tsol-guide-task'), true);
            return;
        }
        var opener = e.target.closest('[data-guide-open]');
        if (opener) {
            e.preventDefault();
            var clip = opener.getAttribute('data-guide-open');
            var wasOpen = current() && current().getAttribute('data-guide-task') === clip;
            var task = open(clip, { scroll: true, focus: true, history: true });
            // A thumbnail click means "play this": start it, unless it is
            // already the one on stage (then just bring the player into view).
            if (task && opener.hasAttribute('data-guide-autoplay') && !wasOpen) { start(task, false); }
            return;
        }
        var tab = e.target.closest('[role="tab"]');
        if (tab && root.contains(tab)) {
            selectTab(tab, false);
            return;
        }
        var cue = e.target.closest('[data-seek]');
        if (cue) {
            var video = cue.closest('.tsol-guide-task').querySelector('video');
            if (video) {
                video.currentTime = parseFloat(cue.getAttribute('data-seek')) || 0;
                var played = video.play();
                if (played && played.catch) { played.catch(function () {}); }
            }
        }
    });

    // Per-video: highlight the spoken line, and record "watched".
    tasks.forEach(function (task) {
        var video = task.querySelector('video');
        if (!video) { return; }
        var clip = task.getAttribute('data-guide-task');
        var cues = [].slice.call(task.querySelectorAll('[data-seek]'));
        var list = task.querySelector('.tsol-guide-transcript');
        var last = null;
        var endcard = task.querySelector('[data-guide-endcard]');
        video.addEventListener('play', function () {
            if (endcard) { endcard.hidden = true; }
            task.classList.add('is-started');
            video.inert = false;
            video.removeAttribute('tabindex');
        });
        video.addEventListener('timeupdate', function () {
            var t = video.currentTime;
            var active = null;
            cues.forEach(function (c) {
                if (parseFloat(c.getAttribute('data-seek')) <= t + 0.05) { active = c; }
            });
            if (active !== last) {
                cues.forEach(function (c) { c.classList.toggle('is-active', c === active); });
                if (active && list && list.offsetParent) {
                    var lt = list.getBoundingClientRect();
                    var ct = active.getBoundingClientRect();
                    if (ct.top < lt.top || ct.bottom > lt.bottom) {
                        list.scrollTop += ct.top - lt.top - 8;
                    }
                }
                last = active;
            }
            if (!watched[clip] && video.duration && t / video.duration > 0.9) {
                watched[clip] = 1;
                saveWatched();
                paintProgress();
            }
        });
        video.addEventListener('ended', function () {
            watched[clip] = 1;
            saveWatched();
            paintProgress();
            task.classList.add('is-ended');
            if (endcard) {
                endcard.hidden = false;
                var nextLink = endcard.querySelector('a, button:last-child');
                if (nextLink && task.contains(document.activeElement)) { nextLink.focus({ preventScroll: true }); }
            }
        });
        video.addEventListener('play', function () { task.classList.remove('is-ended'); task.classList.add('is-started'); });
    });

    // Search across walkthroughs and help answers.
    var input = root.querySelector('#tsol-guide-search');
    var empty = root.querySelector('.tsol-guide-search__empty');
    if (input) {
        input.addEventListener('input', function () {
            var words = input.value.toLowerCase().split(/\s+/).filter(Boolean);
            var firstHit = null;
            var hits = 0;
            function match(el) {
                var hay = el.getAttribute('data-guide-filter') || '';
                return words.every(function (w) { return hay.indexOf(w) !== -1; });
            }
            items.forEach(function (li) {
                var on = !words.length || match(li);
                li.hidden = !on;
                if (on) { hits++; if (!firstHit) { firstHit = li; } }
            });
            faqs.forEach(function (d) {
                var on = !words.length || match(d);
                d.hidden = !on;
                if (words.length) { d.open = on; }
            });
            root.querySelectorAll('[data-guide-faq]').forEach(function (s) {
                s.hidden = !!words.length && !s.querySelector('.tsol-guide-faq__item:not([hidden])');
            });
            root.classList.toggle('is-searching', !!words.length);
            if (empty) { empty.hidden = !words.length || hits > 0; }
            // Put the best match on stage, unless a video is playing.
            var cur = current();
            var playing = cur && cur.querySelector('video') && !cur.querySelector('video').paused;
            if (words.length && firstHit && !playing && cur && cur.getAttribute('data-guide-task') !== firstHit.getAttribute('data-clip')) {
                var curItem = root.querySelector('.tsol-guide-playlist__item.is-current');
                if (!curItem || curItem.hidden) { open(firstHit.getAttribute('data-clip'), {}); }
            }
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var hit = root.querySelector('.tsol-guide-playlist__item:not([hidden])');
                if (hit) { open(hit.getAttribute('data-clip'), { scroll: true, focus: true, history: true }); }
            }
        });
    }

    paintProgress();
    var hash = window.location.hash.indexOf('#task-') === 0 ? window.location.hash.slice(6) : '';
    if (!(hash && open(hash, { scroll: true })) && tasks.length) {
        open(tasks[0].getAttribute('data-guide-task'), {});
    }

    // Back/forward and in-page #task- links switch the player too.
    window.addEventListener('hashchange', function () {
        if (window.location.hash.indexOf('#task-') === 0) {
            open(window.location.hash.slice(6), { scroll: true });
        }
    });
}());
