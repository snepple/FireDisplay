<?php
$files = ['index.php', 'current_index.php'];
foreach ($files as $file) {
    $content = file_get_contents($file);

    // Check if permit tooltip style uses hardcoded values
    $content = preg_replace('/\.permit-tooltip \{[^\}]*background:[^\}]*\}/', '.permit-tooltip { position: absolute; z-index: 1000; background: var(--card-bg); color: var(--text-color); padding: 8px 12px; border-radius: 6px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); font-size: 14px; pointer-events: none; opacity: 0; transition: opacity 0.2s; white-space: nowrap; border: 1px solid var(--border-color); }', $content);

    // Ensure snow cover text is visible
    $content = preg_replace('/\.risk-snow-cover \{[^\}]*color:[^\}]*\}/', '.risk-snow-cover { background-color: var(--card-bg); color: var(--text-color); border: 2px solid var(--border-color); }', $content);

    // Fix toggle slider color
    $content = str_replace(
        '.toggle-switch input:checked + .toggle-slider:before { transform: translateX(14px); background-color: #fff; }',
        '.toggle-switch input:checked + .toggle-slider:before { transform: translateX(14px); background-color: var(--card-bg); }',
        $content
    );

    // Fix font sizes
    $content = str_replace('#page-chores h2 { font-size: 0.8em; }', '#page-chores h2 { font-size: 1.5em; }', $content);
    $content = str_replace('color: white; z-index: 100000;', 'color: #fff; z-index: 100000;', $content);
    $content = str_replace('<h2 style="font-size: 1.0em; border: none; padding: 0; margin: 0 0 5px 0; color: #ffc107;">📢 Announcements</h2>', '<h2 style="font-size: 1.5em; border: none; padding: 0; margin: 0 0 5px 0; color: #ffc107;">📢 Announcements</h2>', $content);
    $content = str_replace('<h2 style="font-size: 1.0em; border: none; padding: 0; margin: 0 0 5px 0;">📅 Department Events</h2>', '<h2 style="font-size: 1.5em; border: none; padding: 0; margin: 0 0 5px 0;">📅 Department Events</h2>', $content);
    $content = str_replace('<h2 style="font-size: 1.0em; border: none; padding: 0; margin: 0 0 5px 0;">🏛️ Town Meetings Here</h2>', '<h2 style="font-size: 1.5em; border: none; padding: 0; margin: 0 0 5px 0;">🏛️ Town Meetings Here</h2>', $content);

    // Ensure the 4.5em elements exist before replacing them to avoid issues if they don't
    $content = preg_replace('/<h2 style="font-size: \d+\.?\d*em; border: none; padding: 0; margin: 0 0 5px 0;">🧑‍🚒 On Duty<\/h2>/', '<h2 style="font-size: 1.5em; border: none; padding: 0; margin: 0 0 5px 0;">🧑‍🚒 On Duty</h2>', $content);
    $content = preg_replace('/<h2 style="font-size: \d+\.?\d*em; border: none; padding: 0; margin: 0 0 5px 0;">🗓️ On Duty Later Today<\/h2>/', '<h2 style="font-size: 1.5em; border: none; padding: 0; margin: 0 0 5px 0;">🗓️ On Duty Later Today</h2>', $content);

    // Make sure we have the global h2 style
    if (strpos($content, 'h2 { font-size: 1.5em; }') === false) {
        $content = str_replace('#permit-info-panel h2, #page-fire-danger h2, #page-map h2 { border-bottom: 2px solid var(--border-color); padding-bottom: 10px; margin-top: 0; }', "#permit-info-panel h2, #page-fire-danger h2, #page-map h2 { border-bottom: 2px solid var(--border-color); padding-bottom: 10px; margin-top: 0; }\n        h2 { font-size: 1.5em; }", $content);
    }

    // Fix tooltips and backgrounds for dark theme
    $content = str_replace('background: rgba(255, 255, 255, 0.9);', 'background: var(--card-bg);', $content);
    $content = str_replace('color: #333;', 'color: var(--text-color);', $content);
    $content = str_replace('color: #000;', 'color: var(--text-color);', $content);
    $content = str_replace('background: white;', 'background: var(--card-bg);', $content);
    $content = str_replace('.event-dept { background-color: #20c997; color: #000; }', '.event-dept { background-color: #20c997; color: var(--text-color); }', $content);

    file_put_contents($file, $content);
}
echo "Done\n";
