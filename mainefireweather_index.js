var JS = {
	ratings: ['unknown','snow','low','moderate','high','veryhigh','extreme'],
	colors: {
		/* 'unknown': '#868686', */
		'unknown': '#259A5C',
		'snow': '#FFFFFF',
		'low': '#259A5C',
		'moderate': '#536DBC',
		'high': '#FFFF46',
		'veryhigh': '#FFAF40',
		'extreme': '#FF3C45'
	},
	fillOpacity: 0.5,
	lastupdate: "",
	unknownFillOpacity: 0.5,
	zoneCenters: [],
	zoneCount: null,
	zoneData: {},
	dataByZone: null,
	stationRefs: {},
	stations: {},
	zones: null,
	classDays: null,
	map: null,
	mapCenter: null,
	progressBar: null,
	geoXml: null,
	hasTouch: ('ontouchstart' in document.documentElement),
	geoJSON: null,
	client: 1,
	isFireSeason: function() {
		var month = new Date().getMonth();
		// Spring (March) through Autumn (November)
		// 0 = Jan, 1 = Feb, 2 = Mar, ..., 10 = Nov, 11 = Dec
		return (month >= 2 && month <= 10);
	},
	onReady : async function() {
		console.log("yes, onReady!")
		/*
			I believe this is the order things need to happen in:
			- get class days
			- get/draw geoJSON file
			- set zone colors
			- draw labels
			- draw stations
		*/
        /*
        loadAll
        .then(setMapCenters)
        .then(getGEoJSON)
        .then(setZoneColors)
        .then(drawLabels)
        .then(drawStations);

        */

		//JS.getClassDays("map")
        JS.getClassDays("map")
        .then((data)=>{
            console.log("getClassdays",JS.classDays,data);
            return JS.getGeoJSON();
        })
        .then((data)=>{
            console.log("new getGeoJson",data);
            return JS.setZoneCenters();
        })
        .then((data)=>{
            console.log("new setZonecenters",data);
            return JS.renderMap();
        })
        .then((data)=>{
            console.log("new renderMap",data);
            return JS.updateJSON();
        })
        .then((data)=>{
            console.log("new updateJSON",data);
            return JS.setColorZones();
        })
        .then((data)=>{
            console.log("new setColorZones",data);
            return JS.addZoneLabels();
        })
        .then((data)=>{
            console.log("new addZoneLabels",data);
            return JS.getStations();
        })
        .then((data)=>{
            console.log("new getStations",data);
            //Add click and mouse listeners to map data elements

            return "Listeners added!";
        })
        .then((data)=>{
            console.log("new listeners added",data);

            return JS.addStationMarkers();
        })
        .then((data)=>{
            console.log("new addStationMarkers",data);
            return JS.addMapListeners();

        });
		// Center of Maine
		JS.mapcenter = new google.maps.LatLng(45.15, -69);

		// Set the center of all the zones
		// JS.zoneCenters['1'] = new google.maps.LatLng(46.837649560937464, -69.8236083984375);
		// JS.zoneCenters['2'] = new google.maps.LatLng(46.7549166192819, -69.0435791015625);
		// JS.zoneCenters['3'] = new google.maps.LatLng(46.430285240839964, -68.1207275390625);
		// JS.zoneCenters['4'] = new google.maps.LatLng(45.12005284153054, -70.4827880859375);
		// JS.zoneCenters['5'] = new google.maps.LatLng(45.71385093029221, -69.2523193359375);
		// JS.zoneCenters['6'] = new google.maps.LatLng(45.460130637921004, -68.1976318359375);
		// JS.zoneCenters['7'] = new google.maps.LatLng(44.61393394730626, -69.5599365234375);
		// JS.zoneCenters['8'] = new google.maps.LatLng(44.972570682240644, -67.7691650390625);
		// JS.zoneCenters['9'] = new google.maps.LatLng(43.96119063892024, -70.7354736328125);
		// JS.zoneCenters['10'] = new google.maps.LatLng(43.89789239125797, -70.0103759765625);
		// JS.zoneCenters['11'] = new google.maps.LatLng(44.34742225636393, -68.8677978515625);
		// JS.zoneCenters['12'] = new google.maps.LatLng(44.653024159812, -67.681274414062);


		//console.log("zoneCenters",JS.zoneCenters);
		//JS.renderMap();

		JS.refreshData();
		// JS.setupSubscription();
		$(document).everyTime("3600s", JS.refreshData);

		$(window).resize(function() {
			google.maps.event.trigger(JS.map, "resize");
			JS.map.setCenter(JS.mapcenter);
		});
	},
    addMapListeners: function() {
        JS.addMapListener(JS.map.data,'click');
        JS.addMapListener(JS.map.data,'mouseover');
        JS.addMapListener(JS.map.data,'mouseout');
        $("#zoneInfo").on('click',function(event) {
            JS.zoneInfoClicked(event);
        });
        $("div.zoneLabel").on('click',function(event) {
            console.log("label clicked");
        });


    },
    setZoneCenters: async function() {
        JS.zoneCenters['1'] = {lat: 46.837649560937464, lng: -69.8236083984375};
		JS.zoneCenters['2'] = {lat: 46.7549166192819, lng: -69.0435791015625};
		JS.zoneCenters['3'] = {lat: 46.430285240839964, lng: -68.1207275390625};
		JS.zoneCenters['4'] = {lat: 45.12005284153054, lng: -70.4827880859375};
		JS.zoneCenters['5'] = {lat: 45.71385093029221, lng: -69.2523193359375};
		//JS.zoneCenters['6'] = {lat: 45.460130637921004, lng: -68.1976318359375};
		//BM Moved #6 over because a station was obscuring it.
		JS.zoneCenters['6'] = {lat: 45.5, lng: -68.1};
		JS.zoneCenters['7'] = {lat: 44.61393394730626, lng: -69.5599365234375};
		JS.zoneCenters['8'] = {lat: 44.972570682240644, lng: -67.7691650390625};
		JS.zoneCenters['9'] = {lat: 43.96119063892024, lng: -70.7354736328125};
		JS.zoneCenters['10'] = {lat: 43.89789239125797, lng: -70.0103759765625};
		JS.zoneCenters['11'] = {lat: 44.34742225636393, lng: -68.8677978515625};
		JS.zoneCenters['12'] = {lat: 44.653024159812, lng: -67.681274414062};
        return "ZoneCenters set";
    },
	setupSubscription: function() {
		ADD.SMScarriers = ADD.getSMScarriers();
		$("#subscribe").on("click", function(e) {
			e.preventDefault();
			ADD.addContact();
		});
	},

	getClassDays: async function(which) {
		console.log("getClassDay before calls",which);
        return $.getJSON("admin/php/get-content.php?content=class-days&which="+which, '', await function(data) {
		//$.getJSON("admin/php/get-content.php?content=class-days", '', function(data) {

			console.log("getClassDays response",data);
			if(data.result.indexOf("success") >= 0) {
				JS.classDays = data.classdays;
                returnval = data;
				//JS.getGeoJSON();

			}
            return data;
		});


		//console.log("getClassDays after getJson ");
	},
	getGeoJSON: function(path) {
		path = (!!path) ? path: 'files/MaineWeatherZones.json';
		return $.getJSON(path, function(data) {
			console.log("getGeoJSON",path, data);
			JS.geoJSON = data;
			//console.log("getGeoJSON",JS.classDays);
//			JS.renderMap();
//			JS.setColorZones();
//			JS.addZoneLabels();
            return data;
		});
	},
	setColorZones: async function() {
        console.log("setColorZones",JS.map.data);
		JS.map.data.forEach(function(thisfeature) {
			const zoneParams = JS.getZoneStyles(thisfeature);
			JS.map.data.overrideStyle(thisfeature, zoneParams)
			var name = thisfeature.getProperty('name');
			//JS.addMapListener(thisfeature,"Zone clicked: "+name);
			//console.log("setcolorzones in loop",name,thisfeature);

		});
//		JS.getStations();
//		//Add click and mouse listeners to map data elements
//		JS.addMapListener(JS.map.data,'click');
//		JS.addMapListener(JS.map.data,'mouseover');
//		JS.addMapListener(JS.map.data,'mouseout');
//		$("#zoneInfo").on('click',function(event) {
//			JS.zoneInfoClicked(event);
//		});
        return "setColorZones success!";

	},
	addMapListener(data,eventtype) {
        //console.log("addMapListener",eventtype);
        if(eventtype == "labelClick") {
            console.log("addMapListener",eventtype);
            data.addListener("click",(event)=>{
                console.log("clicked!",this.label);
            });


        } else {
            google.maps.event.addListener(data, eventtype, function(event) {
                // 	//do nothing for now
               //console.log("addMapListener",eventtype);
                if(eventtype == "click") {JS.clickHandler(event);}
//                if(eventtype == "labelClick") {
//                    console.log("labelClick listener added",data.label);
//                    //thisMarker.addListener("click",(event)=>{
//                    JS.clickHandler(event);
//                }
                if(eventtype.substr(0,5) == "mouse") {JS.mouseHandler(eventtype,event);}
               //console.log("Marker clicked",eventtype,thisMarker.label);
            });

        }



	},
	zoneInfoClicked: function() {
		console.log("zoneInfoClicked");
		$("#zoneInfo").removeClass('active');

	},
	clickHandler(event) {
		const zoneInfoShowing = $("#zoneInfo").hasClass('active')
		console.log("clicked",event.feature.getProperty('name'),zoneInfoShowing);

        $("#zoneInfo").removeClass('active');
        if(!zoneInfoShowing) JS.getStationsByZone(event.feature.getProperty('name'));


	},
	mouseHandler(eventtype,event) {
		//console.log(eventtype,event.feature.getProperty('name'));
	},
	getStationsByZone: function(zone) {
		$("#zoneInfo").addClass('active');
		$("#zoneInfo h1").html("Zone "+zone);
		$("#zoneInfo #stationList").html("");
		let stationList = ""
		JS.stations.forEach((thiszone) => {
			//const url = (thiszone.source == "WIMS") ? "" :`./station.php?station=${thiszone.source_id}`;
			const url = `./station.php?station=${thiszone.source_id}`;
			if(thiszone.zoneid == zone){
				const iconClass = (thiszone.source == "FEMS") ? "WIMSicon" : "RWicon";

                stationList += `<li class="${iconClass}"><a href="${url}">${thiszone.station_name}</a></li>`;
	           console.log("stationList for zone",iconClass,url,thiszone.station_name,thiszone);

			}
			//console.log("Zone",zone,thiszone);
		})
		//"<ul class='stationDetail'>"+stationList+'</ul>';
		$("#zoneInfo #stationList").html("<ul class='stationDetail'>"+stationList+'</ul>');

	},
	getStations: async function() {
		return $.getJSON('php/get-stations.php?level=all&flag=map&client='+JS.client, function(data) {
			JS.stations = data;
			console.log("got stations",data);
            return data;
			//JS.addStationMarkers();
		});
	},
	getStationStatus: function(lastupdate) {
		// determine online/offline when data_at more than 1/2 hour old

		var status = "unknown";
		var thentime = new Date(lastupdate);
		var nowtime = new Date();
		if((nowtime - thentime) > 1800) {
			status = "offline";
		} else {
			status = "online";
		}
		//console.log("getStationStatus",thentime,nowtime,lastupdate);

		return status;
	},

	addStationMarkers: async function() {
		if(!JS.isFireSeason()) {
			console.log("Not fire season - station markers not added.");
			return "Not fire season";
		}
        //console.log("add station markers",JS.stations);
		const mymarkers = {};
		//const baseUrl = window.location.origin+window.location.pathname;
		const RWicon = "./images/icon_stationmap_sm.png";
		const WIMSicon = "./images/icon_stationmap-nonRW.png";

		//console.log("linkMarkers baseUrl:",baseUrl,"iconpath:",iconpath);
		//console.log("linkMarkers JS.stations:",JS.stations);
		let mOptions = {}, latlon, station, thisMarker;
		for(let sta in JS.stations) {
			const station = JS.stations[sta];
			const iconpath = (station.source == "FEMS") ? WIMSicon : RWicon;
			const status = JS.getStationStatus(station.lastupdate);
			//console.log("linkMarkers",sta,station);
			latlon = new google.maps.LatLng(station.latitude, station.longitude, false);
			mOptions = {
				//map: JS.map,
				position: latlon,
				cursor: "pointer",
				//title: station.weatherpage,
				title: station.station_name,
				icon: iconpath,
				//url: "station.php?station=" + sta,
				url: "station.php?station=" + `${station.source_id}`,
				optimized: false,
				clickable:true,
				zIndex: 11000000
			};
			if(status=="online") {
				thisMarker = new google.maps.Marker(mOptions);
				thisMarker.setMap(JS.map);
			} else {
				console.log(status+" - marker not added: "+station.title);
			}
			//console.log("new thisMarker: "+station.weatherpage);
			//click listener for marker
            google.maps.event.addListener(thisMarker, 'click', function() {
				// go to station page
				console.log("click! "+this.url);
				window.location.href = this.url;
			});
		}
        return "station markers added!";
		//console.log("callback - addZoneLabels");
		//JS.gracefulCallback(callback);
	},

	getZoneStyles: function(thisfeature) {
		const zoneParams = {};
		//console.log("getZoneStyles",thisfeature.getProperty('name'),zoneParams);
		const propId = thisfeature.getProperty('name');
		let classDayColor = JS.colors[JS.ratings[0]];
		let thisfillOpacity = JS.unknownFillOpacity;
		let thisstrokeColor = "#FFFFFF";
		let thisstrokeWeight = 1.5;
		let unknownColor = JS.colors[JS.ratings[0]];
		classDayColor = JS.getZoneColor(propId);
		thisfillOpacity = (classDayColor == unknownColor) ? JS.unknownFillOpacity : JS.fillOpacity;
		//console.log("getZoneStyles",propId,thisfillOpacity,classDayColor,JS.ratings[0],JS.colors);
		zoneParams.strokeColor = thisstrokeColor;
		zoneParams.strokeWeight = thisstrokeWeight;
		zoneParams.strokeOpacity = 1;
		zoneParams.fillOpacity = thisfillOpacity;
		zoneParams.fillColor = classDayColor;
		zoneParams.zIndex = 100;
		//console.log("setZoneColor", propId,classDayColor);
		return zoneParams;

	},
	addZoneLabels: async () => {
		//console.log("addZoneLabels called");
		for(i=1; i<13;i++) {
			label = i.toString();
			position = JS.zoneCenters[label];
			JS.addMapLabel(label,position);
			//console.log("addZoneLabels",i,label,position);
		}
        return "addZoneLabels success!";
	},
	addMapLabel: function(labeltext,thisposition) {
		let mOptions = {}, latlon, station, thisMarker;
		const position = new google.maps.LatLng(thisposition.lat,thisposition.lng);
		//console.log("addMapLabel",label,position);
		let fillColor = JS.getZoneColor(label,"color");
		//let fillColor = 'red';
		//console.log("addMapLabel color",fillColor);
		//if(fillColor === undefined) fillColor = "#868686";
		//console.log("addMapLabel - fillColor",fillColor);
		//textColor = JS.getZoneColor(parseInt(label),"textcolor");
		//console.log("addMapLabel-getZoneColor: label="+label+" fillColor="+fillColor+" textColor="+textColor);
		mOptions = {
			position: position,
			cursor: "pointer",
			label: {
				text: labeltext,
                id: labeltext,
				className: 'zoneLabel',
				color: "var(--text-color)",
				fontWeight: "bold",
				fontSize: "26px"
			},
			shape: "circle",
			icon: {
				path: google.maps.SymbolPath.CIRCLE,
				scale: 10,
				//labelOrigin: new google.maps.Point(4,0),
				strokeColor: fillColor,
				strokeWeight: 0
			},
			optimized: false,
			clickable: true
			//zIndex: 11000000
		};
		//console.log("addMapLabel moptions",mOptions);
		thisMarker = new google.maps.Marker(mOptions);
		//mOptions.label.color = "red";
		//mOptions.label.fontSize = "15px";
		//console.log("addMapLabel moptions",mOptions);
		//thisMarkerLabel = new google.maps.Marker(mOptions);
		thisMarker.setMap(JS.map);
/*
        thisMarker.addListener("click",(event)=>{
           console.log("Marker clicked",thisMarker.label);
        });
*/
		JS.addMapListener(thisMarker,"labelClick");

	},

	refreshData : function() {
		//get any red flag alerts
		$.getJSON("admin/php/get-content.php?content=updatelog", '', function(data) {
			const isRedFlag = data.msg_type == "Red Flag";
			console.log("alert data",isRedFlag,data);

			if(data.alert !== "" && data.active == '1' && isRedFlag) {
				$("#alerts .alert").show();
				$("#alerts .alert .textblock p").html(data.alert);
			} else {
				$("#alerts .alert").hide();
			}

			var word = "forecast ";
			// $("span#actual-or-not").html(word);
			// if(data.spans.time != '') $("span#classes-as-of").html(" as of " + data.spans.time);
		});
		//get stations
		$.getJSON("php/get-stations.php?level=all&flag=map&client="+JS.client, '', function(data) {
			console.log("Got stations. About to update lastupdated",JS.lastupdate,data);
            JS.stations = data.stations;
			JS.zones = data.zones;
            JS.lastupdate = data[0].mapupdate;
            $('#lastupdate').html(JS.formatTime(JS.lastupdate));
		});


	},

	renderMap : async function() {
		var mapOptions = {
			draggable: true,
			scrollwheel: true,
			navigationControl: true,
			mapTypeControl: false,
			scaleControl: true,
			disableDefaultUI: true,
			disableDoubleClickZoom: false,
			center: JS.mapcenter,
			zoom: (JS.hasTouch ? 6 : 7),
			//Terrain or Roadmap?
			mapTypeId: google.maps.MapTypeId.ROADMAP
			//mapTypeId: google.maps.MapTypeId.TERRAIN

		};
		//JS.updateMap();
		JS.map = new google.maps.Map(document.getElementById("google-map"), mapOptions,JS.googleCallBack);
        //const jsonPath = 'files/MaineWeatherZones.json';
		//var jsonData = {};
		// const myGeoJSON = $.getJSON(jsonPath, function(data) {
		// 	jsonData = data;
		// 	console.log("json",data,jsonData);
		// 	JS.updateJSON(data);
		// });
		//JS.updateJSON(jsonPath);

		//console.log("geoJson",jsonData,myGeoJSON); // output 'testing'
        return "map rendered!"

	},
    updateJSON: async function(jsonPath) {
		console.log("updateJSON",jsonPath); // output 'testing'
		//console.log("updateJSON - geoJSON",JS.geoJSON); // output 'testing'
		JS.map.data.addGeoJson(JS.geoJSON)

		// JS.map.data.loadGeoJson(jsonPath,
		// {idPropertyName: "Name"}, //sets the property
		JS.map.data.setStyle({
			strokeColor: 'white',
			fillColor: '#868686',
			fillOpacity: .1,
			cursor: "pan-hand",
			strokeWeight: 1.5
		},null);
		// console.log("updateJSON - classdays",JS.classDays); // output 'testing'
		//JS.polygonClick();
        return "geoJSON loaded"
	},

	updateMap : function() {
		// Create the progress bar
		JS.progressBar = new progressBar();
		JS.map.controls[google.maps.ControlPosition.LEFT].push(JS.progressBar.getDiv());
		JS.progressBar.start(12);

		// Start the parsing
		JS.zoneCount = 1;
		var geoOptions = {
			map: JS.map,
			zoom: false,
			suppressInfoWindows: true,
			afterParse: JS.parseNext
		}
		JS.geoXml = new geoXML3.parser(geoOptions);
		//JS.geoXml.parse('files/MaineWeatherZones1.kml');




		JS.map.data.loadGeoJson('files/MaineWeatherZones.json');

	},

	linkMarkers : function() {
		JS.addStationMarkers();
	},

	// Parse the next KML
	parseNext : function(doc) {
		//console.log("doc@parseNext:", doc);
		JS.colorZones(doc);

		// Stop at 12
		if(JS.zoneCount >= 12) {
			JS.progressBar.updateBar(1);
			JS.progressBar.hide();
			// TODO hide station icons until spring
			JS.linkMarkers();

			return;
		}

		JS.zoneCount++;
		JS.progressBar.updateBar(1);
		//JS.geoXml.parse('files/MaineWeatherZones'+JS.zoneCount+'.kml');
	},

	colorZones : function(doc) {

		var geoXmlDoc = doc[0];
		for(var i = 0 ; i < geoXmlDoc.gpolygons.length ; i++) {
			// Get the polygon
			var polygon = geoXmlDoc.gpolygons[i];

			// Get the zoneid (the title)
			var zoneid = polygon.title;
			var zoneLabel = new MapLabel({
				text: zoneid,
				position: JS.zoneCenters[zoneid],
				map: JS.map,
				fontSize: (JS.hasTouch ? 18 : 25),
				strokeWeight: 2,
				align: 'center'
			});

			var classDayColor = JS.getZoneColor(JS.zoneCount);
			polygon.setOptions({
				strokeColor: "#666666",
				strokeWeight: 1.5,
				fillColor: classDayColor,
				fillOpacity: 0.5
			});
		}
	},

	getZoneColor : function(count) {
		//classdays values are strings
		var cd = (!!JS.classDays[count]) ? parseInt(JS.classDays[count]): 0;
		const color = JS.colors[JS.ratings[cd]];
		//console.log("getZoneColor",count,cd+2,JS.ratings[cd],color,JS.classDays[count],JS.colors);
		return color;
	},
	formatTime: function(ts,format="MMM d h:mm tt",br=true) {
        // remove ms from ts
        testType = typeof ts;
        console.log("formatTime: ts =",typeof testType,testType,ts);
        if(testType === "object") console.log("formatTime: ts =",testType,ts);
        if(testType === "string") {
            ts = (ts.indexOf(".")  > -1 && testType !== "object") ? ts.split('.')[0] : ts;
        }
		if(ts!=null) {
			rDate = Date.parse(ts).toString(format);
		} else {
			rDate = ts;
		}
		return rDate;
	},

	// Function for a polygon click
	polygonClick : function(poly, text) {
		google.maps.event.addListener(poly, 'click', function(event) {
			if(event) {
				console.log(event);
			}
		});
	}
};
//$(document).ready(JS.onReady);
