/*
 * InfoLoop kiosk runtime.
 *
 * Boots from /?screen=TOKEN (or a token saved in localStorage), loads the
 * screen config from /api/screen/config, then:
 *  - renders the menu and pages (fetching /api/page/{slug} on navigation)
 *  - rotates the playlist for non-interactive screens
 *  - enforces the inactivity timeout back to the default page
 *  - sends engagement beacons to /api/stats/event
 */
(function () {
    'use strict';

    var root = document.getElementById('kiosk-root');
    if (!root) { return; }

    var state = {
        token: root.dataset.screenToken || localStorage.getItem('il_screen_token') || '',
        config: null,
        currentSlug: null,
        playlistTimer: null,
        playlistIndex: 0,
        inactivityTimer: null,
        galleryTimer: null
    };

    function api(path) {
        return fetch(path + (path.indexOf('?') === -1 ? '?' : '&') + 'token=' + encodeURIComponent(state.token), {
            headers: { 'Accept': 'application/json' }
        }).then(function (r) {
            if (!r.ok) { throw new Error('HTTP ' + r.status); }
            return r.json();
        });
    }

    function postStats(pageId, event) {
        if (!state.token || !pageId) { return; }
        fetch('/api/stats/event', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ token: state.token, page_id: pageId, event: event || 'view' })
        }).catch(function () {});
    }

    function boot() {
        if (!state.token) {
            showBoot('No screen token. Open this page as /?screen=YOUR_TOKEN');
            return;
        }
        localStorage.setItem('il_screen_token', state.token);
        api('/api/screen/config').then(function (cfg) {
            state.config = cfg;
            applyBranding(cfg.branding);
            renderShell(cfg);
            if (cfg.playlist && cfg.playlist.length > 0) {
                startPlaylist(cfg.playlist);
            } else if (cfg.default_page) {
                renderPage(cfg.default_page);
            } else {
                renderHome(cfg);
            }
            resetInactivity();
        }).catch(function (err) {
            showBoot('Cannot load screen configuration (' + err.message + '). Check the token.');
        });
    }

    function showBoot(message) {
        var el = root.querySelector('.kiosk-boot');
        if (el) { el.querySelector('.kiosk-loading').textContent = message; }
    }

    function applyBranding(branding) {
        if (branding && branding.brand_name) { document.title = branding.brand_name; }
    }

    function el(tag, className, text) {
        var e = document.createElement(tag);
        if (className) { e.className = className; }
        if (text !== undefined) { e.textContent = text; }
        return e;
    }

    function renderShell(cfg) {
        root.innerHTML = '';
        var header = el('header', 'kiosk-header');
        header.appendChild(el('div', 'kiosk-brand', cfg.branding.brand_name || 'InfoLoop'));
        var nav = el('nav', 'kiosk-menu');
        buildMenu(nav, cfg.menu || []);
        header.appendChild(nav);
        root.appendChild(header);
        var stage = el('main', 'kiosk-stage');
        stage.id = 'stage';
        root.appendChild(stage);
        var toast = el('div', 'kiosk-toast');
        toast.id = 'toast';
        root.appendChild(toast);
    }

    function buildMenu(nav, items) {
        items.forEach(function (item) {
            if (item.id) {
                var a = el('a', item.slug === state.currentSlug ? 'active' : '', item.title);
                a.href = '#';
                a.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    navigate(item.slug);
                });
                nav.appendChild(a);
            } else if (item._children) {
                buildMenu(nav, item._children);
            }
        });
    }

    function renderHome(cfg) {
        var stage = document.getElementById('stage');
        stage.innerHTML = '';
        stage.appendChild(el('h1', null, 'Welcome to ' + (cfg.branding.brand_name || 'InfoLoop')));
        var p = el('p', null, 'Tap a menu item above to start.');
        stage.appendChild(p);
    }

    function navigate(slug) {
        api('/api/page/' + encodeURIComponent(slug)).then(function (data) {
            if (data.page) { renderPage(data.page); }
            resetInactivity();
            postStats(data.page ? data.page.id : 0, 'interaction');
        }).catch(function () { toast('Page unavailable'); });
    }

    function renderPage(page) {
        stopPlaylist();
        state.currentSlug = page.slug;
        var stage = document.getElementById('stage');
        if (!stage) { return; }
        stage.innerHTML = '';
        stage.appendChild(el('h1', null, page.title));
        applyPageType(stage, page);
        var body = el('article', 'page-body');
        body.innerHTML = page.body_html || '';
        stage.appendChild(body);
        if (page.custom_css) {
            var style = document.createElement('style');
            style.textContent = page.custom_css;
            document.head.appendChild(style);
        }
        if (page.head_html) {
            var head = document.createElement('div');
            head.innerHTML = page.head_html;
            document.head.appendChild(head.firstChild);
        }
        if (page.custom_js) {
            try { (new Function(page.custom_js))(); } catch (e) { console.error(e); }
        }
        postStats(page.id, 'view');
        markActiveMenu(page.slug);
        resetInactivity();
    }

    function applyPageType(stage, page) {
        var s = page.settings || {};
        if (page.page_type === 'gallery' && Array.isArray(s.items) && s.items.length) {
            renderGallery(stage, s);
        } else if (page.page_type === 'pdf' && s.file_id) {
            stage.appendChild(iframe('/api/media-pdf/' + s.file_id + '#page=' + (s.start_page || 1), 'pdf-frame'));
        } else if (page.page_type === 'video' && s.file_id) {
            var v = document.createElement('video');
            v.src = '/api/media-file/' + s.file_id;
            v.className = 'video-el';
            v.controls = true; v.loop = !!s.loop; v.autoplay = !!s.autoplay; v.muted = !!s.muted; v.playsInline = true;
            stage.appendChild(v);
        } else if (page.page_type === 'youtube' && s.video_id) {
            var params = 'autoplay=' + (s.autoplay ? 1 : 0) + '&loop=' + (s.loop ? 1 : 0) + '&rel=0&modestbranding=1';
            stage.appendChild(iframe('https://www.youtube-nocookie.com/embed/' + encodeURIComponent(s.video_id) + '?' + params, 'video-el'));
        } else if (page.page_type === 'iframe' && s.url) {
            stage.appendChild(iframe(s.url, 'iframe-el'));
        } else if (page.page_type === 'clock') {
            renderClock(stage, s);
        }
    }

    function iframe(src, className) {
        var f = document.createElement('iframe');
        f.src = src; f.className = className; f.setAttribute('allowfullscreen', '');
        return f;
    }

    function renderGallery(stage, s) {
        var wrap = el('div', 'gallery-stage');
        var img = document.createElement('img');
        var dots = el('div', 'gallery-dots');
        wrap.appendChild(img); wrap.appendChild(dots);
        stage.appendChild(wrap);
        var i = 0;
        function show(n) {
            i = (n + s.items.length) % s.items.length;
            img.src = s.items[i];
            dots.innerHTML = '';
            s.items.forEach(function (_, k) { dots.appendChild(el('span', k === i ? 'on' : '')); });
        }
        show(0);
        if (s.mode === 'manual') {
            wrap.addEventListener('click', function () { show(i + 1); resetInactivity(); });
        } else {
            state.galleryTimer = setInterval(function () { show(i + 1); }, (s.interval_sec || 5) * 1000);
        }
    }

    function renderClock(stage, s) {
        var face = el('div', 'clock-face');
        var date = el('div', 'clock-date');
        stage.appendChild(face); stage.appendChild(date);
        var tz = s.timezone || 'UTC';
        function tick() {
            var now = new Date();
            var time = now.toLocaleTimeString('en-GB', { timeZone: tz, hour12: false, hour: '2-digit', minute: '2-digit', second: s.show_seconds === false ? undefined : '2-digit' });
            face.textContent = time;
            date.textContent = now.toLocaleDateString('en-GB', { timeZone: tz, weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        }
        tick();
        state.clockTimer = setInterval(tick, 1000);
    }

    function startPlaylist(playlist) {
        state.playlistIndex = 0;
        function play() {
            var item = playlist[state.playlistIndex % playlist.length];
            if (item.payload) { renderPage(item.payload); }
            state.playlistIndex++;
            state.playlistTimer = setTimeout(play, (item.duration_sec || 15) * 1000);
        }
        play();
    }

    function stopPlaylist() {
        if (state.playlistTimer) { clearTimeout(state.playlistTimer); state.playlistTimer = null; }
        if (state.galleryTimer) { clearInterval(state.galleryTimer); state.galleryTimer = null; }
        if (state.clockTimer) { clearInterval(state.clockTimer); state.clockTimer = null; }
    }

    function resetInactivity() {
        if (state.inactivityTimer) { clearTimeout(state.inactivityTimer); }
        var cfg = state.config;
        if (!cfg || !cfg.timeouts || !cfg.timeouts.enabled) { return; }
        state.inactivityTimer = setTimeout(function () {
            if (cfg.default_page) { renderPage(cfg.default_page); }
            else if (cfg.playlist && cfg.playlist.length) { startPlaylist(cfg.playlist); }
        }, (cfg.timeouts.inactivity_sec || 30) * 1000);
    }

    function markActiveMenu(slug) {
        document.querySelectorAll('.kiosk-menu a').forEach(function (a) {
            a.classList.toggle('active', a.textContent !== '' && slug === state.currentSlug && a.dataset.slug === slug);
        });
    }

    function toast(message) {
        var t = document.getElementById('toast');
        if (!t) { return; }
        t.textContent = message;
        t.classList.add('show');
        setTimeout(function () { t.classList.remove('show'); }, 2500);
    }

    ['pointerdown', 'touchstart', 'keydown', 'wheel'].forEach(function (evt) {
        document.addEventListener(evt, resetInactivity, { passive: true });
    });

    boot();
})();
