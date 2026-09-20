(function () {
    'use strict';
    var data = window.PLANNER_DATA;
    if (!data) return;

    var lang = data.language === 'en' ? 'en' : 'de';
    var routeStops = [];
    var stopSequence = 0;
    var selectedItems = new Set();
    var selectedMealPlans = {};
    var selectedRooms = {};
    var activeStopUid = '';
    var activeType = 'accommodation';
    var formatter = new Intl.NumberFormat(lang === 'de' ? 'de-DE' : 'en-GB', { style: 'currency', currency: 'EUR', minimumFractionDigits: 0, maximumFractionDigits: 2 });
    var rateFormatter = new Intl.NumberFormat(lang === 'de' ? 'de-DE' : 'en-GB', { style: 'currency', currency: 'EUR', minimumFractionDigits: 2, maximumFractionDigits: 2 });
    var numberFormatter = new Intl.NumberFormat(lang === 'de' ? 'de-DE' : 'en-GB', { maximumFractionDigits: 0 });
    var routeOptions = document.querySelector('[data-route-options]');
    var catalogOptions = document.querySelector('[data-catalog-options]');
    var routeCount = document.querySelector('[data-route-count]');
    var destinationSearch = document.querySelector('[data-destination-search]');
    var summaryRoute = document.querySelector('[data-summary-route]');
    var printRouteList = document.querySelector('[data-print-route-list]');
    var travelers = document.querySelector('[data-travelers]');
    var tripDuration = document.querySelector('[data-trip-duration]');
    var vehicleSelect = document.querySelector('[data-vehicle-select]');
    var vehicleServiceSelect = document.querySelector('[data-vehicle-service]');
    var vehicleCapacity = document.querySelector('[data-vehicle-capacity]');
    var vehiclePricing = document.querySelector('[data-vehicle-pricing]');
    var vehicleDistanceInput = document.querySelector('[data-vehicle-distance]');
    var printVehicle = document.querySelector('[data-print-vehicle]');
    var vehiclePreview = document.querySelector('[data-vehicle-preview]');
    var vehicleImage = document.querySelector('[data-vehicle-image]');
    var vehiclePreviewName = document.querySelector('[data-vehicle-preview-name]');
    var guideSelect = document.querySelector('[data-guide-select]');
    var guidePreview = document.querySelector('[data-guide-preview]');
    var guideImage = document.querySelector('[data-guide-image]');
    var guideName = document.querySelector('[data-guide-name]');
    var guideDetails = document.querySelector('[data-guide-details]');
    var guidePricing = document.querySelector('[data-guide-pricing]');
    var printGuideRow = document.querySelector('[data-print-guide-row]');
    var printGuide = document.querySelector('[data-print-guide]');
    var selectedVehicleId = 0;
    var selectedVehicleService = 'with_driver';
    var selectedGuideId = 0;
    var mapElement = document.querySelector('[data-google-route-map]');
    var mapStatus = document.querySelector('[data-map-status]');
    var distanceOutput = document.querySelector('[data-route-distance]');
    var durationOutput = document.querySelector('[data-route-duration]');
    var distanceInput = document.getElementById('route-distance-input');
    var durationInput = document.getElementById('route-duration-input');
    var routeMap = null;
    var mapsLibraries = null;
    var mapPolylines = [];
    var mapMarkers = [];
    var routeTimer = null;
    var renderedRouteSignature = '';
    var routeRequestVersion = 0;
    var mobileViewButtons = Array.from(document.querySelectorAll('[data-mobile-view]'));
    var mobileRouteCounts = Array.from(document.querySelectorAll('[data-mobile-route-count]'));
    var mobileEstimate = document.querySelector('[data-mobile-estimate]');
    var routeMapCard = document.querySelector('[data-route-map-card]');
    var mobileMapToggle = document.querySelector('[data-mobile-map-toggle]');

    function isMobilePlanner() {
        return window.matchMedia && window.matchMedia('(max-width: 760px)').matches;
    }
    function setMobileView(view) {
        var nextView = ['route', 'builder', 'summary'].indexOf(view) !== -1 ? view : 'builder';
        document.body.setAttribute('data-mobile-planner-view', nextView);
        mobileViewButtons.forEach(function (button) {
            button.classList.toggle('active', button.getAttribute('data-mobile-view') === nextView);
        });
        if (isMobilePlanner()) window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function findDestination(id) {
        return data.destinations.find(function (destination) { return destination.id === Number(id); });
    }
    function findItem(id) {
        return data.items.find(function (item) { return item.id === Number(id); });
    }
    function text(record, key) {
        return record[key + '_' + lang] || record[key + '_de'] || '';
    }
    function esc(value) {
        var div = document.createElement('div');
        div.textContent = String(value == null ? '' : value);
        return div.innerHTML;
    }
    function findStop(uid) {
        return routeStops.find(function (stop) { return stop.uid === String(uid); }) || null;
    }
    function activeStop() {
        return findStop(activeStopUid);
    }
    function selectionKey(stopUid, itemId) {
        return String(stopUid) + ':' + Number(itemId);
    }
    function selectionParts(key) {
        var separator = String(key).lastIndexOf(':');
        return {
            stopUid: String(key).slice(0, separator),
            itemId: Number(String(key).slice(separator + 1))
        };
    }
    function minimumRoomCount() {
        return Math.max(1, Math.ceil(Number(travelers.value || 1) / 2));
    }
    function normaliseRoomCounts() {
        var people = Math.max(1, Number(travelers.value || 1));
        var minimum = minimumRoomCount();
        Array.from(selectedItems).forEach(function (key) {
            var parts = selectionParts(key);
            var item = findItem(parts.itemId);
            if (!item || item.type !== 'accommodation') return;
            selectedRooms[key] = Math.max(minimum, Math.min(people, Number(selectedRooms[key] || minimum)));
        });
    }
    function roomCountText(count) {
        return count + ' ' + (Number(count) === 1 ? data.labels.room : data.labels.rooms);
    }
    function nightCountText(count) {
        return count + ' ' + (Number(count) === 1 ? data.labels.night : data.labels.nights);
    }
    function selectedServices(stopUid) {
        return Array.from(selectedItems).filter(function (key) {
            return selectionParts(key).stopUid === String(stopUid);
        }).map(function (key) {
            var parts = selectionParts(key);
            var item = findItem(parts.itemId);
            return item ? { item: item, key: key } : null;
        }).filter(Boolean);
    }
    function serviceTypeLabel(type) {
        var key = 'type' + type.charAt(0).toUpperCase() + type.slice(1);
        return data.labels[key] || type;
    }
    function servicePriceText(item) {
        if (item.price_basis === 'free') return data.labels.free;
        if (item.price_basis === 'on_request') return data.labels.onRequest;
        if (Number(item.price) <= 0) return data.labels.included;
        if (item.type === 'accommodation') return formatter.format(item.price) + ' / ' + data.labels.perRoomNight;
        if (item.price_basis === 'per_person_night') return formatter.format(item.price) + ' / ' + data.labels.perPersonNightService;
        if (item.price_basis === 'per_booking') return formatter.format(item.price) + ' / ' + data.labels.perBooking;
        return formatter.format(item.price) + ' / ' + data.labels.perPerson;
    }
    function itemClassificationText(item) {
        var parts = [];
        var classification = text(item, 'classification');
        if (classification) parts.push(classification);
        if (Number(item.star_rating) > 0) parts.push(Number(item.star_rating) + '★');
        if (item.market_segment) parts.push(String(item.market_segment).replace('_', '-'));
        if (item.booking_required) parts.push(data.labels.bookingRequired);
        return parts.join(' · ');
    }
    function serviceDetails(item, key) {
        var details = [];
        if (item.type === 'accommodation') {
            details.push(roomCountText(selectedRooms[key] || minimumRoomCount()));
            var mealPlan = (item.meal_plans || []).find(function (plan) { return plan.code === selectedMealPlans[key]; });
            if (mealPlan) details.push(text(mealPlan, 'name'));
        }
        details.push(servicePriceText(item));
        return details.join(' · ');
    }
    function printServicesMarkup(stopUid) {
        var services = selectedServices(stopUid);
        if (!services.length) return '';
        return '<ul class="print-services">' + services.map(function (selection) {
            return '<li class="print-service"><span>' + esc(serviceTypeLabel(selection.item.type)) + '</span><strong>' + esc(text(selection.item, 'name')) + '</strong><em>' + esc(serviceDetails(selection.item, selection.key)) + '</em></li>';
        }).join('') + '</ul>';
    }
    function vehicleCapacityText(capacity) {
        return data.labels.vehicleCapacity.replace('%d', String(capacity));
    }
    function vehicleDayCount() {
        if (!routeStops.length) return 0;
        return routeStops.reduce(function (total, stop) {
            return total + Number(stop.nights || 0);
        }, 0) + 1;
    }
    function currentVehicle() {
        return (data.vehicles || []).find(function (vehicle) { return Number(vehicle.id) === Number(selectedVehicleId); }) || null;
    }
    function vehiclePriceCalculation() {
        var vehicle = currentVehicle();
        var days = vehicleDayCount();
        var distance = Math.max(0, Number(distanceInput.value || 0));
        if (!vehicle || !days) return { days: days, dailyRate: 0, kmRate: 0, distance: distance, cost: 0 };
        var selfDrive = selectedVehicleService === 'self_drive' && vehicle.self_drive_allowed && Number(vehicle.capacity) <= 3;
        var dailyRate = selfDrive ? Number(vehicle.price_per_day_without_driver || 0) : Number(vehicle.price_per_day_with_driver || 0);
        var kmRate = selfDrive ? 0 : Number(vehicle.driver_price_per_km || 0);
        return {
            days: days,
            dailyRate: dailyRate,
            kmRate: kmRate,
            distance: distance,
            cost: (dailyRate * days) + (kmRate * distance)
        };
    }
    function vehiclePricingText(calculation) {
        var parts = [calculation.days + ' ' + data.labels.vehicleDays + ' × ' + rateFormatter.format(calculation.dailyRate) + ' ' + data.labels.perDay];
        if (selectedVehicleService === 'with_driver') {
            parts.push(numberFormatter.format(calculation.distance) + ' km × ' + rateFormatter.format(calculation.kmRate) + ' ' + data.labels.perKm);
        }
        return parts.join(' + ') + ' = ' + rateFormatter.format(calculation.cost);
    }
    function renderVehicle() {
        if (!vehicleSelect) return;
        var people = Math.max(1, Number(travelers.value || 1));
        var eligible = (data.vehicles || []).filter(function (vehicle) {
            return Number(vehicle.capacity) >= people;
        }).sort(function (a, b) {
            return Number(a.capacity) - Number(b.capacity);
        });
        var selectedStillEligible = eligible.some(function (vehicle) { return Number(vehicle.id) === Number(selectedVehicleId); });
        if (!selectedStillEligible) selectedVehicleId = eligible.length ? Number(eligible[0].id) : 0;
        vehicleSelect.innerHTML = eligible.map(function (vehicle) {
            return '<option value="' + vehicle.id + '"' + (Number(vehicle.id) === selectedVehicleId ? ' selected' : '') + '>' +
                esc(text(vehicle, 'name')) + ' · ' + esc(vehicleCapacityText(vehicle.capacity)) + '</option>';
        }).join('');
        vehicleSelect.disabled = eligible.length === 0;
        var selectedVehicle = eligible.find(function (vehicle) { return Number(vehicle.id) === selectedVehicleId; });
        if (!selectedVehicle) {
            vehicleCapacity.textContent = data.labels.noVehicle;
            vehicleServiceSelect.innerHTML = '';
            vehicleServiceSelect.disabled = true;
            vehiclePricing.textContent = '';
            if (printVehicle) printVehicle.textContent = data.labels.noVehicle;
            if (vehiclePreview) vehiclePreview.hidden = true;
            return;
        }
        var services = [{ value: 'with_driver', label: data.labels.withDriver }];
        if (selectedVehicle.self_drive_allowed && Number(selectedVehicle.capacity) <= 3) {
            services.push({ value: 'self_drive', label: data.labels.selfDrive });
        }
        if (!services.some(function (service) { return service.value === selectedVehicleService; })) selectedVehicleService = 'with_driver';
        vehicleServiceSelect.innerHTML = services.map(function (service) {
            return '<option value="' + service.value + '"' + (service.value === selectedVehicleService ? ' selected' : '') + '>' + esc(service.label) + '</option>';
        }).join('');
        vehicleServiceSelect.disabled = services.length === 1;
        vehicleCapacity.textContent = vehicleCapacityText(selectedVehicle.capacity);
        var calculation = vehiclePriceCalculation();
        var serviceLabel = selectedVehicleService === 'self_drive' ? data.labels.selfDrive : data.labels.withDriver;
        vehiclePricing.textContent = vehiclePricingText(calculation) + (selectedVehicleService === 'with_driver' && calculation.distance === 0 && calculation.kmRate > 0 ? ' · ' + data.labels.distancePending : '');
        if (printVehicle) printVehicle.textContent = text(selectedVehicle, 'name') + ' · ' + serviceLabel + ' · ' + vehiclePricingText(calculation);
        if (vehiclePreview) {
            vehiclePreview.hidden = !selectedVehicle.image;
            if (selectedVehicle.image) vehicleImage.src = selectedVehicle.image;
            vehiclePreviewName.textContent = text(selectedVehicle, 'name');
        }
    }
    function guidePriceCalculation() {
        var guide = (data.guides || []).find(function (item) { return Number(item.id) === Number(selectedGuideId); });
        var days = vehicleDayCount();
        return { guide: guide || null, days: days, cost: guide ? Number(guide.daily_rate || 0) * days : 0 };
    }
    function renderGuide() {
        if (!guideSelect) return;
        guideSelect.innerHTML = '<option value="0">' + esc(data.labels.noGuide) + '</option>' + (data.guides || []).map(function (guide) {
            return '<option value="' + guide.id + '"' + (Number(guide.id) === Number(selectedGuideId) ? ' selected' : '') + '>' + esc(guide.name) + ' · ' + rateFormatter.format(guide.daily_rate) + ' / ' + esc(data.labels.perDay) + '</option>';
        }).join('');
        var calculation = guidePriceCalculation();
        if (!calculation.guide) { if (guidePreview) guidePreview.hidden = true; if (printGuideRow) printGuideRow.hidden = true; return; }
        if (guidePreview) guidePreview.hidden = false;
        if (guideImage) { guideImage.hidden = !calculation.guide.image; if (calculation.guide.image) guideImage.src = calculation.guide.image; }
        if (guideName) guideName.textContent = calculation.guide.name;
        if (guideDetails) guideDetails.textContent = calculation.guide.languages + (calculation.guide.specializations ? ' · ' + calculation.guide.specializations : '');
        if (guidePricing) guidePricing.textContent = calculation.days + ' ' + data.labels.guideDays + ' × ' + rateFormatter.format(calculation.guide.daily_rate) + ' = ' + rateFormatter.format(calculation.cost);
        if (printGuideRow) printGuideRow.hidden = false;
        if (printGuide) printGuide.textContent = calculation.guide.name + ' · ' + calculation.guide.languages + ' · ' + calculation.days + ' ' + data.labels.guideDays + ' · ' + rateFormatter.format(calculation.cost);
    }
    function addDestination(id, nightsOverride) {
        if (routeStops.length >= 20) {
            window.alert(data.labels.maxStops);
            return null;
        }
        var destination = findDestination(id);
        if (!destination) return null;
        stopSequence += 1;
        var initialNights = Number.isFinite(Number(nightsOverride)) && nightsOverride !== null
            ? Number(nightsOverride)
            : Number(destination.nights || 0);
        var stop = {
            uid: 'stop-' + stopSequence,
            destination_id: Number(id),
            nights: Math.max(0, Math.min(14, initialNights))
        };
        routeStops.push(stop);
        return stop;
    }
    function removeDestination(stopUid) {
        if (!findStop(stopUid)) return;
        routeStops = routeStops.filter(function (stop) { return stop.uid !== stopUid; });
        Array.from(selectedItems).forEach(function (key) {
            if (selectionParts(key).stopUid !== stopUid) return;
            selectedItems.delete(key);
            delete selectedMealPlans[key];
            delete selectedRooms[key];
        });
        if (activeStopUid === stopUid) activeStopUid = routeStops.length ? routeStops[0].uid : '';
    }
    function moveDestination(stopUid, direction) {
        var from = routeStops.findIndex(function (stop) { return stop.uid === stopUid; });
        var to = from + direction;
        if (from < 0 || to < 0 || to >= routeStops.length) return;
        var swap = routeStops[to];
        routeStops[to] = routeStops[from];
        routeStops[from] = swap;
    }

    function renderRoutes() {
        var searchTerm = destinationSearch ? destinationSearch.value.trim().toLocaleLowerCase(lang === 'de' ? 'de-DE' : 'en-GB') : '';
        var selectedDestinationIds = new Set(routeStops.map(function (stop) { return stop.destination_id; }));
        var destinationStopCounts = routeStops.reduce(function (counts, stop) {
            counts[stop.destination_id] = (counts[stop.destination_id] || 0) + 1;
            return counts;
        }, {});
        var availableDestinations = data.destinations.filter(function (destination) {
            if (!searchTerm && selectedDestinationIds.has(destination.id)) return false;
            if (!searchTerm) return true;
            var haystack = [destination.code,text(destination,'name'),text(destination,'region')].join(' ').toLocaleLowerCase(lang === 'de' ? 'de-DE' : 'en-GB');
            return haystack.indexOf(searchTerm) !== -1;
        });
        var selectedMarkup = routeStops.map(function (stop, position) {
            var destination = findDestination(stop.destination_id);
            if (!destination) return '';
            var routeStatusClass = Number(stop.nights) === 0
                ? ' is-zero-nights'
                : (destinationStopCounts[stop.destination_id] > 1 ? ' is-repeat-destination' : ' is-selected-once');
            var controls = '<span class="route-move"><button type="button" data-move-up aria-label="' + esc(data.labels.moveUp) + '"' + (position === 0 ? ' disabled' : '') + '>↑</button><button type="button" data-move-down aria-label="' + esc(data.labels.moveDown) + '"' + (position === routeStops.length - 1 ? ' disabled' : '') + '>↓</button></span>';
            return '<div class="route-choice selected-stop' + routeStatusClass + (activeStopUid === stop.uid ? ' active' : '') + '" data-stop-row="' + esc(stop.uid) + '">' +
                '<button type="button" class="route-toggle" data-route-remove aria-label="' + esc(data.labels.removeStop) + '"><span class="route-number accent-' + esc(destination.accent) + '">' + (position + 1) + '</span></button>' +
                '<button type="button" class="route-name" data-route-activate><strong>' + esc(text(destination, 'name')) + '</strong><small>' + esc(nightCountText(stop.nights)) + ' · ' + esc(data.labels.from) + ' ' + esc(formatter.format(destination.price)) + '</small></button>' +
                controls + '<button type="button" class="route-open" data-route-activate>' + (activeStopUid === stop.uid ? '●' : '›') + '</button>' +
                '<button type="button" class="route-repeat" data-add-again>＋ ' + esc(data.labels.addAgainShort) + '</button></div>';
        }).join('');
        var availableMarkup = availableDestinations.map(function (destination) {
            var isRepeat = selectedDestinationIds.has(destination.id);
            return '<div class="route-choice" data-destination-row="' + destination.id + '">' +
                '<button type="button" class="route-toggle" data-route-add aria-label="' + esc(data.labels.addStop) + '"><span class="route-number accent-' + esc(destination.accent) + '">+</span></button>' +
                '<button type="button" class="route-name" data-route-add><strong>' + esc(text(destination, 'name')) + '</strong><small>' + (isRepeat ? esc(data.labels.alreadySelectedAddAgain) : esc(nightCountText(destination.nights)) + ' · ' + esc(data.labels.from) + ' ' + esc(formatter.format(destination.price))) + '</small></button>' +
                '<span class="route-move"></span><button type="button" class="route-open" data-route-add>›</button></div>';
        }).join('');
        routeOptions.innerHTML = selectedMarkup + availableMarkup;

        routeOptions.querySelectorAll('[data-stop-row]').forEach(function (row) {
            var stopUid = row.getAttribute('data-stop-row');
            var stop = findStop(stopUid);
            if (!stop) return;
            row.querySelector('[data-route-remove]').addEventListener('click', function () { removeDestination(stopUid); renderAll(); });
            row.querySelectorAll('[data-route-activate]').forEach(function (button) {
                button.addEventListener('click', function () { activeStopUid = stopUid; renderAll(); if (isMobilePlanner()) setMobileView('builder'); });
            });
            var addAgain = row.querySelector('[data-add-again]');
            if (addAgain) addAgain.addEventListener('click', function () {
                var duplicate = addDestination(stop.destination_id, 0);
                if (duplicate) activeStopUid = duplicate.uid;
                renderAll();
                if (duplicate && isMobilePlanner()) setMobileView('builder');
            });
            var moveUp = row.querySelector('[data-move-up]');
            var moveDown = row.querySelector('[data-move-down]');
            if (moveUp) moveUp.addEventListener('click', function () { moveDestination(stopUid, -1); renderAll(); });
            if (moveDown) moveDown.addEventListener('click', function () { moveDestination(stopUid, 1); renderAll(); });
        });
        routeOptions.querySelectorAll('[data-destination-row]').forEach(function (row) {
            var id = Number(row.getAttribute('data-destination-row'));
            row.querySelectorAll('[data-route-add]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var stop = addDestination(id);
                    if (stop) activeStopUid = stop.uid;
                    renderAll();
                    if (stop && isMobilePlanner()) setMobileView('builder');
                });
            });
        });
        routeCount.textContent = routeStops.length;
        mobileRouteCounts.forEach(function (counter) { counter.textContent = routeStops.length; });
    }

    function renderActive() {
        var stop = activeStop();
        var destination = stop ? findDestination(stop.destination_id) : null;
        var nightDown = document.querySelector('[data-night-down]');
        var nightUp = document.querySelector('[data-night-up]');
        if (!destination || !stop) {
            document.querySelector('[data-active-region]').textContent = '';
            document.querySelector('[data-active-name]').textContent = data.labels.selectDestination;
            document.querySelector('[data-active-nights]').textContent = '—';
            if (nightDown) nightDown.disabled = true;
            if (nightUp) nightUp.disabled = true;
            return;
        }
        if (nightDown) nightDown.disabled = false;
        if (nightUp) nightUp.disabled = false;
        document.querySelector('[data-active-region]').textContent = text(destination, 'region');
        document.querySelector('[data-active-name]').textContent = text(destination, 'name');
        document.querySelector('[data-active-nights]').textContent = nightCountText(stop.nights);
    }

    function renderOptions() {
        var stop = activeStop();
        if (!stop) {
            catalogOptions.innerHTML = '<div class="options-empty">' + esc(data.labels.selectDestinationHelp) + '</div>';
            return;
        }
        var options = data.items.filter(function (item) {
            return item.destination_id === stop.destination_id && item.type === activeType;
        });
        if (!options.length) {
            catalogOptions.innerHTML = '<div class="options-empty">' + esc(data.labels.empty) + '</div>';
            return;
        }
        catalogOptions.innerHTML = options.map(function (item) {
            var key = selectionKey(stop.uid, item.id);
            var isSelected = selectedItems.has(key);
            var priceUnit = item.type === 'accommodation' ? data.labels.perRoomNight : (item.price_basis === 'per_person_night' ? data.labels.perPersonNightService : (item.price_basis === 'per_booking' ? data.labels.perBooking : data.labels.person));
            var background = item.image ? ' style="background-image:linear-gradient(rgba(10,40,32,.15),rgba(10,40,32,.5)),url(' + esc(item.image) + ')"' : '';
            var classificationText = itemClassificationText(item);
            var optionCard = '<button class="catalog-option' + (isSelected ? ' selected' : '') + '" type="button" data-item="' + item.id + '">' +
                '<span class="option-visual accent-' + esc(item.accent) + '"' + background + '><span>' + esc(item.code) + '</span>' + (item.featured ? '<b>OUR PICK</b>' : '') + '</span>' +
                '<span class="option-info"><p>' + esc(text(item, 'description')) + '</p><h3>' + esc(text(item, 'name')) + '</h3>' + (classificationText ? '<span class="option-classification">' + esc(classificationText) + '</span>' : '') + '<small>' + esc(text(item, 'meta')) + '</small>' +
                '<span class="option-bottom"><strong>' + esc(servicePriceText(item)) + '</strong><span class="choose-chip">' + (isSelected ? '✓ ' + esc(data.labels.chosen) : '+ ' + esc(data.labels.choose)) + '</span></span></span></button>';
            if (!isSelected || item.type !== 'accommodation') return '<div class="catalog-item-wrap">' + optionCard + '</div>';

            var people = Math.max(1, Number(travelers.value || 1));
            var minimumRooms = minimumRoomCount();
            selectedRooms[key] = Math.max(minimumRooms, Math.min(people, Number(selectedRooms[key] || minimumRooms)));
            var roomOptions = '';
            for (var roomCount = minimumRooms; roomCount <= people; roomCount += 1) {
                roomOptions += '<option value="' + roomCount + '"' + (selectedRooms[key] === roomCount ? ' selected' : '') + '>' + esc(roomCountText(roomCount)) + '</option>';
            }
            var roomSelector = '<label>' + esc(data.labels.roomSelection) + '<select data-room-count data-selection-key="' + esc(key) + '">' + roomOptions + '</select><small>' + esc(data.labels.doubleRoomNotice) + '</small></label>';
            var mealSelector = '';
            if (item.meal_plans && item.meal_plans.length) {
                var selectedCode = selectedMealPlans[key] || item.meal_plans[0].code;
                selectedMealPlans[key] = selectedCode;
                var mealOptions = item.meal_plans.map(function (plan) {
                var priceLabel = plan.supplement > 0 ? '+ ' + formatter.format(plan.supplement) + ' ' + data.labels.perPersonNight : data.labels.includedMealPlan;
                return '<option value="' + esc(plan.code) + '"' + (selectedCode === plan.code ? ' selected' : '') + '>' + esc(text(plan, 'name')) + ' · ' + esc(priceLabel) + '</option>';
                }).join('');
                mealSelector = '<label>' + esc(data.labels.mealPlan) + '<select data-meal-plan data-selection-key="' + esc(key) + '">' + mealOptions + '</select></label>';
            }
            return '<div class="catalog-item-wrap selected">' + optionCard + '<div class="accommodation-controls">' + roomSelector + mealSelector + '</div></div>';
        }).join('');
        catalogOptions.querySelectorAll('[data-item]').forEach(function (button) {
            button.addEventListener('click', function () {
                var id = Number(button.getAttribute('data-item'));
                var item = findItem(id);
                var key = selectionKey(stop.uid, id);
                if (selectedItems.has(key)) selectedItems.delete(key);
                else {
                    if (item.type === 'accommodation') {
                        Array.from(selectedItems).forEach(function (candidateKey) {
                            var candidateParts = selectionParts(candidateKey);
                            var candidate = findItem(candidateParts.itemId);
                            if (candidateParts.stopUid !== stop.uid || !candidate || candidate.type !== 'accommodation') return;
                            selectedItems.delete(candidateKey);
                            delete selectedMealPlans[candidateKey];
                            delete selectedRooms[candidateKey];
                        });
                    }
                    selectedItems.add(key);
                    if (item.type === 'accommodation') selectedRooms[key] = minimumRoomCount();
                    if (item.type === 'accommodation' && item.meal_plans && item.meal_plans.length) selectedMealPlans[key] = item.meal_plans[0].code;
                }
                if (!selectedItems.has(key)) {
                    delete selectedMealPlans[key];
                    delete selectedRooms[key];
                }
                renderAll();
            });
        });
        catalogOptions.querySelectorAll('[data-room-count]').forEach(function (select) {
            select.addEventListener('change', function () {
                selectedRooms[select.getAttribute('data-selection-key')] = Number(select.value);
                renderSummary();
            });
        });
        catalogOptions.querySelectorAll('[data-meal-plan]').forEach(function (select) {
            select.addEventListener('change', function () {
                selectedMealPlans[select.getAttribute('data-selection-key')] = select.value;
                renderSummary();
            });
        });
    }

    function renderSummary() {
        var people = Number(travelers.value || 1);
        normaliseRoomCounts();
        renderVehicle();
        renderGuide();
        var estimate = 0;
        Array.from(selectedItems).forEach(function (key) {
            var parts = selectionParts(key);
            var item = findItem(parts.itemId);
            var stop = findStop(parts.stopUid);
            if (!item || !stop) return;
            var nights = Number(stop.nights);
            if (item.type === 'accommodation') estimate += item.price * (selectedRooms[key] || minimumRoomCount()) * nights;
            else if (item.price_basis === 'per_person_night') estimate += item.price * people * nights;
            else if (item.price_basis === 'per_booking') estimate += item.price;
            else estimate += item.price * people;
            if (item.type === 'accommodation' && selectedMealPlans[key]) {
                var mealPlan = (item.meal_plans || []).find(function (plan) { return plan.code === selectedMealPlans[key]; });
                if (mealPlan) estimate += mealPlan.supplement * people * nights;
            }
        });
        estimate += vehiclePriceCalculation().cost;
        estimate += guidePriceCalculation().cost;
        var formattedEstimate = formatter.format(estimate);
        document.querySelector('[data-estimate]').textContent = formattedEstimate;
        if (mobileEstimate) mobileEstimate.textContent = formattedEstimate;
        summaryRoute.innerHTML = routeStops.map(function (stop) {
            var destination = findDestination(stop.destination_id);
            var serviceSummary = selectedServices(stop.uid).map(function (selection) {
                return '<em>' + esc(serviceTypeLabel(selection.item.type)) + ': ' + esc(text(selection.item, 'name')) + '</em>';
            }).join('');
            return '<li><span>' + esc(text(destination, 'name')) + serviceSummary + '</span><small>' + esc(nightCountText(stop.nights)) + '</small></li>';
        }).join('');
        if (printRouteList) printRouteList.innerHTML = routeStops.map(function (stop, index) {
            var destination = findDestination(stop.destination_id);
            return '<li class="print-route-stop"><span>' + (index + 1) + '</span><div><small>' + esc(text(destination, 'region')) + '</small><strong>' + esc(text(destination, 'name')) + '</strong>' + printServicesMarkup(stop.uid) + '</div><b>' + esc(nightCountText(stop.nights)) + '</b></li>';
        }).join('');
        document.getElementById('destinations-json').value = JSON.stringify(routeStops.map(function (stop, index) { return { id: stop.destination_id, stop_uid: stop.uid, nights: stop.nights, order: index }; }));
        document.getElementById('items-json').value = JSON.stringify(Array.from(selectedItems).map(function (key) {
            var parts = selectionParts(key);
            return { id: parts.itemId, stop_uid: parts.stopUid, meal_plan_code: selectedMealPlans[key] || '', rooms: selectedRooms[key] || 0 };
        }));
        document.getElementById('estimate-input').value = String(Math.round(estimate));
        if (tripDuration) {
            var totalNights = routeStops.reduce(function (total, stop) { return total + Number(stop.nights || 0); }, 0);
            tripDuration.value = String(totalNights);
        }
    }

    function setMapStatus(message, error) {
        if (!mapStatus) return;
        mapStatus.textContent = message || '';
        mapStatus.classList.toggle('visible', Boolean(message));
        mapStatus.classList.toggle('error', Boolean(error));
    }
    function setRouteMetrics(distanceMetres, durationMillis) {
        var kilometres = Math.max(0, Math.round((Number(distanceMetres) || 0) / 1000));
        var minutes = Math.max(0, Math.round((Number(durationMillis) || 0) / 60000));
        distanceInput.value = String(kilometres);
        if (vehicleDistanceInput) vehicleDistanceInput.value = String(kilometres);
        durationInput.value = String(minutes);
        distanceOutput.textContent = kilometres ? numberFormatter.format(kilometres) + ' km' : '—';
        if (!minutes) durationOutput.textContent = '—';
        else {
            var hours = Math.floor(minutes / 60);
            var remainder = minutes % 60;
            durationOutput.textContent = (hours ? hours + ' ' + data.labels.hours + ' ' : '') + (remainder ? remainder + ' ' + data.labels.minutes : '');
        }
        renderSummary();
    }
    function clearMapObjects() {
        mapPolylines.forEach(function (polyline) { polyline.setMap(null); });
        mapMarkers.forEach(function (marker) { marker.map = null; });
        mapPolylines = [];
        mapMarkers = [];
    }
    function routePoints() {
        return routeStops.map(function (stop) { return findDestination(stop.destination_id); }).filter(Boolean);
    }
    function coordinates(destination) {
        return { lat: Number(destination.latitude), lng: Number(destination.longitude) };
    }
    function scheduleRouteMap() {
        window.clearTimeout(routeTimer);
        routeTimer = window.setTimeout(renderRouteMap, 350);
    }
    async function renderRouteMap() {
        if (!routeMap || !mapsLibraries) return;
        var places = routePoints();
        var signature = places.map(function (place) { return place.id + ':' + place.latitude + ':' + place.longitude; }).join('|');
        if (signature === renderedRouteSignature) return;
        renderedRouteSignature = signature;
        routeRequestVersion += 1;
        var requestVersion = routeRequestVersion;
        clearMapObjects();
        setRouteMetrics(0, 0);

        if (!places.length) {
            setMapStatus(data.labels.mapHelp, false);
            return;
        }
        if (places.some(function (place) { return place.latitude === null || place.longitude === null || !Number.isFinite(Number(place.latitude)) || !Number.isFinite(Number(place.longitude)); })) {
            setMapStatus(data.labels.mapMissingCoordinates, true);
            return;
        }
        if (places.length === 1) {
            var pin = new mapsLibraries.PinElement({ glyph: '1', background: '#15382e', borderColor: '#ffffff', glyphColor: '#ffffff' });
            mapMarkers = [new mapsLibraries.AdvancedMarkerElement({ map: routeMap, position: coordinates(places[0]), title: text(places[0], 'name'), content: pin.element })];
            routeMap.setCenter(coordinates(places[0]));
            routeMap.setZoom(9);
            setMapStatus('', false);
            return;
        }

        setMapStatus(data.labels.mapLoading, false);
        try {
            var request = {
                origin: coordinates(places[0]),
                destination: coordinates(places[places.length - 1]),
                intermediates: places.slice(1, -1).map(function (place) { return { location: coordinates(place) }; }),
                travelMode: 'DRIVING',
                region: 'lk',
                fields: ['path', 'legs', 'distanceMeters', 'durationMillis']
            };
            var result = await mapsLibraries.Route.computeRoutes(request);
            if (requestVersion !== routeRequestVersion) return;
            if (!result.routes || !result.routes.length) throw new Error('No route returned');
            var route = result.routes[0];
            mapPolylines = route.createPolylines();
            mapPolylines.forEach(function (polyline) {
                polyline.setOptions({ strokeColor: '#b77b17', strokeOpacity: 0.92, strokeWeight: 5 });
                polyline.setMap(routeMap);
            });
            mapMarkers = await route.createWaypointAdvancedMarkers({ map: routeMap });
            var bounds = new mapsLibraries.LatLngBounds();
            route.path.forEach(function (point) { bounds.extend(point); });
            routeMap.fitBounds(bounds, 42);
            setRouteMetrics(route.distanceMeters, route.durationMillis);
            setMapStatus('', false);
        } catch (error) {
            if (requestVersion !== routeRequestVersion) return;
            console.error('Google Maps route error:', error);
            renderedRouteSignature = '';
            setMapStatus(data.labels.mapError, true);
        }
    }
    async function initialiseGoogleMap() {
        try {
            var libraries = await Promise.all([
                google.maps.importLibrary('maps'),
                google.maps.importLibrary('marker'),
                google.maps.importLibrary('routes'),
                google.maps.importLibrary('core')
            ]);
            mapsLibraries = {
                Map: libraries[0].Map,
                AdvancedMarkerElement: libraries[1].AdvancedMarkerElement,
                PinElement: libraries[1].PinElement,
                Route: libraries[2].Route,
                LatLngBounds: libraries[3].LatLngBounds
            };
            routeMap = new mapsLibraries.Map(mapElement, {
                center: { lat: 7.65, lng: 80.70 },
                zoom: 7,
                mapId: data.maps.mapId || 'DEMO_MAP_ID',
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: true
            });
            renderedRouteSignature = '';
            await renderRouteMap();
        } catch (error) {
            console.error('Google Maps load error:', error);
            setMapStatus(data.labels.mapError, true);
        }
    }
    function loadGoogleMaps() {
        if (!mapElement) return;
        if (!data.maps || !data.maps.apiKey) {
            setMapStatus(data.labels.mapMissingKey, true);
            return;
        }
        window.__ceylonGoogleMapsReady = function () { initialiseGoogleMap(); };
        var query = new URLSearchParams({
            key: data.maps.apiKey,
            v: 'weekly',
            loading: 'async',
            callback: '__ceylonGoogleMapsReady',
            language: lang,
            region: 'LK'
        });
        var script = document.createElement('script');
        script.src = 'https://maps.googleapis.com/maps/api/js?' + query.toString();
        script.async = true;
        script.onerror = function () { setMapStatus(data.labels.mapError, true); };
        document.head.appendChild(script);
    }

    function renderAll() {
        renderRoutes();
        renderActive();
        renderOptions();
        renderSummary();
        scheduleRouteMap();
    }

    document.querySelectorAll('[data-type]').forEach(function (button) {
        button.addEventListener('click', function () {
            activeType = button.getAttribute('data-type');
            document.querySelectorAll('[data-type]').forEach(function (tab) { tab.classList.toggle('active', tab === button); });
            renderOptions();
        });
    });
    mobileViewButtons.forEach(function (button) {
        button.addEventListener('click', function () { setMobileView(button.getAttribute('data-mobile-view')); });
    });
    document.querySelectorAll('[data-mobile-close]').forEach(function (button) {
        button.addEventListener('click', function () { setMobileView(routeStops.length ? 'builder' : 'route'); });
    });
    function updateMobileMapToggle() {
        if (!routeMapCard || !mobileMapToggle) return;
        var collapsed = routeMapCard.classList.contains('mobile-collapsed');
        mobileMapToggle.textContent = collapsed ? mobileMapToggle.getAttribute('data-show-label') : mobileMapToggle.getAttribute('data-hide-label');
        mobileMapToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }
    if (routeMapCard && mobileMapToggle) {
        if (isMobilePlanner()) routeMapCard.classList.add('mobile-collapsed');
        updateMobileMapToggle();
        mobileMapToggle.addEventListener('click', function () {
            routeMapCard.classList.toggle('mobile-collapsed');
            updateMobileMapToggle();
            if (!routeMapCard.classList.contains('mobile-collapsed')) scheduleRouteMap();
        });
        window.addEventListener('resize', function () {
            if (!isMobilePlanner()) routeMapCard.classList.remove('mobile-collapsed');
            updateMobileMapToggle();
        });
    }
    travelers.addEventListener('change', function () { normaliseRoomCounts(); renderAll(); });
    if (vehicleSelect) vehicleSelect.addEventListener('change', function () {
        selectedVehicleId = Number(vehicleSelect.value || 0);
        renderSummary();
    });
    if (vehicleServiceSelect) vehicleServiceSelect.addEventListener('change', function () {
        selectedVehicleService = vehicleServiceSelect.value === 'self_drive' ? 'self_drive' : 'with_driver';
        renderSummary();
    });
    if (vehicleDistanceInput) vehicleDistanceInput.addEventListener('input', function () {
        var kilometres = Math.max(0, Math.min(5000, Number(vehicleDistanceInput.value || 0)));
        distanceInput.value = String(kilometres);
        distanceOutput.textContent = kilometres ? numberFormatter.format(kilometres) + ' km' : '—';
        renderSummary();
    });
    if (guideSelect) guideSelect.addEventListener('change', function () { selectedGuideId = Number(guideSelect.value || 0); renderSummary(); });
    if (destinationSearch) destinationSearch.addEventListener('input', renderRoutes);
    document.querySelector('[data-night-down]').addEventListener('click', function () {
        var stop = activeStop();
        if (!stop) return;
        stop.nights = Math.max(0, Number(stop.nights) - 1);
        renderAll();
    });
    document.querySelector('[data-night-up]').addEventListener('click', function () {
        var stop = activeStop();
        if (!stop) return;
        stop.nights = Math.min(14, Number(stop.nights) + 1);
        renderAll();
    });
    var printButton = document.querySelector('[data-print-route]');
    if (printButton) printButton.addEventListener('click', function () { window.print(); });
    if (data.initialTour && Array.isArray(data.initialTour.stops) && data.initialTour.stops.length) {
        data.initialTour.stops.forEach(function (templateStop) {
            var stop = addDestination(Number(templateStop.destination_id), Number(templateStop.nights));
            if (!stop) return;
            if (!activeStopUid) activeStopUid = stop.uid;
            var accommodationSelected = false;
            (templateStop.item_ids || []).forEach(function (itemId) {
                var item = findItem(Number(itemId));
                if (!item || Number(item.destination_id) !== Number(stop.destination_id)) return;
                if (item.type === 'accommodation' && accommodationSelected) return;
                var key = selectionKey(stop.uid, item.id);
                selectedItems.add(key);
                if (item.type === 'accommodation') {
                    accommodationSelected = true;
                    selectedRooms[key] = minimumRoomCount();
                    if (item.meal_plans && item.meal_plans.length) selectedMealPlans[key] = item.meal_plans[0].code;
                }
            });
        });
    } else if (data.initialDestination) {
        var initialStop = addDestination(data.initialDestination);
        if (initialStop) activeStopUid = initialStop.uid;
    }
    setMobileView(routeStops.length ? 'builder' : 'route');
    renderAll();
    loadGoogleMaps();
})();
