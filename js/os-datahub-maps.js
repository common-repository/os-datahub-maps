/**
Copyright (c) 2022 Skirrid Systems

os_datahub-maps.js is the main Javascript code used to generate a map.

Location coordinate conversion and validation functions are based on original code by Jon Lynch.
*/

'use strict';

// Self-executing function to process the global osDataHubMap object
(function() {
    if (typeof osDataHubMap != 'object') {
        return;
    }

    // Draw all maps when page load completes.
    document.addEventListener('DOMContentLoaded', function() {
        loadAllMaps();
    });

    function loadAllMaps() {
        // Add common initialisations used for all maps on the page.
        osDataHubMap.serviceUrl = 'https://api.os.uk/maps/raster/v1/zxy';
        osDataHubMap.crs = new L.Proj.CRS(
            'EPSG:27700',
            '+proj=tmerc +lat_0=49 +lon_0=-2 +k=0.9996012717 +x_0=400000 +y_0=-100000 +ellps=airy +towgs84=446.448,-125.157,542.06,0.15,0.247,0.842,-20.489 +units=m +no_defs',
            {
                origin: [ -238375.0, 1376256.0 ],
                resolutions: [ 896.0, 448.0, 224.0, 112.0, 56.0, 28.0, 14.0, 7.0, 3.5, 1.75, 0.875, 0.4375, 0.21875, 0.109375 ]
            });
        osDataHubMap.colorList = [ 'red', 'orange', 'yellow', 'green', 'blue', 'violet', 'gold', 'grey', 'black' ];
        osDataHubMap.defaultMarker = new L.Icon.Default();
        osDataHubMap.colorMarkers = [];

        osDataHubMap.mapList.forEach(function(mapItem) {
            drawMap(mapItem);
        });
    }

    // Expose the drawMap item only for debugging standalone JS module.
    // osDataHubMap.drawMap = drawMap;

    // Main map drawing method.
    function drawMap(mapItem) {
        // Declare all the main variables here.
        let center;
        let numLayers = 0;
        let numLoaded = 0;
        let startMarker = false;
        let endMarker = false;
        const osAttribution = 'Contains OS data &copy; Crown copyright and database rights ' + new Date().getFullYear();
        const markerSet = [];
        const options = mapItem.options;
        const defaultOptions = {
            mapId: 0,
            openDataStyle: 'Outdoor',
            zoom: 7,
            minZoom: 2,
            maxZoom: 11,
            maxLeisure: 9.5,
            zoomStep: 1,
            center: 'SU372155',
            defaultCenter: 'SU372155',
            mapFit: 'center',
            autoFitMargin: 0,
            popup: '',
            gestures: true,
            fullscreen: false,
            autoFit: false,
            restrictPan: false,
            canPrint: false,
            locate: false,
            showScale: true,
            gpxStyle: false,
            waypoint: true,
            imperial: false,
            hover: false,
            markerLink: '',
            markerZoom: '',
            startIconColor: false,
            endIconColor: false,
            layerListOpen: false,
            elevationUrl: '',
            extentList: [],
            layerList: [],
            markerList: [],
            gpxList: [],
            kmlList: []
        };

        // Set missing options from default options.
        for (let key in defaultOptions) {
            if (!options.hasOwnProperty(key)) {
                options[key] = defaultOptions[key];
            }
        }

        // Set up tile layers
        const tileLayers = [];
        let opendataMinZoom = options.minZoom;
        if (options.maxLeisure) {
            const leisureLayer = L.tileLayer(osDataHubMap.serviceUrl + '/Leisure_27700/{z}/{x}/{y}.png?key=' + osDataHubMap.apiKey, {
                maxZoom: options.maxLeisure,
                attribution: osAttribution
            });
            tileLayers.push(leisureLayer);
            opendataMinZoom = options.maxLeisure;
        }
        const opendataLayer = L.tileLayer(osDataHubMap.serviceUrl + '/' + options.openDataStyle + '_27700/{z}/{x}/{y}.png?key=' + osDataHubMap.apiKey, {
            maxZoom: options.maxZoom,
            minZoom: opendataMinZoom,
            attribution: osAttribution
        });
        tileLayers.push(opendataLayer);
        const defaultCenter = convertCoordinates(options.defaultCenter);
        if (options.mapFit === 'center') {
            center = convertCoordinates(options.center);
            if (center === null) {
                center = defaultCenter;
            }
        }
        const mapIdent = 'os-datahub-map-' + options.mapId;
        const mapDiv = document.getElementById(mapIdent);
        const gestures = (options.gestures === 'mobile') ? L.Browser.mobile : options.gestures;
        const mapOptions = {
            crs: osDataHubMap.crs,
            gestureHandling: gestures,
            layers: tileLayers,
            maxZoom: options.maxZoom,
            minZoom: options.minZoom,
            wheelPxPerZoomLevel: 60 / options.zoomStep,
            zoom: options.zoom,
            zoomDelta: options.zoomStep,
            zoomSnap: options.zoomStep
        };
        if (options.mapFit === 'center') {
            mapOptions.center = center;
        }
        const map = L.map(mapIdent, mapOptions);
        // Restrict panning option
        if (options.restrictPan) {
            const whenReadyCallback = function() {
                map.setMaxBounds(map.getBounds().pad(options.restrictPan));
            };
            map.whenReady(whenReadyCallback);
        }
        // Show print button
        if (options.canPrint) {
            L.control.browserPrint({
                printModes: ['Portrait', 'Landscape', 'Auto']
            }).addTo(map);
        }
        // Show map scale ruler
        if (options.showScale) {
            L.control.scale().addTo(map);
        }
        // Fit to extents
        let extBounds;
        if (options.mapFit === 'extent') {
            const corner1 = L.latLng(convertCoordinates(options.extentList[0]));
            const corner2 = L.latLng(convertCoordinates(options.extentList[1]));
            extBounds = L.latLngBounds(corner1, corner2);
            if (extBounds.isValid()) {
                map.fitBounds(extBounds);
            } else {
                map.setView(defaultCenter, options.zoom);
            }
        }
        // Full screen handling
        if (options.fullscreen) {
            L.control.fullscreen().addTo(map);
            map.on('enterFullscreen', function() {
                map.gestureHandling.disable();
            });
            map.on('exitFullscreen', function() {
                if (gestures) map.gestureHandling.enable();
            });
        }
        // Location handling
        if (options.locate) {
            const lc = L.control.locate({keepCurrentZoomLevel: true, locateOptions: {enableHighAccuracy: true}}).addTo(map);
            if (options.startLocate) lc.start();
        }
        // Add switching layers
        const conditionalLayers = [];   // List of groups that layers can be assigned to.
        const overlays = {};            // Items in the switching control.
        if ((typeof options.layerList === 'object') && (options.layerList.length !== 0)) {
            options.layerList.forEach(function(layerName) {
                let isEnabled = true;
                if (layerName.charAt(0) === '-') {
                    layerName = layerName.substr(1);
                    isEnabled = false;
                }
                const newLayer = L.layerGroup();
                overlays[layerName] = newLayer;
                conditionalLayers.push({ enabled: isEnabled, mapLayer: newLayer });
                if (isEnabled) {
                    newLayer.addTo(map);
                }
            });
            // Add switching control to map.
            L.control.layers(null, overlays, {collapsed: !options.layerListOpen}).addTo(map);
        }
        // Add markers and other feature layers which may be used to autofit.
        const autofitGroup = L.featureGroup();
        // Add markers
        if (typeof options.markerList === 'object') {
            options.markerList.forEach(function(marker) {
                const newLayer = L.marker(convertCoordinates(marker.position));
                if (marker.color !== '') {
                    newLayer.setIcon(getColorMarker(marker.color));
                }
                loadLayer(newLayer, marker.group, '');
                if (marker.text !== '') {
                    addPopup(newLayer, marker.text);
                }
                markerSet.push(newLayer);
            });
        }
        // Add KML layers
        if (typeof options.kmlList === 'object') {
            options.kmlList.forEach(function(kmlLayer) {
                const kmlOptions = { style: function() { return { color: kmlLayer.color, opacity: kmlLayer.opacity, weight: kmlLayer.weight }; } };
                const newLayer = omnivore.kml(kmlLayer.url, null, L.geoJson(null, kmlOptions));
                loadLayer(newLayer, kmlLayer.group, 'ready');
                if (kmlLayer.text !== '') {
                    addPopup(newLayer, kmlLayer.text);
                    preventAutoPan(newLayer);
                }
            });
        }
        // Add GPX layers
        if (typeof options.gpxList === 'object') {
            options.gpxList.forEach(function(gpxLayer) {
                // Find matching color marker, or default if unsupported.
                if (options.startIconColor !== '') {
                    startMarker = getColorMarker(options.startIconColor);
                }
                if (options.endIconColor !== '') {
                    endMarker = getColorMarker(options.endIconColor);
                }
                const polylineOptions = { opacity: gpxLayer.opacity, weight: gpxLayer.weight };
                if (!options.gpxStyle) {
                    polylineOptions.color = gpxLayer.color;
                }
                const parseOptions = [ 'track', 'route' ];
                if (options.waypoint) {
                    parseOptions.push('waypoint');
                }
                const newLayer = new L.GPX(gpxLayer.url, {
                    async: true,
                    gpx_options: { parseElements: parseOptions },
                    marker_options: { endIcon: endMarker, endIconUrl: false, startIcon: startMarker, startIconUrl: false, wptIcons: { '': osDataHubMap.defaultMarker } },
                    polyline_options: polylineOptions
                });
                loadLayer(newLayer, gpxLayer.group, 'loaded');
                if (gpxLayer.text !== '') {
                    addPopup(newLayer, gpxLayer.text);
                    preventAutoPan(newLayer);
                }
            });
        }
        // Add an elevation profile
        if (options.elevationUrl) {
            const elevationOptions = {
                autofitBounds: false,
                detached: true,
                downloadLink: false,
                dragging: false,
                elevationDiv: '#os-datahub-elev-' + options.mapId,
                followMarker: false,
                imperial: options.imperial,
                legend: false,
                margins: { top: 20 },
                marker: 'position-marker',
                reverseCoords: false,
                ruler: false,
                summary: false,
                theme: 'lightblue-theme',
                waypoints: false
            };
            const controlElevation = L.control.elevation(elevationOptions).addTo(map);
            controlElevation.load(options.elevationUrl);
        }
        // Add OS branding logo
        const logoDiv = document.createElement('div');
        logoDiv.className = 'os-api-logo';
        mapDiv.appendChild(logoDiv);

        // Fit map to extents
        if (options.autoFit && (numLayers === 0)) {
            autoFitMap();
        }
        // Add fly-to-marker if selected.
        if (markerSet.length) {
            function zoomToMarker(markerVal, zoomVal) {
                if ((markerVal > 0) && (markerVal <= markerSet.length)) {
                    const marker = markerSet[markerVal - 1];
                    if (marker) {
                        let zoom = parseFloat(zoomVal);
                        if (isNaN(zoom)) {
                            zoom = 0;
                        } else if (zoom < options.minZoom) {
                            zoom = options.minZoom;
                        } else if (zoom > options.maxZoom) {
                            zoom = options.maxZoom;
                        }
                        if (!map.hasLayer(marker)) {
                            marker.addTo(map);
                        }
                        if (zoom === 0) {
                            map.flyTo(marker.getLatLng());
                        } else {
                            map.flyTo(marker.getLatLng(), zoom);
                        }
                        marker.openPopup();
                    }
                }
            }
            const linkOpts = options.markerLink.split(',').map((str)=>str.trim());
            if (linkOpts.includes('link')) {
                // Add listener for links generated by [osmap_link]
                const linkElements = document.getElementsByClassName(mapIdent + '-marker-link');
                for (let i = 0; i < linkElements.length; i++) {
                    linkElements[i].addEventListener( 'click', function(e) {
                        if ('marker' in e.target.dataset && 'zoom' in e.target.dataset) {
                            zoomToMarker(e.target.dataset.marker, e.target.dataset.zoom);
                        }
                    });
                }
            }
            if (linkOpts.includes('auto') || linkOpts.includes('custom')) {
                // Add listener for auto and custom links
                const listElement = document.getElementById(mapIdent + '-marker-list');
                if (listElement) {
                    listElement.addEventListener( 'click', function(e) {
                        if ('marker' in e.target.dataset) {
                            zoomToMarker(e.target.dataset.marker, options.markerZoom);
                        }
                    });
                }
            }
        }

        if (options.popup !== '') {
            function drawInPopup() {
                map.invalidateSize();
                if (options.autoFit) {
                    autoFitMap();
                } else if (options.mapFit === 'extent') {
                    if (extBounds.isValid()) {
                        map.fitBounds(extBounds);
                    } else {
                        map.setView(defaultCenter, options.zoom);
                    }
                } else if (options.mapFit === 'center') {
                    map.setView(mapOptions.center, options.zoom);
                }
                if (options.restrictPan) {
                    map.setMaxBounds(map.getBounds().pad(options.restrictPan));
                }
            }
            if (options.popup.startsWith('paoc')) {
                // Popup anything on click
                jQuery(document).on('paoc_popup_open', function(e, target, opts) {
                    const mapOptNum = options.popup.match(/\d+/g);  // paoc-123 from [osmap popup='paoc-123']
                    const targetNum = target.match(/\d+/g);         // paoc-popup-123-3 from [popup_anything id=123]
                    // Redraw if there is a match, or if matching is not used.
                    if ((mapOptNum === null) || (targetNum === null) || (mapOptNum[0] === targetNum[0])) {
                        drawInPopup();
                    }
                });
            } else {
                let moPopupTimer;
                // Create a mutation observer
                const observer = new MutationObserver(function(mutationList, observer) {
                    for (const mutation of mutationList) {
                        if ((mutation.type === 'attributes') && (mutation.attributeName === 'style')) {
                            // This fires multiple times, so use a timer to wait for quiet.
                            moPopupTimer = setTimeout(drawInPopup, 100);
                        }
                    }
                });
                // Need to find a target to observe for changes in style.
                // This one works for Popup Maker.
                const moTarget = mapDiv.parentElement.parentElement;
                observer.observe(moTarget, {attributes: true});
            }
        }

        // Add references for JS hooks.
        mapItem.map = map;
        mapItem.markers = markerSet;
        // -------- End of map generation --------

        // Internal function definitions.
        // Convert a string of digits to a number, used on eastings and northings.
        // Up to 5 digits are supported and trailing 0 is assumed for shorter strings.
        function partialGridRef(digits) {
            const padded = digits + '0000';
            return parseInt(padded.substr(0, 5), 10);
        }

        // Convert a UK National Grid Reference to Latitude/Longitude
        function gridRefToLatLong(ngr) {
            let eastings;
            let northings;
            let c;
            let offset;

            // First convert the two-character prefix to get Northings and Eastings
            // First char can be S, T, N, O or H
            // These represent five 500km squares.
            c = ngr.charAt(0);
            if (c === 'S') {
                eastings  = 0;
                northings = 0;
            } else if (c === 'T') {
                eastings  = 500000;
                northings = 0;
            } else if (c === 'N') {
                eastings  = 0;
                northings = 500000;
            } else if (c === 'O') {
                northings = 500000;
                eastings  = 500000;
            } else if (c === 'H') {
                northings = 1000000;
                eastings  = 0;
            } else {
                return null;
            }

            // Second char can be anything except I
            // This gives the 25 x 100km squares inside
            c = ngr.charAt(1);
            if (c === 'I') {
                return null;
            }

            c = ngr.charCodeAt(1) - 65;
            if (c > 8) {
                c -= 1;
            }
            eastings  += (c % 5) * 100000;
            northings += (4 - Math.floor(c / 5)) * 100000;

            // Take the remainder of the ref to be split into two equal halves
            ngr = ngr.substr(2);
            if ((ngr.length % 2) === 1) {
                return null;
            }
            // Maximum length is 5 digits (1m resolution), minimum is 2 digits
            if ((ngr.length > 10) || (ngr.length < 4)) {
                return null;
            }

            // Add the numeric part to the eastings.
            offset = partialGridRef(ngr.substr(0, ngr.length / 2));
            if (Number.isNaN(offset)) {
                return null;
            }
            eastings += offset;
            // Add the numeric part to the northings.
            offset = partialGridRef(ngr.substr(ngr.length / 2));
            if (Number.isNaN(offset)) {
                return null;
            }
            northings += offset;
            // Convert to Latitude/Longitude
            return proj4('EPSG:27700', 'EPSG:4326', [eastings, northings]).reverse();
        }

        function validatePolar(value) {
            if (Number.isNaN(value)) {
                console.log(value + ' is not a valid coordinate');
                return false;
            }
            if ((value >= -180) && (value <= 180)) {
                return true;
            }
            console.log(value + ' is not a valid coordinate');
            return false;
        }

        // Validate location and return as Latitude/Longitude.
        // Accepts a location in UK National Grid format or Latitude/Longitude
        function convertCoordinates(userPos) {
            // Sanitise by converting to uppercase and removing any spaces.
            const pos = userPos.toUpperCase().replace(/\s+/g, '');
            // First try to interpret as a UK National Grid Reference.
            const osGridRef = gridRefToLatLong(pos);
            if (osGridRef) {
                return osGridRef;
            }
            // Next try to split at a comma to get a lat/long pair.
            const coord = pos.split(',');
            if (coord.length !== 2) {
                console.log('\'' + pos + '\' is not a valid position. It must be either a UK grid reference or two decimal numbers separated by a comma.');
                return null;
            }
            // Validate the coordinate values.
            if (validatePolar(coord[0]) && validatePolar(coord[1])) {
                // Check for reversed lat/long
                if (coord[0] < coord[1]) {
                    return [coord[1], coord[0]];
                }
                return [coord[0], coord[1]];
            }
            return null;
        }

        // Called to fit map to extents.
        function autoFitMap() {
            const fitBounds = autofitGroup.getBounds();
            if (fitBounds.isValid()) {
                const fitOptions = { padding: [ options.autoFitMargin, options.autoFitMargin ] };
                map.fitBounds(fitBounds, fitOptions);
            } else {
                map.setView(defaultCenter, options.zoom);
            }
        }
        // Called when all layers have been loaded.
        function doLayersLoaded(e) {
            if (options.autoFit) {
                autoFitMap();
            }
            const elev = document.getElementById('os-datahub-elev-' + options.mapId);
            if (elev !== null) {
                if ((e.target.get_elevation_max() - e.target.get_elevation_min()) > 1) {
                    elev.style.visibility = 'visible';
                } else {
                    elev.style.display = 'none';
                }
            }
        }
        // Add a new layer to the map.
        function loadLayer(layer, group, cond) {
            // Group 0 is reserved for unconditional layer.
            if (typeof group !== 'number') {
                group = 0;
            }
            // Add the conditional layers separately.
            if (group !== 0) {
                conditionalLayers[group - 1].mapLayer.addLayer(layer);
            }
            // Add layer to map only if being displayed.
            if ((group === 0) || conditionalLayers[group - 1].enabled) {
                layer.addTo(map);
            }
            // Add to the autofit group regardless of whether it's currently shown.
            autofitGroup.addLayer(layer);
            // Check the readiness condition.
            if (cond !== '') {
                numLayers += 1;
                layer.on(cond, function(e) {
                    if (cond === 'ready') {
                        e.target.eachLayer(function(theLayer) {
                            let text = '';
                            if (theLayer.feature.properties.name !== undefined) {
                                text = '<div class="name">' + theLayer.feature.properties.name + '</div>';
                            }
                            if (theLayer.feature.properties.description !== undefined) {
                                text += '<div class="description">' + theLayer.feature.properties.description + '</div>';
                            }
                            if (text !== '') {
                                addPopup(theLayer, text);
                            }
                        });
                    }
                    numLoaded += 1;
                    if (numLoaded === numLayers) {
                        doLayersLoaded(e);
                    }
                });
                layer.on('error', function(e) {
                    console.log('Error loading file: ' + e.err);
                    numLoaded += 1;
                    if (numLoaded === numLayers) {
                        doLayersLoaded(e);
                    }
                });
            }
        }
        // Handler for timed popups.
        function addPopup(layer, text) {
            let popupOptions = {};
            if (!options.popup) {
                // This doesn't currently work within a popup window.
                const popupMargin = 40;
                popupOptions.maxHeight = mapDiv.clientHeight - popupMargin;
                popupOptions.maxWidth = Math.min(mapDiv.clientWidth - popupMargin, 300);
            };
            layer.bindPopup(text, popupOptions);
            if (options.hover) {
                let popupTimer;
                layer.on('mouseover', function(e) {
                        clearTimeout(popupTimer);
                        e.target.openPopup();
                    }).on('mouseout', function(e) {
                        popupTimer = setTimeout(function() {
                            e.target.closePopup();
                        }, 2500);
                    });
            }
        }
        // Prevent layer from auto-panning when popup opens
        function preventAutoPan(layer) {
            const popup = layer.getPopup();
            popup.options.autoPan = false;
            popup.update();
        }
        // Get a coloured marker icon using one already constructed if possible.
        function getColorMarker(markerColor) {
            let theMarker = osDataHubMap.colorMarkers.find((obj) => obj.color === markerColor);
            if (theMarker === undefined) {
                // Create the icon if possible.
                let markerOptions;
                if (osDataHubMap.colorList.includes(markerColor)) {
                    // Using markers from https://github.com/pointhi/leaflet-color-markers
                    markerOptions = {
                        iconAnchor: [12, 41],
                        iconSize: [25, 41],
                        iconUrl: osDataHubMap.markerPath + 'marker-icon-2x-' + markerColor + '.png',
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41],
                        shadowUrl: osDataHubMap.markerPath + 'marker-shadow.png'
                    };
                } else {
                    const number = markerColor.match(/^(\d+)$/);
                    if (number) {
                        // Using markers from https://mapicons.mapsmarker.com/numbers-letters/numbers/
                        markerOptions = {
                            iconAnchor: [16, 37],
                            iconSize: [32, 37],
                            popupAnchor: [1, -30]
                        };
                        const suffix = number[1];
                        if (suffix === '0') {
                            markerOptions.iconUrl = osDataHubMap.markerPath + 'letter_s' + '.png';
                        } else {
                            let index = parseInt(suffix, 10);
                            if (index <= 30) {
                                markerOptions.iconUrl = osDataHubMap.markerPath + 'number_' + index + '.png';
                            }
                        }
                    } else {
                        const letter = markerColor.match(/^([A-Za-z])$/);
                        if (letter) {
                            // Using markers from https://mapicons.mapsmarker.com/numbers-letters/letters/
                            markerOptions = {
                                iconAnchor: [16, 37],
                                iconSize: [32, 37],
                                popupAnchor: [1, -30]
                            };
                            markerOptions.iconUrl = osDataHubMap.markerPath + 'letter_' + letter[1].toLowerCase() + '.png';
                        }
                    }
                }
                if ((markerOptions !== undefined) && (markerOptions.iconUrl !== undefined)) {
                    theMarker = { color: markerColor, icon: new L.Icon(markerOptions) };
                    osDataHubMap.colorMarkers.push(theMarker);
                }
            }
            if (theMarker === undefined) {
                return osDataHubMap.defaultMarker;
            }
            return theMarker.icon;
        }
    }   // End of drawMap method definition
// End of self-executing function.
})();
