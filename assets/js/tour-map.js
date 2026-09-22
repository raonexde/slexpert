(function(){
    'use strict';
    var data=window.TOUR_MAP_DATA;
    var element=document.querySelector('[data-tour-map]');
    var status=document.querySelector('[data-tour-map-status]');
    if(!data||!element)return;
    function message(text){if(status)status.textContent=text||'';}
    function point(value){return{lat:Number(value.lat),lng:Number(value.lng)};}
    async function initialise(){
        try{
            var libraries=await Promise.all([google.maps.importLibrary('maps'),google.maps.importLibrary('marker'),google.maps.importLibrary('routes'),google.maps.importLibrary('core')]);
            var Map=libraries[0].Map,AdvancedMarkerElement=libraries[1].AdvancedMarkerElement,PinElement=libraries[1].PinElement,Route=libraries[2].Route,LatLngBounds=libraries[3].LatLngBounds;
            var places=(data.points||[]).filter(function(item){return Number.isFinite(Number(item.lat))&&Number.isFinite(Number(item.lng));});
            var map=new Map(element,{center:{lat:7.65,lng:80.7},zoom:7,mapId:data.mapId||'DEMO_MAP_ID',mapTypeControl:false,streetViewControl:false,fullscreenControl:true});
            if(!places.length){message(data.labels.error);return;}
            if(data.mode==='hiking'){
                var hikingBounds=new LatLngBounds();
                places.forEach(function(place,index){var hikingPin=new PinElement({glyph:String(index+1),background:'#15382e',borderColor:'#fff',glyphColor:'#fff'});new AdvancedMarkerElement({map:map,position:point(place),title:place.name,content:hikingPin.element});hikingBounds.extend(point(place));});
                if(places.length===1){map.setCenter(point(places[0]));map.setZoom(10);}else{map.fitBounds(hikingBounds,42);}
                message(data.labels.hiking||'');return;
            }
            if(places.length===1){var pin=new PinElement({glyph:'1',background:'#15382e',borderColor:'#fff',glyphColor:'#fff'});new AdvancedMarkerElement({map:map,position:point(places[0]),title:places[0].name,content:pin.element});map.setCenter(point(places[0]));map.setZoom(9);message('');return;}
            var result=await Route.computeRoutes({origin:point(places[0]),destination:point(places[places.length-1]),intermediates:places.slice(1,-1).map(function(place){return{location:point(place)};}),travelMode:'DRIVING',region:'lk',fields:['path','legs','distanceMeters','durationMillis']});
            if(!result.routes||!result.routes.length)throw new Error('No route');
            var route=result.routes[0];route.createPolylines().forEach(function(line){line.setOptions({strokeColor:'#b77b17',strokeOpacity:.92,strokeWeight:5});line.setMap(map);});await route.createWaypointAdvancedMarkers({map:map});var bounds=new LatLngBounds();route.path.forEach(function(position){bounds.extend(position);});map.fitBounds(bounds,42);message('');
        }catch(error){console.error(error);message(data.labels.error);}
    }
    if(!data.apiKey){message(data.labels.missing);return;}
    window.__sriLankaExpertTourMapReady=initialise;
    var params=new URLSearchParams({key:data.apiKey,v:'weekly',loading:'async',callback:'__sriLankaExpertTourMapReady',language:data.language||'de',region:'LK'});
    var script=document.createElement('script');script.src='https://maps.googleapis.com/maps/api/js?'+params.toString();script.async=true;script.onerror=function(){message(data.labels.error);};document.head.appendChild(script);
})();
