import re

with open('index.html', 'r') as f:
    content = f.read()

# I see the user passed the file content in the prompt, let's just write that fixed content directly.
# Wait, the conflict markers are in the content the user provided... Let's use regex to resolve it.

# Resolve theme colors: use the user's `main` branch colors, as they are their explicit preference
content = re.sub(
r"<<<<<<< refactor-calendars-and-ui-8145019277421902235\n.*?=======\n(.*?)\n>>>>>>> main",
r"\1",
content,
flags=re.DOTALL)

# But wait, there are several conflicts where I want MY changes (like the scrollable CSS fixes)
# I will handle them one by one.

# Let's read the current file with conflict markers
with open('index.html', 'r') as f:
    content = f.read()

# 1. Theme variables (use theirs)
c1_mine = """        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap');

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
            --item-bg: #10141a;"""

c1_theirs = """

        /* Default Dark Theme Variables */
        :root {
            --bg-color: #0f1e2d;
            --card-bg: #162b40;
            --text-color: #fff;
            --muted-text: #aeb6c1;
            --border-color: #1f3d5c;
            --event-hover: #1f3d5c;
            --today-bg: #004085;
            --today-border: #9fceff;
            --item-bg: #0f1e2d;"""

# 2. Light Theme Overrides (use theirs)
c2_mine = """            --bg-color: #f5f5f7;
            --card-bg: #ffffff;
            --text-color: #1d1d1f;
            --muted-text: #86868b;
            --border-color: #d2d2d7;
            --event-hover: #f0f0f5;
            --today-bg: #e6f2ff;
            --today-border: #007bff;
            --item-bg: #fbfbfd;"""

c2_theirs = """            --bg-color: #e1e1e1;
            --card-bg: #f0f0f0;
            --text-color: #1d1d1f;
            --muted-text: #86868b;
            --border-color: #c3c3c3;
            --event-hover: #e1e1e1;
            --today-bg: #e6f2ff;
            --today-border: #007bff;
            --item-bg: #ffffff;"""

# 3. Font family (use theirs)
c3_mine = """            font-family: 'Inter', sans-serif;"""
c3_theirs = """            font-family: Arial, Helvetica, sans-serif;"""

# 4. h2 font family (use theirs)
c4_mine = """            font-family: 'Agency FB', sans-serif;"""
c4_theirs = """            font-family: Arial, Helvetica, sans-serif;"""

# 5. event border radius (use theirs)
c5_mine = """            border-radius: 4px;"""
c5_theirs = """            border-radius: 0px;"""

# 6. fire danger content border radius (use theirs)
c6_mine = """        #fire-danger-content { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; box-sizing: border-box; background-color: var(--card-bg); border-radius: 4px; padding: 20px;}"""
c6_theirs = """        #fire-danger-content { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; box-sizing: border-box; background-color: var(--card-bg); border-radius: 0px; padding: 20px;}"""

# 7. Layout scroll fixes (USE MINE BUT KEEP THEIR BORDERS)
c7_mine = """        #permitMap { height: 100%; width: 100%; border-radius: 4px; }
        .permit-tooltip { background-color: rgba(255, 255, 255, 0.9); border: 1px solid #888; border-radius: 3px; color: #333; font-weight: bold; padding: 4px 8px; font-size: 10pt; box-shadow: 0 1px 3px rgba(0,0,0,0.4); }
        .flame-marker-icon { filter: drop-shadow(0px 2px 3px rgba(0, 0, 0, 0.7)); }

        #page-calendar { display: none; flex-direction: column; gap: 15px; }
        .calendar-content-row { display: flex; flex: 1; gap: 15px; min-height: 0; margin-top: 15px; overflow-y: auto; }
        .calendar-main-content { flex: 5; display: flex; flex-direction: column; min-height: 0; }
        .calendar-sidebar { flex: 1; background-color: var(--card-bg); border-radius: 8px; padding: 10px; display: flex; flex-direction: column; overflow-y: auto; min-height: 0; }"""

c7_theirs = """        #permitMap { height: 100%; width: 100%; border-radius: 0px; }
        .permit-tooltip { background-color: rgba(255, 255, 255, 0.9); border: 1px solid #888; border-radius: 0px; color: #333; font-weight: bold; padding: 4px 8px; font-size: 10pt; box-shadow: 0 1px 3px rgba(0,0,0,0.4); }
        .flame-marker-icon { filter: drop-shadow(0px 2px 3px rgba(0, 0, 0, 0.7)); }

        #page-calendar { display: none; flex-direction: column; gap: 15px; }
        .calendar-content-row { display: flex; flex: 1; gap: 15px; min-height: 0; margin-top: 15px; }
        .calendar-main-content { flex: 5; display: flex; flex-direction: column; min-height: 0; }
        .calendar-sidebar { flex: 1; background-color: var(--card-bg); border-radius: 0px; padding: 10px; display: flex; flex-direction: column; overflow: hidden; min-height: 0; }"""

c7_merged = """        #permitMap { height: 100%; width: 100%; border-radius: 0px; }
        .permit-tooltip { background-color: rgba(255, 255, 255, 0.9); border: 1px solid #888; border-radius: 0px; color: #333; font-weight: bold; padding: 4px 8px; font-size: 10pt; box-shadow: 0 1px 3px rgba(0,0,0,0.4); }
        .flame-marker-icon { filter: drop-shadow(0px 2px 3px rgba(0, 0, 0, 0.7)); }

        #page-calendar { display: none; flex-direction: column; gap: 15px; }
        .calendar-content-row { display: flex; flex: 1; gap: 15px; min-height: 0; margin-top: 15px; overflow-y: auto; }
        .calendar-main-content { flex: 5; display: flex; flex-direction: column; min-height: 0; }
        .calendar-sidebar { flex: 1; background-color: var(--card-bg); border-radius: 0px; padding: 10px; display: flex; flex-direction: column; overflow-y: auto; min-height: 0; }"""

# 8. open shift item radius
c8_mine = """            background-color: var(--item-bg); padding: 10px 12px; border-radius: 6px;"""
c8_theirs = """            background-color: var(--item-bg); padding: 10px 12px; border-radius: 0px;"""

# 9. calendar grid min height (USE MINE)
c9_mine = """        #calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 5px; flex-grow: 1; min-height: min-content; }"""
c9_theirs = """        #calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 5px; flex-grow: 1; min-height: 0; }"""

# 10. calendar day height and border radius (USE MINE FOR MIN HEIGHT, THEIRS FOR RADIUS)
c10_mine = """        .calendar-day { background-color: var(--card-bg); border: 1px solid var(--border-color); padding: 5px; font-size: 10pt; display: flex; flex-direction: column; overflow: hidden; min-height: 100px; min-width: 0; border-radius: 4px;}"""
c10_theirs = """        .calendar-day { background-color: var(--card-bg); border: 1px solid var(--border-color); padding: 5px; font-size: 10pt; display: flex; flex-direction: column; overflow: hidden; min-height: 0; min-width: 0; border-radius: 0px;}"""
c10_merged = """        .calendar-day { background-color: var(--card-bg); border: 1px solid var(--border-color); padding: 5px; font-size: 10pt; display: flex; flex-direction: column; overflow: hidden; min-height: 100px; min-width: 0; border-radius: 0px;}"""

# 11. calendar event radius (USE THEIRS)
c11_mine = """        .calendar-event { font-size: clamp(6px, 1.2vh, 10pt); padding: 1px 4px; border-radius: 3px; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; border: 1px solid transparent; line-height: 1.2; flex-shrink: 1; min-height: 0; }"""
c11_theirs = """        .calendar-event { font-size: clamp(6px, 1.2vh, 10pt); padding: 1px 4px; border-radius: 0px; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; border: 1px solid transparent; line-height: 1.2; flex-shrink: 1; min-height: 0; }"""

# 12. chore item radius (USE THEIRS)
c12_mine = """        .legend-color-box { width: 20px; height: 20px; border-radius: 4px; }
        .chore-item { background-color: var(--card-bg); border: 1px solid var(--border-color); padding: 25px 40px; border-radius: 8px; text-align: center; overflow: hidden; min-height: 0; }"""
c12_theirs = """        .legend-color-box { width: 20px; height: 20px; border-radius: 0px; }
        .chore-item { background-color: var(--card-bg); border: 1px solid var(--border-color); padding: 25px 40px; border-radius: 0px; text-align: center; overflow: hidden; min-height: 0; }"""

# 13. permit modal radius (USE THEIRS)
c13_mine = """        #permit-modal-content { background-color: var(--card-bg); padding: 15px; border-radius: 8px; border: 1px solid var(--muted-text); width: 100%; max-width: 900px; color: var(--text-color); font-size: 14pt; cursor: pointer; display: flex; flex-direction: column; }"""
c13_theirs = """        #permit-modal-content { background-color: var(--card-bg); padding: 15px; border-radius: 0px; border: 1px solid var(--muted-text); width: 100%; max-width: 900px; color: var(--text-color); font-size: 14pt; cursor: pointer; display: flex; flex-direction: column; }"""

# 14. announcement card radius (USE THEIRS)
c14_mine = """        .announcement-card { background: var(--item-bg); padding: 20px; border-radius: 6px; border: 1px solid var(--border-color); margin-bottom: 10px; text-align: left;}"""
c14_theirs = """        .announcement-card { background: var(--item-bg); padding: 20px; border-radius: 0px; border: 1px solid var(--border-color); margin-bottom: 10px; text-align: left;}"""

# 15. permit container radius (USE THEIRS)
c15_mine = """                 <div id="burnPermitsContainer" style="flex: 2; background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 4px; overflow: hidden; min-height: 0;"></div>
                 <div id="permitMap" style="flex: 1; border-radius: 4px;"></div>"""
c15_theirs = """                 <div id="burnPermitsContainer" style="flex: 2; background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0px; overflow: hidden; min-height: 0;"></div>
                 <div id="permitMap" style="flex: 1; border-radius: 0px;"></div>"""

# 16. duties page date (USE MINE - hidden)
c16_mine = """        <!-- <p id="duties-page-date" class="duties-date"></p> -->"""
c16_theirs = """        <p id="duties-page-date" class="duties-date"></p>"""

# API logic conflicts: MINE is API folder, THEIRS is root
c17_mine = """                const configResponse = await fetch('api/get_config.php', { cache: 'no-store' });"""
c17_theirs = """                const configResponse = await fetch('admin.php?api=true', { cache: 'no-store' });"""

c18_mine = """            const scheduleUrl = `${appConfig.calendar_urls?.main || 'https://calendar.google.com/calendar/ical/c303c9aa08e0a090db126a0b15eb0bc0e8b66cc1af810aa971059b7b01b6d25a@group.calendar.google.com/public/basic.ics'}?nocache=${Date.now()}`;
            const proxyUrl = `api/fetch_calendar.php?url=${encodeURIComponent(scheduleUrl)}&_cb=${Date.now()}`;"""
c18_theirs = """            const scheduleUrl = `https://calendar.google.com/calendar/ical/c303c9aa08e0a090db126a0b15eb0bc0e8b66cc1af810aa971059b7b01b6d25a@group.calendar.google.com/public/basic.ics?nocache=${Date.now()}`;
            const proxyUrl = `fetch_calendar.php?url=${encodeURIComponent(scheduleUrl)}&_cb=${Date.now()}`;"""

c19_mine = """            const fireDangerCalendarUrl = `${appConfig.calendar_urls?.burn_permits || 'https://calendar.google.com/calendar/ical/permitsburn@gmail.com/public/basic.ics'}?nocache=${Date.now()}`;
            const proxyUrl = `api/fetch_calendar.php?url=${encodeURIComponent(fireDangerCalendarUrl)}&_cb=${Date.now()}`;"""
c19_theirs = """            const fireDangerCalendarUrl = `https://calendar.google.com/calendar/ical/permitsburn@gmail.com/public/basic.ics?nocache=${Date.now()}`;
            const proxyUrl = `fetch_calendar.php?url=${encodeURIComponent(fireDangerCalendarUrl)}&_cb=${Date.now()}`;"""

c20_mine = """            const calendarUrl = `${appConfig.calendar_urls?.burn_permits || 'https://calendar.google.com/calendar/ical/permitsburn@gmail.com/public/basic.ics'}?nocache=${Date.now()}`;
            const fetchUrl = `api/fetch_calendar.php?url=${encodeURIComponent(calendarUrl)}&_cb=${Date.now()}`;"""
c20_theirs = """            const calendarUrl = `https://calendar.google.com/calendar/ical/permitsburn@gmail.com/public/basic.ics?nocache=${Date.now()}`;
            const fetchUrl = `fetch_calendar.php?url=${encodeURIComponent(calendarUrl)}&_cb=${Date.now()}`;"""

c21_mine = """                const response = await fetch('api/speak.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ text: textToRead }) });"""
c21_theirs = """                const response = await fetch('speak.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ text: textToRead }) });"""

c22_mine = """            const calendarUrl = `${appConfig.calendar_urls?.town_meetings || 'https://calendar.google.com/calendar/ical/amarshall@oaklandme.gov/public/basic.ics'}?nocache=${Date.now()}`;
            const proxyUrl = `api/fetch_calendar.php?url=${encodeURIComponent(calendarUrl)}&_cb=${Date.now()}`;"""
c22_theirs = """            const calendarUrl = `https://calendar.google.com/calendar/ical/amarshall@oaklandme.gov/public/basic.ics?nocache=${Date.now()}`;
            const proxyUrl = `fetch_calendar.php?url=${encodeURIComponent(calendarUrl)}&_cb=${Date.now()}`;"""

c23_mine = """            const holidayUrl = `${appConfig.calendar_urls?.holidays || 'https://calendars.icloud.com/holidays/us_en-us.ics'}?nocache=${Date.now()}`;
            const proxyUrl = `api/fetch_calendar.php?url=${encodeURIComponent(holidayUrl)}&_cb=${Date.now()}`;"""
c23_theirs = """            const holidayUrl = `https://calendars.icloud.com/holidays/us_en-us.ics?nocache=${Date.now()}`;
            const proxyUrl = `fetch_calendar.php?url=${encodeURIComponent(holidayUrl)}&_cb=${Date.now()}`;"""


import re

def resolve(content, mine, theirs, choice):
    # Create the conflict pattern dynamically
    # Need to escape special characters for regex
    mine_esc = re.escape(mine)
    theirs_esc = re.escape(theirs)

    pattern = rf"<<<<<<< .*?\n{mine_esc}\n=======\n{theirs_esc}\n>>>>>>> main\n"
    content = re.sub(pattern, choice + "\n", content)

    # Handle the case where they appear without the trailing newline
    pattern2 = rf"<<<<<<< .*?\n{mine_esc}\n=======\n{theirs_esc}\n>>>>>>> main"
    content = re.sub(pattern2, choice, content)

    return content

content = resolve(content, c1_mine, c1_theirs, c1_theirs)
content = resolve(content, c2_mine, c2_theirs, c2_theirs)
content = resolve(content, c3_mine, c3_theirs, c3_theirs)
content = resolve(content, c4_mine, c4_theirs, c4_theirs)
content = resolve(content, c5_mine, c5_theirs, c5_theirs)
content = resolve(content, c6_mine, c6_theirs, c6_theirs)
content = resolve(content, c7_mine, c7_theirs, c7_merged)
content = resolve(content, c8_mine, c8_theirs, c8_theirs)
content = resolve(content, c9_mine, c9_theirs, c9_mine)
content = resolve(content, c10_mine, c10_theirs, c10_merged)
content = resolve(content, c11_mine, c11_theirs, c11_theirs)
content = resolve(content, c12_mine, c12_theirs, c12_theirs)
content = resolve(content, c13_mine, c13_theirs, c13_theirs)
content = resolve(content, c14_mine, c14_theirs, c14_theirs)
content = resolve(content, c15_mine, c15_theirs, c15_theirs)
content = resolve(content, c16_mine, c16_theirs, c16_mine)
content = resolve(content, c17_mine, c17_theirs, c17_mine)
content = resolve(content, c18_mine, c18_theirs, c18_mine)
content = resolve(content, c19_mine, c19_theirs, c19_mine)
content = resolve(content, c20_mine, c20_theirs, c20_mine)
content = resolve(content, c21_mine, c21_theirs, c21_mine)
content = resolve(content, c22_mine, c22_theirs, c22_mine)
content = resolve(content, c23_mine, c23_theirs, c23_mine)

with open('index.php', 'w') as f: # Save back to index.php
    f.write(content)
