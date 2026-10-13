// AE Tracker bookmarklet: reads the open leaderboard on the Atlas Earth web map and sends it to the tracker
// for review. __TARGET__ is replaced with the tracker's import URL when the bookmarklet is generated.
(function () {
    var target = '__TARGET__';
    if (location.hostname.indexOf('atlasearth.com') === -1) {
        alert('AE Tracker: open the Atlas Earth map (app.atlasearth.com/map) and its leaderboard first.');
        return;
    }
    var tab = document.querySelector('.leaderboardButton.active');
    var tabName = null;
    ['city', 'state', 'country', 'global'].forEach(function (c) {
        if (tab && tab.classList.contains(c)) tabName = c;
    });
    if (!tabName || !document.querySelector('.leaderboardList')) {
        alert('AE Tracker: open the leaderboard (the trophy button) first.');
        return;
    }
    if (tabName === 'global') {
        alert('AE Tracker: switch the leaderboard to the town/city, county or country tab.');
        return;
    }

    // Open the tracker window now, while the browser still counts this as your click (avoids popup blockers).
    window.open('', 'aetracker-import');

    var list = document.querySelector('.leaderboardList');
    var last = -1, stable = 0, tries = 0;

    function text(root, selector) {
        var el = root.querySelector(selector);
        return el ? el.textContent.trim() : '';
    }

    // Scroll the list to the bottom until no more rows load, in case it loads more as you scroll.
    function loadAll() {
        var n = document.querySelectorAll('.leaderboardList .leaderboardItem').length;
        stable = n === last ? stable + 1 : 0;
        last = n;
        if (stable < 2 && tries++ < 40) {
            list.scrollTop = list.scrollHeight;
            setTimeout(loadAll, 600);
            return;
        }
        send();
    }

    function send() {
        var rows = [];
        document.querySelectorAll('.leaderboardList .leaderboardItem').forEach(function (el) {
            var name = text(el, '.playerInfo h3');
            var parcels = text(el, '.parcelsOwned h4');
            if (name && /\d/.test(parcels)) {
                rows.push({
                    rank: parseInt(text(el, '.playerImg span'), 10) || null,
                    username: name,
                    parcels: parseInt(parcels.replace(/[^\d]/g, ''), 10),
                    title: text(el, '.playerInfo h4'),
                    title_place: text(el, '.playerInfo h5')
                });
            }
        });
        list.scrollTop = 0;
        if (!rows.length) {
            alert('AE Tracker: no players found on this leaderboard.');
            return;
        }
        var data = JSON.stringify({
            source: location.href,
            tab: tabName,
            place: text(document, '.mapTopMenu-location'),
            description: text(document, '.leaderBoardDescription.active'),
            rows: rows
        });
        // Also copy it, so it can be pasted into the tracker's Import page if sending is blocked.
        try { navigator.clipboard.writeText(data).catch(function () {}); } catch (e) {}

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = target;
        form.target = 'aetracker-import';
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'payload';
        input.value = data;
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
        form.remove();
    }

    loadAll();
})();
