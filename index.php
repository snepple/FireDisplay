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
        die("<h1 style='color: #aeb6c1; text-align: center; font-family: \"Inter\", sans-serif; padding-top: 50px;'>Access Denied</h1>");
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
            --bg-color: #1c1c1c;
            --card-bg: #1C212B;
            --text-color: #fff;
            --muted-text: #cbd5e1;
            --border-color: #30363d;
            --event-hover: #30363d;
            --hover-bg: #2a313c;
            --today-bg: #004085;
            --today-border: #9fceff;
            --item-bg: #10141a;
        }

        /* Light Theme Overrides */
        body.light-theme {
            --bg-color: #f5f5f7;
            --card-bg: #ffffff;
            --text-color: #1d1d1f;
            --muted-text: #475569;
            --border-color: #d2d2d7;
            --event-hover: #f0f0f5;
            --hover-bg: #e5e5ea;
            --today-bg: #e6f2ff;
            --today-border: #007bff;
            --item-bg: #fbfbfd;
        }

        html, body {
            height: 100%;
            margin: 0;
            overflow: hidden;
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }

        ::-webkit-scrollbar {
            display: none;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: "Inter", sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: clamp(4px, 1.5vh, 10px);
            font-size: clamp(30px, 4vh, 60px);
            box-sizing: border-box;
            transition: background-color 0.5s, color 0.5s;
        }

        .page-container {
            width: 100%;
            height: 100%;
            display: none;
            min-height: 0;
        }
        #page-dashboard { flex-direction: row; align-items: stretch; height: 100%; gap: clamp(2px, 1vh, 15px); width: 100%; }
        #page-chores { flex-direction: column; align-items: center; height: 100%; gap: clamp(2px, 1vh, 15px); width: 100%; }

        .main-layout { display: flex; flex-direction: column; height: 100%; gap: clamp(2px, 1vh, 15px); min-width: 0; }
        #top-section { flex: 1; min-width: 0; }
        #combined-permits-container { flex: 2; min-width: 0; min-height: 0; display: flex; flex-direction: column; }
        .container { width: 100%; flex: 1; display: flex; flex-direction: column; min-height: 0; }

        h2 {
            font-family: 'Agency FB', sans-serif;
            text-transform: uppercase;
            text-align: center;
            color: var(--text-color);

            padding-bottom: 3px;
            margin-top: 0;
            margin-bottom: clamp(1px, 0.5vh, 5px);
            font-size: 0.6em;
            letter-spacing: 1.5px;
            flex-shrink: 0;
        }
        .event:nth-child(even) { background-color: rgba(255, 255, 255, 0.03); }
        body.light-theme .event:nth-child(even) { background-color: rgba(0, 0, 0, 0.03); }
        .event {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--card-bg);
            padding: clamp(3px, 1vh, 9px) clamp(6px, 1.5vw, 18px);

            flex-shrink: 0;
            border-radius: 4px;
        }
        .event.clickable { cursor: pointer; }
        .event.clickable:hover { background-color: var(--event-hover); }
        .event-left { text-align: left; overflow: hidden;
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
        .event-right { text-align: right; flex-shrink: 0; padding-left: 10px;}
        .event-name { font-size: 1.2em; font-weight: 500; color: var(--text-color); white-space: nowrap; text-overflow: ellipsis; overflow: hidden;
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
        .event-role { font-size: 1.0em; font-weight: 500; color: var(--muted-text); text-transform: uppercase; margin-top: 1px; letter-spacing: 0.5px; }
        .event-until { font-size: 0.9em; color: var(--muted-text); font-weight: 400; }

        .permit-details { display: flex; flex-direction: column; width: 100%; gap: 4px; }
        .permit-address { font-size: clamp(1.2em, 3vw, 2.4em); font-weight: 500; color: var(--text-color); white-space: normal; }
        .permit-burn-info { display: flex; justify-content: space-between; align-items: center; width: 100%; }
        .permit-type { font-size: 2.0em; font-weight: 500; color: var(--muted-text); text-transform: uppercase; }
        .permit-time { font-size: 1.8em; color: var(--muted-text); font-weight: 400; }

        .no-events { text-align: center; color: var(--muted-text); padding: clamp(6px, 1.5vh, 15px); background-color: var(--card-bg); font-size: 1.2em; flex-shrink: 0; }
        .no-burn-permits { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; text-align: center; padding: clamp(8px, 2vh, 20px); box-sizing: border-box; min-height: 0; }
        .no-burn-permits img { max-width: 400px; width: 50%; max-height: 50vh; height: auto; margin-bottom: clamp(10px, 2vh, 30px); object-fit: contain; flex-shrink: 1; min-height: 0; }
        .no-burn-permits p { font-size: clamp(1.5em, min(4vw, 6vh), 5em); font-weight: 700; color: var(--text-color); margin: 0; line-height: 1.2; flex-shrink: 1; min-height: 0; overflow: hidden; display: -webkit-box; -webkit-box-orient: vertical; }


        #fire-danger-content { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; box-sizing: border-box; background-color: var(--card-bg); border-radius: 4px; padding: clamp(4px, 1.5vh, 10px);}
        #danger-meter { width: 60%; min-height: 60px; height: auto; padding: clamp(4px, 1.5vh, 10px); box-sizing: border-box; border: 2px solid var(--muted-text); border-radius: 5px; margin-bottom: clamp(5px, 1.5vh, 15px); display: flex; align-items: center; justify-content: center; font-size: clamp(1.2em, 3vw, 2.4em); font-weight: 700; text-transform: uppercase; }
        #danger-date { font-size: 0.3em; color: var(--muted-text); margin-top: clamp(1px, 0.5vh, 5px); }
        .risk-snow-cover { background-color: #ffffff; color: #000 !important; border-color:#000 !important;}
        .risk-low { background-color: #28a745; color:#fff;}
        .risk-moderate { background-color: #007bff; color:#fff;}
        .risk-high { background-color: #ffc107; color: #000 !important; }
        .risk-very-high { background-color: #8B4513; color:#fff;}
        .risk-extreme { background-color: #dc3545; color:#fff;}

        #permitMap { height: 100%; width: 100%; border-radius: 4px; }
        .permit-tooltip { background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 3px; color: var(--text-color); font-weight: bold; padding: 4px 8px; font-size: 0.9em; box-shadow: 0 1px 3px rgba(0,0,0,0.4); }
        .flame-marker-icon { filter: drop-shadow(0px 2px 3px rgba(0, 0, 0, 0.7)); }

        #page-calendar { display: none; flex-direction: column; gap: clamp(2px, 1vh, 15px); height: 100%; }
        .calendar-content-row { display: flex; flex: 1; gap: clamp(5px, 1.5vw, 15px); min-height: 0; margin-top: clamp(2px, 1vh, 15px); overflow: hidden;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .calendar-content-row::-webkit-scrollbar { display: none; }
        .calendar-main-content { flex: 0 0 calc(85% - 7.5px); max-width: calc(85% - 7.5px); box-sizing: border-box; display: flex; flex-direction: column; min-height: 0; }
        .calendar-sidebar { flex: 0 0 calc(15% - 7.5px); max-width: calc(15% - 7.5px); box-sizing: border-box; background-color: var(--card-bg); border-radius: 8px; padding: clamp(4px, 1vh, 10px); display: flex; flex-direction: column; overflow: hidden;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .calendar-sidebar::-webkit-scrollbar { display: none; }
        .calendar-sidebar h3 { font-size: clamp(10px, 1.5vh, 0.9em); text-align: center; margin: 0 0 clamp(2px, 0.5vh, 10px) 0; color: var(--muted-text);  padding-bottom: clamp(1px, 0.5vh, 5px); flex-shrink: 0; }

        #open-shifts-section { display: flex; flex-direction: column; flex-grow: 1; min-height: 0; }
        #open-shifts-list { display: flex; flex-direction: column; flex-grow: 1; overflow: hidden; padding-top: 5px; min-height: 0;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        #open-shifts-list::-webkit-scrollbar { display: none; }

        .open-shift-link, .open-shift-link:visited { text-decoration: none; color: inherit; display: block; }

        .open-shift-item {
            padding: clamp(4px, 1vh, 10px) clamp(6px, 1vw, 12px);
            display: flex; justify-content: space-between; align-items: center;
            background-color: var(--item-bg);  border-radius: 6px;
            margin-bottom: clamp(2px, 0.5vh, 8px); font-size: clamp(8px, 1vw, 16px); gap: clamp(5px, 1vw, 15px);
            flex-shrink: 0; border: 1px solid var(--border-color);
        }
        .open-shift-item:hover { background-color: var(--event-hover); }
        .open-shift-date { font-weight: 600; font-size: clamp(8px, 1.2vh, 1.1em); color: var(--text-color); white-space: nowrap; flex-shrink: 0; }
        .open-shift-right { display: flex; flex-direction: column; align-items: flex-end; min-width: 0; flex-shrink: 1; }
        .open-shift-role { font-weight: 800; color: #ff6b6b; font-size: clamp(8px, 1.2vh, 0.95em); line-height: 1.2; white-space: nowrap; overflow: hidden;
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
            text-overflow: ellipsis; max-width: 100%; overflow: hidden; }
        .open-shift-time { font-size: clamp(6px, 1vh, 0.8em); color: var(--muted-text); font-weight: 500; white-space: nowrap; }

        .calendar-arrow { opacity: 0.15; cursor: pointer; transition: opacity 0.3s ease; user-select: none; padding: 0 15px; font-size: 24px; }
        .calendar-arrow:hover { opacity: 0.8; }

        #calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: clamp(2px, 0.5vh, 5px); flex-grow: 1; min-height: 0; }

        .chores-header, .calendar-header { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); text-align: center; margin-bottom: clamp(1px, 0.5vh, 5px); }
        .chores-header { font-size: clamp(8pt, 1.5vh, 12pt); color: #ffc107; font-weight: bold; }
        .calendar-header { font-size: clamp(8px, 1.2vh, 0.9em); color: var(--muted-text); }

        .calendar-day { background-color: var(--card-bg); border: 1px solid var(--border-color); padding: clamp(1px, 0.5vh, 5px); font-size: clamp(8px, 1.2vh, 0.9em); display: flex; flex-direction: column; overflow: hidden;
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
            min-height: 0; min-width: 0; border-radius: 4px; }
        .calendar-day.is-today { background-color: var(--today-bg); border-color: var(--today-border); }
        .calendar-day.other-month { opacity: 0.3; }

        .day-number { display: flex; justify-content: space-between; align-items: center; color: var(--muted-text); margin-bottom: 1px; font-weight: 700; flex-shrink: 0; font-size: clamp(8px, 1.5vh, 12pt); }
        .calendar-day.new-month { border-top: 2px solid #ffc107; }
        .calendar-day.new-month .day-number { color: #ffc107; }
        .chore-number { font-size: 1.0em; font-weight: normal; color: #ffc107; background-color: rgba(255, 255, 255, 0.1); border-radius: 50%; width: 1.2em; height: 1.2em; display: inline-flex; align-items: center; justify-content: center; }
        body.light-theme .chore-number { background-color: rgba(0,0,0,0.05); }

        .calendar.event:nth-child(even) { background-color: rgba(255, 255, 255, 0.03); }
        body.light-theme .event:nth-child(even) { background-color: rgba(0, 0, 0, 0.03); }
        .event { font-size: clamp(4px, 1.2vh, 10pt); padding: 1px 4px; border-radius: 3px; margin-bottom: clamp(1px, 0.2vh, 2px); white-space: nowrap; overflow: hidden;
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
            text-overflow: ellipsis; border: 1px solid transparent; line-height: 1.2; flex-shrink: 1; min-height: 0; overflow: hidden; }

        .calendar-event.event-open { background-color: transparent; background-image: repeating-linear-gradient(45deg, transparent, transparent 4px, rgba(128,128,128,0.2) 4px, rgba(128,128,128,0.2) 8px); color: var(--text-color); border: 1px dashed var(--muted-text); }
        .event-career { background-color: #6f42c1; color: white; }
        .event-per-diem { background-color: #e83e8c; color: white; }
        .event-night-duty { background-color: #fd7e14; color: white; }
        .event-town-meeting { background-color: #007bff; color: white; }
        .event-dept { background-color: #20c997; color: #000; }
        .event-unpublished { background-color: transparent; color: var(--muted-text); text-align: center; border: 1px dashed var(--border-color); font-style: italic; padding: 4px; margin-top: clamp(1px, 0.5vh, 5px); white-space: normal; }

        .calendar-legend { display: flex; justify-content: center; gap: clamp(10px, 1.5vw, 25px); margin-bottom: clamp(2px, 0.5vh, 10px); font-size: clamp(8pt, 1vh, 11pt); color: var(--muted-text); flex-shrink: 0; }
        .legend-item { display: flex; align-items: center; gap: clamp(2px, 0.5vw, 8px); }
        .legend-color-box { width: clamp(10px, 1.5vh, 20px); height: clamp(10px, 1.5vh, 20px); border-radius: 4px; }
        .chore-item { background-color: var(--card-bg); border: 1px solid var(--border-color); padding: clamp(10px, 2vh, 25px) clamp(15px, 2vw, 40px); border-radius: 8px; text-align: center; overflow: hidden;
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
            min-height: 0; }
        .chore-list { list-style-type: none; padding: 0; margin: 0; font-size: clamp(30px, 5vh, 70px); font-weight: 700; line-height: 1.5; color: var(--text-color); }
        #page-chores .chore-list li { font-size: clamp(30px, 6.5vh, 80px); line-height: 1.2; list-style-type: none; margin-bottom: clamp(2px, 1vh, 10px); }
        .national-day { font-size: 18pt; color: var(--muted-text); font-style: italic; margin-top: 20px; }
        #debug-log { display: none; }

        #permit-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.8); z-index: 10000; justify-content: center; align-items: center; padding: clamp(4px, 1.5vh, 10px); box-sizing: border-box; }
        #permit-modal-content { background-color: var(--card-bg); padding: clamp(6px, 1.5vh, 15px); border-radius: 8px; border: 1px solid var(--muted-text); width: 100%; max-width: 900px; color: var(--text-color); font-size: 0.9em; cursor: pointer; display: flex; flex-direction: column;  max-height: 90vh;}
        #permit-modal-header h2 { margin-top: 0; margin-bottom: clamp(1px, 0.5vh, 5px); color: var(--text-color); white-space: normal; text-align: center; font-size: 1.2em; }
        #permit-modal-body { max-height: 70vh; overflow-y: auto; line-height: 1.4; }
        #permit-modal-body table { width: 100%; border-collapse: collapse; font-size: 11pt; }
        #permit-modal-body td { padding: 5px;  vertical-align: top; }
        #permit-modal-body tr:last-child td { border-bottom: none; }
        #permit-modal-body td:first-child { color: var(--muted-text); width: 30%; font-weight: bold; }
        #permit-modal-body strong { color: var(--text-color); font-weight: 500; }
        #permit-modal-footer p { font-size: 0.9em; text-align: center; color: var(--muted-text); margin-top: clamp(5px, 1.5vh, 15px); margin-bottom: 0; }



        .toggle-switch { position: relative; display: inline-block; width: 32px; height: 18px; margin: 0; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--border-color); transition: .4s; border-radius: 18px; border: 1px solid #555; }
        .toggle-slider:before { position: absolute; content: ""; height: 12px; width: 12px; left: 2px; bottom: 2px; background-color: var(--muted-text); transition: .4s; border-radius: 50%; }
        .toggle-switch input:checked + .toggle-slider { background-color: #28a745; border-color: #28a745; }
        .toggle-switch input:checked + .toggle-slider:before { transform: translateX(14px); background-color: var(--card-bg); }

        .announcement-content p { font-size: 16pt; line-height: 1.4; margin: 0 0 10px 0; color: var(--text-color); }
        .announcement-content h1, .announcement-content h2, .announcement-content h3 { color: #ffc107; margin-top:0; border:none; padding:0; text-align: left; }
        .announcement-content ul, .announcement-content ol { text-align: left; font-size: 16pt; margin: 0 0 10px 20px; color: var(--text-color);}
        .announcement-card { background: var(--item-bg); padding: clamp(4px, 1.5vh, 10px); border-radius: 6px; border: 1px solid var(--border-color); margin-bottom: clamp(2px, 1vh, 10px); text-align: left;}

        #admin-link:hover { opacity: 1 !important; }

        #page-chores h2 { font-size: clamp(24px, 3vh, 45px); }
        #page-chores .event { font-size: clamp(12px, 2.5vh, 35px); padding: clamp(4px, 1vh, 10px) clamp(6px, 1vw, 15px); margin-bottom: clamp(1px, 0.5vh, 5px); }
        #page-chores .event-name { font-size: 1.2em; }
        #page-chores .event-role { font-size: 1em; }
        #page-chores .event-until { font-size: 1em; }

        ::-webkit-scrollbar {
            display: none;
        }

        /* Fire Danger Toast Notification */
        #fire-danger-toast {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background-color: var(--card-bg);
            color: var(--text-color);
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: 500;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
            pointer-events: none;
            z-index: 100;
            border: 1px solid var(--border-color);
        }
        #fire-danger-toast.show {
            opacity: 1;
        }
        #fire-danger-toast.success { border-color: #28a745; color: #28a745; }
        #fire-danger-toast.error { border-color: #dc3545; color: #dc3545; }
        #fire-danger-toast.loading { border-color: #007bff; color: #007bff; }


        button:focus-visible, a:focus-visible, input:focus-visible {
            outline: 3px solid #ffc107;
            outline-offset: 2px;
        }
</style>
</head>
<body>
    <div id="audio-unlock-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); color: #fff; z-index: 100000; align-items: center; justify-content: center; font-size: 3rem; cursor: pointer;">
        Click anywhere to enable audio for this session
    </div>
    <a href="admin.php" id="admin-link" title="Open Admin Dashboard" aria-label="Open Admin Dashboard" style="position: absolute; bottom: 15px; right: 15px; z-index: 10000; opacity: 0.15; color: var(--text-color); text-decoration: none; font-size: 24px; transition: opacity 0.3s;">⚙️</a>

    <div id="audio-toggle-wrapper" title="Toggle Audio Announcements" style="display: none;">
        <label class="toggle-switch">
            <input type="checkbox" id="audio-toggle-checkbox" aria-label="Toggle Audio Announcements">
            <span class="toggle-slider"></span>
        </label>
    </div>



    <div id="page-dashboard" class="page-container">
        <div class="main-layout" id="top-section">
            <div class="container">
                <div id="fire-danger-content" style="justify-content: flex-start; padding-top: clamp(10px, 1.5vh, 20px); position: relative;">
                     <div id="fire-danger-toast">Updating...</div>
                     <button onclick="loadFireDanger(true)" style="position: absolute; bottom: 5px; right: 5px; background: none; border: none; cursor: pointer; opacity: 0.3; padding: 5px;" title="Force Reload Fire Danger" aria-label="Force Reload Fire Danger"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"></path><path d="M3 12a9 9 0 1 0 2.63-6.37L21 8"></path></svg></button>
                     <h2 style="font-size: clamp(24px, 3vh, 45px); margin-bottom: clamp(5px, 1vh, 15px); width: 100%;">🔥 Fire Danger</h2>
                     <div style="flex-grow: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; width: 100%;">
                     <div id="danger-meter">Loading...</div>
                     <div id="danger-image-container" style="position: relative; max-width: 60%; margin-bottom: clamp(5px, 1.5vh, 15px); aspect-ratio: 16/9; display:none; width: 100%; flex: 1; min-height: 0;">
                         <img id="danger-image-active" src="" alt="Fire Danger Level" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border-radius: 5px; object-fit: contain; transition: opacity 0.5s ease-in-out; opacity: 1;" />
                         <img id="danger-image-standby" src="" alt="" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border-radius: 5px; object-fit: contain; transition: opacity 0.5s ease-in-out; opacity: 0;" />
                     </div>
                     <div id="danger-date"></div>
                     </div>

                </div>
            </div>
        </div>
        <div class="container" id="combined-permits-container">
             <div id="permits-content-wrapper" style="display: flex; flex-grow: 1; min-height: 0; gap: clamp(2px, 1vh, 15px); width: 100%;">
                 <div id=\"burnPermitsContainer\" style=\"flex: 1; background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 4px; overflow: hidden; display: flex; flex-direction: column; -ms-overflow-style: none; scrollbar-width: none; min-height: 0; position: relative;\">
                     <h2 style="font-size: clamp(24px, 3vh, 45px); margin: clamp(10px, 1.5vh, 20px) 0 clamp(5px, 1vh, 15px) 0; flex-shrink: 0; width: 100%;">Active Burn Permits</h2>
<a href=\"manual_permit.html\" target=\"_blank\" title=\"Add Manual Burn Permit\" style=\"position: absolute; top: 10px; right: 10px; z-index: 1000; opacity: 0.15; color: var(--text-color); text-decoration: none; font-size: 24px; transition: opacity 0.3s;\" onmouseover=\"this.style.opacity=1\" onmouseout=\"this.style.opacity=0.15\">➕</a>
                     <div id="burnPermitsList" style="flex-grow: 1; overflow-y: auto; min-height: 0;"></div>
                 </div>
                 <div id="permitMap" style="flex: 2; border-radius: 4px;"></div>
             </div>
        </div>
    </div>

    <div id="page-calendar" class="page-container">
        <div class="calendar-content-row">
            <div class="calendar-main-content">
                <div style="display: flex; justify-content: center; align-items: center;  margin-bottom: clamp(1px, 0.5vh, 5px); padding-bottom: 3px; flex-shrink: 0;">
                    <button class="calendar-arrow" aria-label="Previous month" onclick="changeMonth(-1)" style="background:none;border:none;color:inherit;font:inherit;">&#10094;</button>
                    <h2 id="calendar-month-year" style="border: none; margin: 0; padding: 0;"></h2>
                    <button class="calendar-arrow" aria-label="Next month" onclick="changeMonth(1)" style="background:none;border:none;color:inherit;font:inherit;">&#10095;</button>
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
                <div id="schedule-published-text" style="font-size: 0.4em; color: var(--muted-text); text-align: center; margin-top: clamp(1px, 0.5vh, 5px); flex-shrink: 0;"></div>
            </div>
            <div class="calendar-sidebar">
                <div id="open-shifts-section">
                    <h2 style="font-size: clamp(24px, 3vh, 45px); border: none; padding: 0; margin: 0 0 clamp(1px, 0.5vh, 5px) 0;">Upcoming Open Shifts</h2>
                    <div id="open-shifts-list"></div>
                </div>
            </div>
        </div>
    </div>

    <div id="page-chores" class="page-container">
        <div id="chores-layout" style="display: flex; width: 100%; height: 100%; gap: clamp(2px, 1vh, 15px); min-height: 0;">

            <div id="chores-duties-column" style="flex: 1.5; display: flex; flex-direction: column; gap: clamp(2px, 1vh, 15px); min-width: 0; min-height: 0;">

                <div class="chore-item" id="announcements-wrapper" style="display: none; flex-direction: column; padding: clamp(4px, 1.5vh, 10px);">
                    <h2 style="font-size: 1.0em; border: none; padding: 0; margin: 0 0 clamp(1px, 0.5vh, 5px) 0; color: #ffc107;">📢 Announcements</h2>
                    <div id="announcements-container" style="display: flex; flex-direction: column; overflow: hidden;
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        "></div>
                </div>

                <div class="chore-item" style="flex-grow: 1; display: flex; flex-direction: column; justify-content: flex-start; padding: clamp(4px, 1.5vh, 10px);">
                    <h2 style="font-size: clamp(24px, 3vh, 45px); border: none; padding: 0; margin: 0 0 clamp(1px, 0.5vh, 5px) 0;">Today's Station Duties</h2>
                    <ul id="chore-list" class="chore-list"></ul>
                    <div id="holiday-container" style="display: none;"><p id="national-day" class="national-day"></p></div>
                </div>
            </div>

            <div id="chores-staff-column" style="flex: 1; display: flex; flex-direction: column; min-width: 0; min-height: 0;">
                 <div class="chore-item" style="height: 100%; box-sizing: border-box; display: flex; flex-direction: column; padding: clamp(4px, 1.5vh, 10px); overflow: hidden;
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        min-height: 0;">

                     <div id="dept-events-container" style="display: none; margin-bottom: clamp(5px, 1.5vh, 15px); flex-shrink: 0;">
                         <h2 style="font-size: 1.0em; border: none; padding: 0; margin: 0 0 clamp(1px, 0.5vh, 5px) 0;">📅 Department Events</h2>
                         <div id="dept-events-list" style="display: flex; flex-direction: column; gap: clamp(4px, 1vh, 10px);"></div>
                     </div>

                     <div id="town-meetings-container" style="display: none; margin-bottom: clamp(5px, 1.5vh, 15px); flex-shrink: 0;">
                         <h2 style="font-size: 1.0em; border: none; padding: 0; margin: 0 0 clamp(1px, 0.5vh, 5px) 0;">🏛️ Town Meetings Here</h2>
                         <div id="town-meetings-list" style="display: flex; flex-direction: column; gap: clamp(4px, 1vh, 10px);"></div>
                     </div>

                     <div id="chores-on-duty-now-wrapper" style="flex-shrink: 0;">
                          <h2 style="font-size: clamp(24px, 3vh, 45px); border: none; padding: 0; margin: 0 0 clamp(1px, 0.5vh, 5px) 0;">🧑‍🚒 On Duty</h2>
                          <div id="chores-on-duty-container"></div>
                     </div>
                     <div id="chores-on-duty-later-wrapper" style="margin-top: clamp(5px, 1.5vh, 15px); flex-shrink: 0;">
                          <h2 style="font-size: clamp(24px, 3vh, 45px); border: none; padding: 0; margin: 0 0 clamp(1px, 0.5vh, 5px) 0;">🗓️ On Duty Later Today</h2>
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
    <script>

        const originalFetch = window.fetch;
        const fetchPromises = new Map();

        function hideIamRespondingTitleBar() {
            try {
                if (window.parent && window.parent !== window) {
                    const parentDoc = window.parent.document;
                    const tileNames = parentDoc.querySelectorAll('.tile-name');
                    tileNames.forEach(tileName => {
                        if (tileName.textContent.trim() === 'Third-Party Website') {
                            const titleActions = tileName.closest('.title-actions');
                            if (titleActions && !titleActions.dataset.modifiedByFireDisplay) {
                                titleActions.style.background = 'transparent';
                                titleActions.style.position = 'absolute';
                                titleActions.style.top = '0';
                                titleActions.style.left = '0';
                                titleActions.style.width = '100%';
                                titleActions.style.height = '0';
                                titleActions.style.zIndex = '1000';

                                tileName.style.display = 'none';

                                const rightSide = titleActions.querySelector('.right-side');
                                if (rightSide) {
                                    rightSide.style.position = 'absolute';
                                    rightSide.style.top = '5px';
                                    rightSide.style.right = '5px';
                                    rightSide.style.background = 'rgba(0, 0, 0, 0.5)';
                                    rightSide.style.padding = '2px';
                                    rightSide.style.borderRadius = '5px';
                                }

                                const gridItem = titleActions.closest('.react-grid-item');
                                if (gridItem) {
                                    const tileBody = gridItem.querySelector('.tile-body');
                                    if (tileBody) {
                                        tileBody.style.height = '100%';
                                    }
                                }

                                titleActions.dataset.modifiedByFireDisplay = 'true';
                            }
                        }
                    });
                }
            } catch (e) {
                // Cross-origin or other error
            }
        }
        setInterval(hideIamRespondingTitleBar, 1000);


        window.fetch = function(...args) {
            const url = typeof args[0] === 'string' ? args[0] : (args[0] instanceof Request ? args[0].url : '');

            // Deduplicate requests to fetch_calendar.php
            if (typeof url === 'string' && url.includes('api/fetch_calendar.php')) {
                let urlObj;
                try {
                    urlObj = new URL(url, window.location.origin);
                } catch (e) {
                    return originalFetch.apply(this, args);
                }

                const originalTargetUrl = urlObj.searchParams.get('url');

                if (originalTargetUrl) {
                    if (fetchPromises.has(originalTargetUrl)) {
                        return fetchPromises.get(originalTargetUrl).then(res => res.clone());
                    }

                    const fetchPromise = originalFetch.apply(this, args).then(res => {
                        setTimeout(() => fetchPromises.delete(originalTargetUrl), 5000);
                        return res;
                    }).catch(err => {
                        fetchPromises.delete(originalTargetUrl);
                        throw err;
                    });

                    fetchPromises.set(originalTargetUrl, fetchPromise);
                    return fetchPromise.then(res => res.clone());
                }
            }

            return originalFetch.apply(this, args);
        };
        let appConfig = null;
        let holidaysByDate = {};
        let announcedPermitUIDs = new Set();
        let permitCheckDate = new Date().toLocaleDateString();
        let rotationInterval = null;
        let currentPageIndex = 0;
        let modalCloseTimer = null;
        let permitMap = null;
        let permitMapLayerControl = null;
        let staticLocationsLayerGroup = null;
        let permitsLayerGroup = null;
        let permitMarkers = [];
        let mapLocationMarkers = [];
        let mapLocationsData = [];
        let parcelLayer = null;
        // Cache geocoded addresses to prevent redundant Nominatim/Gemini API calls and improve performance
        const geocodeCache = new Map();

        let hasFireDanger = false;
        let hasBurnPermits = false;


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

        const stationIcon = L.divIcon({
            className: "custom-station-marker",
            html: "<div style=\"width: 28px; height: 28px; background-color: #dc3545; border: 2px solid white; border-radius: 4px; box-shadow: 0 0 5px rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center;\"><svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\" fill=\"white\" width=\"16\" height=\"16\"><path d=\"M19 9.3V4h-3v2.6L12 3 2 12h3v8h5v-6h4v6h5v-8h3l-3-2.7zm-9 .7c0-1.1.9-2 2-2s2 .9 2 2h-4z\"/></svg></div>",
            iconSize: [28, 28],
            iconAnchor: [14, 14],
            popupAnchor: [0, -14],
            tooltipAnchor: [14, 0]
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
            const container = document.getElementById('burnPermitsList');
            if (!container) return;
            const containerClientHeight = container.clientHeight;
            if (containerClientHeight === 0) return;

            let excessHeight = container.scrollHeight - containerClientHeight - 2;
            if (excessHeight <= 0) return;

            const children = Array.from(container.children);
            let itemsToRemove = 0;

            // Batch read
            const childHeights = children.map(child => child.offsetHeight || 20);

            // Compute
            for (let i = children.length - 1; i >= 0; i--) {
                if (excessHeight <= 0) break;
                excessHeight -= childHeights[i];
                itemsToRemove++;
            }
            itemsToRemove = Math.max(1, itemsToRemove);

            // Batch write
            for (let i = 0; i < itemsToRemove; i++) {
                if (container.lastElementChild) {
                    container.removeChild(container.lastElementChild);
                }
            }
        }

        function pruneCalendar() {
            const pageCalendar = document.getElementById('page-calendar');
            const grid = document.getElementById('calendar-grid');
            const pageClientHeight = pageCalendar ? pageCalendar.clientHeight : 0;

            if (pageCalendar && grid && pageClientHeight > 0) {
                let excessPageHeight = pageCalendar.scrollHeight - pageClientHeight - 2;

                if (excessPageHeight > 0) {
                    const days = grid.querySelectorAll('.calendar-day');
                    if (days.length > 0) {
                        let todayIndex = -1;
                        for (let i = 0; i < days.length; i++) {
                            if (days[i].classList.contains('is-today')) {
                                todayIndex = i;
                                break;
                            }
                        }

                        if (todayIndex >= 7) {
                            let currentWeekIndex = Math.floor(todayIndex / 7);
                            let hiddenRows = 0;

                            // Compute all rows needed to hide
                            while (excessPageHeight > 0 && hiddenRows < currentWeekIndex) {
                                let rowsToHide = 0;
                                let accumulatedHeight = 0;

                                for (let r = hiddenRows; r < currentWeekIndex; r++) {
                                    const startIndex = r * 7;
                                    let maxRowHeight = 0;
                                    // Batch Read heights for this row
                                    for (let i = 0; i < 7; i++) {
                                        if (days[startIndex + i] && days[startIndex + i].offsetHeight > maxRowHeight) {
                                            maxRowHeight = days[startIndex + i].offsetHeight;
                                        }
                                    }
                                    accumulatedHeight += (maxRowHeight || 50);
                                    rowsToHide++;
                                    if (excessPageHeight - accumulatedHeight <= 0) break;
                                }

                                rowsToHide = Math.max(1, rowsToHide);

                                // Batch Write style.display='none'
                                for (let r = 0; r < rowsToHide && hiddenRows + r < currentWeekIndex; r++) {
                                    const startIndex = (hiddenRows + r) * 7;
                                    for (let i = 0; i < 7; i++) {
                                        if (days[startIndex + i]) {
                                            days[startIndex + i].style.display = 'none';
                                        }
                                    }
                                }
                                hiddenRows += rowsToHide;
                                excessPageHeight -= accumulatedHeight;

                                const totalWeeks = days.length / 7;
                                const remainingWeeks = totalWeeks - hiddenRows;
                                grid.style.gridTemplateRows = `repeat(${remainingWeeks}, minmax(0, 1fr))`;
                            }
                        }
                    }
                }
            }

            const sidebar = document.querySelector('.calendar-sidebar');
            const list = document.getElementById('open-shifts-list');
            if (sidebar && list) {
                const sidebarClientHeight = sidebar.clientHeight;
                if (sidebarClientHeight === 0) return;

                let sidebarExcessHeight = sidebar.scrollHeight - sidebarClientHeight - 2;
                if (sidebarExcessHeight > 0) {
                    const links = Array.from(list.querySelectorAll('.open-shift-link'));
                    // Batch read
                    const linkHeights = links.map(link => link.offsetHeight || 20);
                    let itemsToRemove = 0;

                    // Compute
                    for (let i = links.length - 1; i >= 0; i--) {
                        if (sidebarExcessHeight <= 0) break;
                        sidebarExcessHeight -= linkHeights[i];
                        itemsToRemove++;
                    }
                    itemsToRemove = Math.max(1, itemsToRemove);

                    // Fast batch write without layout thrashing
                    for (let i = 0; i < itemsToRemove; i++) {
                        if (list.lastElementChild) {
                            list.removeChild(list.lastElementChild);
                        }
                    }
                }
            }
        }

        function pruneChores() {
            const column = document.querySelector('#chores-staff-column .chore-item');
            if (column) {
                const columnClientHeight = column.clientHeight;
                if (columnClientHeight > 0) {
                    let excessColumnHeight = column.scrollHeight - columnClientHeight - 2;

                    if (excessColumnHeight > 0) {
                        const targets = [
                            { id: 'chores-on-duty-later-container', wrapperId: 'chores-on-duty-later-wrapper' },
                            { id: 'chores-on-duty-container', wrapperId: 'chores-on-duty-now-wrapper' },
                            { id: 'town-meetings-list', wrapperId: 'town-meetings-container' },
                            { id: 'dept-events-list', wrapperId: 'dept-events-container' }
                        ];

                        for (const target of targets) {
                            if (excessColumnHeight <= 0) break;
                            const container = document.getElementById(target.id);
                            if (container && container.children.length > 0) {
                                const children = Array.from(container.children);

                                // Batch read
                                const childHeights = children.map(child => child.offsetHeight || 20);
                                let itemsToRemove = 0;

                                // Compute
                                for (let i = children.length - 1; i >= 0; i--) {
                                    if (excessColumnHeight <= 0) break;
                                    excessColumnHeight -= childHeights[i];
                                    itemsToRemove++;
                                }
                                itemsToRemove = Math.max(1, itemsToRemove);

                                // Batch write
                                for (let i = 0; i < itemsToRemove; i++) {
                                    if (container.lastElementChild) {
                                        container.removeChild(container.lastElementChild);
                                    }
                                }

                                if (container.children.length === 0) {
                                    const wrapper = document.getElementById(target.wrapperId);
                                    if (wrapper) wrapper.style.display = 'none';
                                }
                            }
                        }
                    }
                }
            }

            const annCol = document.getElementById('chores-duties-column');
            const annCont = document.getElementById('announcements-container');
            if (annCol && annCont) {
                const annColClientHeight = annCol.clientHeight;
                if (annColClientHeight > 0) {
                    let excessAnnColHeight = annCol.scrollHeight - annColClientHeight - 2;

                    if (excessAnnColHeight > 0 && annCont.children.length > 0) {
                        const children = Array.from(annCont.children);

                        // Batch read
                        const childHeights = children.map(child => child.offsetHeight || 20);
                        let itemsToRemove = 0;

                        // Compute
                        for (let i = children.length - 1; i >= 0; i--) {
                            if (excessAnnColHeight <= 0) break;
                            excessAnnColHeight -= childHeights[i];
                            itemsToRemove++;
                        }
                        itemsToRemove = Math.max(1, itemsToRemove);

                        // Batch write
                        for (let i = 0; i < itemsToRemove; i++) {
                            if (annCont.lastElementChild) {
                                annCont.removeChild(annCont.lastElementChild);
                            }
                        }

                        if(annCont.children.length === 0) {
                            document.getElementById('announcements-wrapper').style.display = 'none';
                        }
                    }
                }
            }
        }

        window.addEventListener('load', function() {
            const toggleWrapper = document.getElementById('audio-toggle-wrapper');
            if (toggleWrapper) toggleWrapper.style.display = 'none';

            const toggleCheckbox = document.getElementById('audio-toggle-checkbox');
            if (toggleCheckbox) {
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
            }

            initializeApp();
        });

        function formatAddressTitleCase(str) {
            if (!str) return str;
            let lowerStr = str.toLowerCase();
            let words = lowerStr.split(/( |\-)/);
            for (let i = 0; i < words.length; i++) {
                if (words[i].length > 0 && words[i] !== ' ' && words[i] !== '-') {
                    words[i] = words[i].charAt(0).toUpperCase() + words[i].slice(1);
                }
            }
            let titleStr = words.join('');
            titleStr = titleStr.replace(/\b(Nw|Ne|Sw|Se)\b/g, (match) => match.toUpperCase());
            titleStr = titleStr.replace(/\bMc([a-zA-Z])/gi, (match, p1) => 'Mc' + p1.toUpperCase());
            titleStr = titleStr.replace(/\bMac([a-zA-Z])/gi, (match, p1) => 'Mac' + p1.toUpperCase());
            titleStr = titleStr.replace(/\b(\d+)(St|Nd|Rd|Th)\b/gi, (match, num, suffix) => num + suffix.toLowerCase());
            titleStr = titleStr.replace(/\bMe\b/g, 'ME');
            titleStr = titleStr.replace(/\bPo Box\b/gi, 'PO Box');
            return titleStr;
        }

        function formatMapTooltipAddress(rawLocation) {
            if (!rawLocation) return 'Address not provided';
            let parts = rawLocation.split(',').map(p => p.trim());
            let street = parts[0];
            let city = parts.length > 1 ? parts[1] : null;
            if (city && (city.toLowerCase() === 'maine' || city.toLowerCase() === 'me')) {
                city = null;
            }
            let resultStr = street;
            if (city && city.toLowerCase() !== 'oakland') {
                resultStr += ', ' + city;
            }
            return formatAddressTitleCase(resultStr);
        }

        function escapeHtml(unsafe) {
            return (unsafe || "").toString()
                 .replace(/&/g, "&amp;")
                 .replace(/</g, "&lt;")
                 .replace(/>/g, "&gt;")
                 .replace(/"/g, "&quot;")
                 .replace(/'/g, "&#039;");
        }

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

        async function loadAppConfig() {
            try {
                const urlParams = new URLSearchParams(window.location.search);
                const token = urlParams.get('token') || '';
                const configUrl = 'api/get_config.php' + (token ? '?token=' + encodeURIComponent(token) : '');
                const configResponse = await fetch(configUrl, { cache: 'no-store' });
                if (!configResponse.ok) throw new Error("Config fetch failed");
                appConfig = await configResponse.json();

                applyTheme();

                // Play silent audio on user interaction to unlock audio context
                document.body.addEventListener('click', function unlockAudio() {
                    if (appConfig.dashboard_settings && appConfig.dashboard_settings.audio_enabled) {
                        alertPlayer.play().catch(e => {});
                        voicePlayer.play().catch(e => {});
                        document.body.removeEventListener('click', unlockAudio);
                        const overlay = document.getElementById('audio-unlock-overlay');
                        if (overlay) overlay.style.display = 'none';
                    }
                }, { once: true });

                if (appConfig.headers && appConfig.headers.length === 7) {
                    const headerDivs = document.querySelectorAll('.chores-header div');
                    appConfig.headers.forEach((text, i) => {
                        if(headerDivs[i]) headerDivs[i].innerHTML = text;
                    });
                }
            } catch (e) {
                console.error("Failed to load config, using fallbacks.", e);
                appConfig = {
                    dashboard_settings: { theme: 'dark', pages: { dashboard: { enabled: true, duration: 15 }, calendar: { enabled: true, duration: 15 }, chores: { enabled: true, duration: 15 } } },
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
        }

        let sseReconnectDelay = 1000;
        let eventSource = null;

        function setupSSEConnection() {
            if (eventSource) {
                eventSource.close();
            }

            const token = encodeURIComponent(new URLSearchParams(window.location.search).get('token') || '');
            eventSource = new EventSource('api/stream_updates.php?token=' + token);

            eventSource.addEventListener('update', function(e) {
                console.log("SSE Update received:", e.data);
                try {
                    const data = JSON.parse(e.data);
                    if (data.changed) {
                        if (data.changed.includes('permits') || data.changed.includes('fire_danger') || data.changed.includes('mainefireweather')) {
                            // Instead of reloading everything, intelligently update what changed
                            if (data.changed.includes('fire_danger') || data.changed.includes('mainefireweather')) {
                                loadFireDanger();
                            }
                            if (data.changed.includes('permits')) {
                                loadBurnPermits();
                            }
                        }
                    }
                } catch (err) {
                    console.error("Error parsing SSE data", err);
                }
            });

            eventSource.addEventListener('timeout', function(e) {
                // Server closed after timeout, browser will auto-reconnect.
                // Reset exponential backoff.
                sseReconnectDelay = 1000;
            });

            eventSource.onerror = function(err) {
                console.error("EventSource failed:", err);
                eventSource.close();

                // Exponential backoff reconnect
                setTimeout(setupSSEConnection, sseReconnectDelay);
                sseReconnectDelay = Math.min(sseReconnectDelay * 2, 60000); // max 1 minute
            };

            eventSource.onopen = function() {
                console.log("SSE Connection opened");
                sseReconnectDelay = 1000;
            };
        }

        // Also keep a 15-minute fallback to update calendars/meetings
        function setupDataRefreshSchedules() {
            setInterval(updateAllData, 900000);

            // Midnight calendar refresh
            const now = new Date();
            const millisTillMidnight = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1, 0, 1, 0) - now;
            setTimeout(() => { updateAllData(); setInterval(updateAllData, 3600000); }, millisTillMidnight);
        }


        async function initializeApp() {
            initPermitMap();

            await loadAppConfig();

            updateAllData().then(() => {
                startRotation();
            });

            setupDataRefreshSchedules();
            setupSSEConnection();
        }

        async function updateAllData() {
            calendarMonthOffset = 0;

            if (appConfig && appConfig.dashboard_settings && appConfig.dashboard_settings.theme === 'auto') {
                applyTheme(); // Refresh auto theme
            }

            try {
                // ⚡ Bolt Optimization: Initialize all independent asynchronous fetches concurrently
                // This prevents rendering waterfall delays caused by sequentially awaiting network calls.
                const fireSchedulePromise = fetchFireSchedule();
                const holidaysPromise = loadHolidays();
                const townMeetingsPromise = fetchAllTownMeetings();
                const fireDangerPromise = loadFireDanger();
                const burnPermitsPromise = loadBurnPermits();

                const [fireSchedule, townMeetings] = await Promise.all([
                    fireSchedulePromise,
                    townMeetingsPromise,
                    holidaysPromise,
                    fireDangerPromise,
                    burnPermitsPromise
                ]);

                currentFireEvents = fireSchedule;
                currentTownMeetings = townMeetings;

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
            const token = encodeURIComponent(new URLSearchParams(window.location.search).get('token') || '');
            const proxyUrl = `api/fetch_calendar.php?url=${encodeURIComponent(scheduleUrl)}&_cb=${Date.now()}&token=${token}`;
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

            // High-Frequency Window (8:00 AM - 12:00 PM EST), assuming system timezone is reasonably close or user wants local time 8-12.
            if (hour >= 8 && hour < 12 && !isUpdatedToday) {
                pollInterval = 300000; // 5 minutes
            }


        }


        function showUpdateToast(message, type) {
            const toast = document.getElementById('fire-danger-toast');
            if(!toast) return;
            toast.textContent = message;
            toast.className = 'show ' + type;

            if (window.fireDangerToastTimeout) clearTimeout(window.fireDangerToastTimeout);

            if (type !== 'loading') {
                window.fireDangerToastTimeout = setTimeout(() => {
                    toast.classList.remove('show');
                }, 4000);
            }
        }

        async function loadFireDanger(force = false) {
            let fetchUrl = `api/fetch_mainefireweather.php?nocache=${Date.now()}&token=${encodeURIComponent(new URLSearchParams(window.location.search).get('token') || '')}`;
            if (force) {
                fetchUrl += '&force=1';
                showUpdateToast("Updating...", "loading");
            }


            const fireDangerApiUrl = `api/get_fire_danger.php?nocache=${Date.now()}&token=${encodeURIComponent(new URLSearchParams(window.location.search).get('token') || '')}`;
            const meterDiv = document.getElementById('danger-meter');
            const dateDiv = document.getElementById('danger-date');

            let lastUpdateStr = "";

            let riskLevel = "Unknown";

            // Primary: Fetch from mainefireweather api
            try {
                const mwfUrl = fetchUrl;
                const mwfResp = await fetch(mwfUrl);
                if (mwfResp.ok) {
                    const mwfData = await mwfResp.json(); window.lastMwfData = mwfData;
                    const zone = appConfig.fire_danger_zone || '7';

                    if (mwfData && mwfData.classdays && mwfData.classdays[zone]) {
                        const levelInt = parseInt(mwfData.classdays[zone]);
                        const levelsMap = { 1: "Snow Cover", 2: "Low", 3: "Moderate", 4: "High", 5: "Very High", 6: "Extreme" };
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

                const imgContainer = document.getElementById('danger-image-container');
                const imgActive = document.getElementById('danger-image-active');
                const imgStandby = document.getElementById('danger-image-standby');

                const baseSrc = "assets/images/" + riskLevel.toLowerCase().replace(/ /g, '') + ".png";
                const newSrc = baseSrc + "?t=" + Date.now();

                let currentPath = "";
                try { currentPath = new URL(imgActive.src, window.location.href).pathname; } catch(e){}

                if (!currentPath.includes(baseSrc)) {
                    imgStandby.style.transition = 'none';
                    imgStandby.style.opacity = '0';
                    void imgStandby.offsetWidth;

                    imgStandby.src = newSrc;
                    imgStandby.onload = () => {
                        imgStandby.style.transition = 'opacity 0.5s ease-in-out';
                        imgStandby.style.opacity = '1';
                        imgActive.style.opacity = '0';

                        setTimeout(() => {
                            imgActive.style.transition = 'none';
                            imgActive.src = newSrc;
                            imgActive.style.opacity = '1';
                            imgStandby.style.transition = 'none';
                            imgStandby.style.opacity = '0';

                            imgContainer.style.display = 'block';

                            void imgActive.offsetWidth;
                            imgActive.style.transition = 'opacity 0.5s ease-in-out';
                        }, 500); // match transition duration
                    };
                } else {
                    if (imgActive) imgActive.src = newSrc;
                    if (imgContainer) imgContainer.style.display = 'block';
                }
                if (lastUpdateStr !== "") {
                    dateDiv.textContent = `Published by Maine Forest Service (${lastUpdateStr})`;
                } else {
                    dateDiv.textContent = "Published by Maine Forest Service";
                }




                if (meterDiv.dataset.lastLevel !== riskLevel) {
                     announceFireDanger(riskLevel);
                }
                meterDiv.dataset.lastLevel = riskLevel;

                if (force) {
                    if (lastUpdateStr !== "") {
                        showUpdateToast(`Updated: ${riskLevel} (${lastUpdateStr})`, "success");
                    } else {
                        showUpdateToast(`Updated: ${riskLevel}`, "success");
                    }
                }
            } else {
                hasFireDanger = false;
                document.getElementById('top-section').style.display = 'none';

                meterDiv.textContent = "Unavailable";
                meterDiv.className = "danger-meter";
                const imgContainer = document.getElementById('danger-image-container');
                if (imgContainer) imgContainer.style.display = 'none';
                dateDiv.textContent = "Will be available once published by the state (usually after 9a).";


                delete meterDiv.dataset.lastLevel;
                if (force) {
                    showUpdateToast("Failed to update", "error");
                }
            }
        }

        async function loadBurnPermits() {
            const today = new Date().toLocaleDateString();
            if (today !== permitCheckDate) {
                announcedPermitUIDs.clear();
                permitCheckDate = today;
            }
            const container = document.getElementById('burnPermitsList');

            const useEmailPermits = appConfig.email_integration && appConfig.email_integration.permit_address && appConfig.email_integration.permit_address.trim() !== '';

            try {
                let todaysPermits = [];
                let activePermits = [];

                let permitsSource = 'ics';
                if (useEmailPermits) {
                    const fetchUrl = `api/get_permits.php?nocache=${Date.now()}&token=${encodeURIComponent(new URLSearchParams(window.location.search).get('token') || '')}`;
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

                    if (activePermits.length > 0) {
                        permitsSource = 'email';
                    }
                }

                if (permitsSource === 'ics') {
                    todaysPermits = [];
                    activePermits = [];
                    const calendarUrl = `${appConfig.calendar_urls?.burn_permits || 'https://calendar.google.com/calendar/ical/permitsburn@gmail.com/public/basic.ics'}?nocache=${Date.now()}`;
                    const token = encodeURIComponent(new URLSearchParams(window.location.search).get('token') || '');
                    const fetchUrl = `api/fetch_calendar.php?url=${encodeURIComponent(calendarUrl)}&_cb=${Date.now()}&token=${token}`;

                    const response = await fetch(fetchUrl, { headers: { 'Pragma': 'no-cache', 'Cache-Control': 'no-cache' }, cache: 'no-store' });
                    if (!response.ok) { throw new Error(`Network response was not ok`) }
                    const icsData = await response.text();
                    const jcalData = ICAL.parse(icsData);
                    const comp = new ICAL.Component(jcalData);
                    const allEvents = comp.getAllSubcomponents('vevent');
                    const windowStart = new Date();
                    windowStart.setHours(9, 0, 0, 0);
                    const nowForWindow = new Date();
                    if (nowForWindow.getHours() < 9) {
                        windowStart.setDate(windowStart.getDate() - 1);
                    }
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
                        let address = permit.location ? formatAddressTitleCase(permit.location.split(',')[0].trim()) : 'Address not provided';
                        announceNewBurnPermit(address);
                        announcedPermitUIDs.add(permit.uid);
                    }
                });

                activePermits.sort((a, b) => a.startDate.toJSDate() - b.startDate.toJSDate());

                container.innerHTML = '';
                if (activePermits.length > 0) {
                    hasBurnPermits = true;
                    const fragment = document.createDocumentFragment();
                    if (permitsSource === 'email') {
                        activePermits.forEach(e => renderBurnPermitJsonEvent(e, fragment));
                    } else {
                        activePermits.forEach(e => renderBurnPermitEvent(e, fragment));
                    }
                    container.appendChild(fragment);
                } else {
                    hasBurnPermits = false;
                    container.innerHTML = '<div class="no-burn-permits"><img src="assets/images/no_burn_permits.png" alt="No Burn Permits"><p>No active burn permits at this time.</p></div>';
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
            const settings = appConfig?.dashboard_settings || {};
            if (!settings.audio_enabled || !settings.tts_enabled) return;
            try {
                const token = encodeURIComponent(new URLSearchParams(window.location.search).get('token') || '');
                const response = await fetch('api/speak.php?token=' + token, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ text: textToRead }) });
                if (!response.ok) { fallbackTTS(textToRead); return; }
                const arrayBuffer = await response.arrayBuffer();
                const blob = new Blob([arrayBuffer], { type: 'audio/mpeg' });
                const audioUrl = URL.createObjectURL(blob);
                voicePlayer.src = audioUrl;
                voicePlayer.play().catch(e => console.error("Could not play TTS.", e));
            } catch (err) { fallbackTTS(textToRead); }
        }

        function fallbackTTS(textToRead) {
            const settings = appConfig?.dashboard_settings || {};
            if (!settings.audio_enabled || !settings.tts_enabled || !window.speechSynthesis) return;
            window.speechSynthesis.cancel();
            const utterance = new SpeechSynthesisUtterance(textToRead);
            utterance.lang = 'en-US';
            utterance.rate = 0.9;
            window.speechSynthesis.speak(utterance);
        }

        function announceFireDanger(level) {
            const settings = appConfig?.dashboard_settings || {};
            if (!settings.audio_enabled) return;
            try {
                alertPlayer.src = settings.alert_audio_fire || 'https://cdn.freesound.org/previews/219/219244_4032688-lq.mp3';
                alertPlayer.volume = 0.5;
                alertPlayer.play().catch(e => {
                    if (e.name === 'NotAllowedError') {
                        const overlay = document.getElementById('audio-unlock-overlay');
                        if (overlay) overlay.style.display = 'flex';
                    }
                });
                alertPlayer.onended = () => {
                    if (settings.tts_enabled) {
                        playGoogleTTS(`Today's Fire Danger is: ${level}`);
                    }
                };
            } catch (e) { console.error(e); }
        }

        function announceNewBurnPermit(address) {
            const settings = appConfig?.dashboard_settings || {};
            if (!settings.audio_enabled) return;
            try {
                alertPlayer.src = settings.alert_audio_permit || 'https://cdn.freesound.org/previews/415/415763_6142149-lq.mp3';
                alertPlayer.volume = 0.6;
                alertPlayer.play().catch(e => {
                    if (e.name === 'NotAllowedError') {
                        const overlay = document.getElementById('audio-unlock-overlay');
                        if (overlay) overlay.style.display = 'flex';
                    }
                });
                alertPlayer.onended = () => {
                    if (settings.tts_enabled) {
                        playGoogleTTS(`New Permit Issued for ${address}`);
                    }
                };
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
            let summary = escapeHtml(meeting.summary || 'Meeting');

            if (isDeptEvent && meeting.eventType) {
                const loc = escapeHtml(meeting.location || '');
                if (meeting.eventType === 'Room Rental') {
                    summary = `Room Rental${loc ? ' (' + loc + ')' : ''} - ${summary}`;
                } else if (meeting.eventType !== 'Training') {
                    summary = `${summary}${loc ? ' - ' + loc : ''}`;
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
                <div class="event-right"><div class="event-until" style="font-size:0.9em;">${escapeHtml(timeStr)}</div></div>
            </div>`;
        }

        async function fetchAllTownMeetings() {
            const calendarUrl = `${appConfig.calendar_urls?.town_meetings || 'https://calendar.google.com/calendar/ical/amarshall@oaklandme.gov/public/basic.ics'}?nocache=${Date.now()}`;
            const token = encodeURIComponent(new URLSearchParams(window.location.search).get('token') || '');
            const proxyUrl = `api/fetch_calendar.php?url=${encodeURIComponent(calendarUrl)}&_cb=${Date.now()}&token=${token}`;
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
                            // Perf opt: cache duration and construct occurrence instead of calling getOccurrenceDetails in loop
                            const duration = vevent.duration;
                            const iterator = vevent.iterator();
                            let next;
                            while ((next = iterator.next()) && next.toJSDate() < nextYear) {
                                const endDate = next.clone();
                                endDate.addDuration(duration);
                                allMeetings.push({ startDate: next, endDate: endDate, item: vevent });
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
                let annHtml = "";
                appConfig.announcements.forEach(ann => {
                    if (ann.start_date <= todayStr && ann.end_date >= todayStr) {
                        hasAnn = true;
                        annHtml += `<div class="announcement-card announcement-content">${escapeHtml(ann.content)}</div>`;
                    }
                });
                if(annHtml) annCont.insertAdjacentHTML('beforeend', annHtml);
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
                        let meetingHtml = "";
                        todaysMeetings.forEach(meeting => {
                            meetingHtml += createMeetingEventHtml(meeting, false);
                        });
                        townListDiv.insertAdjacentHTML('beforeend', meetingHtml);
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
                    let deptHtml = "";
                    todaysDeptEvents.forEach(meeting => {
                        deptHtml += createMeetingEventHtml(meeting, true);
                    });
                    deptListDiv.insertAdjacentHTML('beforeend', deptHtml);
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
                const nowFragment = document.createDocumentFragment();
                combinedOnDutyNow.forEach(e => renderEvent(e, nowFragment));
                onDutyContainer.appendChild(nowFragment);
            } else {
                onDutyContainer.innerHTML = '<p class="no-events">No personnel on duty.</p>';
            }

            onDutyLater.sort((a, b) => a.startDate.toJSDate() - b.startDate.toJSDate());
            const combinedOnDutyLater = combineConsecutiveShifts(onDutyLater);
            if (combinedOnDutyLater.length > 0) {
                onDutyLaterWrapper.style.display = 'block';
                const laterFragment = document.createDocumentFragment();
                combinedOnDutyLater.forEach(e => renderEvent(e, laterFragment));
                onDutyLaterContainer.appendChild(laterFragment);
            } else {
                onDutyLaterWrapper.style.display = 'none';
            }
        }

        async function loadHolidays() {
            const holidayUrl = `${appConfig.calendar_urls?.holidays || 'https://calendars.icloud.com/holidays/us_en-us.ics'}?nocache=${Date.now()}`;
            const token = encodeURIComponent(new URLSearchParams(window.location.search).get('token') || '');
            const proxyUrl = `api/fetch_calendar.php?url=${encodeURIComponent(holidayUrl)}&_cb=${Date.now()}&token=${token}`;
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
                choreList.insertAdjacentHTML('beforeend', `<li>${vehicleTasks.join(' & ')}</li>`);
            }
            if (dayOfWeek === 5) { choreList.insertAdjacentHTML('beforeend', `<li>Complete Medication Logs</li>`); }
        }

        function renderNumberedChores(now, choreList) {
            const choreNum = getChoreNumber(now);
            const todaysChores = appConfig.chores ? appConfig.chores.filter(c => c.id == choreNum) : [];
            if (todaysChores.length > 0) {
                let choresHtml = "";
                todaysChores.forEach(c => { choresHtml += `<li>Clean ${escapeHtml(c.name)} (#${choreNum})</li>`; });
                choreList.insertAdjacentHTML('beforeend', choresHtml);
            } else {
                 choreList.insertAdjacentHTML('beforeend', `<li>Clean (#${choreNum})</li>`);
            }
        }

        function renderSpecialChores(now, choreList) {
            const todaysSpecialChores = getTodaysSpecialChores(now);
            let spHtml = "";
            todaysSpecialChores.forEach(scName => {
                spHtml += `<li style="color:#20c997;">${escapeHtml(scName)}</li>`;
            });
            if(spHtml) choreList.insertAdjacentHTML('beforeend', spHtml);
        }

        function renderEverydayChores(choreList) {
            const everyDayTasks = appConfig.everyday_chores || ["Clean Bathrooms", "Empty Trash Cans", "Wash Coffee Pot and Dishes"];
            let html = "";
            everyDayTasks.forEach(task => { html += `<li>${escapeHtml(task)}</li>`; });
            if(html) choreList.insertAdjacentHTML('beforeend', html);
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

        function getChoreNumber(date) {
            if (!appConfig || !appConfig.chore_anchor || !appConfig.chores || appConfig.chores.length === 0) return 1;
            const [year, month, day] = (appConfig.chore_anchor || "2025-07-15").split('-');
            const anchorTime = new Date(Date.UTC(year, month - 1, day, 12, 0, 0)).getTime();

            const targetTime = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate(), 12, 0, 0)).getTime();
            const diffDays = Math.round((targetTime - anchorTime) / (1000 * 60 * 60 * 24));

            const uniqueIds = [...new Set(appConfig.chores.map(item => item.id))].sort((a,b)=>a-b);
            const totalChores = parseInt(appConfig.chore_num_indices) || uniqueIds.length || 6;

            let choreIndex = (((diffDays % totalChores) + totalChores) % totalChores) + 1;
            return choreIndex;
        }


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
                    // Perf opt: cache duration and construct occurrence instead of calling getOccurrenceDetails in loop
                    const duration = vevent.duration;
                    const iterator = vevent.iterator();
                    let next;
                    while ((next = iterator.next()) && next.toJSDate() < maxParseDate) {
                        const endDate = next.clone();
                        endDate.addDuration(duration);
                        processOccurrence({ startDate: next, endDate: endDate, item: vevent });
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

                const choreNum = getChoreNumber(currentDay);
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
                openShiftsList.innerHTML = '<p class="no-events" style="font-size: 0.9em;">No open shifts found.</p>';
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

        let rotationTimeout = null;

        function startRotation() {
            if (rotationTimeout) clearTimeout(rotationTimeout);
            const pages = [ document.getElementById('page-dashboard'), document.getElementById('page-calendar'), document.getElementById('page-chores') ];
            const pageKeys = ['dashboard', 'calendar', 'chores'];

            const pagesConfig = (appConfig && appConfig.dashboard_settings && appConfig.dashboard_settings.pages) ||
                                { dashboard: { enabled: true, duration: 15 }, calendar: { enabled: true, duration: 15 }, chores: { enabled: true, duration: 15 } };

            function isPageEnabled(index) {
                const key = pageKeys[index];
                if (!pagesConfig[key] || !pagesConfig[key].enabled) return false;
                if (index === 0 && !hasFireDanger && !hasBurnPermits) return false; // Existing logic
                return true;
            }

            // Fallback if all pages disabled
            let anyEnabled = false;
            for(let i=0; i<pages.length; i++) {
                if(isPageEnabled(i)) { anyEnabled = true; break; }
            }
            if(!anyEnabled) {
                // Force dashboard if everything is disabled so screen isn't blank
                pages[0].style.display = 'flex';
                pages[1].style.display = 'none';
                pages[2].style.display = 'none';
                return;
            }

            // Ensure current index is enabled
            let attempts = 0;
            while (!isPageEnabled(currentPageIndex) && attempts < pages.length) {
                currentPageIndex = (currentPageIndex + 1) % pages.length;
                attempts++;
            }

            pages.forEach((page, index) => {
                if(page) page.style.display = (index === currentPageIndex) ? 'flex' : 'none';
            });

            performPruning(currentPageIndex);

            if (pages[currentPageIndex].id === 'page-dashboard' && permitMap && hasBurnPermits) {
                setTimeout(() => permitMap.invalidateSize(), 0);
            }

            function rotateNext() {
                if (document.getElementById('permit-modal-overlay').style.display === 'flex') {
                    // Paused, try again later without advancing
                    rotationTimeout = setTimeout(rotateNext, 1000);
                    return;
                }

                pages[currentPageIndex].style.display = 'none';

                let nextIndex = (currentPageIndex + 1) % pages.length;
                let findAttempts = 0;
                while (!isPageEnabled(nextIndex) && findAttempts < pages.length) {
                    nextIndex = (nextIndex + 1) % pages.length;
                    findAttempts++;
                }
                currentPageIndex = nextIndex;

                const newPage = pages[currentPageIndex];
                if (newPage) {
                    newPage.style.display = 'flex';
                    performPruning(currentPageIndex);

                    if (newPage.id === 'page-dashboard' && permitMap && hasBurnPermits) {
                        setTimeout(() => permitMap.invalidateSize(), 10);
                    }
                }

                const currentKey = pageKeys[currentPageIndex];
                const durationSeconds = (pagesConfig[currentKey] && pagesConfig[currentKey].duration) ? pagesConfig[currentKey].duration : 15;
                rotationTimeout = setTimeout(rotateNext, durationSeconds * 1000);
            }

            const initialKey = pageKeys[currentPageIndex];
            const initialDurationSeconds = (pagesConfig[initialKey] && pagesConfig[initialKey].duration) ? pagesConfig[initialKey].duration : 15;
            rotationTimeout = setTimeout(rotateNext, initialDurationSeconds * 1000);
        }

        function processEventForDashboard(vevent, searchStart, windowEnd, now, onDutyNow, onDutyLater) {
            if (vevent.isRecurring()) {
                // Perf opt: cache duration and construct occurrence instead of calling getOccurrenceDetails in loop
                const duration = vevent.duration;
                const iterator = vevent.iterator(ICAL.Time.fromJSDate(searchStart));
                let next;
                while ((next = iterator.next()) && next.toJSDate() < windowEnd) {
                    const endDate = next.clone();
                    endDate.addDuration(duration);
                    const occurrence = { startDate: next, endDate: endDate, item: vevent };
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

        function showPermitDetails(detailsHtml, lat = null, lon = null) {
            pauseRotation();
            const modalOverlay = document.getElementById('permit-modal-overlay');
            const modalBody = document.getElementById('permit-modal-body');

            const cleanedDetailsHtml = detailsHtml.replace(/<td[^>]*>.*?DEPARTMENT OF AGRICULTURE, CONSERVATION &amp; FORESTRY<br>\s*OPEN BURNING PERMIT.*?<\/td>/s, '');

            let html = `<table><tbody>${cleanedDetailsHtml}</tbody></table>`;
            if (lat !== null && lon !== null) {
                html += '<div id="modalMap" style="height: 200px; width: 100%; margin-top: clamp(5px, 1.5vh, 15px); border-radius: 4px; pointer-events: none;"></div>';
            }
            modalBody.innerHTML = html;
            modalOverlay.style.display = 'flex';

            if (lat !== null && lon !== null) {
                const modalMapInstance = L.map('modalMap', {zoomControl: false, dragging: false, scrollWheelZoom: false, doubleClickZoom: false}).setView([lat, lon], 15);
                const tileUrl = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
                L.tileLayer(tileUrl, {
                    maxZoom: 19
                }).addTo(modalMapInstance);
                L.marker([lat, lon], { icon: flameIcon }).addTo(modalMapInstance);
                setTimeout(() => { modalMapInstance.invalidateSize(); }, 100);
            }

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


        function loadParcelsInBounds() {
            if (!permitMap) return;
            const currentZoom = permitMap.getZoom();

            if (currentZoom >= 15) {
                const bounds = permitMap.getBounds();
                const xmin = bounds.getWest();
                const ymin = bounds.getSouth();
                const xmax = bounds.getEast();
                const ymax = bounds.getNorth();

                const envelope = `${xmin},${ymin},${xmax},${ymax}`;

                const url = `https://services1.arcgis.com/ymRuOiGrZIWWY3H2/ArcGIS/rest/services/Oak_Parcels_Publish/FeatureServer/0/query?geometryType=esriGeometryEnvelope&geometry=${encodeURIComponent(envelope)}&inSR=4326&spatialRel=esriSpatialRelIntersects&outFields=OWNER_S_NAME,LOCATION_ADDRESS,MAP_LOT_1&outSR=4326&f=geojson`;

                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        if (parcelLayer) {
                            permitMap.removeLayer(parcelLayer);
                        }

                        parcelLayer = L.geoJSON(data, {
                            style: {
                                color: '#2b8cbe',
                                weight: 1.5,
                                opacity: 0.7,
                                fillOpacity: 0.1
                            },
                            onEachFeature: function (feature, layer) {
                                if (feature.properties) {
                                    const owner = feature.properties.OWNER_S_NAME || 'Unknown Owner';
                                    const address = feature.properties.LOCATION_ADDRESS || 'No Address';
                                    const mapLot = feature.properties.MAP_LOT_1 || 'N/A';

                                    const popupContent = `
                                        <div style="font-size: 14px; font-family: 'Inter', sans-serif; color: var(--text-color);">
                                            <strong>${owner}</strong><br/>
                                            ${address}<br/>
                                            <span style="color: var(--muted-text); font-size: 12px;">Map/Lot: ${mapLot}</span>
                                        </div>
                                    `;
                                    layer.bindPopup(popupContent);
                                }
                            }
                        });
                        parcelLayer.addTo(permitMap);
                    })
                    .catch(err => console.error("Error loading parcels:", err));
            } else {
                if (parcelLayer) {
                    permitMap.removeLayer(parcelLayer);
                    parcelLayer = null;
                }
            }
        }

        function initPermitMap() {
            if (permitMap) return;
            try {
                permitMap = L.map('permitMap').setView([44.5445, -69.7262], 12);
                permitMapLayerControl = L.control.layers(null, null, {position: 'topright'}).addTo(permitMap);
                const tileUrl = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
                L.tileLayer(tileUrl, {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    maxZoom: 19
                }).addTo(permitMap);

                // Load Town Boundary
                fetch("https://services1.arcgis.com/ymRuOiGrZIWWY3H2/ArcGIS/rest/services/OaklandState/FeatureServer/6/query?where=TOWN='Oakland'&outFields=*&outSR=4326&f=geojson")
                    .then(response => response.json())
                    .then(data => {
                        L.geoJSON(data, {
                            style: {
                                color: '#ff7800',
                                weight: 3,
                                opacity: 0.8,
                                fillOpacity: 0.05
                            },
                            interactive: false // don't block clicks to underlying map/parcels
                        }).addTo(permitMap);
                    })
                    .catch(err => console.error("Error loading town boundary:", err));

                permitMap.on('moveend', loadParcelsInBounds);
                // Also load initially if zoom is right
                loadParcelsInBounds();
            } catch (e) {
                console.error("Could not initialize permit map.", e);
                document.getElementById('permitMap').innerHTML = "Map service unavailable.";
            }
        }


        async function fetchAndRenderMapLocations() {
            if (!permitMap) return;
            try {
                const res = await fetch(`api/get_locations.php?token=${encodeURIComponent(new URLSearchParams(window.location.search).get('token') || '')}`);
                const locations = await res.json();

                if (staticLocationsLayerGroup) {
                    staticLocationsLayerGroup.clearLayers();
                } else {
                    staticLocationsLayerGroup = L.layerGroup().addTo(permitMap);
                    if(permitMapLayerControl) permitMapLayerControl.addOverlay(staticLocationsLayerGroup, 'Map Locations');
                }

                // Add predefined map locations
                locations.forEach(loc => {
                    if (loc.lat && loc.lon) {
                        let color = '#3388ff';
                        let radius = 5;

                        if (loc.type === 'Hydrant' || loc.type === 'Dry Hydrant') { color = '#007bff'; radius = 4; }
                        else if (loc.type === 'Fire Station') { color = '#dc3545'; radius = 6; }
                        else if (loc.type === 'Pump House') { color = '#28a745'; radius = 4; }

                        const marker = L.circleMarker([loc.lat, loc.lon], {
                            radius: radius,
                            fillColor: color,
                            color: '#fff',
                            weight: 1,
                            opacity: 1,
                            fillOpacity: 0.8
                        });

                        marker.bindPopup(`<strong>${loc.type || 'Location'}</strong><br>${loc.address}`);
                        marker.addTo(staticLocationsLayerGroup);
                    }
                });

                // Add fire stations from config
                if (appConfig && appConfig.department_info && appConfig.department_info.stations) {
                    const stations = appConfig.department_info.stations;

                    const stationPromises = stations.map(async (station) => {
                        const address = station.address;
                        if (!address) return null;

                        let geocoded = null;
                        if (geocodeCache.has(address)) {
                            geocoded = geocodeCache.get(address);
                        } else {
                            const url = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(address)}&format=json&limit=1`;
                            try {
                                const nominatimResult = await fetch(url).then(res => res.json());
                                if (nominatimResult && nominatimResult.length > 0) {
                                    geocoded = nominatimResult;
                                    geocodeCache.set(address, nominatimResult);
                                } else {
                                    const fallbackUrl = `api/gemini_geocode.php?address=${encodeURIComponent(address)}&token=${encodeURIComponent(new URLSearchParams(window.location.search).get('token') || '')}`;
                                    const geminiResult = await fetch(fallbackUrl).then(res => res.json());
                                    if (geminiResult && geminiResult.lat && geminiResult.lon) {
                                        geocoded = [{ lat: geminiResult.lat, lon: geminiResult.lon }];
                                        geocodeCache.set(address, geocoded);
                                    }
                                }
                            } catch (e) {
                                console.error("Error geocoding station:", e);
                            }
                        }

                        if (geocoded && geocoded.length > 0) {
                            return { station: station, coords: geocoded[0] };
                        }
                        return null;
                    });

                    const resolvedStations = await Promise.all(stationPromises);

                    resolvedStations.forEach(result => {
                        if (result) {
                            const { station, coords } = result;
                            const marker = L.marker([coords.lat, coords.lon], {
                                icon: stationIcon,
                                zIndexOffset: 1000 // Ensure stations are always on top
                            });

                            marker.bindPopup(`<strong>${station.number}</strong><br>${station.address}`);
                            marker.addTo(staticLocationsLayerGroup);

                            // Include in bounds calculations
                            locations.push({ lat: coords.lat, lon: coords.lon });
                        }
                    });
                }
            } catch (e) {
                console.error("Failed to load map locations", e);
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

                if (geocodeCache.has(address)) {
                    return geocodeCache.get(address); // Performance: Avoid Promise wrapper overhead for cached hits
                }

                // Clean up intersection addresses for better Nominatim compatibility
                let searchAddress = address;
                searchAddress = searchAddress.replace(/^Corner of\s+/i, '');
                searchAddress = searchAddress.replace(/^Intersection of\s+/i, '');

                const url = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(searchAddress)}&format=json&limit=1`;
                return fetch(url).then(res => res.json()).then(async (nominatimResult) => {
                    if (nominatimResult && nominatimResult.length > 0) {
                        geocodeCache.set(address, nominatimResult);
                        return nominatimResult;
                    } else {
                        console.warn("Nominatim failed for:", address, ". Falling back to Gemini...");
                        const fallbackUrl = `api/gemini_geocode.php?address=${encodeURIComponent(address)}&token=${encodeURIComponent(new URLSearchParams(window.location.search).get('token') || '')}`;
                        return fetch(fallbackUrl).then(res => res.json()).then(geminiResult => {
                            if (geminiResult && geminiResult.lat && geminiResult.lon) {
                                const result = [{ lat: geminiResult.lat, lon: geminiResult.lon }];
                                geocodeCache.set(address, result);
                                return result;
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

            const dynamicIconSize = permits.length > 5 ? 24 : 48;
            const dynamicIconAnchor = dynamicIconSize / 2;
            const dynamicFlameIcon = L.divIcon({
                className: "custom-tv-marker",
                html: "<div style=\"width: 25px; height: 25px; background-color: #ff3b30; border: 3px solid white; border-radius: 50%; box-shadow: 0 0 10px rgba(255, 59, 48, 0.8);\"></div>",
                iconSize: [25, 25],
                iconAnchor: [12, 12],
                popupAnchor: [0, -12],
                tooltipAnchor: [15, 0]
            });
            /*
                iconUrl: 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0iI2Y5NzQwNiIgc3Ryb2tlPSIjZGMyNjI2IiBzdHJva2Utd2lkdGg9IjEuNSI+PHBhdGggZD0iTT EyIDJDMTIgMiA4IDYuNSA4IDExYzAgNCA0IDggNCA4czQtNCA0LThjMC00LjUtNC05LTQtOXoiLz48cGF0aCBkPSJNMTIgNGMtMS41IDEuNS0yIDQtMiA2cy41IDQuNSAyIDYiLz48cGF0aCBkPSJNMTQuNSAxM2MuNS0xLjUgMS0zLjUgMC01LjUiLz48L3N2Zz4=',
                iconSize: [dynamicIconSize, dynamicIconSize],
                iconAnchor: [dynamicIconAnchor, dynamicIconSize],
                popupAnchor: [0, -dynamicIconSize],
                tooltipAnchor: [dynamicIconAnchor, -Math.floor(dynamicIconSize * 0.6)]
            }); */

            results.forEach((result, index) => {
                if (result && result.length > 0) {
                    const { lat, lon } = result[0];
                    const permit = permits[index];
                    const address = formatMapTooltipAddress(permit.location);
                    const detailsHtml = permit.description.split('Details:')[1] || '';

                    const marker = L.marker([lat, lon], { icon: dynamicFlameIcon }).addTo(permitMap)
                        .bindTooltip(address, {
                            permanent: true,
                            direction: 'right',
                            offset: [10, 0],
                            className: 'permit-tooltip'
                        });

                    if (detailsHtml.trim() !== '') {
                        marker.on('click', () => {
                            showPermitDetails(detailsHtml, lat, lon);
                        });
                    }

                    if(!permitsLayerGroup) { permitsLayerGroup = L.layerGroup().addTo(permitMap); permitMapLayerControl.addOverlay(permitsLayerGroup, 'Burn Permits'); }
                    marker.addTo(permitsLayerGroup);
                    permitMarkers.push(marker);
                    locations.push([lat, lon]);
                }
            });

            if (locations.length > 0) {
                const bounds = L.latLngBounds(locations);
                permitMap.fitBounds(bounds, { padding: [75, 75], maxZoom: 12 });
                fetchAndRenderMapLocations();

                setTimeout(() => permitMap.invalidateSize(), 100);
            }
        }

                function renderBurnPermitJsonEvent(eventData, container) {
            const eventDiv = document.createElement('div');
            eventDiv.classList.add('event');

            const uid = eventData.uid || '';
            let address = eventData.address ? formatAddressTitleCase(eventData.address.split(',')[0].trim()) : 'Unknown Address'; if (address !== 'Unknown Address') { address = address.replace(/\b(?:Oakland(?:\s+Maine|\s+ME)?|Maine|ME)\b/gi, '').trim(); address = formatAddressTitleCase(address); }
            const type = eventData.type || 'Open Burn';

            const expDate = new Date(eventData.expires);
            const timeStr = expDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

            eventDiv.innerHTML = `
                <div class="permit-details">
                    <div class="permit-address">${escapeHtml(address)}</div>
                    <div class="permit-burn-info">
                        <span class="permit-type">${escapeHtml(type)}</span>
                        <span class="permit-time">Expires: ${escapeHtml(timeStr)}</span>
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
                        <tr><td>Address:</td><td><strong>${escapeHtml(permitData.address)}</strong></td></tr>
                        <tr><td>Type:</td><td><strong>${escapeHtml(permitData.type)}</strong></td></tr>
                        <tr><td>Expires:</td><td><strong>${escapeHtml(new Date(permitData.expires).toLocaleString())}</strong></td></tr>
                        <tr><td>Details:</td><td>${escapeHtml(permitData.details || '').replace(/\n/g, '<br>')}</td></tr>
                    </tbody>
                </table>
            `;
            modalBody.innerHTML = tableHTML;
            modalOverlay.style.display = 'flex';
        }


        function renderBurnPermitEvent(eventData, container) {
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
                eventDiv.addEventListener('click', () => {
                    let lat = null, lon = null;
                    if (eventData.location) {
                        const addr = eventData.location;
                        if (geocodeCache.has(addr)) {
                            const res = geocodeCache.get(addr);
                            if (res && res.length > 0) {
                                lat = res[0].lat;
                                lon = res[0].lon;
                            }
                        }
                    }
                    showPermitDetails(detailsHtml, lat, lon);
                });
            }

            let displayAddress = eventData.location ? formatAddressTitleCase(eventData.location.split(',')[0].trim()) : 'Address not provided'; if (displayAddress !== 'Address not provided') { displayAddress = displayAddress.replace(/\b(?:Oakland(?:\s+Maine|\s+ME)?|Maine|ME)\b/gi, '').trim(); displayAddress = formatAddressTitleCase(displayAddress); }
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
                    <div class="permit-address">${escapeHtml(displayAddress)}</div>
                    <div class="permit-burn-info">
                        <span class="permit-type">${escapeHtml(burnType)}</span>
                    </div>
                    <div class="permit-time">${escapeHtml(startTime)} - ${escapeHtml(endTime)}</div>
                </div>
            `;

            container.appendChild(eventDiv);
        }

    </script>
</body>
</html>
