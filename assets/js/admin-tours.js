(function () {
    'use strict';
    var data = window.TOUR_EDITOR_DATA || {};
    var destinations = Array.isArray(data.destinations) ? data.destinations : [];
    var items = Array.isArray(data.items) ? data.items : [];
    var stops = Array.isArray(data.stops) ? data.stops.map(normalizeStop) : [];
    var prices = Array.isArray(data.prices) ? data.prices.map(normalizePrice) : [];
    var stopList = document.querySelector('[data-stop-list]');
    var priceList = document.querySelector('[data-price-list]');
    var typeLabels = {sight:'Sehenswürdigkeit',activity:'Aktivität',shop:'Shop',service:'Zusatzleistung'};

    function normalizeStop(stop) {
        return {
            destination_id: String(stop.destination_id || ''),
            nights: Math.max(0, parseInt(stop.nights || 0, 10)),
            title_de: String(stop.title_de || ''),
            title_en: String(stop.title_en || ''),
            description_de: String(stop.description_de || ''),
            description_en: String(stop.description_en || ''),
            item_ids: Array.isArray(stop.item_ids) ? stop.item_ids.map(String) : []
        };
    }

    function normalizePrice(price) {
        return {
            label_de: String(price.label_de || ''), label_en: String(price.label_en || ''),
            valid_from: String(price.valid_from || ''), valid_to: String(price.valid_to || ''),
            min_travelers: Math.max(1, parseInt(price.min_travelers || 1, 10)),
            max_travelers: Math.max(1, parseInt(price.max_travelers || 20, 10)),
            price_per_person: String(price.price_per_person || 0), active: Number(price.active) === 1
        };
    }

    function field(tag, name, value, type) {
        var input = document.createElement(tag);
        input.name = name;
        if (tag === 'textarea') input.rows = 3;
        if (type) input.type = type;
        input.value = value == null ? '' : value;
        return input;
    }

    function label(text, input) {
        var wrapper = document.createElement('label');
        wrapper.appendChild(document.createTextNode(text));
        wrapper.appendChild(input);
        return wrapper;
    }

    function bindValue(input, object, key) {
        input.addEventListener('input', function () { object[key] = input.value; });
        input.addEventListener('change', function () { object[key] = input.value; });
    }

    function renderStops() {
        if (!stopList) return;
        stopList.innerHTML = '';
        stops.forEach(function (stop, index) {
            var card = document.createElement('article');
            card.className = 'tour-stop-editor';

            var head = document.createElement('div');
            head.className = 'tour-stop-head';
            var heading = document.createElement('div');
            var number = document.createElement('span');
            number.textContent = String(index + 1);
            var title = document.createElement('strong');
            var destination = destinations.find(function (entry) { return String(entry.id) === stop.destination_id; });
            title.textContent = destination ? destination.name_de : 'Neue Station';
            heading.appendChild(number); heading.appendChild(title);
            var controls = document.createElement('div');
            [['↑','up'],['↓','down'],['Entfernen','remove']].forEach(function (definition) {
                var button = document.createElement('button');
                button.type = 'button'; button.textContent = definition[0]; button.dataset.action = definition[1];
                button.addEventListener('click', function () {
                    if (definition[1] === 'remove') {
                        if (stops.length > 1) stops.splice(index, 1);
                    } else if (definition[1] === 'up' && index > 0) {
                        var previous = stops[index - 1]; stops[index - 1] = stops[index]; stops[index] = previous;
                    } else if (definition[1] === 'down' && index < stops.length - 1) {
                        var next = stops[index + 1]; stops[index + 1] = stops[index]; stops[index] = next;
                    }
                    renderStops();
                });
                controls.appendChild(button);
            });
            head.appendChild(heading); head.appendChild(controls); card.appendChild(head);

            var grid = document.createElement('div');
            grid.className = 'tour-stop-fields';
            var destinationSelect = document.createElement('select');
            destinationSelect.name = 'destination_id[]'; destinationSelect.required = true;
            var placeholder = document.createElement('option');
            placeholder.value = ''; placeholder.textContent = 'Bitte wählen'; destinationSelect.appendChild(placeholder);
            destinations.forEach(function (entry) {
                var option = document.createElement('option'); option.value = entry.id;
                option.textContent = entry.name_de + (Number(entry.active) === 1 ? '' : ' (inaktiv)');
                option.selected = String(entry.id) === stop.destination_id; destinationSelect.appendChild(option);
            });
            destinationSelect.addEventListener('change', function () {
                stop.destination_id = destinationSelect.value;
                stop.item_ids = [];
                renderStops();
            });
            grid.appendChild(label('Reiseziel', destinationSelect));

            var nightsInput = field('input','stop_nights[]',stop.nights,'number');
            nightsInput.min = '0'; nightsInput.max = '28'; nightsInput.required = true;
            nightsInput.addEventListener('input', function () { stop.nights = Math.max(0,parseInt(nightsInput.value || 0,10)); });
            grid.appendChild(label('Nächte', nightsInput));

            var titleDe = field('input','stop_title_de[]',stop.title_de); bindValue(titleDe,stop,'title_de');
            var titleEn = field('input','stop_title_en[]',stop.title_en); bindValue(titleEn,stop,'title_en');
            grid.appendChild(label('Etappentitel Deutsch (optional)', titleDe));
            grid.appendChild(label('Stage title English (optional)', titleEn));

            var descriptionDe = field('textarea','stop_description_de[]',stop.description_de); bindValue(descriptionDe,stop,'description_de');
            var descriptionEn = field('textarea','stop_description_en[]',stop.description_en); bindValue(descriptionEn,stop,'description_en');
            grid.appendChild(label('Tagesbeschreibung Deutsch', descriptionDe));
            grid.appendChild(label('Day description English', descriptionEn));

            var destinationItems = items.filter(function (item) { return String(item.destination_id) === stop.destination_id; });
            var hotelSelect = document.createElement('select'); hotelSelect.name = 'stop_items[' + index + '][]';
            var noHotel = document.createElement('option'); noHotel.value = ''; noHotel.textContent = 'Kein Hotel vorausgewählt'; hotelSelect.appendChild(noHotel);
            destinationItems.filter(function (item) { return item.type === 'accommodation'; }).forEach(function (item) {
                var option = document.createElement('option'); option.value = item.id;
                option.textContent = item.name_de + (Number(item.active) === 1 ? '' : ' (inaktiv)');
                option.selected = stop.item_ids.indexOf(String(item.id)) !== -1; hotelSelect.appendChild(option);
            });
            hotelSelect.addEventListener('change', function () {
                var accommodationIds = destinationItems.filter(function (item) { return item.type === 'accommodation'; }).map(function (item) { return String(item.id); });
                stop.item_ids = stop.item_ids.filter(function (itemId) { return accommodationIds.indexOf(itemId) === -1; });
                if (hotelSelect.value) stop.item_ids.unshift(hotelSelect.value);
            });
            grid.appendChild(label('Vorausgewähltes Hotel', hotelSelect));

            var extrasSelect = document.createElement('select'); extrasSelect.name = 'stop_items[' + index + '][]'; extrasSelect.multiple = true; extrasSelect.size = 6;
            destinationItems.filter(function (item) { return item.type !== 'accommodation'; }).forEach(function (item) {
                var option = document.createElement('option'); option.value = item.id;
                option.textContent = (typeLabels[item.type] || item.type) + ': ' + item.name_de + (Number(item.active) === 1 ? '' : ' (inaktiv)');
                option.selected = stop.item_ids.indexOf(String(item.id)) !== -1; extrasSelect.appendChild(option);
            });
            extrasSelect.addEventListener('change', function () {
                var extraIds = destinationItems.filter(function (item) { return item.type !== 'accommodation'; }).map(function (item) { return String(item.id); });
                stop.item_ids = stop.item_ids.filter(function (itemId) { return extraIds.indexOf(itemId) === -1; });
                Array.from(extrasSelect.selectedOptions).forEach(function (option) { stop.item_ids.push(option.value); });
            });
            var extrasLabel = label('Enthaltene Erlebnisse / Leistungen', extrasSelect); extrasLabel.className = 'tour-items-field';
            grid.appendChild(extrasLabel);
            card.appendChild(grid); stopList.appendChild(card);
        });
    }

    function renderPrices() {
        if (!priceList) return;
        priceList.innerHTML = '';
        prices.forEach(function (price, index) {
            var row = document.createElement('article'); row.className = 'tour-price-row';
            [['label_de','Bezeichnung Deutsch','text'],['label_en','Label English','text'],['valid_from','Von','date'],['valid_to','Bis','date'],['min_travelers','Min. Personen','number'],['max_travelers','Max. Personen','number'],['price_per_person','Preis / Person (€)','number']].forEach(function (definition) {
                var input = field('input','price_' + definition[0] + '[]',price[definition[0]],definition[2]);
                if (definition[2] === 'number') { input.min = definition[0] === 'price_per_person' ? '0' : '1'; input.step = definition[0] === 'price_per_person' ? '0.01' : '1'; }
                bindValue(input,price,definition[0]); row.appendChild(label(definition[1],input));
            });
            var activeLabel = document.createElement('label'); activeLabel.className = 'tour-price-active';
            var active = document.createElement('input'); active.type = 'checkbox'; active.name = 'price_active[' + index + ']'; active.value = '1'; active.checked = price.active;
            active.addEventListener('change',function(){price.active=active.checked;});
            activeLabel.appendChild(active); activeLabel.appendChild(document.createTextNode(' Aktiv')); row.appendChild(activeLabel);
            var remove = document.createElement('button'); remove.type = 'button'; remove.textContent = 'Entfernen';
            remove.addEventListener('click',function(){prices.splice(index,1);renderPrices();}); row.appendChild(remove);
            priceList.appendChild(row);
        });
    }

    var addStop = document.querySelector('[data-add-stop]');
    if (addStop) addStop.addEventListener('click',function(){stops.push(normalizeStop({nights:1}));renderStops();});
    var addPrice = document.querySelector('[data-add-price]');
    if (addPrice) addPrice.addEventListener('click',function(){prices.push(normalizePrice({label_de:'Saisonpreis',label_en:'Season price',min_travelers:1,max_travelers:20,active:1}));renderPrices();});
    renderStops(); renderPrices();
}());
