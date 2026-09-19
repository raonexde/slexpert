(function () {
    'use strict';
    var data = window.ITINERARY_MAP_DATA;
    var mapElement = document.querySelector('[data-itinerary-map]');
    var statusElement = document.querySelector('[data-itinerary-map-status]');
    var distanceElement = document.querySelector('[data-itinerary-distance]');
    var durationElement = document.querySelector('[data-itinerary-duration]');
    if (!data || !mapElement || !statusElement) return;

    function status(message, error) {
        statusElement.textContent = message || '';
        statusElement.classList.toggle('visible', Boolean(message));
        statusElement.classList.toggle('error', Boolean(error));
    }
    function point(place) { return { lat: Number(place.latitude), lng: Number(place.longitude) }; }
    async function initialise() {
        try {
            var libraries = await Promise.all([
                google.maps.importLibrary('maps'),google.maps.importLibrary('marker'),
                google.maps.importLibrary('routes'),google.maps.importLibrary('core')
            ]);
            var Map = libraries[0].Map, AdvancedMarkerElement = libraries[1].AdvancedMarkerElement;
            var PinElement = libraries[1].PinElement, Route = libraries[2].Route, LatLngBounds = libraries[3].LatLngBounds;
            var places = data.places.filter(function (place) { return place.latitude !== null && place.longitude !== null; });
            if (!places.length) throw new Error('No coordinates');
            var map = new Map(mapElement,{center:point(places[0]),zoom:places.length>1?7:10,mapId:data.mapId||'DEMO_MAP_ID',mapTypeControl:false,streetViewControl:false,fullscreenControl:false});
            if(places.length===1){var pin=new PinElement({glyph:'1',background:'#15382e',borderColor:'#fff',glyphColor:'#fff'});new AdvancedMarkerElement({map:map,position:point(places[0]),title:places[0].name,content:pin.element});status('',false);return;}
            var result=await Route.computeRoutes({origin:point(places[0]),destination:point(places[places.length-1]),intermediates:places.slice(1,-1).map(function(place){return{location:point(place)};}),travelMode:'DRIVING',region:'lk',fields:['path','legs','distanceMeters','durationMillis']});
            if(!result.routes||!result.routes.length)throw new Error('No route');
            var route=result.routes[0];route.createPolylines().forEach(function(polyline){polyline.setOptions({strokeColor:'#b77b17',strokeOpacity:.94,strokeWeight:5});polyline.setMap(map);});
            await route.createWaypointAdvancedMarkers({map:map});var bounds=new LatLngBounds();route.path.forEach(function(pathPoint){bounds.extend(pathPoint);});map.fitBounds(bounds,45);
            var kilometres=Math.round((Number(route.distanceMeters)||0)/1000),minutes=Math.round((Number(route.durationMillis)||0)/60000);if(kilometres&&distanceElement)distanceElement.textContent=new Intl.NumberFormat(data.language==='de'?'de-DE':'en-GB').format(kilometres)+' km';if(minutes&&durationElement){var hours=Math.floor(minutes/60),remainder=minutes%60;durationElement.textContent=(hours?hours+' '+data.labels.hours+' ':'')+(remainder?remainder+' '+data.labels.minutes:'');}status('',false);
        } catch(error){console.error('Itinerary map error:',error);status(data.labels.error,true);}
    }
    function load(){if(!data.apiKey){status(data.labels.missingKey,true);return;}window.__ceylonItineraryMapReady=function(){initialise();};var params=new URLSearchParams({key:data.apiKey,v:'weekly',loading:'async',callback:'__ceylonItineraryMapReady',language:data.language||'de',region:'LK'});var script=document.createElement('script');script.src='https://maps.googleapis.com/maps/api/js?'+params.toString();script.async=true;script.onerror=function(){status(data.labels.error,true);};document.head.appendChild(script);}
    load();
})();
