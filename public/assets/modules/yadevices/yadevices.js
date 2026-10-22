/*
 * YaDevices admin UI.
 *
 * Server rendering owns the data. This file only handles local interactions:
 * search/filter, notify-group rows, auth polling and small copy helpers.
 */
(function (window, document) {
    'use strict';

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"'`]/g, function (char) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '`': '&#96;'}[char];
        });
    }

    function showToast(text, tone) {
        if (window.MDJUIToast) {
            window.MDJUIToast.show({type: tone || 'success', title: 'Я.Устройства', text: text});
        }
    }

    // ---------------------------------------------------------------------
    // Station list
    // ---------------------------------------------------------------------
    function initStationList(scope) {
        var list = scope.querySelector('[data-yv-stations]');
        var search = scope.querySelector('[data-yv-station-search]');
        var platform = scope.querySelector('[data-yv-platform-filter]');
        var empty = scope.querySelector('[data-yv-station-empty]');
        var count = scope.querySelector('[data-yv-station-count]');
        if (!list || !search) {
            return;
        }
        if (list.dataset.yvReady === '1') {
            return;
        }
        list.dataset.yvReady = '1';

        var cards = Array.prototype.slice.call(list.querySelectorAll('[data-yv-station]'));

        function filter() {
            var query = search.value.trim().toLowerCase();
            var selected = platform ? platform.value : '';
            var visible = 0;
            cards.forEach(function (card) {
                var haystack = (card.getAttribute('data-search') || '').toLowerCase();
                var matches = (query === '' || haystack.indexOf(query) !== -1)
                    && (selected === '' || card.getAttribute('data-platform') === selected);
                card.hidden = !matches;
                if (matches) {
                    visible++;
                }
            });
            if (empty) {
                empty.hidden = visible !== 0;
            }
            if (count) {
                count.textContent = 'Станций: ' + visible;
            }
        }

        search.addEventListener('input', filter);
        if (platform) {
            platform.addEventListener('change', filter);
        }
        filter();
    }

    // ---------------------------------------------------------------------
    // Device list
    // ---------------------------------------------------------------------
    function initDeviceList(scope) {
        scope.querySelectorAll('[data-yv-toggle-form]').forEach(function (form) {
            if (form.dataset.yvReady === '1') {
                return;
            }
            form.dataset.yvReady = '1';
            var toggle = form.querySelector('[data-yv-device-toggle]');
            if (!toggle) {
                return;
            }
            toggle.addEventListener('change', function () {
                toggle.disabled = true;
                form.submit();
            });
        });

        var all = scope.querySelector('[data-yv-skills-all]');
        if (all && all.dataset.yvReady !== '1') {
            all.dataset.yvReady = '1';
            all.addEventListener('click', function () {
                var form = all.closest('form');
                if (!form) {
                    return;
                }
                form.querySelectorAll('input[type="checkbox"]').forEach(function (checkbox) {
                    checkbox.checked = true;
                });
            });
        }
    }

    // ---------------------------------------------------------------------
    // Settings
    // ---------------------------------------------------------------------
    function initSettings(scope) {
        var monitor = scope.querySelector('[data-yv-monitor-toggle]');
        var monitorFields = scope.querySelector('[data-yv-monitor-fields]');
        if (monitor && monitorFields) {
            var syncMonitor = function () {
                monitorFields.hidden = !monitor.checked;
            };
            if (monitor.dataset.yvReady !== '1') {
                monitor.dataset.yvReady = '1';
                monitor.addEventListener('change', syncMonitor);
            }
            syncMonitor();
        }

        var muvs = scope.querySelector('[data-yv-muvs]');
        if (muvs && muvs.dataset.yvReady !== '1') {
            muvs.dataset.yvReady = '1';
            muvs.addEventListener('change', function () {
                var input = document.getElementById('yv-min-level');
                if (!input) {
                    return;
                }
                input.value = muvs.checked ? '%ThisComputer.minMsgLevel%' : '';
            });
        }

        var groups = scope.querySelector('[data-yv-notify-groups]');
        var add = scope.querySelector('[data-yv-add-group]');
        if (groups && add && add.dataset.yvReady !== '1') {
            add.dataset.yvReady = '1';
            var nextIndex = groups.querySelectorAll('[data-yv-notify-group]').length;
            add.addEventListener('click', function () {
                var template = document.getElementById('yvNotifyGroupTemplate');
                if (!template) {
                    return;
                }
                var html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex++));
                var holder = document.createElement('div');
                holder.innerHTML = html.trim();
                var group = holder.firstElementChild;
                if (group) {
                    groups.appendChild(group);
                    var name = group.querySelector('input[type="text"]');
                    if (name) {
                        name.focus();
                    }
                }
            });

            groups.addEventListener('click', function (event) {
                var remove = event.target.closest('[data-yv-group-remove]');
                if (remove) {
                    var group = remove.closest('[data-yv-notify-group]');
                    if (group && groups.querySelectorAll('[data-yv-notify-group]').length > 1) {
                        group.remove();
                    } else if (group) {
                        group.querySelectorAll('input[type="checkbox"]').forEach(function (checkbox) {
                            checkbox.checked = false;
                        });
                        var input = group.querySelector('input[type="text"]');
                        if (input) {
                            input.value = '';
                        }
                    }
                    return;
                }
                var selectAll = event.target.closest('[data-yv-group-all]');
                if (selectAll) {
                    var target = selectAll.closest('[data-yv-notify-group]');
                    if (target) {
                        target.querySelectorAll('input[type="checkbox"]').forEach(function (checkbox) {
                            checkbox.checked = true;
                        });
                    }
                    return;
                }
                var clear = event.target.closest('[data-yv-group-none]');
                if (clear) {
                    var clearTarget = clear.closest('[data-yv-notify-group]');
                    if (clearTarget) {
                        clearTarget.querySelectorAll('input[type="checkbox"]').forEach(function (checkbox) {
                            checkbox.checked = false;
                        });
                    }
                }
            });
        }
    }

    // ---------------------------------------------------------------------
    // Station card helpers
    // ---------------------------------------------------------------------
    function initStationCard(scope) {
        scope.querySelectorAll('[data-yv-preset]').forEach(function (button) {
            if (button.dataset.yvReady === '1') {
                return;
            }
            button.dataset.yvReady = '1';
            button.addEventListener('click', function () {
                var input = document.getElementById('yv-test-text');
                if (input) {
                    input.value = button.getAttribute('data-yv-preset') || '';
                    input.focus();
                }
            });
        });

        scope.querySelectorAll('[data-yv-copy]').forEach(function (button) {
            if (button.dataset.yvReady === '1') {
                return;
            }
            button.dataset.yvReady = '1';
            button.addEventListener('click', function () {
                var value = button.getAttribute('data-yv-copy') || '';
                var done = function () {
                    showToast('Скопировано.');
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(value).then(done).catch(function () {
                        showToast('Не удалось скопировать.', 'warning');
                    });
                } else {
                    showToast('Буфер обмена недоступен.', 'warning');
                }
            });
        });
    }

    // ---------------------------------------------------------------------
    // Scenarios
    // ---------------------------------------------------------------------
    function initScenarios(scope) {
        var input = scope.querySelector('[data-yv-scenario-search]');
        var table = scope.querySelector('[data-yv-scenario-table]');
        var empty = scope.querySelector('[data-yv-scenario-empty]');
        if (!input || !table || input.dataset.yvReady === '1') {
            return;
        }
        input.dataset.yvReady = '1';
        var rows = Array.prototype.slice.call(table.querySelectorAll('[data-yv-scenario]'));
        input.addEventListener('input', function () {
            var query = input.value.trim().toLowerCase();
            var visible = 0;
            rows.forEach(function (row) {
                var match = query === '' || (row.getAttribute('data-search') || '').toLowerCase().indexOf(query) !== -1;
                row.hidden = !match;
                if (match) {
                    visible++;
                }
            });
            if (empty) {
                empty.hidden = visible !== 0;
            }
        });
    }

    // ---------------------------------------------------------------------
    // Auth polling
    // ---------------------------------------------------------------------
    function initAuthPoll(scope) {
        scope.querySelectorAll('[data-yv-auth-poll]').forEach(function (box) {
            if (box.dataset.yvReady === '1') {
                return;
            }
            box.dataset.yvReady = '1';
            var pollUrl = box.getAttribute('data-poll-url');
            var doneUrl = box.getAttribute('data-done-url');
            var errorUrl = box.getAttribute('data-error-url');
            var csrf = box.getAttribute('data-csrf') || '';
            var auth = box.getAttribute('data-auth') || '';
            var status = box.querySelector('.md-status');
            var stopped = false;

            var setStatus = function (text, tone) {
                if (!status) {
                    return;
                }
                status.className = 'md-status md-status--' + (tone || 'warning');
                status.innerHTML = '<span class="md-status__dot"></span>' + escapeHtml(text);
            };

            var check = function () {
                if (stopped) {
                    return;
                }
                var body = new URLSearchParams();
                body.set('csrf_token', csrf);
                body.set('auth', auth);
                fetch(pollUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
                    body: body.toString()
                }).then(function (response) {
                    return response.json();
                }).then(function (data) {
                    if (data.state === 'otp_auth_finished') {
                        stopped = true;
                        setStatus('Авторизация завершена', 'online');
                        window.location.href = doneUrl;
                        return;
                    }
                    if (data.state === 'auth_error') {
                        stopped = true;
                        setStatus('Ошибка авторизации', 'error');
                        window.location.href = errorUrl;
                        return;
                    }
                    setStatus('Ожидание подтверждения…', 'warning');
                    window.setTimeout(check, 1500);
                }).catch(function () {
                    setStatus('Нет связи, повторяем…', 'warning');
                    window.setTimeout(check, 3000);
                });
            };

            check();
        });
    }

    function init(root) {
        var scope = root && root.querySelectorAll ? root : document;
        initStationList(scope);
        initDeviceList(scope);
        initSettings(scope);
        initStationCard(scope);
        initScenarios(scope);
        initAuthPoll(scope);
    }

    if (window.MDJAdminUI && typeof window.MDJAdminUI.registerModuleUI === 'function') {
        window.MDJAdminUI.registerModuleUI('yadevices', {init: init});
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            init(document);
        });
    } else {
        init(document);
    }
})(window, document);
