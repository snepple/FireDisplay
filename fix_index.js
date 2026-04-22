const fs = require('fs');

let content = fs.readFileSync('index.php', 'utf8');

const replacement = `                function isOutdatedDate(dateStr) {
            if (!dateStr) return true;

            // Try matching "April 21" or "Apr 21" or similar format
            const match = dateStr.match(/([a-zA-Z]+)\\s+(\\d+)/);
            if (!match) return false; // If format is unknown, assume valid to prevent false hiding

            const monthName = match[1].toLowerCase();
            const day = parseInt(match[2], 10);

            const monthNames = ["jan", "feb", "mar", "apr", "may", "jun", "jul", "aug", "sep", "oct", "nov", "dec"];
            let monthIndex = -1;
            for (let i = 0; i < monthNames.length; i++) {
                if (monthName.startsWith(monthNames[i])) {
                    monthIndex = i;
                    break;
                }
            }

            if (monthIndex === -1) return false;

            const now = new Date();
            const dataDate = new Date(now.getFullYear(), monthIndex, day);

            // If dataDate is in the future by more than a month, assume it's from last year
            if (dataDate > now && (dataDate.getTime() - now.getTime() > 30 * 24 * 60 * 60 * 1000)) {
                dataDate.setFullYear(now.getFullYear() - 1);
            }

            const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const dataDayDate = new Date(dataDate.getFullYear(), dataDate.getMonth(), dataDate.getDate());

            const diffTime = Math.abs(today - dataDayDate);
            const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));

            if (dataDayDate < today) {
                if (diffDays === 1) {
                    // Yesterday's data
                    // Check if it's past 8 AM today
                    if (now.getHours() >= 8) {
                        return true; // Outdated (past 8 AM)
                    } else {
                        return false; // Still valid (before 8 AM)
                    }
                } else {
                    return true; // Older than yesterday
                }
            }

            return false;
        }

        async function loadFireDanger(force = false) {
            let fetchUrl = \`api/fetch_mainefireweather.php?nocache=\${Date.now()}\`;
            if (force) { fetchUrl += '&force=1'; }
`;

content = content.replace(/<<<<<<< HEAD[\s\S]*?async function loadFireDanger\(\) \{[\s\S]*?=======[\s\S]*?async function loadFireDanger\(force = false\) \{[\s\S]*?let fetchUrl = `api\/fetch_mainefireweather\.php\?nocache=\$\{Date\.now\(\)\}`;[\s\S]*?if \(force\) \{ fetchUrl \+= '&force=1'; \}[\s\S]*?>>>>>>> origin\/main/m, replacement);

fs.writeFileSync('index.php', content, 'utf8');

let currentContent = fs.readFileSync('current_index.php', 'utf8');
currentContent = currentContent.replace(/<<<<<<< HEAD[\s\S]*?async function loadFireDanger\(\) \{[\s\S]*?=======[\s\S]*?async function loadFireDanger\(force = false\) \{[\s\S]*?let fetchUrl = `api\/fetch_mainefireweather\.php\?nocache=\$\{Date\.now\(\)\}`;[\s\S]*?if \(force\) \{ fetchUrl \+= '&force=1'; \}[\s\S]*?>>>>>>> origin\/main/m, replacement);
fs.writeFileSync('current_index.php', currentContent, 'utf8');
