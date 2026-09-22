(function () {
    'use strict';
    var data = window.STAY_DATA;
    if (!data) return;
    var lang = data.language === 'en' ? 'en' : 'de';
    var formatter = new Intl.NumberFormat(lang === 'de' ? 'de-DE' : 'en-GB', { style: 'currency', currency: 'EUR', minimumFractionDigits: 0, maximumFractionDigits: 2 });
    var selectedHotelId = Number(data.initialHotelId || 0);
    var selectedRoomTypeId = 0;
    var selectedMealCode = '';
    var selectedServices = new Set();
    var activeServiceType = 'service';
    var hotelGrid = document.querySelector('[data-hotel-grid]');
    var serviceGrid = document.querySelector('[data-service-grid]');
    var destinationFilter = document.querySelector('[data-destination-filter]');
    var adultsInput = document.querySelector('[data-adults]');
    var childrenInput = document.querySelector('[data-children]');
    var roomsInput = document.querySelector('[data-rooms]');
    var nightsInput = document.querySelector('[data-nights]');
    var startDateInput = document.querySelector('input[name="start_date"]');
    var roomSection = document.querySelector('[data-room-section]');
    var roomTypeInput = document.querySelector('[data-room-type]');
    var roomTypeHidden = document.querySelector('[data-room-type-input]');
    var roomNote = document.querySelector('[data-room-note]');
    var mealSection = document.querySelector('[data-meal-section]');
    var mealInput = document.querySelector('[data-meal-plan]');
    var mealNote = document.querySelector('[data-meal-note]');
    var occupancyMessage = document.querySelector('[data-occupancy-message]');
    var submitButton = document.querySelector('[data-submit-stay]');

    function esc(value) {
        var div = document.createElement('div');
        div.textContent = String(value == null ? '' : value);
        return div.innerHTML;
    }
    function text(record, key) {
        return record[key + '_' + lang] || record[key + '_de'] || '';
    }
    function hotel() {
        return data.hotels.find(function (item) { return Number(item.id) === Number(selectedHotelId); }) || null;
    }
    function roomType() {
        var selectedHotel=hotel();if(!selectedHotel)return null;
        var types=selectedHotel.room_types||[];var room=types.find(function(item){return Number(item.id)===Number(selectedRoomTypeId);})||types[0]||null;
        selectedRoomTypeId=room?Number(room.id):0;return room;
    }
    function selectedRoomRate(room,nights){
        if(!room)return hotel()?Number(hotel().rate):0;var date=startDateInput?startDateInput.value:'';
        var seasonal=(room.rates||[]).find(function(rate){return date&&(!rate.from||date>=rate.from)&&(!rate.to||date<=rate.to)&&Number(nights||0)>=Number(rate.minimum_nights||1);});
        return Number(seasonal?seasonal.rate:room.rate||0);
    }
    function people() {
        return {
            adults: Math.max(1, Number(adultsInput.value || 1)),
            children: Math.max(0, Number(childrenInput.value || 0)),
            rooms: Math.max(1, Number(roomsInput.value || 1)),
            nights: Math.max(1, Number(nightsInput.value || 1))
        };
    }
    function nightText(count) {
        return count + ' ' + (Number(count) === 1 ? data.labels.night : data.labels.nights);
    }
    function priceBasisLabel(basis) {
        if (basis === 'per_person_night') return data.labels.perPersonNight;
        if (basis === 'per_booking') return data.labels.perBooking;
        if (basis === 'free') return data.labels.free;
        if (basis === 'on_request') return data.labels.onRequest;
        return data.labels.perPerson;
    }
    function renderNights() {
        var current = Number(nightsInput.value || (data.type === 'ayurveda' ? 7 : 7));
        var selectedHotel = hotel();
        var options;
        if (data.type === 'ayurveda') {
            options = selectedHotel && selectedHotel.night_options.length ? selectedHotel.night_options : [7, 14, 21, 28];
        } else {
            options = [];
            for (var i = 1; i <= 60; i += 1) options.push(i);
        }
        if (options.indexOf(current) === -1) current = options[0];
        nightsInput.innerHTML = options.map(function (night) {
            return '<option value="' + night + '"' + (night === current ? ' selected' : '') + '>' + esc(nightText(night)) + '</option>';
        }).join('');
    }
    function renderHotels() {
        var destinationId = Number(destinationFilter.value || 0);
        var hotels = data.hotels.filter(function (item) { return !destinationId || Number(item.destination_id) === destinationId; });
        if (!hotels.length) {
            hotelGrid.innerHTML = '<div class="stay-empty">' + esc(data.labels.emptyHotels) + '</div>';
            document.querySelector('[data-pricing-rule]').textContent = '';
            return;
        }
        hotelGrid.innerHTML = hotels.map(function (item) {
            var chosen = Number(item.id) === Number(selectedHotelId);
            var image = item.image ? ' style="background-image:linear-gradient(rgba(12,45,37,.1),rgba(12,45,37,.55)),url(' + esc(item.image) + ')"' : '';
            var classification = text(item, 'classification');
            var stars = Number(item.star_rating || 0) > 0 ? ' · ' + '★'.repeat(Number(item.star_rating)) : '';
            var segment = item.market_segment ? ' · ' + String(item.market_segment).replace('_', ' ') : '';
            return '<article class="stay-hotel' + (chosen ? ' selected' : '') + '">' +
                '<div class="stay-hotel-image accent-' + esc(item.accent) + '"' + image + '><span>' + esc(item.code) + '</span>' + (item.featured ? '<b>OUR PICK</b>' : '') + '</div>' +
                '<div><p>' + esc(text(item, 'destination_name')) + ' · ' + esc(text(item, 'region')) + '</p><h3>' + esc(text(item, 'name')) + '</h3>' + (classification ? '<span class="stay-hotel-classification">' + esc(classification + stars + segment) + '</span>' : '') + '<small>' + esc(text(item, 'meta')) + '</small>' +
                '<div class="stay-hotel-price"><strong>' + esc(formatter.format(item.rate)) + '</strong><span>' + esc(data.labels.perRoomNight) + '</span></div>' +
                '<button type="button" data-select-hotel="' + item.id + '">' + (chosen ? '✓ ' + esc(data.labels.chosen) : '+ ' + esc(data.labels.chooseHotel)) + '</button></div></article>';
        }).join('');
        hotelGrid.querySelectorAll('[data-select-hotel]').forEach(function (button) {
            button.addEventListener('click', function () {
                var nextHotelId = Number(button.getAttribute('data-select-hotel'));
                if (nextHotelId !== selectedHotelId) selectedServices.clear();
                selectedHotelId = nextHotelId;
                selectedRoomTypeId = 0;
                selectedMealCode = '';
                renderNights();
                normaliseRooms();
                renderAll();
            });
        });
        var selectedHotel = hotel();
        var selectedRoom = roomType();
        var rule = document.querySelector('[data-pricing-rule]');
        rule.textContent = selectedHotel ? data.labels.pricingRule
            .replace('%g', String(selectedRoom?selectedRoom.standard_guests:selectedHotel.standard_guests))
            .replace('%e', String(selectedRoom?selectedRoom.extra_bed_percent:selectedHotel.extra_bed_percent))
            .replace('%a', String(selectedHotel.child_max_age))
            .replace('%c', String(selectedRoom?selectedRoom.child_percent:selectedHotel.child_percent)) : '';
    }
    function renderRoomTypes(){
        var selectedHotel=hotel();if(!selectedHotel){roomSection.hidden=true;selectedRoomTypeId=0;roomTypeHidden.value='';return;}
        var types=selectedHotel.room_types||[];roomSection.hidden=!types.length;if(!types.length){selectedRoomTypeId=0;roomTypeHidden.value='';return;}
        var selectedRoom=roomType();roomTypeInput.innerHTML=types.map(function(room){return '<option value="'+room.id+'"'+(Number(room.id)===Number(selectedRoomTypeId)?' selected':'')+'>'+esc(text(room,'name'))+' · '+esc(formatter.format(selectedRoomRate(room,Number(nightsInput.value||1))))+' '+esc(data.labels.perRoomNight)+'</option>';}).join('');
        roomTypeHidden.value=String(selectedRoomTypeId);roomNote.textContent=[text(selectedRoom,'bed_type'),text(selectedRoom,'amenities')].filter(Boolean).join(' · ');
    }
    function normaliseRooms() {
        var selectedHotel = hotel();
        var selectedRoom = roomType();
        var occupancy = people();
        var maximum = selectedRoom ? Math.max(Number(selectedRoom.standard_guests || 2), Number(selectedRoom.maximum_guests || 3)) : (selectedHotel ? Math.max(Number(selectedHotel.standard_guests || 2), Number(selectedHotel.maximum_guests || 3)) : 3);
        var minimumRooms = Math.max(1, Math.ceil((occupancy.adults + occupancy.children) / maximum));
        if (Number(roomsInput.value) < minimumRooms) roomsInput.value = String(minimumRooms);
    }
    function renderMealPlans() {
        var selectedHotel = hotel();
        if (!selectedHotel) {
            mealSection.hidden = true;
            selectedMealCode = '';
            return;
        }
        mealSection.hidden = false;
        var plans = selectedHotel.meal_plans || [];
        if (data.type === 'ayurveda') plans = plans.filter(function (plan) { return plan.code === 'all_inclusive'; });
        if (!plans.length) {
            mealInput.innerHTML = '<option value="">' + esc(data.type === 'ayurveda' ? data.labels.allInclusiveRequired : data.labels.included) + '</option>';
            selectedMealCode = '';
            mealInput.disabled = true;
            mealNote.textContent = data.type === 'ayurveda' ? data.labels.allInclusiveRequired : '';
            return;
        }
        mealInput.disabled = false;
        if (!plans.some(function (plan) { return plan.code === selectedMealCode; })) selectedMealCode = plans[0].code;
        mealInput.innerHTML = plans.map(function (plan) {
            var price = Number(plan.supplement) > 0 ? '+ ' + formatter.format(plan.supplement) + ' · ' + data.labels.perPersonNight : data.labels.included;
            return '<option value="' + esc(plan.code) + '"' + (plan.code === selectedMealCode ? ' selected' : '') + '>' + esc(text(plan, 'name')) + ' · ' + esc(price) + '</option>';
        }).join('');
        mealInput.value = selectedMealCode;
        mealNote.textContent = data.type === 'ayurveda' ? data.labels.allInclusiveRequired : '';
    }
    function servicePrice(service) {
        if (service.price_basis === 'free') return data.labels.free;
        if (service.price_basis === 'on_request') return data.labels.onRequest;
        if (Number(service.price) <= 0) return data.labels.included;
        return formatter.format(service.price) + ' · ' + priceBasisLabel(service.price_basis);
    }
    function renderServices() {
        var selectedHotel = hotel();
        if (!selectedHotel) {
            serviceGrid.innerHTML = '<div class="stay-empty">' + esc(data.labels.selectHotelFirst) + '</div>';
            return;
        }
        var services = data.services.filter(function (service) {
            var related=selectedHotel.related_items||[];return Number(service.destination_id) === Number(selectedHotel.destination_id) && service.type === activeServiceType && (!related.length||related.indexOf(Number(service.id))!==-1);
        });
        if (!services.length) {
            serviceGrid.innerHTML = '<div class="stay-empty">' + esc(data.labels.noServices) + '</div>';
            return;
        }
        serviceGrid.innerHTML = services.map(function (service) {
            var chosen = selectedServices.has(service.id);
            return '<button type="button" class="stay-service' + (chosen ? ' selected' : '') + '" data-service="' + service.id + '"><span>' + (chosen ? '✓' : '+') + '</span><div><small>' + esc(text(service, 'classification') || service.type) + '</small><strong>' + esc(text(service, 'name')) + '</strong><p>' + esc(text(service, 'description')) + '</p><em>' + esc(servicePrice(service)) + '</em></div></button>';
        }).join('');
        serviceGrid.querySelectorAll('[data-service]').forEach(function (button) {
            button.addEventListener('click', function () {
                var id = Number(button.getAttribute('data-service'));
                if (selectedServices.has(id)) selectedServices.delete(id); else selectedServices.add(id);
                renderServices();
                renderSummary();
            });
        });
    }
    function calculate() {
        var selectedHotel = hotel();
        var selectedRoom = roomType();
        var occupancy = people();
        if (!selectedHotel) return { valid: false, room: 0, extra: 0, meal: 0, services: 0, total: 0, extraAdults: 0, extraChildren: 0 };
        var standardGuests = Math.max(1, Number(selectedRoom?selectedRoom.standard_guests:(selectedHotel.standard_guests || 2)));
        var maximumGuests = Math.max(standardGuests, Number(selectedRoom?selectedRoom.maximum_guests:(selectedHotel.maximum_guests || 3)));
        var standardCapacity = occupancy.rooms * standardGuests;
        var maximumCapacity = occupancy.rooms * maximumGuests;
        var includedAdults = Math.min(occupancy.adults, standardCapacity);
        var includedChildren = Math.min(occupancy.children, Math.max(0, standardCapacity - includedAdults));
        var extraAdults = Math.max(0, occupancy.adults - includedAdults);
        var extraChildren = Math.max(0, occupancy.children - includedChildren);
        var extraFactor = Math.max(0, Number(selectedRoom?selectedRoom.extra_bed_percent:(selectedHotel.extra_bed_percent || 0))) / 100;
        var childFactor = Math.max(0, Number(selectedRoom?selectedRoom.child_percent:(selectedHotel.child_percent || 0))) / 100;
        var currentRoomRate=selectedRoomRate(selectedRoom,occupancy.nights);
        var roomCost = currentRoomRate * occupancy.rooms * occupancy.nights;
        var extraCost = (currentRoomRate * extraFactor * extraAdults * occupancy.nights) +
            (currentRoomRate * extraFactor * childFactor * extraChildren * occupancy.nights);
        var plan = (selectedHotel.meal_plans || []).find(function (mealPlan) { return mealPlan.code === selectedMealCode; });
        var weightedPeople = occupancy.adults + (occupancy.children * childFactor);
        var mealCost = plan ? Number(plan.supplement) * weightedPeople * occupancy.nights : 0;
        var servicesCost = 0;
        selectedServices.forEach(function (id) {
            var service = data.services.find(function (item) { return item.id === id && Number(item.destination_id) === Number(selectedHotel.destination_id); });
            if (!service) return;
            if (service.price_basis === 'per_booking') servicesCost += Number(service.price);
            else if (service.price_basis === 'per_person_night') servicesCost += Number(service.price) * weightedPeople * occupancy.nights;
            else servicesCost += Number(service.price) * weightedPeople;
        });
        return {
            valid: occupancy.adults + occupancy.children <= maximumCapacity && (data.type !== 'ayurveda' || Boolean(plan)),
            room: roomCost, extra: extraCost, meal: mealCost, services: servicesCost,
            total: roomCost + extraCost + mealCost + servicesCost,
            extraAdults: extraAdults, extraChildren: extraChildren,
            maximumCapacity: maximumCapacity, occupancy: occupancy, plan: plan, roomType:selectedRoom
        };
    }
    function renderSummary() {
        var selectedHotel = hotel();
        var result = calculate();
        document.querySelector('[data-room-total]').textContent = formatter.format(result.room);
        document.querySelector('[data-extra-total]').textContent = formatter.format(result.extra);
        document.querySelector('[data-meal-total]').textContent = formatter.format(result.meal);
        document.querySelector('[data-services-total]').textContent = formatter.format(result.services);
        document.querySelector('[data-total]').textContent = formatter.format(result.total);
        document.querySelector('[data-estimate-input]').value = result.total.toFixed(2);
        document.querySelector('[data-hotel-input]').value = selectedHotel ? String(selectedHotel.id) : '';
        roomTypeHidden.value=result.roomType?String(result.roomType.id):'';
        document.querySelector('[data-services-input]').value = JSON.stringify(Array.from(selectedServices));
        occupancyMessage.textContent = selectedHotel && !result.valid && result.occupancy.adults + result.occupancy.children > result.maximumCapacity ? data.labels.occupancyError : '';
        occupancyMessage.classList.toggle('visible', Boolean(occupancyMessage.textContent));
        submitButton.disabled = !selectedHotel || !result.valid;
        var selectedStay = document.querySelector('[data-selected-stay]');
        if (!selectedHotel) {
            selectedStay.innerHTML = '<p>' + esc(data.labels.selectHotelFirst) + '</p>';
            return;
        }
        var extras = [];
        if (result.extraAdults) extras.push(result.extraAdults + ' × ' + data.labels.adults);
        if (result.extraChildren) extras.push(result.extraChildren + ' × ' + data.labels.children);
        selectedStay.innerHTML = '<small>' + esc(text(selectedHotel, 'destination_name')) + '</small><strong>' + esc(text(selectedHotel, 'name')) + '</strong><span>' +
            esc((result.roomType?text(result.roomType,'name')+' · ':'')+result.occupancy.rooms + ' ' + data.labels.rooms + ' · ' + nightText(result.occupancy.nights)) + '</span>' +
            (extras.length ? '<em>' + esc(extras.join(' · ')) + '</em>' : '');
    }
    function renderAll() {
        renderHotels();
        renderRoomTypes();
        renderMealPlans();
        renderServices();
        renderSummary();
    }

    document.querySelectorAll('[data-service-type]').forEach(function (button) {
        button.addEventListener('click', function () {
            activeServiceType = button.getAttribute('data-service-type');
            document.querySelectorAll('[data-service-type]').forEach(function (tab) { tab.classList.toggle('active', tab === button); });
            renderServices();
        });
    });
    destinationFilter.addEventListener('change', function () {
        if (hotel() && Number(hotel().destination_id) !== Number(destinationFilter.value || 0) && Number(destinationFilter.value || 0)) {
            selectedHotelId = 0;
            selectedRoomTypeId = 0;
            selectedMealCode = '';
            selectedServices.clear();
        }
        renderAll();
    });
    [adultsInput, childrenInput].forEach(function (input) {
        input.addEventListener('change', function () { normaliseRooms(); renderSummary(); });
    });
    roomsInput.addEventListener('change', renderSummary);
    nightsInput.addEventListener('change', function(){renderRoomTypes();renderSummary();});
    roomTypeInput.addEventListener('change',function(){selectedRoomTypeId=Number(roomTypeInput.value||0);normaliseRooms();renderAll();});
    if(startDateInput)startDateInput.addEventListener('change',function(){renderRoomTypes();renderSummary();});
    mealInput.addEventListener('change', function () { selectedMealCode = mealInput.value; renderSummary(); });
    if (!data.hotels.some(function (item) { return Number(item.id) === selectedHotelId; })) selectedHotelId = 0;
    renderNights();
    normaliseRooms();
    renderAll();
})();
