const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const http = require('http');

describe('fetch_calendar.php', () => {
  let phpServer;
  let mockServer;
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
        'http://127.0.0.1:8082/mock-calendar.ics',
        'http://127.0.0.1:8082/mock-calendar-with-qs.ics?allowed=1',
        'http://localhost:0/mock-calendar.ics' // Using invalid port/host to ensure fast curl failure without hitting network
      ]
    };
    fs.writeFileSync(testConfigPath, JSON.stringify(testConfig));

    // Ensure data dir exists
    if (!fs.existsSync(testDataDir)) {
      fs.mkdirSync(testDataDir, { recursive: true });
    }

    // Start a secondary Node HTTP server to serve the mock calendar without deadlocking PHP
    mockServer = http.createServer((req, res) => {
      if (req.url.startsWith('/mock-calendar.ics') || req.url.startsWith('/mock-calendar-with-qs.ics')) {
        res.writeHead(200, { 'Content-Type': 'text/calendar' });
        res.end('BEGIN:VCALENDAR\nMock Calendar\nEND:VCALENDAR');
      } else {
        res.writeHead(404);
        res.end();
      }
    });
    mockServer.listen(8082);

    // Start PHP built-in server
    phpServer = spawn('php', ['-S', '127.0.0.1:8081', '-t', path.join(__dirname, '../')]);

    // Give the servers a moment to start
    setTimeout(done, 1000);
  });

  afterAll((done) => {
    // Stop PHP built-in server
    if (phpServer) {
      phpServer.kill();
    }

    // Stop mock server
    if (mockServer) {
      mockServer.close();
    }

    // Restore original config
    if (fs.existsSync(testConfigPath + '.bak')) {
        fs.renameSync(testConfigPath + '.bak', testConfigPath);
    } else {
        if (fs.existsSync(testConfigPath)) {
            fs.unlinkSync(testConfigPath);
        }
    }

    done();
  });

  it('should fetch allowed calendar URL and proxy the content', async () => {
    const url = 'http://127.0.0.1:8082/mock-calendar.ics';
    const response = await fetch(`http://127.0.0.1:8081/api/fetch_calendar.php?url=${encodeURIComponent(url)}`);

    expect(response.status).toBe(200);
    const text = await response.text();
    expect(text).toContain('BEGIN:VCALENDAR');
    expect(text).toContain('Mock Calendar');
  });

  it('should return 403 for disallowed URLs', async () => {
    const url = 'https://example.com/not-allowed.ics';
    const response = await fetch(`http://127.0.0.1:8081/api/fetch_calendar.php?url=${encodeURIComponent(url)}`);

    expect(response.status).toBe(403);
  });

  it('should return 400 for missing URL parameter', async () => {
    const response = await fetch(`http://127.0.0.1:8081/api/fetch_calendar.php`);
    expect(response.status).toBe(400);
  });

  it('should fetch allowed calendar URL with query string', async () => {
    const url = 'http://127.0.0.1:8082/mock-calendar.ics?test=123';
    const response = await fetch(`http://127.0.0.1:8081/api/fetch_calendar.php?url=${encodeURIComponent(url)}`);
    expect(response.status).toBe(200);
    const text = await response.text();
    expect(text).toContain('BEGIN:VCALENDAR');
    expect(text).toContain('Mock Calendar');
  });

  it('should return 200 for allowed calendar URL from hardcoded list, ignoring external fetch results', async () => {
    const url = 'https://calendar.google.com/calendar/ical/permitsburn@gmail.com/public/basic.ics';
    const response = await fetch(`http://127.0.0.1:8081/api/fetch_calendar.php?url=${encodeURIComponent(url)}`);
    expect(response.status).toBe(200);
  });

  it('should return 403 for disallowed URL that attempts to spoof an allowed one via query string', async () => {
    // The requested URL is disallowed, but its query string contains an allowed URL.
    const url = 'https://example.com/malicious.ics?fake=http://127.0.0.1:8082/mock-calendar.ics';
    const response = await fetch(`http://127.0.0.1:8081/api/fetch_calendar.php?url=${encodeURIComponent(url)}`);
    expect(response.status).toBe(403);
  });

  it('should return 200 when allowed URL config has query string but requested does not', async () => {
    // The PHP code does explode('?', $url)[0], so requested URL without query string should match the base URL of the allowed one
    const url = 'http://127.0.0.1:8082/mock-calendar-with-qs.ics';
    const response = await fetch(`http://127.0.0.1:8081/api/fetch_calendar.php?url=${encodeURIComponent(url)}`);
    expect(response.status).toBe(200);
  });

  it('should return 200 when allowed URL config has query string and requested has different query string', async () => {
    const url = 'http://127.0.0.1:8082/mock-calendar-with-qs.ics?different=2';
    const response = await fetch(`http://127.0.0.1:8081/api/fetch_calendar.php?url=${encodeURIComponent(url)}`);
    expect(response.status).toBe(200);
  });

  it('should return 403 for path traversal attempts on an allowed URL', async () => {
    // We want to fetch /evil.ics by appending to allowed base.
    // The requested URL will be: http://127.0.0.1:8082/mock-calendar.ics/../evil.ics
    // The base URL check is: 'http://127.0.0.1:8082/mock-calendar.ics/../evil.ics' === 'http://127.0.0.1:8082/mock-calendar.ics'
    // Which is false.
    const url = 'http://127.0.0.1:8082/mock-calendar.ics/../evil.ics';
    const response = await fetch(`http://127.0.0.1:8081/api/fetch_calendar.php?url=${encodeURIComponent(url)}`);
    expect(response.status).toBe(403);
  });

  it('should return 403 for bypass attempt using URL fragment', async () => {
    // Fragments are sent in the URL string here.
    // requested: 'http://127.0.0.1:8082/mock-calendar.ics#evil'
    // base: 'http://127.0.0.1:8082/mock-calendar.ics#evil' !== 'http://127.0.0.1:8082/mock-calendar.ics'
    const url = 'http://127.0.0.1:8082/mock-calendar.ics#evil';
    const response = await fetch(`http://127.0.0.1:8081/api/fetch_calendar.php?url=${encodeURIComponent(url)}`);
    expect(response.status).toBe(403);
  });
});
