document.querySelectorAll('[data-menu-toggle]').forEach(function (button) {
    button.addEventListener('click', function () {
        var menu = document.querySelector('[data-menu]');
        if (menu) menu.classList.toggle('open');
    });
});

window.setTimeout(function () {
    document.querySelectorAll('.flash').forEach(function (flash) {
        flash.classList.add('hide');
    });
}, 5000);

(function () {
    var cards = Array.from(document.querySelectorAll('[data-place-card]'));
    var search = document.querySelector('[data-place-search]');
    var filters = Array.from(document.querySelectorAll('[data-place-filter]'));
    var empty = document.querySelector('[data-place-empty]');
    if (!cards.length || !search || !filters.length) return;

    var currentFilter = 'all';
    function normalise(value) {
        return String(value || '').toLocaleLowerCase(document.documentElement.lang === 'de' ? 'de-DE' : 'en-GB');
    }
    function renderDestinations() {
        var term = normalise(search.value).trim();
        var visible = 0;
        cards.forEach(function (card) {
            var matchesSearch = !term || normalise(card.getAttribute('data-search')).indexOf(term) !== -1;
            var groups = ' ' + (card.getAttribute('data-groups') || '') + ' ';
            var matchesFilter = currentFilter === 'catalog'
                || (currentFilter === 'all' && card.getAttribute('data-featured') === '1')
                || (currentFilter !== 'all' && currentFilter !== 'catalog' && groups.indexOf(' ' + currentFilter + ' ') !== -1);
            if (term) matchesFilter = true;
            var show = matchesSearch && matchesFilter;
            card.classList.toggle('destination-hidden', !show);
            if (show) visible += 1;
        });
        if (empty) empty.hidden = visible !== 0;
    }
    filters.forEach(function (button) {
        button.addEventListener('click', function () {
            currentFilter = button.getAttribute('data-place-filter') || 'all';
            filters.forEach(function (candidate) { candidate.classList.toggle('active', candidate === button); });
            if (currentFilter !== 'all') search.value = '';
            renderDestinations();
        });
    });
    search.addEventListener('input', renderDestinations);
    renderDestinations();
})();
