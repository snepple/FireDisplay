<?php
$configFile = 'config.json';
$config = [];
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true);
}

$dashboardToken = isset($config['dashboard_token']) ? $config['dashboard_token'] : '';

// If a token is set in the config, require it
if (!empty($dashboardToken)) {
    $providedToken = isset($_GET['token']) ? $_GET['token'] : '';
    if ($providedToken !== $dashboardToken) {
        http_response_code(403);
        die("<h1 style='color: #aeb6c1; text-align: center; font-family: sans-serif; padding-top: 50px;'>Access Denied</h1>");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Current Fire Department Schedule</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap');

        /* Default Dark Theme Variables */
        :root {
            --bg-color: #10141a;
            --card-bg: #1C212B;
            --text-color: #fff;
            --muted-text: #aeb6c1;
            --border-color: #30363d;
            --event-hover: #30363d;
            --today-bg: #004085;
            --today-border: #9fceff;
            --item-bg: #10141a;
        }

        /* Light Theme Overrides */
        body.light-theme {
            --bg-color: #f5f5f7;
            --card-bg: #ffffff;
            --text-color: #1d1d1f;
            --muted-text: #86868b;
            --border-color: #d2d2d7;
            --event-hover: #f0f0f5;
            --today-bg: #e6f2ff;
            --today-border: #007bff;
            --item-bg: #fbfbfd;
        }

        html, body {
            height: 100%;
            margin: 0;
            overflow: hidden;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Inter', sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10px;
            font-size: 625%;
            box-sizing: border-box;
            transition: background-color 0.5s, color 0.5s;
        }
        @media (max-width: 1600px) { body { font-size: 500%; } }
        @media (max-width: 1300px) { body { font-size: 400%; } }
        @media (max-width: 1000px) { body { font-size: 300%; } }
        @media (max-width: 700px)  { body { font-size: 220%; } }

        .page-container {
            width: 100%;
            height: 100%;
            display: none;
            min-height: 0;
        }
        #page-dashboard { flex-direction: row; align-items: stretch; height: 100%; gap: 15px; width: 100%; }
        #page-chores { flex-direction: column; align-items: center; height: 100%; gap: 15px; width: 100%; }

        .main-layout { display: flex; flex-direction: column; height: 100%; gap: 15px; min-width: 0; }
        #top-section { flex: 1; min-width: 0; }
        #combined-permits-container { flex: 2; min-width: 0; min-height: 0; display: flex; flex-direction: column; }
        .container { width: 100%; flex: 1; display: flex; flex-direction: column; min-height: 0; }

        h2 {
            font-family: 'Agency FB', sans-serif;
            text-transform: uppercase;
            text-align: center;
            color: var(--muted-text);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 3px;
            margin-top: 0;
            margin-bottom: 5px;
            font-size: 16.25pt;
            letter-spacing: 1.5px;
            flex-shrink: 0;
        }
        .event {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--card-bg);
            padding: 9px 18px;
            border-bottom: 1px solid var(--border-color);
            flex-shrink: 0;
            border-radius: 4px;
        }
        .event.clickable { cursor: pointer; }
        .event.clickable:hover { background-color: var(--event-hover); }
        .event-left { text-align: left; overflow: hidden; }
        .event-right { text-align: right; flex-shrink: 0; padding-left: 10px;}
        .event-name { font-size: 0.23em; font-weight: 500; color: var(--text-color); white-space: nowrap; text-overflow: ellipsis; overflow: hidden;}
        .event-role { font-size: 0.15em; font-weight: 500; color: var(--muted-text); text-transform: uppercase; margin-top: 1px; letter-spacing: 0.5px; }
        .event-until { font-size: 0.22em; color: var(--muted-text); font-weight: 400; }

        .permit-details { display: flex; flex-direction: column; width: 100%; gap: 4px; }
        .permit-address { font-size: 0.23em; font-weight: 500; color: var(--text-color); white-space: normal; }
        .permit-burn-info { display: flex; justify-content: space-between; align-items: center; width: 100%; }
        .permit-type { font-size: 0.15em; font-weight: 500; color: var(--muted-text); text-transform: uppercase; }
        .permit-time { font-size: 0.18em; color: var(--muted-text); font-weight: 400; }

        .no-events { text-align: center; color: var(--muted-text); padding: 15px; background-color: var(--card-bg); font-size: 0.23em; flex-shrink: 0; }

        #fire-danger-content { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; box-sizing: border-box; background-color: var(--card-bg); border-radius: 4px; padding: 20px;}
        #danger-meter { width: 40%; height: 60px; border: 2px solid var(--muted-text); border-radius: 5px; margin-bottom: 15px; display: flex; align-items: center; justify-content: center; font-size: 0.4em; font-weight: 700; text-transform: uppercase; }
        #danger-date { font-size: 0.12em; color: var(--muted-text); margin-top: 5px; }
        .risk-snow-cover { background-color: #ffffff; color: #000 !important; border-color:#000 !important;}
        .risk-low { background-color: #28a745; color:#fff;}
        .risk-moderate { background-color: #007bff; color:#fff;}
        .risk-high { background-color: #ffc107; color: #000 !important; }
        .risk-very-high { background-color: #8B4513; color:#fff;}
        .risk-extreme { background-color: #dc3545; color:#fff;}

        #permitMap { height: 100%; width: 100%; border-radius: 4px; }
        .permit-tooltip { background-color: rgba(255, 255, 255, 0.9); border: 1px solid #888; border-radius: 3px; color: #333; font-weight: bold; padding: 4px 8px; font-size: 10pt; box-shadow: 0 1px 3px rgba(0,0,0,0.4); }
        .flame-marker-icon { filter: drop-shadow(0px 2px 3px rgba(0, 0, 0, 0.7)); }

        #page-calendar { display: none; flex-direction: column; gap: 15px; }
        .calendar-content-row { display: flex; flex: 1; gap: 15px; min-height: 0; margin-top: 15px; overflow-y: auto; }
        .calendar-main-content { flex: 5; display: flex; flex-direction: column; min-height: 0; }
        .calendar-sidebar { flex: 1; background-color: var(--card-bg); border-radius: 8px; padding: 10px; display: flex; flex-direction: column; overflow-y: auto; min-height: 0; }
        .calendar-sidebar h3 { font-size: 14pt; text-align: center; margin: 0 0 10px 0; color: var(--muted-text); border-bottom: 1px solid var(--border-color); padding-bottom: 5px; flex-shrink: 0; }

        #open-shifts-section { display: flex; flex-direction: column; flex-grow: 1; min-height: 0; }
        #open-shifts-list { display: flex; flex-direction: column; flex-grow: 1; overflow: hidden; min-height: 0; padding-top: 5px; }

        .open-shift-link, .open-shift-link:visited { text-decoration: none; color: inherit; display: block; }

        .open-shift-item {
            display: flex; justify-content: space-between; align-items: center;
            background-color: var(--item-bg); padding: 10px 12px; border-radius: 6px;
            margin-bottom: 8px; font-size: clamp(10px, 1.1vw, 16px); gap: 15px;
            flex-shrink: 0; border: 1px solid var(--border-color);
        }
        .open-shift-item:hover { background-color: var(--event-hover); }
        .open-shift-date { font-weight: 600; font-size: 1.1em; color: var(--text-color); white-space: nowrap; flex-shrink: 0; }
        .open-shift-right { display: flex; flex-direction: column; align-items: flex-end; min-width: 0; flex-shrink: 1; }
        .open-shift-role { font-weight: 800; color: #ff6b6b; font-size: 0.95em; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
        .open-shift-time { font-size: 0.8em; color: var(--muted-text); font-weight: 500; white-space: nowrap; }

        .calendar-arrow { opacity: 0.15; cursor: pointer; transition: opacity 0.3s ease; user-select: none; padding: 0 15px; font-size: 24px; }
        .calendar-arrow:hover { opacity: 0.8; }

        #calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 5px; flex-grow: 1; min-height: min-content; }

        .chores-header, .calendar-header { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); text-align: center; margin-bottom: 5px; }
        .chores-header { font-size: 12pt; color: #ffc107; font-weight: bold; }
        .calendar-header { font-size: 14pt; color: var(--muted-text); }

        .calendar-day { background-color: var(--card-bg); border: 1px solid var(--border-color); padding: 5px; font-size: 10pt; display: flex; flex-direction: column; overflow: hidden; min-height: 100px; min-width: 0; border-radius: 4px;}
        .calendar-day.is-today { background-color: var(--today-bg); border-color: var(--today-border); }
        .calendar-day.other-month { opacity: 0.3; }

        .day-number { display: flex; justify-content: space-between; align-items: center; color: var(--muted-text); margin-bottom: 5px; font-weight: 700; flex-shrink: 0; }
        .calendar-day.new-month { border-top: 2px solid #ffc107; }
        .calendar-day.new-month .day-number { color: #ffc107; }
        .chore-number { font-size: 0.7em; font-weight: normal; color: #ffc107; background-color: rgba(255, 255, 255, 0.1); border-radius: 50%; width: 1.5em; height: 1.5em; display: inline-flex; align-items: center; justify-content: center; }
        body.light-theme .chore-number { background-color: rgba(0,0,0,0.05); }

        .calendar-event { font-size: clamp(6px, 1.2vh, 10pt); padding: 1px 4px; border-radius: 3px; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; border: 1px solid transparent; line-height: 1.2; flex-shrink: 1; min-height: 0; }

        .calendar-event.event-open { background-color: transparent; background-image: repeating-linear-gradient(45deg, transparent, transparent 4px, rgba(128,128,128,0.2) 4px, rgba(128,128,128,0.2) 8px); color: var(--text-color); border: 1px dashed var(--muted-text); }
        .event-career { background-color: #6f42c1; color: white; }
        .event-per-diem { background-color: #e83e8c; color: white; }
        .event-night-duty { background-color: #fd7e14; color: white; }
        .event-town-meeting { background-color: #007bff; color: white; }
        .event-dept { background-color: #20c997; color: #000; }
        .event-unpublished { background-color: transparent; color: var(--muted-text); text-align: center; border: 1px dashed var(--border-color); font-style: italic; padding: 4px; margin-top: 5px; white-space: normal; }

        .calendar-legend { display: flex; justify-content: center; gap: 25px; margin-bottom: 10px; font-size: 11pt; color: var(--muted-text); flex-shrink: 0; }
        .legend-item { display: flex; align-items: center; gap: 8px; }
        .legend-color-box { width: 20px; height: 20px; border-radius: 4px; }
        .chore-item { background-color: var(--card-bg); border: 1px solid var(--border-color); padding: 25px 40px; border-radius: 8px; text-align: center; overflow: hidden; min-height: 0; }
        .chore-list { list-style-type: none; padding: 0; margin: 0; font-size: 28pt; font-weight: 700; line-height: 1.5; color: var(--text-color); }
        .duties-date { font-size: 18pt; color: var(--muted-text); text-align: center; margin-top: -10px; margin-bottom: 15px; }
        .national-day { font-size: 18pt; color: var(--muted-text); font-style: italic; margin-top: 20px; }
        #debug-log { display: none; }

        #permit-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.8); z-index: 10000; justify-content: center; align-items: center; padding: 20px; box-sizing: border-box; }
        #permit-modal-content { background-color: var(--card-bg); padding: 15px; border-radius: 8px; border: 1px solid var(--muted-text); width: 100%; max-width: 900px; color: var(--text-color); font-size: 14pt; cursor: pointer; display: flex; flex-direction: column; }
        #permit-modal-header h2 { margin-top: 0; margin-bottom: 5px; font-size: 14pt; color: #ffc107; border-bottom: 1px solid var(--border-color); white-space: nowrap; }
        #permit-modal-body { max-height: 70vh; overflow-y: auto; line-height: 1.4; }
        #permit-modal-body table { width: 100%; border-collapse: collapse; font-size: 11pt; }
        #permit-modal-body td { padding: 5px; border-bottom: 1px solid var(--border-color); vertical-align: top; }
        #permit-modal-body tr:last-child td { border-bottom: none; }
        #permit-modal-body td:first-child { color: var(--muted-text); width: 30%; font-weight: bold; }
        #permit-modal-body strong { color: #ffc107; font-weight: 500; }

        #audio-toggle-wrapper { display: none; position: absolute; top: 15px; left: 15px; z-index: 10000; opacity: 0.25; transition: opacity 0.3s ease; }
        #audio-toggle-wrapper:hover { opacity: 1; }
        .toggle-switch { position: relative; display: inline-block; width: 32px; height: 18px; margin: 0; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--border-color); transition: .4s; border-radius: 18px; border: 1px solid #555; }
        .toggle-slider:before { position: absolute; content: ""; height: 12px; width: 12px; left: 2px; bottom: 2px; background-color: var(--muted-text); transition: .4s; border-radius: 50%; }
        .toggle-switch input:checked + .toggle-slider { background-color: #28a745; border-color: #28a745; }
        .toggle-switch input:checked + .toggle-slider:before { transform: translateX(14px); background-color: #fff; }

        .announcement-content p { font-size: 16pt; line-height: 1.4; margin: 0 0 10px 0; color: var(--text-color); }
        .announcement-content h1, .announcement-content h2, .announcement-content h3 { color: #ffc107; margin-top:0; border:none; padding:0; text-align: left; }
        .announcement-content ul, .announcement-content ol { text-align: left; font-size: 16pt; margin: 0 0 10px 20px; color: var(--text-color);}
        .announcement-card { background: var(--item-bg); padding: 20px; border-radius: 6px; border: 1px solid var(--border-color); margin-bottom: 10px; text-align: left;}

        #admin-link:hover { opacity: 1 !important; }
    </style>
</head>
<body>

    <a href="admin.php" id="admin-link" title="Open Admin Dashboard" style="position: absolute; bottom: 15px; right: 15px; z-index: 10000; opacity: 0.15; color: var(--text-color); text-decoration: none; font-size: 24px; transition: opacity 0.3s;">⚙️</a>

    <div id="audio-toggle-wrapper" title="Toggle Audio Announcements">
        <label class="toggle-switch">
            <input type="checkbox" id="audio-toggle-checkbox">
            <span class="toggle-slider"></span>
        </label>
    </div>

    <div id="page-dashboard" class="page-container">
        <div class="main-layout" id="top-section">
            <div class="container">
                <h2>🔥 Fire Danger</h2>
                <div id="fire-danger-content">
                     <div id="danger-meter">Loading...</div>
                     <div id="danger-date"></div>
                     <div id="danger-map-container" style="margin-top: 15px; width: 100%; flex: 1; display: none; overflow: hidden; border-radius: 4px; border: 1px solid var(--border-color); position: relative;"><iframe id="danger-map-iframe" src="about:blank" style="position: absolute; top: -50px; left: -220px; width: 250%; height: 600px; border: none; pointer-events: none;" scrolling="no"></iframe></div>
                </div>
            </div>
        </div>
        <div class="container" id="combined-permits-container">
             <h2>Active Online-Issued Burn Permits</h2>
             <div id="permits-content-wrapper" style="display: flex; flex-grow: 1; min-height: 0; gap: 15px; width: 100%;">
                 <div id="burnPermitsContainer" style="flex: 2; background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 4px; overflow: hidden; min-height: 0;"></div>
                 <div id="permitMap" style="flex: 1; border-radius: 4px;"></div>
             </div>
        </div>
    </div>

    <div id="page-calendar" class="page-container">
        <div class="calendar-content-row">
            <div class="calendar-main-content">
                <div style="display: flex; justify-content: center; align-items: center; border-bottom: 1px solid var(--border-color); margin-bottom: 5px; padding-bottom: 3px; flex-shrink: 0;">
                    <div class="calendar-arrow" onclick="changeMonth(-1)">&#10094;</div>
                    <h2 id="calendar-month-year" style="border: none; margin: 0; padding: 0;"></h2>
                    <div class="calendar-arrow" onclick="changeMonth(1)">&#10095;</div>
                </div>

                <div class="calendar-legend">
                    <div class="legend-item"><div class="legend-color-box event-career"></div><span>Career</span></div>
                    <div class="legend-item"><div class="legend-color-box event-per-diem"></div><span>Per Diem</span></div>
                    <div class="legend-item"><div class="legend-color-box event-night-duty"></div><span>Night Duty</span></div>
                    <div class="legend-item"><div class="legend-color-box event-town-meeting"></div><span>Town Mtg</span></div>
                    <div class="legend-item"><div class="legend-color-box event-dept"></div><span>Dept Event</span></div>
                </div>
                <div class="chores-header">
                    <div>9-1</div><div>9-2</div><div>9-3</div><div>9-4</div><div>9-5</div><div>9-6<br>Meds</div><div>R4<br>R9</div>
                </div>
                <div class="calendar-header"><div>Sunday</div><div>Monday</div><div>Tuesday</div><div>Wednesday</div><div>Thursday</div><div>Friday</div><div>Saturday</div></div>
                <div id="calendar-grid"></div>
                <div id="schedule-published-text" style="font-size: 10pt; color: var(--muted-text); text-align: center; margin-top: 5px; flex-shrink: 0;"></div>
            </div>
            <div class="calendar-sidebar">
                <div id="open-shifts-section">
                    <h3>Upcoming Open Shifts</h3>
                    <div id="open-shifts-list"></div>
                </div>
            </div>
        </div>
    </div>

    <div id="page-chores" class="page-container">
        <h2 style="margin-bottom: 5px;">Today's Overview</h2>
        <!-- <p id="duties-page-date" class="duties-date"></p> -->

        <div id="chores-layout" style="display: flex; width: 100%; height: calc(100% - 120px); gap: 15px; min-height: 0;">

            <div id="chores-duties-column" style="flex: 1.5; display: flex; flex-direction: column; gap: 15px; min-width: 0; min-height: 0;">

                <div class="chore-item" id="announcements-wrapper" style="display: none; flex-direction: column; padding: 20px;">
                    <h2 style="font-size: 24pt; border: none; padding: 0; margin: 0 0 15px 0; color: #ffc107;">📢 Announcements</h2>
                    <div id="announcements-container" style="display: flex; flex-direction: column; overflow: hidden;"></div>
                </div>

                <div class="chore-item" style="flex-grow: 1; display: flex; flex-direction: column; justify-content: center; padding: 25px;">
                    <h2 style="font-size: 24pt; border: none; padding: 0; margin: 0 0 15px 0;">Today's Station Duties</h2>
                    <ul id="chore-list" class="chore-list"></ul>
                    <div id="holiday-container" style="display: none;"><p id="national-day" class="national-day"></p></div>
                </div>
            </div>

            <div id="chores-staff-column" style="flex: 1; display: flex; flex-direction: column; min-width: 0; min-height: 0;">
                 <div class="chore-item" style="height: 100%; box-sizing: border-box; display: flex; flex-direction: column; padding: 20px; overflow: hidden; min-height: 0;">

                     <div id="dept-events-container" style="display: none; margin-bottom: 30px; flex-shrink: 0;">
                         <h2 style="font-size: 24pt; border: none; padding: 0; margin: 0 0 15px 0;">📅 Department Events</h2>
                         <div id="dept-events-list" style="display: flex; flex-direction: column; gap: 10px;"></div>
                     </div>

                     <div id="town-meetings-container" style="display: none; margin-bottom: 30px; flex-shrink: 0;">
                         <h2 style="font-size: 24pt; border: none; padding: 0; margin: 0 0 15px 0;">🏛️ Town Meetings Here</h2>
                         <div id="town-meetings-list" style="display: flex; flex-direction: column; gap: 10px;"></div>
                     </div>

                     <div id="chores-on-duty-now-wrapper" style="flex-shrink: 0;">
                          <h2 style="font-size: 24pt; border: none; padding: 0; margin: 0 0 15px 0;">🧑‍🚒 On Duty</h2>
                          <div id="chores-on-duty-container"></div>
                     </div>
                     <div id="chores-on-duty-later-wrapper" style="margin-top: 30px; flex-shrink: 0;">
                          <h2 style="font-size: 24pt; border: none; padding: 0; margin: 0 0 15px 0;">🗓️ On Duty Later Today</h2>
                          <div id="chores-on-duty-later-container"></div>
                     </div>
                 </div>
            </div>
        </div>
    </div>

    <div id="permit-modal-overlay">
        <div id="permit-modal-content">
            <div id="permit-modal-header">
                <h2>DEPARTMENT OF AGRICULTURE, CONSERVATION & FORESTRY OPEN BURNING PERMIT</h2>
            </div>
            <div id="permit-modal-body"></div>
            <div id="permit-modal-footer">
                <p>Click this window to close (auto-closes in 1 minute)</p>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ical.js/1.5.0/ical.min.js"></script>
    <script src="js/date_utils.js"></script>
    <script src="js/utils/chores.js"></script>
    <script>
        let appConfig = null;
        let holidaysByDate = {};
        let announcedPermitUIDs = new Set();
        let permitCheckDate = new Date().toLocaleDateString();
        let rotationInterval = null;
        let currentPageIndex = 0;
        let modalCloseTimer = null;
        let permitMap = null;
        let permitMarkers = [];

        let hasFireDanger = false;
        let hasBurnPermits = false;
        let audioEnabled = false;

        let currentFireEvents = [];
        let currentTownMeetings = [];
        let calendarMonthOffset = 0;

        const alertPlayer = new Audio();
        const voicePlayer = new Audio();

        const flameIcon = L.icon({
            iconUrl: 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0iI2Y5NzQwNiIgc3Ryb2tlPSIjZGMyNjI2IiBzdHJva2Utd2lkdGg9IjEuNSI+PHBhdGggZD0iTT EyIDJDMTIgMiA4IDYuNSA4IDExYzAgNCA0IDggNCA4czQtNCA0LThjMC00LjUtNC05LTQtOXoiLz48cGF0aCBkPSJNMTIgNGMtMS41IDEuNS0yIDQtMiA2cy41IDQuNSAyIDYiLz48cGF0aCBkPSJNMTQuNSAxM2MuNS0xLjUgMS0zLjUgMC01LjUiLz48L3N2Zz4=',
            iconSize: [32, 32],
            iconAnchor: [16, 32],
            popupAnchor: [0, -32],
            tooltipAnchor: [16, -20],
            className: 'flame-marker-icon'
        });

        window.changeMonth = function(delta) {
            calendarMonthOffset += delta;
            renderCalendar(currentFireEvents, currentTownMeetings);
            startRotation();
        };

        function performPruning(pageIndex) {
            setTimeout(() => {
                const pages = [ document.getElementById('page-dashboard'), document.getElementById('page-calendar'), document.getElementById('page-chores') ];
                const activePage = pages[pageIndex];
                if (!activePage) return;

                if (activePage.id === 'page-dashboard') pruneDashboard();
                if (activePage.id === 'page-calendar') pruneCalendar();
                if (activePage.id === 'page-chores') pruneChores();
            }, 200);
        }

        function pruneDashboard() {
            const container = document.getElementById('burnPermitsContainer');
            if (!container) return;
            if (container.clientHeight === 0) return;
            while (container.scrollHeight > container.clientHeight + 2 && container.children.length > 0) {
                container.removeChild(container.lastElementChild);
            }
        }

        function pruneCalendar() {
            const sidebar = document.querySelector('.calendar-sidebar');
            const list = document.getElementById('open-shifts-list');
            if (sidebar && list) {
                if (sidebar.clientHeight === 0) return;
                while (sidebar.scrollHeight > sidebar.clientHeight + 2 && list.querySelectorAll('.open-shift-link').length > 0) {
                    const links = list.querySelectorAll('.open-shift-link');
                    const lastLink = links[links.length - 1];
                    lastLink.parentNode.removeChild(lastLink);
                }
            }
        }

        function pruneChores() {
            const column = document.querySelector('#chores-staff-column .chore-item');
            if (column) {
                const targets = [
                    { id: 'chores-on-duty-later-container', wrapperId: 'chores-on-duty-later-wrapper' },
                    { id: 'chores-on-duty-container', wrapperId: 'chores-on-duty-now-wrapper' },
                    { id: 'town-meetings-list', wrapperId: 'town-meetings-container' },
                    { id: 'dept-events-list', wrapperId: 'dept-events-container' }
                ];
                targets.forEach(target => {
                    const container = document.getElementById(target.id);
                    if (container) {
                        if (column.clientHeight === 0) return;
                        while (column.scrollHeight > column.clientHeight + 2 && container.children.length > 0) {
                            container.removeChild(container.lastElementChild);
                        }
                        if (container.children.length === 0) {
                            const wrapper = document.getElementById(target.wrapperId);
                            if (wrapper) wrapper.style.display = 'none';
                        }
                    }
                });
            }

            const annCol = document.getElementById('chores-duties-column');
            const annCont = document.getElementById('announcements-container');
            if (annCol && annCont) {
                if (annCol.clientHeight === 0) return;
                while (annCol.scrollHeight > annCol.clientHeight + 2 && annCont.children.length > 0) {
                    annCont.removeChild(annCont.lastElementChild);
                }
                if(annCont.children.length === 0) document.getElementById('announcements-wrapper').style.display = 'none';
            }
        }

        window.addEventListener('load', function() {
            const toggleWrapper = document.getElementById('audio-toggle-wrapper');
            if (toggleWrapper) toggleWrapper.style.display = 'block';

            const toggleCheckbox = document.getElementById('audio-toggle-checkbox');
            toggleCheckbox.addEventListener('change', function(e) {
                audioEnabled = e.target.checked;
                if (audioEnabled) {
                    const silentStr = 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA=';
                    alertPlayer.src = silentStr;
                    alertPlayer.play().catch(err => {});
                    voicePlayer.src = silentStr;
                    voicePlayer.play().catch(err => {});
                }
            });

            initializeApp();
        });

        function parseLocalYMD(dateStr) {
            if (!dateStr) return new Date(0);
            const [y, m, d] = dateStr.split('-');
            return new Date(y, m - 1, d);
        }

        function formatYMD(dateObj) {
            const year = dateObj.getFullYear();
            const month = String(dateObj.getMonth() + 1).padStart(2, '0');
            const day = String(dateObj.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        function applyTheme() {
            if (!appConfig || !appConfig.dashboard_settings) return;
            const theme = appConfig.dashboard_settings.theme || 'dark';
            if (theme === 'light') {
                document.body.classList.add('light-theme');
            } else if (theme === 'auto') {
                const h = new Date().getHours();
                if (h >= 7 && h < 19) document.body.classList.add('light-theme');
                else document.body.classList.remove('light-theme');
            } else {
                document.body.classList.remove('light-theme');
            }
        }

        async function initializeApp() {
            initPermitMap();

            try {
                const configResponse = await fetch('api/get_config.php', { cache: 'no-store' });
                if (!configResponse.ok) throw new Error("Config fetch failed");
                appConfig = await configResponse.json();

                applyTheme();

                if (appConfig.headers && appConfig.headers.length === 7) {
                    const headerDivs = document.querySelectorAll('.chores-header div');
                    appConfig.headers.forEach((text, i) => {
                        if(headerDivs[i]) headerDivs[i].innerHTML = text;
                    });
                }
            } catch (e) {
                console.error("Failed to load config, using fallbacks.", e);
                appConfig = {
                    dashboard_settings: { theme: 'dark' },
                    department_info: { name: "Fire Department", stations: [], apparatus: [] },
                    truck_check: { anchor: "2025-07-13", interval: 2 },
                    truck_wash: { anchor: "2025-07-20", interval: 2 },
                    chore_anchor: "2025-07-15",
                    chore_num_indices: 6,
                    everyday_chores: ["Clean Bathrooms", "Empty Trash Cans", "Wash Coffee Pot and Dishes"],
                    headers: ["9-1", "9-2", "9-3", "9-4", "9-5", "9-6<br>Meds", "R4<br>R9"],
                    chores: [
                        {id: 1, name: "Kitchen"}, {id: 2, name: "Dispatch/Vacuum/Offices/Bunks"},
                        {id: 3, name: "Entries/Halls/Windows"}, {id: 4, name: "Bay Floors"},
                        {id: 5, name: "Tool/Gear/Compressor/Gym"}, {id: 6, name: "Atlantic/Stand-by"}
                    ],
                    manual_events: [], announcements: [], special_chores: []
                };
            }

            updateAllData().then(() => {
                startRotation();
            });

            setInterval(updateAllData, 900000);
            const scheduleHourlyUpdate = () => {
                const now = new Date();
                const target = new Date(now);
                target.setMinutes(1, 0, 0);
                if (now.getTime() > target.getTime()) target.setHours(target.getHours() + 1);
                const delay = target.getTime() - now.getTime();
                setTimeout(() => { updateAllData(); setInterval(updateAllData, 3600000); }, delay);
            };
            scheduleHourlyUpdate();
        }

        async function updateAllData() {
            calendarMonthOffset = 0;

            if (appConfig && appConfig.dashboard_settings && appConfig.dashboard_settings.theme === 'auto') {
                applyTheme(); // Refresh auto theme
            }

            try {
                const fireSchedulePromise = fetchFireSchedule();
                const holidaysPromise = loadHolidays();
                const townMeetingsPromise = fetchAllTownMeetings();

                await loadFireDanger();
                await loadBurnPermits();

                currentFireEvents = await fireSchedulePromise;
                currentTownMeetings = await townMeetingsPromise;
                await holidaysPromise;

                renderDashboard(currentFireEvents);
                renderChoresPage(currentFireEvents, currentTownMeetings);
                renderCalendar(currentFireEvents, currentTownMeetings);

                performPruning(currentPageIndex);
            } catch (e) {
                console.error("A critical error occurred in updateAllData:", e);
            }
        }

        async function fetchFireSchedule() {
            const scheduleUrl = `${appConfig.calendar_urls?.main || 'https://calendar.google.com/calendar/ical/c303c9aa08e0a090db126a0b15eb0bc0e8b66cc1af810aa971059b7b01b6d25a@group.calendar.google.com/public/basic.ics'}?nocache=${Date.now()}`;
            const proxyUrl = `api/fetch_calendar.php?url=${encodeURIComponent(scheduleUrl)}&_cb=${Date.now()}`;
            try {
                const response = await fetch(proxyUrl, { headers: { 'Pragma': 'no-cache', 'Cache-Control': 'no-cache' }, cache: 'no-store' });
                if (!response.ok) throw new Error(`Network response was not ok (${response.status})`);
                const icsData = await response.text();
                if (!icsData) throw new Error("ICS data is empty.");
                const jcalData = ICAL.parse(icsData);
                const comp = new ICAL.Component(jcalData);
                return comp.getAllSubcomponents('vevent');
            } catch (error) {
                console.error(`ERROR fetching fire schedule: ${error.message}`);
                return [];
            }
        }

        function manageFireDangerPolling(lastUpdateStr) {
            if (window.fireDangerInterval) {
                clearInterval(window.fireDangerInterval);
            }

            const now = new Date();
            const hour = now.getHours(); // 0-23

            // "Last Update: Apr 12 2026, 8AM" -> roughly check if it contains today's short month+day
            const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
            const todayStr = `${months[now.getMonth()]} ${now.getDate()} ${now.getFullYear()}`;

            const isUpdatedToday = lastUpdateStr.includes(todayStr) || lastUpdateStr.includes(`${months[now.getMonth()]} ${now.getDate()}, ${now.getFullYear()}`);

            let pollInterval = 3600000; // 1 hour default

            // High-Frequency Window (9:00 AM - 10:00 AM EST), assuming system timezone is reasonably close or user wants local time 9-10.
            if (hour === 9 && !isUpdatedToday) {
                pollInterval = 300000; // 5 minutes
            }

            window.fireDangerInterval = setInterval(loadFireDanger, pollInterval);
        }

        async function loadFireDanger() {
            const fireDangerApiUrl = `api/get_fire_danger.php?nocache=${Date.now()}`;
            const meterDiv = document.getElementById('danger-meter');
            const dateDiv = document.getElementById('danger-date');

            let lastUpdateStr = "";

            let riskLevel = "Unknown";

            // Primary: Fetch from mainefireweather api
            try {
                const mwfUrl = `api/fetch_mainefireweather.php?nocache=${Date.now()}`;
                const mwfResp = await fetch(mwfUrl);
                if (mwfResp.ok) {
                    const mwfData = await mwfResp.json();
                    const zone = appConfig.fire_danger_zone || '8';

                    if (mwfData && mwfData.classdays && mwfData.classdays[zone]) {
                        const levelInt = parseInt(mwfData.classdays[zone]);
                        const levelsMap = { 1: "Low", 2: "Moderate", 3: "High", 4: "Very High", 5: "Extreme" };
                        if (levelsMap[levelInt]) {
                            riskLevel = levelsMap[levelInt];
                            lastUpdateStr = mwfData.lastUpdate || "";
                        }
                    }
                }
            } catch (error) {
                console.error(`ERROR fetching from mainefireweather API: ${error.message}`);
            }

            // Secondary: Fetch from email integration
            if (riskLevel === "Unknown") {
                try {
                    const response = await fetch(fireDangerApiUrl);
                    if (response.ok) {
                        const data = await response.json();
                        if (data && data.level && data.level !== "Unknown") {
                            riskLevel = data.level;
                        }
                    }
                } catch (error) {
                    console.error(`ERROR fetching fire danger from email api: ${error.message}`);
                }
            }

            // Fallback to ICS calendar if no email integration data available
            const useEmailDanger = appConfig.email_integration && appConfig.email_integration.danger_address && appConfig.email_integration.danger_address.trim() !== '';

            if (riskLevel === "Unknown" && !useEmailDanger && currentFireEvents && currentFireEvents.length > 0) {
                // Try to parse from the ICS Fire Events schedule
                const now = new Date();
                const todayEvents = currentFireEvents.filter(e => {
                    const event = new ICAL.Event(e);
                    const start = event.startDate.toJSDate();
                    const end = event.endDate.toJSDate();
                    return now >= start && now < end && event.summary.includes("Fire Danger");
                });

                if (todayEvents.length > 0) {
                    const summary = new ICAL.Event(todayEvents[0]).summary;
                    // Try to extract level, e.g. "Today's Fire Danger: High"
                    const parts = summary.split(':');
                    if (parts.length > 1) {
                        riskLevel = parts[1].trim();
                    } else {
                        // Just look for the words
                        const levels = ['Extreme', 'Very High', 'High', 'Moderate', 'Low'];
                        for (const l of levels) {
                            if (summary.toLowerCase().includes(l.toLowerCase())) {
                                riskLevel = l;
                                break;
                            }
                        }
                    }
                }
            }

            if (riskLevel !== "Unknown") {
                hasFireDanger = true;
                document.getElementById('top-section').style.display = 'flex';

                const riskClass = "risk-" + riskLevel.toLowerCase().replace(/ /g, '-');

                meterDiv.textContent = riskLevel;
                meterDiv.className = "danger-meter " + riskClass;
                if (lastUpdateStr !== "") {
                    dateDiv.textContent = `Published by Maine Forest Service (${lastUpdateStr})`;
                } else {
                    dateDiv.textContent = "Published by Maine Forest Service";
                }

                const mapContainer = document.getElementById('danger-map-container');
                const mapIframe = document.getElementById('danger-map-iframe');
                mapIframe.src = "https://mainefireweather.org/index.php";
                mapContainer.style.display = 'block';

                if (meterDiv.dataset.lastLevel !== riskLevel) {
                     announceFireDanger(riskLevel);
                }
                meterDiv.dataset.lastLevel = riskLevel;
            } else {
                hasFireDanger = false;
                document.getElementById('top-section').style.display = 'none';

                meterDiv.textContent = "Unavailable";
                meterDiv.className = "danger-meter";
                dateDiv.textContent = "Will be available once published by the state (usually after 9a).";
                document.getElementById('danger-map-container').style.display = 'none';
                delete meterDiv.dataset.lastLevel;
            }
        }

        async function loadBurnPermits() {
            const today = new Date().toLocaleDateString();
            if (today !== permitCheckDate) {
                announcedPermitUIDs.clear();
                permitCheckDate = today;
            }
            const container = document.getElementById('burnPermitsContainer');

            const useEmailPermits = appConfig.email_integration && appConfig.email_integration.permit_address && appConfig.email_integration.permit_address.trim() !== '';

            try {
                let todaysPermits = [];
                let activePermits = [];

                if (useEmailPermits) {
                    const fetchUrl = `api/get_permits.php?nocache=${Date.now()}`;
                    const response = await fetch(fetchUrl, { headers: { 'Pragma': 'no-cache', 'Cache-Control': 'no-cache' }, cache: 'no-store' });
                    if (!response.ok) { throw new Error(`Network response was not ok`) }

                    const data = await response.json();

                    // Map to expected structure
                    activePermits = data.map(p => {
                        return {
                            uid: p.uid,
                            startDate: { toJSDate: () => new Date(p.created_at) },
                            endDate: { toJSDate: () => new Date(p.expires) },
                            summary: p.type + " at " + p.address,
                            description: "Details:" + p.details,
                            location: p.address,
                            address: p.address,
                            type: p.type,
                            expires: p.expires,
                            details: p.details
                        };
                    });
                    todaysPermits = activePermits;
                } else {
                    const calendarUrl = `${appConfig.calendar_urls?.burn_permits || 'https://calendar.google.com/calendar/ical/permitsburn@gmail.com/public/basic.ics'}?nocache=${Date.now()}`;
                    const fetchUrl = `api/fetch_calendar.php?url=${encodeURIComponent(calendarUrl)}&_cb=${Date.now()}`;

                    const response = await fetch(fetchUrl, { headers: { 'Pragma': 'no-cache', 'Cache-Control': 'no-cache' }, cache: 'no-store' });
                    if (!response.ok) { throw new Error(`Network response was not ok`) }
                    const icsData = await response.text();
                    const jcalData = ICAL.parse(icsData);
                    const comp = new ICAL.Component(jcalData);
                    const allEvents = comp.getAllSubcomponents('vevent');
                    const windowStart = new Date();
                    windowStart.setHours(9, 0, 0, 0);
                    const windowEnd = new Date(windowStart);
                    windowEnd.setDate(windowStart.getDate() + 1);
                    allEvents.forEach(event => {
                        const vevent = new ICAL.Event(event);
                        if (vevent.startDate.toJSDate() < windowEnd && vevent.endDate.toJSDate() > windowStart) {
                            todaysPermits.push(vevent)
                        }
                    });

                    const now = new Date();
                    activePermits = todaysPermits.filter(p => {
                        const start = p.startDate.toJSDate();
                        const end = p.endDate.toJSDate();
                        const summary = p.summary || '';
                        return now >= start && now < end && summary !== "Today's Fire Danger";
                    });
                }

                todaysPermits.forEach(permit => {
                    if (permit.uid && !announcedPermitUIDs.has(permit.uid)) {
                        let address = permit.location ? permit.location.split(',')[0].trim() : 'Address not provided';
                        announceNewBurnPermit(address);
                        announcedPermitUIDs.add(permit.uid);
                    }
                });

                activePermits.sort((a, b) => a.startDate.toJSDate() - b.startDate.toJSDate());

                container.innerHTML = '';
                if (activePermits.length > 0) {
                    hasBurnPermits = true;
                    activePermits.forEach(e => renderBurnPermitEvent(e, container));
                } else {
                    hasBurnPermits = false;
                    container.innerHTML = '<p class="no-events">No active online burn permits at this time.</p>';
                }

                updatePermitMap(activePermits);

            } catch (error) {
                console.error(`ERROR fetching burn permits: ${error.message}`);
                hasBurnPermits = false;
                container.innerHTML = '<p class="no-events">Could not load burn permits.</p>';
                updatePermitMap([]);
            }
        }
    </script>

    <script>
        async function playGoogleTTS(textToRead) {
            if (!audioEnabled) return;
            try {
                const response = await fetch('api/speak.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ text: textToRead }) });
                if (!response.ok) { fallbackTTS(textToRead); return; }
                const arrayBuffer = await response.arrayBuffer();
                const blob = new Blob([arrayBuffer], { type: 'audio/mpeg' });
                const audioUrl = URL.createObjectURL(blob);
                voicePlayer.src = audioUrl;
                voicePlayer.play().catch(e => console.error("Could not play TTS.", e));
            } catch (err) { fallbackTTS(textToRead); }
        }

        function fallbackTTS(textToRead) {
            if (!audioEnabled || !window.speechSynthesis) return;
            window.speechSynthesis.cancel();
            const utterance = new SpeechSynthesisUtterance(textToRead);
            utterance.lang = 'en-US';
            utterance.rate = 0.9;
            window.speechSynthesis.speak(utterance);
        }

        function announceFireDanger(level) {
            if (!audioEnabled) return;
            try {
                alertPlayer.src = 'https://cdn.freesound.org/previews/219/219244_4032688-lq.mp3';
                alertPlayer.volume = 0.5;
                alertPlayer.play().catch(e => {});
                alertPlayer.onended = () => { playGoogleTTS(`Today's Fire Danger is: ${level}`); };
            } catch (e) { console.error(e); }
        }

        function announceNewBurnPermit(address) {
            if (!audioEnabled) return;
            try {
                alertPlayer.src = 'https://cdn.freesound.org/previews/415/415763_6142149-lq.mp3';
                alertPlayer.volume = 0.6;
                alertPlayer.play().catch(e => {});
                alertPlayer.onended = () => { playGoogleTTS(`New Permit Issued for ${address}`); };
            } catch (e) { console.error(e); }
        }

        function getNthWeekdayOfMonth(year, month, weekday, n) {
            let d = new Date(year, month, 1);
            let count = 0;
            while (d.getMonth() === month) {
                if (d.getDay() === weekday) {
                    count++;
                    if (count === n) return new Date(d);
                }
                d.setDate(d.getDate() + 1);
            }
            return null;
        }

        function getTodaysSpecialChores(dateObj) {
            let choresToday = [];
            if (!appConfig || !appConfig.special_chores) return choresToday;

            const targetYMD = formatYMD(dateObj);

            appConfig.special_chores.forEach(sc => {
                const baseDate = parseLocalYMD(sc.start_date);
                const limitDate = sc.end_type === 'date' && sc.end_date_bound ? parseLocalYMD(sc.end_date_bound) : new Date(2100,0,1);
                limitDate.setHours(23,59,59);

                if (dateObj > limitDate || formatYMD(baseDate) > targetYMD) return;

                let current = new Date(baseDate);
                let occurrences = 0;
                const maxOccurrences = sc.end_type === 'occurrences' ? parseInt(sc.end_occurrences) : 9999;

                if (sc.recurrence === 'none') {
                    if (formatYMD(current) === targetYMD) choresToday.push(sc.name);
                } else if (sc.recurrence === 'days') {
                    let interval = parseInt(sc.recur_interval) || 1;
                    while (current <= limitDate && occurrences < maxOccurrences) {
                        if (formatYMD(current) === targetYMD) { choresToday.push(sc.name); break; }
                        current.setDate(current.getDate() + interval);
                        occurrences++;
                    }
                } else if (sc.recurrence === 'weeks') {
                    let interval = parseInt(sc.recur_interval) || 1;
                    while (current <= limitDate && occurrences < maxOccurrences) {
                        if (sc.recur_weekdays && sc.recur_weekdays.includes(current.getDay().toString())) {
                            if (formatYMD(current) === targetYMD) { choresToday.push(sc.name); break; }
                            occurrences++;
                        }
                        current.setDate(current.getDate() + 1);
                        if (current.getDay() === 0) current.setDate(current.getDate() + (interval - 1) * 7);
                    }
                } else if (sc.recurrence === 'monthly_date') {
                    let interval = parseInt(sc.recur_interval) || 1;
                    let targetD = parseInt(sc.recur_month_date) || 1;
                    current.setDate(1);
                    while (current <= limitDate && occurrences < maxOccurrences) {
                        let tmp = new Date(current.getFullYear(), current.getMonth(), targetD);
                        if (tmp >= baseDate && tmp <= limitDate) {
                            if (formatYMD(tmp) === targetYMD) { choresToday.push(sc.name); break; }
                            occurrences++;
                        }
                        current.setMonth(current.getMonth() + interval);
                    }
                } else if (sc.recurrence === 'monthly_nth') {
                    let interval = parseInt(sc.recur_interval) || 1;
                    let targetNth = parseInt(sc.recur_month_nth);
                    let targetDay = parseInt(sc.recur_month_nth_day);
                    current.setDate(1);
                    while (current <= limitDate && occurrences < maxOccurrences) {
                        let nThDay = getNthWeekdayOfMonth(current.getFullYear(), current.getMonth(), targetDay, targetNth);
                        if (nThDay && nThDay >= baseDate && nThDay <= limitDate) {
                            if (formatYMD(nThDay) === targetYMD) { choresToday.push(sc.name); break; }
                            occurrences++;
                        }
                        current.setMonth(current.getMonth() + interval);
                    }
                }
            });
            return choresToday;
        }

        function getManualEvents(startWindow, endWindow) {
            let instances = [];
            if (!appConfig || !appConfig.manual_events) return instances;

            appConfig.manual_events.forEach(evt => {
                const baseDate = parseLocalYMD(evt.start_date);
                const limitDate = parseLocalYMD(evt.end_date);
                limitDate.setHours(23,59,59);

                if (evt.start_time && evt.all_day === false) {
                    const [h, min] = evt.start_time.split(':');
                    baseDate.setHours(h, min, 0, 0);
                } else {
                    baseDate.setHours(0, 0, 0, 0);
                }

                let duration = 0;
                if (evt.end_time && evt.all_day === false) {
                    let endD = new Date(baseDate);
                    const [eh, emin] = evt.end_time.split(':');
                    endD.setHours(eh, emin, 0, 0);
                    if (endD < baseDate) endD.setDate(endD.getDate() + 1);
                    duration = endD - baseDate;
                }

                const addInst = (dObj) => {
                    let s = new Date(dObj);
                    let e = new Date(s.getTime() + duration);
                    if (s < endWindow && e > startWindow) {
                        instances.push({ type: 'dept', summary: evt.title, eventType: evt.event_type, location: evt.location, startDate: s, endDate: e, allDay: evt.all_day });
                    }
                };

                let current = new Date(baseDate);

                if (evt.recurrence === 'none') {
                    if(current <= limitDate) addInst(current);
                } else if (evt.recurrence === 'days') {
                    let interval = parseInt(evt.recur_interval) || 1;
                    while (current <= limitDate && current < endWindow) {
                        addInst(current);
                        current.setDate(current.getDate() + interval);
                    }
                } else if (evt.recurrence === 'weeks') {
                    let interval = parseInt(evt.recur_interval) || 1;
                    while (current <= limitDate && current < endWindow) {
                        if (evt.recur_weekdays && evt.recur_weekdays.includes(current.getDay().toString())) {
                            addInst(current);
                        }
                        current.setDate(current.getDate() + 1);
                        if(current.getDay() === 0) current.setDate(current.getDate() + (interval - 1) * 7);
                    }
                } else if (evt.recurrence === 'monthly_date') {
                    let interval = parseInt(evt.recur_interval) || 1;
                    let targetD = parseInt(evt.recur_month_date) || 1;
                    current.setDate(1);
                    while (current <= limitDate && current < endWindow) {
                        let tmp = new Date(current.getFullYear(), current.getMonth(), targetD);
                        if (tmp >= baseDate && tmp <= limitDate) {
                            tmp.setHours(baseDate.getHours(), baseDate.getMinutes(), 0, 0);
                            addInst(tmp);
                        }
                        current.setMonth(current.getMonth() + interval);
                    }
                } else if (evt.recurrence === 'monthly_nth') {
                    let interval = parseInt(evt.recur_interval) || 1;
                    let targetNth = parseInt(evt.recur_month_nth);
                    let targetDay = parseInt(evt.recur_month_nth_day);
                    current.setDate(1);
                    while (current <= limitDate && current < endWindow) {
                        let nThDay = getNthWeekdayOfMonth(current.getFullYear(), current.getMonth(), targetDay, targetNth);
                        if (nThDay && nThDay >= baseDate && nThDay <= limitDate) {
                            nThDay.setHours(baseDate.getHours(), baseDate.getMinutes(), 0, 0);
                            addInst(nThDay);
                        }
                        current.setMonth(current.getMonth() + interval);
                    }
                }
            });
            return instances;
        }

        function createMeetingEventHtml(meeting, isDeptEvent=false) {
            let summary = meeting.summary || 'Meeting';

            if (isDeptEvent && meeting.eventType) {
                if (meeting.eventType === 'Room Rental') {
                    summary = `Room Rental${meeting.location ? ' (' + meeting.location + ')' : ''} - ${summary}`;
                } else if (meeting.eventType !== 'Training') {
                    summary = `${summary}${meeting.location ? ' - ' + meeting.location : ''}`;
                }
            }

            let timeStr = 'All Day';
            if (!meeting.allDay) {
                let startTime = ''; let endTime = '';
                if (meeting.startDate) {
                    const s = meeting.startDate.toJSDate ? meeting.startDate.toJSDate() : new Date(meeting.startDate);
                    if(s.getHours()!==0 || s.getMinutes()!==0) startTime = s.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }).replace(' AM','a').replace(' PM','p');
                }
                if (meeting.endDate) {
                    const e = meeting.endDate.toJSDate ? meeting.endDate.toJSDate() : new Date(meeting.endDate);
                    if(e.getHours()!==0 || e.getMinutes()!==0) endTime = e.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }).replace(' AM','a').replace(' PM','p');
                }
                timeStr = (startTime && endTime) ? `${startTime} - ${endTime}` : (startTime || 'All Day');
            }

            const dotColor = isDeptEvent ? '#20c997' : '#007bff';
            return `<div class="event" style="padding: 15px 18px; border-left: 4px solid ${dotColor};">
                <div class="event-left"><div class="event-name" style="font-size:1em;">${summary}</div></div>
                <div class="event-right"><div class="event-until" style="font-size:0.9em;">${timeStr}</div></div>
            </div>`;
        }

        async function fetchAllTownMeetings() {
            const calendarUrl = `${appConfig.calendar_urls?.town_meetings || 'https://calendar.google.com/calendar/ical/amarshall@oaklandme.gov/public/basic.ics'}?nocache=${Date.now()}`;
            const proxyUrl = `api/fetch_calendar.php?url=${encodeURIComponent(calendarUrl)}&_cb=${Date.now()}`;
            const allMeetings = [];
            try {
                const response = await fetch(proxyUrl, { headers: { 'Pragma': 'no-cache', 'Cache-Control': 'no-cache' }, cache: 'no-store' });
                if (!response.ok) throw new Error('Failed to fetch town meetings calendar');
                const icsData = await response.text();
                const jcalData = ICAL.parse(icsData);
                const comp = new ICAL.Component(jcalData);
                comp.getAllSubcomponents('vevent').forEach(veventComp => {
                    const vevent = new ICAL.Event(veventComp);
                    if (vevent.location && vevent.location.includes("15 Fairfield St")) {
                        if (vevent.isRecurring()) {
                            const nextYear = new Date();
                            nextYear.setFullYear(nextYear.getFullYear() + 1);
                            const iterator = vevent.iterator();
                            let next;
                            while ((next = iterator.next()) && next.toJSDate() < nextYear) {
                                allMeetings.push(vevent.getOccurrenceDetails(next));
                            }
                        } else {
                            allMeetings.push(vevent);
                        }
                    }
                });
            } catch (error) {
                console.error("Could not load town meetings:", error);
            }
            return allMeetings;
        }

        function renderChoresPage(allEvents, townMeetings = []) {
            const now = new Date();
            const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 0, 0, 0);
            const endOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 23, 59, 59);
            const todayStr = formatYMD(now);

            const annWrap = document.getElementById('announcements-wrapper');
            const annCont = document.getElementById('announcements-container');
            if (annWrap && annCont && appConfig.announcements) {
                annCont.innerHTML = '';
                let hasAnn = false;
                appConfig.announcements.forEach(ann => {
                    if (ann.start_date <= todayStr && ann.end_date >= todayStr) {
                        hasAnn = true;
                        annCont.innerHTML += `<div class="announcement-card announcement-content">${ann.content}</div>`;
                    }
                });
                annWrap.style.display = hasAnn ? 'flex' : 'none';
            }

            const townContainer = document.getElementById('town-meetings-container');
            const townListDiv = document.getElementById('town-meetings-list');
            if (townContainer && townListDiv) {
                townContainer.style.display = 'none';
                townListDiv.innerHTML = '';
                if (townMeetings && townMeetings.length > 0) {
                    const todaysMeetings = townMeetings.filter(meeting => {
                        const eventStart = meeting.startDate.toJSDate();
                        const eventEnd = meeting.endDate.toJSDate();
                        return eventStart < endOfToday && eventEnd > startOfToday;
                    });
                    if (todaysMeetings.length > 0) {
                        todaysMeetings.sort((a, b) => a.startDate.toJSDate() - b.startDate.toJSDate());
                        todaysMeetings.forEach(meeting => {
                            townListDiv.innerHTML += createMeetingEventHtml(meeting, false);
                        });
                        townContainer.style.display = 'flex';
                    }
                }
            }

            const deptContainer = document.getElementById('dept-events-container');
            const deptListDiv = document.getElementById('dept-events-list');
            if (deptContainer && deptListDiv) {
                deptContainer.style.display = 'none';
                deptListDiv.innerHTML = '';

                const todaysDeptEvents = getManualEvents(startOfToday, endOfToday);
                if (todaysDeptEvents.length > 0) {
                    todaysDeptEvents.sort((a, b) => a.startDate - b.startDate);
                    todaysDeptEvents.forEach(meeting => {
                        deptListDiv.innerHTML += createMeetingEventHtml(meeting, true);
                    });
                    deptContainer.style.display = 'flex';
                }
            }

            const choreList = document.getElementById('chore-list');
            choreList.innerHTML = '';

            renderVehicleTasks(now, choreList);
            renderNumberedChores(now, choreList);
            renderSpecialChores(now, choreList);
            renderEverydayChores(choreList);

            const holidayContainer = document.getElementById('holiday-container');
            const nationalDayP = document.getElementById('national-day');
            const todayKey = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
            const holiday = holidaysByDate[todayKey];
            if (holiday) {
                nationalDayP.textContent = `Happy ${holiday}!`;
                holidayContainer.style.display = 'block';
            } else {
                holidayContainer.style.display = 'none';
            }

            const onDutyContainer = document.getElementById('chores-on-duty-container');
            const onDutyLaterContainer = document.getElementById('chores-on-duty-later-container');
            const onDutyLaterWrapper = document.getElementById('chores-on-duty-later-wrapper');

            onDutyContainer.innerHTML = '';
            onDutyLaterContainer.innerHTML = '';

            const windowStart = new Date();
            windowStart.setHours(7, 0, 0, 0);
            if (now.getHours() < 7) {
                windowStart.setDate(windowStart.getDate() - 1);
            }
            const windowEnd = new Date(windowStart);
            windowEnd.setDate(windowStart.getDate() + 1);
            const searchStart = new Date(windowStart);
            searchStart.setDate(searchStart.getDate() - 1);

            let onDutyNow = [], onDutyLater = [];
            (allEvents || []).forEach(eventData => {
                const vevent = new ICAL.Event(eventData);
                processEventForDashboard(vevent, searchStart, windowEnd, now, onDutyNow, onDutyLater);
            });

            onDutyNow.sort((a, b) => a.startDate.toJSDate() - b.startDate.toJSDate());
            const combinedOnDutyNow = combineConsecutiveShifts(onDutyNow);
            if (combinedOnDutyNow.length > 0) {
                combinedOnDutyNow.forEach(e => renderEvent(e, onDutyContainer));
            } else {
                onDutyContainer.innerHTML = '<p class="no-events">No personnel on duty.</p>';
            }

            onDutyLater.sort((a, b) => a.startDate.toJSDate() - b.startDate.toJSDate());
            const combinedOnDutyLater = combineConsecutiveShifts(onDutyLater);
            if (combinedOnDutyLater.length > 0) {
                onDutyLaterWrapper.style.display = 'block';
                combinedOnDutyLater.forEach(e => renderEvent(e, onDutyLaterContainer));
            } else {
                onDutyLaterWrapper.style.display = 'none';
            }
        }

        async function loadHolidays() {
            const holidayUrl = `${appConfig.calendar_urls?.holidays || 'https://calendars.icloud.com/holidays/us_en-us.ics'}?nocache=${Date.now()}`;
            const proxyUrl = `api/fetch_calendar.php?url=${encodeURIComponent(holidayUrl)}&_cb=${Date.now()}`;
            try {
                const response = await fetch(proxyUrl, { headers: { 'Pragma': 'no-cache', 'Cache-Control': 'no-cache' }, cache: 'no-store' });
                if (!response.ok) return;
                const icsData = await response.text();
                const jcalData = ICAL.parse(icsData);
                const comp = new ICAL.Component(jcalData);
                const allEvents = comp.getAllSubcomponents('vevent');
                holidaysByDate = {};
                allEvents.forEach(event => {
                    const vevent = new ICAL.Event(event);
                    if (vevent.startDate.isDate) {
                        const startDate = vevent.startDate;
                        const dateKey = `${startDate.year}-${String(startDate.month).padStart(2,'0')}-${String(startDate.day).padStart(2,'0')}`;
                        holidaysByDate[dateKey] = vevent.summary;
                    }
                });
            } catch (error) {
                console.error("Could not load holiday data:", error);
            }
        }

        function getCleanNameFromSummary(summary) {
            if (!summary) return '';
            const timeRegex = /\s*\d{1,2}(:\d{2})?\s*(am|pm|a|p)?\s*-\s*\d{1,2}(:\d{2})?\s*(am|pm|a|p)?/gi;
            let cleaned = summary.replace(timeRegex, '').trim();
            const roles = ["Career", "Chief", "Per-Diem", "Night Duty"];
            for (const role of roles) {
                const roleRegex = new RegExp(`\\s*${role}\\s*`, 'i');
                cleaned = cleaned.replace(roleRegex, '').trim();
            }
            return cleaned.trim();
        }


        function renderVehicleTasks(now, choreList) {
            const dayOfWeek = now.getDay();
            const rawHeaders = appConfig.headers ? appConfig.headers.map(h => (h || '').replace(/<[^>]*>?/gm, ' ').trim()) : ["9-1", "9-2", "9-3", "9-4", "9-5", "9-6 Meds", "R4 & R9"];

            let vehicleTasks = [];
            if(isTruckCheckWeek(now)) vehicleTasks.push("Check " + rawHeaders[dayOfWeek]);
            if(isTruckWashWeek(now)) vehicleTasks.push("Wash " + rawHeaders[dayOfWeek]);
            if(vehicleTasks.length > 0) {
                choreList.innerHTML += `<li>${vehicleTasks.join(' & ')}</li>`;
            }
            if (dayOfWeek === 5) { choreList.innerHTML += `<li>Complete Medication Logs</li>`; }
        }

        function renderNumberedChores(now, choreList) {
            const choreNum = getChoreNumber(now, appConfig);
            const todaysChores = appConfig.chores ? appConfig.chores.filter(c => c.id == choreNum) : [];
            if (todaysChores.length > 0) {
                todaysChores.forEach(c => { choreList.innerHTML += `<li>Clean ${c.name} (#${choreNum})</li>`; });
            } else {
                 choreList.innerHTML += `<li>Clean (#${choreNum})</li>`;
            }
        }

        function renderSpecialChores(now, choreList) {
            const todaysSpecialChores = getTodaysSpecialChores(now);
            todaysSpecialChores.forEach(scName => {
                choreList.innerHTML += `<li style="color:#20c997;">${scName}</li>`;
            });
        }

        function renderEverydayChores(choreList) {
            const everyDayTasks = appConfig.everyday_chores || ["Clean Bathrooms", "Empty Trash Cans", "Wash Coffee Pot and Dishes"];
            everyDayTasks.forEach(task => { choreList.innerHTML += `<li>${task}</li>`; });
        }

        function combineConsecutiveShifts(eventList) {
            if (eventList.length < 2) return eventList;
            const combinedList = [];
            for (let i = 0; i < eventList.length; i++) {
                let currentEvent = eventList[i];
                if (i + 1 < eventList.length) {
                    let nextEvent = eventList[i + 1];
                    if (getCleanNameFromSummary(currentEvent.summary) === getCleanNameFromSummary(nextEvent.summary) && currentEvent.endDate.toJSDate().getTime() === nextEvent.startDate.toJSDate().getTime()) {
                        const combinedEvent = { summary: currentEvent.summary, startDate: currentEvent.startDate, endDate: nextEvent.endDate, };
                        currentEvent = combinedEvent;
                        i++;
                    }
                }
                combinedList.push(currentEvent);
            }
            return combinedList;
        }

        function renderDashboard(allEvents) {
        }

        function isTruckCheckWeek(date) {
            if (typeof dateUtils !== 'undefined' && typeof dateUtils.isTruckCheckWeek === 'function') {
                return dateUtils.isTruckCheckWeek(date, typeof appConfig !== 'undefined' ? appConfig : null);
            }
            // Fallback just in case
            if (!appConfig || !appConfig.truck_check) return true;
            const [year, month, day] = (appConfig.truck_check.anchor || "2025-07-13").split('-');
            const anchorTime = new Date(year, month - 1, day, 0, 0, 0).getTime();
            const targetTime = new Date(date.getFullYear(), date.getMonth(), date.getDate() - date.getDay(), 0, 0, 0).getTime();
            const diffWeeks = Math.floor(Math.round((targetTime - anchorTime) / 86400000) / 7);
            return diffWeeks % parseInt(appConfig.truck_check.interval || 2) === 0;
        }

        function isTruckWashWeek(date) {
            if (typeof dateUtils !== 'undefined' && typeof dateUtils.isTruckWashWeek === 'function') {
                return dateUtils.isTruckWashWeek(date, typeof appConfig !== 'undefined' ? appConfig : null);
            }
            // Fallback just in case
            if (!appConfig || !appConfig.truck_wash) return false;
            const [year, month, day] = (appConfig.truck_wash.anchor || "2025-07-20").split('-');
            const anchorTime = new Date(year, month - 1, day, 0, 0, 0).getTime();
            const targetTime = new Date(date.getFullYear(), date.getMonth(), date.getDate() - date.getDay(), 0, 0, 0).getTime();
            const diffWeeks = Math.floor(Math.round((targetTime - anchorTime) / 86400000) / 7);
            return diffWeeks % parseInt(appConfig.truck_wash.interval || 2) === 0;
        }

        const { getChoreNumber } = window;


        function formatShortTime(date) {
            let hours = date.getHours();
            let minutes = date.getMinutes();
            const ampm = hours >= 12 ? 'p' : 'a';
            hours = hours % 12;
            hours = hours ? hours : 12;
            const minStr = minutes === 0 ? '' : `:${minutes.toString().padStart(2, '0')}`;
            return `${hours}${minStr}${ampm}`;
        }

        function mergeAndSort(roleArray) {
            if (roleArray.length === 0) return roleArray;

            roleArray.sort((a, b) => a.rawStart.getTime() - b.rawStart.getTime());

            const merged = [];
            let current = roleArray[0];

            for (let i = 1; i < roleArray.length; i++) {
                const next = roleArray[i];
                if (current.name === next.name && current.rawEnd.getTime() === next.rawStart.getTime()) {
                    current.rawEnd = next.rawEnd;
                    current.timeStr = `${formatShortTime(current.rawStart)}-${formatShortTime(current.rawEnd)}`;
                } else {
                    merged.push(current);
                    current = next;
                }
            }
            merged.push(current);
            return merged;
        }

        function buildRoleHtml(roleArray, roleType, prefix, cssClass) {
            if (roleArray.length === 0) {
                return `<div class="calendar-event ${cssClass} event-open" title="${roleType}: Open">${prefix}: Open</div>`;
            }
            let html = '';
            roleArray.forEach(item => {
                let text = `${prefix}: ${item.name}`;
                if (roleArray.length > 1) {
                    text += ` (${item.timeStr})`;
                }
                html += `<div class="calendar-event ${cssClass}" title="${roleType}: ${item.name} ${item.timeStr}">${text}</div>`;
            });
            return html;
        }

        function parseCalendarEvents(allFireEvents, townMeetings, manualEvents, firstDayOfGrid, maxParseDate, maxPublishedDateRef) {
            const eventsByDate = {};

            (allFireEvents || []).forEach(event => {
                const vevent = new ICAL.Event(event);
                const summary = (vevent.summary || '').toLowerCase();
                const isShift = summary.includes('career') || summary.includes('per-diem') || summary.includes('night duty');

                if (!vevent.isRecurring() && isShift) {
                    const sDate = vevent.startDate.toJSDate();
                    if (sDate > maxPublishedDateRef.date) maxPublishedDateRef.date = sDate;
                }

                const processOccurrence = (occurrence) => {
                    const jsDate = occurrence.startDate.toJSDate();
                    const endJS = occurrence.endDate.toJSDate();
                    const dateKey = formatYMD(jsDate);
                    if (!eventsByDate[dateKey]) eventsByDate[dateKey] = [];
                    eventsByDate[dateKey].push({
                        type: 'fire',
                        summary: vevent.summary || '',
                        start: jsDate,
                        end: endJS
                    });
                };

                if (vevent.isRecurring()) {
                    const iterator = vevent.iterator();
                    let next;
                    while ((next = iterator.next()) && next.toJSDate() < maxParseDate) {
                        processOccurrence(vevent.getOccurrenceDetails(next));
                    }
                } else {
                     if (vevent.startDate.toJSDate() < maxParseDate) {
                         processOccurrence(vevent);
                     }
                }
            });

            townMeetings.forEach(meeting => {
                const eventStart = meeting.startDate.toJSDate();
                if (eventStart >= firstDayOfGrid && eventStart < maxParseDate) {
                    const dateKey = formatYMD(eventStart);
                    if (!eventsByDate[dateKey]) eventsByDate[dateKey] = [];
                    eventsByDate[dateKey].push({ type: 'meeting', summary: meeting.summary || 'Meeting', startDate: meeting.startDate });
                }
            });

            manualEvents.forEach(evt => {
                const dateKey = formatYMD(evt.startDate);
                if (!eventsByDate[dateKey]) eventsByDate[dateKey] = [];
                let s = evt.summary;
                if (evt.eventType === 'Room Rental') s = `Rental${evt.location?' ('+evt.location+')':''} - ${s}`;
                eventsByDate[dateKey].push({ type: 'dept', summary: s, startDate: evt.startDate, allDay: evt.allDay });
            });

            return eventsByDate;
        }

        function renderCalendarDays(grid, daysToRender, firstDayOfGrid, targetMonth, todayKey, eventsByDate, maxPubDay, timeRegex) {
            let currentDay = new Date(firstDayOfGrid);
            let allDaysHtml = '';

            for (let i = 0; i < daysToRender; i++) {
                const dateKey = formatYMD(currentDay);
                const dayEvents = eventsByDate[dateKey] || [];
                let dayNumberDisplay;
                let dayClass = 'calendar-day';

                if (dateKey === todayKey) dayClass += ' is-today';
                if (currentDay.getMonth() !== targetMonth.getMonth()) dayClass += ' other-month';

                if (i === 0 || currentDay.getDate() === 1) {
                    dayClass += ' new-month';
                    const monthName = currentDay.toLocaleString('default', { month: 'short' });
                    dayNumberDisplay = `<span>${monthName} ${currentDay.getDate()}</span>`;
                } else {
                    dayNumberDisplay = `<span>${currentDay.getDate()}</span>`;
                }

                if (currentDay.getDay() === 0) {
                    let icons = [];
                    if(isTruckCheckWeek(currentDay)) icons.push('✅');
                    if(isTruckWashWeek(currentDay)) icons.push('🧽');
                    if(icons.length > 0) {
                        dayNumberDisplay = `<span>${icons.join(' ')} ${dayNumberDisplay.replace(/<\/?span>/g, '')}</span>`;
                    }
                }

                const choreNum = getChoreNumber(currentDay, appConfig);
                let dayHtml = `<div class="${dayClass}"><div class="day-number">${dayNumberDisplay}<span class="chore-number">${choreNum}</span></div>`;

                const isUnpublished = currentDay > maxPubDay;

                if (isUnpublished) {
                    dayHtml += `<div class="calendar-event event-unpublished" title="Schedule not yet published">Not Published</div>`;
                } else {
                    const fireEvents = dayEvents.filter(e => e.type === 'fire');
                    const roles = { career: [], perDiem: [], nightDuty: [] };

                    if (fireEvents.length > 0) {
                        fireEvents.forEach(event => {
                            const summary = (event.summary || '').toLowerCase();
                            const name = (event.summary || '').replace(timeRegex, '').replace(/career|per-diem|night duty/ig, '').replace(/-/g, '').trim();
                            const timeStr = `${formatShortTime(event.start)}-${formatShortTime(event.end)}`;

                            if (summary.includes('career')) roles.career.push({ name, timeStr, rawStart: event.start, rawEnd: event.end });
                            if (summary.includes('per-diem')) roles.perDiem.push({ name, timeStr, rawStart: event.start, rawEnd: event.end });
                            if (summary.includes('night duty')) roles.nightDuty.push({ name, timeStr, rawStart: event.start, rawEnd: event.end });
                        });
                    }

                    let careerMerged = mergeAndSort(roles.career);
                    let perDiemMerged = mergeAndSort(roles.perDiem);
                    let nightDutyMerged = mergeAndSort(roles.nightDuty);

                    dayHtml += buildRoleHtml(careerMerged, 'Career', 'C', 'event-career');
                    dayHtml += buildRoleHtml(perDiemMerged, 'Per Diem', 'P', 'event-per-diem');
                    dayHtml += buildRoleHtml(nightDutyMerged, 'Night Duty', 'N', 'event-night-duty');
                }

                const meetings = dayEvents.filter(e => e.type === 'meeting');
                meetings.sort((a, b) => a.startDate.toJSDate() - b.startDate.toJSDate());
                if (meetings.length > 0) {
                    meetings.forEach(meeting => {
                        const time = meeting.startDate.toJSDate().toLocaleTimeString([], { hour: 'numeric', minute:'2-digit' }).replace(' AM','a').replace(' PM','p');
                        dayHtml += `<div class="calendar-event event-town-meeting" title="${meeting.summary}">${time} - ${meeting.summary}</div>`;
                    });
                }

                const deptEvents = dayEvents.filter(e => e.type === 'dept');
                if (deptEvents.length > 0) {
                    deptEvents.forEach(evt => {
                        let timeStr = "";
                        if(!evt.allDay && (evt.startDate.getHours() !== 0 || evt.startDate.getMinutes() !== 0)) {
                            timeStr = evt.startDate.toLocaleTimeString([], { hour: 'numeric', minute:'2-digit' }).replace(' AM','a').replace(' PM','p') + " - ";
                        }
                        dayHtml += `<div class="calendar-event event-dept" title="${evt.summary}">${timeStr}${evt.summary}</div>`;
                    });
                }

                dayHtml += `</div>`;
                allDaysHtml += dayHtml;
                currentDay.setDate(currentDay.getDate() + 1);
            }
            grid.innerHTML = allDaysHtml;
        }

        function renderOpenShifts(openShiftsList, todayInET, maxPubDay, eventsByDate) {
            const allOpenShifts = [];

            let checkDate = new Date(todayInET);
            checkDate.setHours(0,0,0,0);

            for (let i = 0; i < 60; i++) {
                if (checkDate > maxPubDay) {
                    checkDate.setDate(checkDate.getDate() + 1);
                    continue;
                }

                const dateKey = formatYMD(checkDate);
                const dayFireEvents = (eventsByDate[dateKey] || []).filter(e => e.type === 'fire');

                const rolesCount = { career: 0, perDiem: 0, nightDuty: 0 };
                if (dayFireEvents.length > 0) {
                    dayFireEvents.forEach(event => {
                        const summary = (event.summary || '').toLowerCase();
                        if (summary.includes('career')) rolesCount.career++;
                        if (summary.includes('per-diem')) rolesCount.perDiem++;
                        if (summary.includes('night duty')) rolesCount.nightDuty++;
                    });
                }

                const dateString = checkDate.toLocaleDateString('en-US', { weekday: 'short', month: 'numeric', day: 'numeric'});

                if (rolesCount.career === 0) { allOpenShifts.push({ date: dateString, role: 'Career', time: '7a - 7a' }); }
                if (rolesCount.perDiem === 0) { allOpenShifts.push({ date: dateString, role: 'Per Diem', time: '7a - 5p' }); }
                if (rolesCount.nightDuty === 0) { allOpenShifts.push({ date: dateString, role: 'Night Duty', time: '5p - 7a' }); }

                checkDate.setDate(checkDate.getDate() + 1);
            }

            if (allOpenShifts.length > 0) {
                let openShiftsHtml = '';
                allOpenShifts.forEach(shift => {
                    openShiftsHtml += `
                    <a href="https://whentowork.com/logins.htm" target="_blank" class="open-shift-link">
                        <div class="open-shift-item">
                            <div class="open-shift-date">${shift.date}</div>
                            <div class="open-shift-right">
                                <div class="open-shift-role">${shift.role}</div>
                                <div class="open-shift-time">${shift.time}</div>
                            </div>
                        </div>
                    </a>`;
                });
                openShiftsList.innerHTML = openShiftsHtml;
            } else {
                openShiftsList.innerHTML = '<p class="no-events" style="font-size: 10pt;">No open shifts found.</p>';
            }
        }

        function renderCalendar(allFireEvents, townMeetings = []) {
            const timeRegex = /\s*\d{1,2}(:\d{2})?\s*(am|pm|a|p)?\s*-\s*\d{1,2}(:\d{2})?\s*(am|pm|a|p)?/gi;
            const now = new Date();
            const todayInET = new Date(now.toLocaleString('en-US', { timeZone: 'America/New_York' }));

            const targetMonth = new Date(todayInET.getFullYear(), todayInET.getMonth() + calendarMonthOffset, 1);

            let firstDayOfGrid = new Date(targetMonth);
            firstDayOfGrid.setDate(1 - firstDayOfGrid.getDay());
            firstDayOfGrid.setHours(0, 0, 0, 0);

            const startMonthStr = targetMonth.toLocaleString('default', { month: 'long' });
            const startYearStr = targetMonth.getFullYear();

            document.getElementById('calendar-month-year').textContent = `${startMonthStr} ${startYearStr}`;

            const daysInMonth = new Date(targetMonth.getFullYear(), targetMonth.getMonth() + 1, 0).getDate();
            const startOffset = targetMonth.getDay();
            const weeks = Math.ceil((startOffset + daysInMonth) / 7);
            const daysToRender = weeks * 7;

            const grid = document.getElementById('calendar-grid');
            grid.style.gridTemplateRows = `repeat(${weeks}, minmax(0, 1fr))`;

            const calendarEndDate = new Date(firstDayOfGrid);
            calendarEndDate.setDate(calendarEndDate.getDate() + daysToRender);

            const parsingEndDate = new Date(todayInET);
            parsingEndDate.setDate(parsingEndDate.getDate() + 75);
            const maxParseDate = parsingEndDate > calendarEndDate ? parsingEndDate : calendarEndDate;

            const manualEvents = getManualEvents(firstDayOfGrid, maxParseDate);

            let maxPublishedDateRef = { date: new Date(0) };
            const eventsByDate = parseCalendarEvents(allFireEvents, townMeetings, manualEvents, firstDayOfGrid, maxParseDate, maxPublishedDateRef);
            let maxPublishedDate = maxPublishedDateRef.date;

            const maxPubDay = new Date(maxPublishedDate);
            maxPubDay.setHours(23, 59, 59, 999);

            const pubTextDiv = document.getElementById('schedule-published-text');
            if (maxPublishedDate > new Date(0)) {
                pubTextDiv.textContent = `Schedule published through: ${maxPublishedDate.toLocaleDateString()}`;
            } else {
                pubTextDiv.textContent = "";
            }

            const todayKey = formatYMD(todayInET);
            renderCalendarDays(grid, daysToRender, firstDayOfGrid, targetMonth, todayKey, eventsByDate, maxPubDay, timeRegex);

            const openShiftsList = document.getElementById('open-shifts-list');
            renderOpenShifts(openShiftsList, todayInET, maxPubDay, eventsByDate);
        }
        function pauseRotation() {
            if (rotationInterval) {
                clearInterval(rotationInterval);
                rotationInterval = null;
            }
        }

        function resumeRotation() {
             if (!rotationInterval) {
                 startRotation();
            }
        }

        function startRotation() {
            if (rotationInterval) clearInterval(rotationInterval);
            const pages = [ document.getElementById('page-dashboard'), document.getElementById('page-calendar'), document.getElementById('page-chores') ];

            if (currentPageIndex === 0 && !hasFireDanger && !hasBurnPermits) {
                currentPageIndex = 1;
            }

            pages.forEach((page, index) => {
                if(page) page.style.display = (index === currentPageIndex) ? 'flex' : 'none';
            });

            performPruning(currentPageIndex);

            if (pages[currentPageIndex].id === 'page-dashboard' && permitMap && hasBurnPermits) {
                setTimeout(() => permitMap.invalidateSize(), 0);
            }

            rotationInterval = setInterval(() => {
                if (document.getElementById('permit-modal-overlay').style.display === 'flex') return;
                pages[currentPageIndex].style.display = 'none';

                currentPageIndex = (currentPageIndex + 1) % pages.length;

                if (currentPageIndex === 0 && !hasFireDanger && !hasBurnPermits) {
                    currentPageIndex = (currentPageIndex + 1) % pages.length;
                }

                const newPage = pages[currentPageIndex];
                if (!newPage) return;
                newPage.style.display = 'flex';

                performPruning(currentPageIndex);

                if (newPage.id === 'page-dashboard' && permitMap && hasBurnPermits) {
                    setTimeout(() => permitMap.invalidateSize(), 10);
                }
            }, 15000);
        }

        function processEventForDashboard(vevent, searchStart, windowEnd, now, onDutyNow, onDutyLater) {
            if (vevent.isRecurring()) {
                const iterator = vevent.iterator(ICAL.Time.fromJSDate(searchStart));
                let next;
                while ((next = iterator.next()) && next.toJSDate() < windowEnd) {
                    const occurrence = vevent.getOccurrenceDetails(next);
                    const eventStart = occurrence.startDate.toJSDate();
                    const eventEnd = occurrence.endDate.toJSDate();
                    if (eventStart < windowEnd && eventEnd > searchStart) {
                        const eventInstance = {
                            summary: vevent.summary || 'Unknown Event',
                            startDate: occurrence.startDate,
                            endDate: occurrence.endDate,
                        };
                        if (eventStart <= now && eventEnd > now) {
                            onDutyNow.push(eventInstance);
                        } else if (eventStart > now && eventStart < windowEnd) {
                            onDutyLater.push(eventInstance);
                        }
                    }
                }
            } else {
                const eventStart = vevent.startDate.toJSDate();
                const eventEnd = vevent.endDate.toJSDate();
                if (eventStart < windowEnd && eventEnd > searchStart) {
                    if (eventStart <= now && eventEnd > now) {
                        onDutyNow.push(vevent);
                    } else if (eventStart > now && eventStart < windowEnd) {
                        onDutyLater.push(vevent);
                    }
                }
            }
        }

        function renderEvent(eventData,container){
            const eventDiv=document.createElement('div');
            eventDiv.classList.add('event');
            const leftColumn=document.createElement('div');
            leftColumn.classList.add('event-left');
            const nameDiv=document.createElement('div');
            nameDiv.classList.add('event-name');
            const timeRegex=/\s*\d{1,2}(:\d{2})?\s*(am|pm|a|p)?\s*-\s*\d{1,2}(:\d{2})?\s*(am|pm|a|p)?/gi;

            let cleanedSummary=(eventData.summary || '').replace(timeRegex,'').trim();
            const roles=["Career","Chief","Per-Diem","Night Duty"];
            let foundRole='';
            for(const role of roles){
                const roleRegex=new RegExp(`\\s*${role}\\s*`,'i');
                if(roleRegex.test(cleanedSummary)){
                    foundRole=cleanedSummary.match(roleRegex)[0].trim();
                    cleanedSummary=cleanedSummary.replace(roleRegex,'').trim();
                    break;
                }
            }

            nameDiv.textContent=cleanedSummary || 'Busy';
            leftColumn.appendChild(nameDiv);
            if(foundRole){
                const roleDiv=document.createElement('div');
                roleDiv.classList.add('event-role');
                roleDiv.textContent=foundRole;
                leftColumn.appendChild(roleDiv);
            }

            const rightColumn=document.createElement('div');
            rightColumn.classList.add('event-right');
            const untilDiv=document.createElement('div');
            untilDiv.classList.add('event-until');
            const eventStart=eventData.startDate.toJSDate();
            const eventEnd=eventData.endDate.toJSDate();
            let timeText='';
            let nextDaySpan='';

            if(eventEnd.getDate()!==eventStart.getDate()){
                const day=eventEnd.toLocaleDateString([],{weekday:'short'});
                const m=eventEnd.getMonth()+1;
                const d=eventEnd.getDate();
                nextDaySpan=` <span style="font-size: 0.60em; opacity: 0.7;">(${day} ${m}/${d})</span>`;
            }

            if(container.id==='onDutyLaterContainer' || container.id === 'chores-on-duty-later-container'){
                const startTime=eventStart.toLocaleTimeString([],{hour:'numeric',minute:'2-digit'});
                const endTime=eventEnd.toLocaleTimeString([],{hour:'numeric',minute:'2-digit'});
                timeText=`${startTime} - ${endTime}`;
            }else{
                const endTime=eventEnd.toLocaleTimeString([],{hour:'numeric',minute:'2-digit'});
                timeText=`Until ${endTime}`;
            }

            untilDiv.innerHTML=`${timeText}${nextDaySpan}`;
            rightColumn.appendChild(untilDiv);
            eventDiv.appendChild(leftColumn);
            eventDiv.appendChild(rightColumn);
            container.appendChild(eventDiv);
        }

        function showPermitDetails(detailsHtml) {
            pauseRotation();
            const modalOverlay = document.getElementById('permit-modal-overlay');
            const modalBody = document.getElementById('permit-modal-body');

            const cleanedDetailsHtml = detailsHtml.replace(/<td[^>]*>.*?DEPARTMENT OF AGRICULTURE, CONSERVATION &amp; FORESTRY<br>\s*OPEN BURNING PERMIT.*?<\/td>/s, '');

            modalBody.innerHTML = `<table><tbody>${cleanedDetailsHtml}</tbody></table>`;
            modalOverlay.style.display = 'flex';

            const modalContent = document.getElementById('permit-modal-content');
            modalContent.addEventListener('click', closePermitDetails, { once: true });

            if (modalCloseTimer) clearTimeout(modalCloseTimer);
            modalCloseTimer = setTimeout(closePermitDetails, 60000);
        }

        function closePermitDetails() {
            const modalOverlay = document.getElementById('permit-modal-overlay');
            if (modalOverlay.style.display !== 'none') {
                modalOverlay.style.display = 'none';
                if (modalCloseTimer) {
                    clearTimeout(modalCloseTimer);
                    modalCloseTimer = null;
                }
                resumeRotation();
            }
        }

        function initPermitMap() {
            if (permitMap) return;
            try {
                permitMap = L.map('permitMap').setView([44.5445, -69.7262], 12);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    maxZoom: 19
                }).addTo(permitMap);
            } catch (e) {
                console.error("Could not initialize permit map.", e);
                document.getElementById('permitMap').innerHTML = "Map service unavailable.";
            }
        }

        async function updatePermitMap(permits) {
            if (!permitMap) return;
            permitMarkers.forEach(marker => marker.remove());
            permitMarkers = [];

            const mapContainer = document.getElementById('permitMap');

            if (permits.length === 0) {
                mapContainer.style.display = 'none';
                return;
            }

            mapContainer.style.display = 'block';

            const geocodePromises = permits.map(p => {
                const address = p.location;
                if (!address) return Promise.resolve(null);
                const url = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(address)}&format=json&limit=1`;
                return fetch(url).then(res => res.json()).then(async (nominatimResult) => {
                    if (nominatimResult && nominatimResult.length > 0) {
                        return nominatimResult;
                    } else {
                        console.warn("Nominatim failed for:", address, ". Falling back to Gemini...");
                        const fallbackUrl = `api/gemini_geocode.php?address=${encodeURIComponent(address)}`;
                        return fetch(fallbackUrl).then(res => res.json()).then(geminiResult => {
                            if (geminiResult && geminiResult.lat && geminiResult.lon) {
                                return [{ lat: geminiResult.lat, lon: geminiResult.lon }];
                            }
                            return null;
                        }).catch(err => {
                            console.error("Gemini fallback failed:", err);
                            return null;
                        });
                    }
                }).catch(err => {
                    console.error("Geocoding fetch failed:", err);
                    return null;
                });
            });

            const results = await Promise.all(geocodePromises);
            const locations = [];
            results.forEach((result, index) => {
                if (result && result.length > 0) {
                    const { lat, lon } = result[0];
                    const permit = permits[index];
                    const address = permit.location.split(',')[0];
                    const detailsHtml = permit.description.split('Details:')[1] || '';

                    const marker = L.marker([lat, lon], { icon: flameIcon }).addTo(permitMap)
                        .bindTooltip(address, {
                            permanent: true,
                            direction: 'right',
                            offset: [10, 0],
                            className: 'permit-tooltip'
                        });

                    if (detailsHtml.trim() !== '') {
                        marker.on('click', () => {
                            showPermitDetails(detailsHtml);
                        });
                    }

                    permitMarkers.push(marker);
                    locations.push([lat, lon]);
                }
            });

            if (locations.length > 0) {
                const bounds = L.latLngBounds(locations);
                permitMap.fitBounds(bounds, { padding: [75, 75] });

                setTimeout(() => permitMap.invalidateSize(), 100);
            }
        }

                function renderBurnPermitJsonEvent(eventData, container) {
            const eventDiv = document.createElement('div');
            eventDiv.classList.add('event');

            const uid = eventData.uid || '';
            const address = eventData.address || 'Unknown Address';
            const type = eventData.type || 'Open Burn';

            const expDate = new Date(eventData.expires);
            const timeStr = expDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

            eventDiv.innerHTML = `
                <div class="permit-details">
                    <div class="permit-address">${address}</div>
                    <div class="permit-burn-info">
                        <span class="permit-type">${type}</span>
                        <span class="permit-time">Expires: ${timeStr}</span>
                    </div>
                </div>
            `;
            eventDiv.onclick = () => showPermitModal(eventData);
            container.appendChild(eventDiv);
        }

        function showPermitModal(permitData) {
            const modalOverlay = document.getElementById('permit-modal-overlay');
            const modalBody = document.getElementById('permit-modal-body');

            const tableHTML = `
                <table>
                    <tbody>
                        <tr><td>Address:</td><td><strong>${permitData.address}</strong></td></tr>
                        <tr><td>Type:</td><td><strong>${permitData.type}</strong></td></tr>
                        <tr><td>Expires:</td><td><strong>${new Date(permitData.expires).toLocaleString()}</strong></td></tr>
                        <tr><td>Details:</td><td>${(permitData.details || '').replace(/\n/g, '<br>')}</td></tr>
                    </tbody>
                </table>
            `;
            modalBody.innerHTML = tableHTML;
            modalOverlay.style.display = 'flex';
        }


        function old_renderBurnPermitEvent(eventData, container) {
            const eventDiv = document.createElement('div');
            eventDiv.classList.add('event');

            let detailsHtml = '';
            if (eventData.description) {
                const descriptionParts = eventData.description.split('Details:');
                if (descriptionParts.length > 1) {
                    detailsHtml = descriptionParts[1];
                }
            }

            if (detailsHtml.trim() !== '') {
                eventDiv.classList.add('clickable');
                eventDiv.addEventListener('click', () => showPermitDetails(detailsHtml));
            }

            let displayAddress = eventData.location ? eventData.location.split(',')[0].trim() : 'Address not provided';
            let burnType = 'Type not specified';
            if (eventData.description) {
                const descriptionParts = eventData.description.split('Details:');
                burnType = descriptionParts[0].replace('Burn type:', '').trim();
                if (burnType === 'Pile - brush/slash/debris') { burnType = 'Pile - brush/debris'; }
            }

            const eventStart = eventData.startDate.toJSDate();
            const eventEnd = eventData.endDate.toJSDate();
            const timeOptions = { year: '2-digit', month: 'numeric', day: 'numeric', hour: 'numeric', minute: '2-digit' };
            const startTime = eventStart.toLocaleString('en-US', timeOptions);
            const endTime = eventEnd.toLocaleString('en-US', timeOptions);

            eventDiv.innerHTML = `
                <div class="permit-details">
                    <div class="permit-address">${displayAddress}</div>
                    <div class="permit-burn-info">
                        <span class="permit-type">${burnType}</span>
                    </div>
                    <div class="permit-time">${startTime} - ${endTime}</div>
                </div>
            `;

            container.appendChild(eventDiv);
        }

    </script>
</body>
</html>