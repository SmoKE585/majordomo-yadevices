/*
 * YaDevices public player.
 *
 * The legacy frontend still provides jQuery + tiny-pubsub + the WebSocket
 * bridge, so this controller uses `$.subscribe` for live track events and
 * `fetch` for commands. It does not depend on the admin bundle.
 */
(function (window, document) {
    'use strict';

    var root = document.getElementById('yv-player');
    if (!root || root.dataset.yvReady === '1') {
        return;
    }
    root.dataset.yvReady = '1';

    var station = root.getAttribute('data-station') || '';
    var title = root.getAttribute('data-title') || 'Я.Устройства';
    var rootHtml = root.getAttribute('data-root') || '/';
    var bg = root.getAttribute('data-bg') || '0,0,0';
    var textColor = root.getAttribute('data-text') || 'white';
    var zoom = parseFloat(root.getAttribute('data-zoom') || '1') || 1;

    var cover = root.querySelector('[data-yv-cover]');
    var trackEl = root.querySelector('[data-yv-track]');
    var artistEl = root.querySelector('[data-yv-artist]');
    var currentEl = root.querySelector('[data-yv-current]');
    var durationEl = root.querySelector('[data-yv-duration]');
    var volumeEl = root.querySelector('[data-yv-volume]');
    var stateEl = root.querySelector('[data-yv-player-state]');

    var state = {
        playing: false,
        on: false,
        title: '',
        artist: '',
        cover: 'music.yandex.ru/blocks/meta/i/og-image.png',
        progress: 0,
        duration: 0,
        volume: 0
    };

    if (cover) {
        cover.style.setProperty('--yv-bg', bg);
        cover.style.setProperty('--yv-text', textColor);
        cover.style.setProperty('--yv-zoom', String(zoom));
    }

    function formatTime(seconds) {
        var value = Math.max(0, Math.floor(Number(seconds) || 0));
        var minutes = Math.floor(value / 60);
        var rest = value % 60;
        return minutes + ':' + (rest < 10 ? '0' : '') + rest;
    }

    function setState(label, tone) {
        if (!stateEl) {
            return;
        }
        stateEl.className = 'md-status md-status--' + (tone || 'idle');
        stateEl.innerHTML = '<span class="md-status__dot"></span>' + label;
    }

    function render() {
        if (trackEl) {
            trackEl.textContent = state.title || title;
        }
        if (artistEl) {
            artistEl.textContent = state.artist || title;
        }
        if (currentEl) {
            currentEl.textContent = formatTime(state.progress);
        }
        if (durationEl) {
            durationEl.textContent = formatTime(state.duration);
        }
        if (volumeEl) {
            volumeEl.textContent = String(Math.round(state.volume));
        }
        if (cover) {
            var image = String(state.cover || '').replace(/%%/i, '200x200');
            cover.style.backgroundImage =
                'linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(' + bg + ',0.55) 55%, rgba(' + bg + ',1) 100%), url(//' + image + ')';
            cover.classList.toggle('is-playing', state.playing);
        }
        if (state.playing) {
            setState('играет', 'online');
        } else if (state.on) {
            setState('на связи', 'idle');
        } else {
            setState('ожидание', 'idle');
        }
    }

    function load() {
        if (!station) {
            return;
        }
        fetch(rootHtml + 'module/yadevices.html?station=' + encodeURIComponent(station) + '&ajax=1&_=' + Date.now(), {
            credentials: 'same-origin',
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        }).catch(function () {
            setState('нет связи', 'offline');
        });
    }

    function control(action) {
        if (!station) {
            return;
        }
        var realAction = action;
        if (action === 'play' && !state.on) {
            realAction = 'turnOn';
        }
        fetch(rootHtml + 'module/yadevices.html?station=' + encodeURIComponent(station) + '&ajax=1&control=' + encodeURIComponent(realAction), {
            credentials: 'same-origin',
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        }).then(function () {
            window.setTimeout(load, 400);
        }).catch(function () {
            setState('ошибка', 'error');
        });
    }

    root.querySelectorAll('[data-yv-control]').forEach(function (button) {
        button.addEventListener('click', function () {
            control(button.getAttribute('data-yv-control') || '');
        });
    });

    if (window.jQuery && window.jQuery.subscribe) {
        window.jQuery.subscribe('wsConnected', function () {
            if (!window.wsSocket) {
                return;
            }
            window.wsSocket.send(JSON.stringify({
                action: 'Subscribe',
                data: {
                    TYPE: 'events',
                    EVENTS: 'YADEVICES_TRACKS_' + station + ',YADEVICES_STATE_' + station + ',YADEVICES_ONLINE_' + station
                }
            }));
        });

        window.jQuery.subscribe('wsData', function (_, response) {
            if (!response || response.action !== 'events' || !response.data) {
                return;
            }
            var payload;
            try {
                payload = JSON.parse(response.data);
            } catch (error) {
                return;
            }
            var eventData = payload.EVENT_DATA;
            if (!eventData || !eventData.NAME) {
                return;
            }
            var value = eventData.VALUE || {};
            if (eventData.NAME === 'YADEVICES_STATE_' + station) {
                state.playing = !!value.playing;
            } else if (eventData.NAME === 'YADEVICES_TRACKS_' + station) {
                state.on = !!value.on;
                state.playing = !!value.playing;
                state.title = value.title || '';
                state.artist = value.subtitle || '';
                state.cover = value.cover || state.cover;
                state.progress = Number(value.progress) || 0;
                state.duration = Number(value.duration) || 0;
                state.volume = Number(value.volume) || 0;
            } else if (eventData.NAME === 'YADEVICES_ONLINE_' + station) {
                state.on = !!value.online;
                if (!state.on) {
                    state.playing = false;
                }
            }
            render();
        });
    }

    render();
    load();
})(window, document);
