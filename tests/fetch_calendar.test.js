const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const http = require('http');

describe('fetch_calendar.php', () => {
  let phpServer;
  let testConfigPath;
  let testDataDir;

  beforeAll((done) => {
    // Create a temporary configuration to whitelist a specific URL and separate cache
    testConfigPath = path.join(__dirname, '../config.json');
    testDataDir = path.join(__dirname, '../data');

    // Create backup of config.json if it exists
    if (fs.existsSync(testConfigPath)) {
        fs.copyFileSync(testConfigPath, testConfigPath + '.bak');
    }

    const testConfig = {
      calendar_urls: [
        'https://calendars.icloud.com/holidays/us_en-us.ics'
      ]
    };
    fs.writeFileSync(testConfigPath, JSON.stringify(testConfig));

    // Ensure data dir exists
    if (!fs.existsSync(testDataDir)) {
      fs.mkdirSync(testDataDir, { recursive: true });
    }

    // Start PHP built-in server
    phpServer = spawn('php', ['-S', '127.0.0.1:8081', '-t', path.join(__dirname, '../')]);

    // Give the server a moment to start
    setTimeout(done, 1000);
  });

  afterAll(() => {
    // Stop PHP built-in server
    if (phpServer) {
      phpServer.kill();
    }

    // Restore original config
    if (fs.existsSync(testConfigPath + '.bak')) {
        fs.renameSync(testConfigPath + '.bak', testConfigPath);
    } else {
        if (fs.existsSync(testConfigPath)) {
            fs.unlinkSync(testConfigPath);
        }
    }
  });

  it('should fetch allowed calendar URL with SSL enabled', async () => {
    const url = 'https://calendars.icloud.com/holidays/us_en-us.ics';
    const response = await fetch(`http://127.0.0.1:8081/api/fetch_calendar.php?url=${encodeURIComponent(url)}`);

    expect(response.status).toBe(200);
    const text = await response.text();
    expect(text).toContain('BEGIN:VCALENDAR');
    expect(text).toContain('US Holidays');
  });

  it('should return 403 for disallowed URLs', async () => {
    const url = 'https://example.com/not-allowed.ics';
    const response = await fetch(`http://127.0.0.1:8081/api/fetch_calendar.php?url=${encodeURIComponent(url)}`);

    expect(response.status).toBe(403);
  });
});
