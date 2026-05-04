const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const TMP_DIR = path.join(__dirname, 'tmp_email');
const TMP_API_DIR = path.join(TMP_DIR, 'api');
const PORT = 8003;
const MOCK_PORT = 8004;
const BASE_URL = `http://127.0.0.1:${PORT}`;
const MOCK_URL = `http://127.0.0.1:${MOCK_PORT}/gemini_mock.php?key=`;

let phpServer;
let mockServer;

beforeAll((done) => {
    // 1. Create tmp directory structure
    if (fs.existsSync(TMP_DIR)) {
        fs.rmSync(TMP_DIR, { recursive: true, force: true });
    }
    fs.mkdirSync(TMP_API_DIR, { recursive: true });

    // 2. Copy files to tmp/
    const srcFile = path.join(__dirname, '../api/process_email.php');
    const destFile = path.join(TMP_API_DIR, 'process_email.php');
    fs.copyFileSync(srcFile, destFile);

    const secSrc = path.join(__dirname, '../api/security_check.php');
    const secDest = path.join(TMP_API_DIR, 'security_check.php');
    if (fs.existsSync(secSrc)) fs.copyFileSync(secSrc, secDest);

    const mockSrc = path.join(__dirname, 'gemini_mock.php');
    const mockDest = path.join(TMP_DIR, 'gemini_mock.php');
    fs.copyFileSync(mockSrc, mockDest);

    // 3. Patch the Gemini URL in the temporary process_email.php using Node.js fs
    let content = fs.readFileSync(destFile, 'utf8');
    const target = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=';
    content = content.replace(target, MOCK_URL);
    fs.writeFileSync(destFile, content);

    // 4. Create a mock config.json
    const mockConfig = {
        email_integration: {
            danger_address: 'danger@domain.com',
            permit_address: 'permit@domain.com'
        },
        fire_danger_zone: '7',
        api_integrations: {
            gemini_api_key: 'mock-key'
        }
    };
    fs.writeFileSync(path.join(TMP_DIR, 'config.json'), JSON.stringify(mockConfig));

    // 5. Spawn PHP servers
    phpServer = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', TMP_DIR]);
    mockServer = spawn('php', ['-S', `127.0.0.1:${MOCK_PORT}`, '-t', TMP_DIR]);

    // Wait for the servers to start
    setTimeout(done, 1000);
});

afterAll(() => {
    if (phpServer) phpServer.kill();
    if (mockServer) mockServer.kill();

    if (fs.existsSync(TMP_DIR)) {
        fs.rmSync(TMP_DIR, { recursive: true, force: true });
    }
});

async function fetchAndParse(url, options) {
    const response = await fetch(url, options);
    const text = await response.text();
    const jsonStart = text.indexOf('{');
    if (jsonStart !== -1) {
        return JSON.parse(text.substring(jsonStart));
    }
    throw new Error('No JSON found in response: ' + text);
}

describe('process_email.php (Burn Permits)', () => {

    test('extracts fully populated valid burn permit email', async () => {
        const emailBody = `To: permit@domain.com\r\nSubject: Burn Permit\r\n\r\nPermission is hereby granted to: John Doe (DOB: 01/01/1980) 123 Main St, Oakland, ME 04963 Phone: 555-1234 -- Email: john@example.com Date/Time Permit was Issued: 04/15/2024 10:00 AM
Address of Burn Location: 456 Fire Rd, Oakland, ME 04963 Burn Location on the Property: Backyard Municipality/Unorganized Territory: Oakland
Burn Type: Brush Type of Item(s) to Burn: Branches and leaves Burn Requirements: Must have water source
Burning may be conducted from 10:00 AM to 5:00 PM on 04/15/2024`;

        const data = await fetchAndParse(`${BASE_URL}/api/process_email.php?test=true`, {
            method: 'POST',
            body: emailBody
        });

        expect(data.type).toBe('permit');

        const permit = data.data;
        expect(permit.name).toBe('John Doe');
        expect(permit.person_address).toBe('123 Main St, Oakland, ME 04963');
        expect(permit.phone).toBe('555-1234');
        expect(permit.email).toBe('john@example.com');
        expect(permit.burn_location_address).toBe('456 Fire Rd, Oakland, ME 04963');
        expect(permit.burn_location_property).toBe('Backyard');
        expect(permit.burn_type).toBe('Brush');
        expect(permit.items_to_burn).toBe('Branches and leaves');
        expect(permit.expires.startsWith('2024-04-15')).toBe(true);
    });

    test('uses Gemini fallback for malformed burn permit email', async () => {
        const emailBody = `To: permit@domain.com\r\nSubject: Burn Permit\r\n\r\nThis is a non-standard burn permit email that should trigger Gemini.`;

        const data = await fetchAndParse(`${BASE_URL}/api/process_email.php?test=true`, {
            method: 'POST',
            body: emailBody
        });

        expect(data.type).toBe('permit');
        const permit = data.data;
        expect(permit.name).toBe('Mock User');
        expect(permit.person_address).toBe('Mock Address');
        expect(permit.phone).toBe('555-MOCK');
    });

    test('handles -- as empty burn location address', async () => {
        const emailBody = `To: permit@domain.com\r\nSubject: Burn Permit\r\n\r\nPermission is hereby granted to: Lily Rogers (DOB: 12/12/1980 ) 318 Wottons Mill Rd , Warren , ME 04864 , US Phone: (207) 691-9215 -- Email: tigerlily3305@gmail.com Date/Time Permit was Issued 04/22/2026 04:48 PM
Address of Burn Location: -- Burn Location on the Property: -- Municipality/Unorganized Territory: Oakland
Burn Type: Pile - brush/slash/debris Type of Item(s) to Burn: Brush/Lumber (pile less than 10' X 10')
Burning may be conducted from 4:48 pm on 04/22/2026 to 9:00 am on 04/23/2026`;

        const data = await fetchAndParse(`${BASE_URL}/api/process_email.php?test=true`, {
            method: 'POST',
            body: emailBody
        });

        expect(data.type).toBe('permit');

        const permit = data.data;
        expect(permit.name).toBe('Lily Rogers');
        expect(permit.person_address).toBe('318 Wottons Mill Rd , Warren , ME 04864 , US');
        expect(permit.address).toBe('318 Wottons Mill Rd , Warren , ME 04864 , US');
        expect(permit.burn_location_address).toBe('--');
    });

    test('provides defaults when Gemini is disabled or fails', async () => {
        // Disable Gemini by removing API key from config
        const mockConfig = {
            email_integration: {
                danger_address: 'danger@domain.com',
                permit_address: 'permit@domain.com'
            },
            fire_danger_zone: '7',
            api_integrations: {
                gemini_api_key: ''
            }
        };
        fs.writeFileSync(path.join(TMP_DIR, 'config.json'), JSON.stringify(mockConfig));

        const emailBody = `To: permit@domain.com\r\nSubject: Burn Permit\r\n\r\nSome completely random email body without the expected labels.`;

        const data = await fetchAndParse(`${BASE_URL}/api/process_email.php?test=true`, {
            method: 'POST',
            body: emailBody
        });

        expect(data.type).toBe('permit');
        const permit = data.data;
        expect(permit.name).toBe('Unknown');
        expect(permit.person_address).toBe('Unknown Address');

        // Reset config
        mockConfig.api_integrations.gemini_api_key = 'mock-key';
        fs.writeFileSync(path.join(TMP_DIR, 'config.json'), JSON.stringify(mockConfig));
    });

});

describe('process_email.php (Fire Danger)', () => {
    test('extracts fire danger from primary regex match', async () => {
        const emailBody = `To: danger@domain.com\r\nSubject: Daily Fire Danger\r\n\r\nZone 7 Forecast Fire Danger Extreme today.\r\nSome other text.`;

        const data = await fetchAndParse(`${BASE_URL}/api/process_email.php?test=true`, {
            method: 'POST',
            body: emailBody
        });

        expect(data.type).toBe('danger');
        expect(data.data.level).toBe('Extreme');
    });

    test('extracts fire danger from secondary regex match', async () => {
        const emailBody = `To: danger@domain.com\r\nSubject: Daily Fire Danger\r\n\r\nZone 7 Moderate.\r\nSome other text.`;

        const data = await fetchAndParse(`${BASE_URL}/api/process_email.php?test=true`, {
            method: 'POST',
            body: emailBody
        });

        expect(data.type).toBe('danger');
        expect(data.data.level).toBe('Moderate');
    });

    test('extracts fire danger using fallback mechanism (body match)', async () => {
        const emailBody = `To: danger@domain.com\r\nSubject: Daily Update\r\n\r\nThe danger level for our area is Very High today. Please be careful.`;

        const data = await fetchAndParse(`${BASE_URL}/api/process_email.php?test=true`, {
            method: 'POST',
            body: emailBody
        });

        expect(data.type).toBe('danger');
        expect(data.data.level).toBe('Very High');
    });

    test('extracts fire danger using fallback mechanism (subject match)', async () => {
        const emailBody = `To: danger@domain.com\r\nSubject: Fire Danger is Low\r\n\r\nHere is the daily report.`;

        const data = await fetchAndParse(`${BASE_URL}/api/process_email.php?test=true`, {
            method: 'POST',
            body: emailBody
        });

        expect(data.type).toBe('danger');
        expect(data.data.level).toBe('Low');
    });

    test('uses Gemini fallback when no level matches in regex', async () => {
        const emailBody = `To: danger@domain.com\r\nSubject: Daily Update\r\n\r\nNo information is available today in the body either.`;

        const data = await fetchAndParse(`${BASE_URL}/api/process_email.php?test=true`, {
            method: 'POST',
            body: emailBody
        });

        expect(data.type).toBe('danger');
        expect(data.data.level).toBe('Moderate'); // From mock
    });

    test('handles custom fire_danger_zone in config', async () => {
        const mockConfig = {
            email_integration: {
                danger_address: 'danger@domain.com',
                permit_address: 'permit@domain.com'
            },
            fire_danger_zone: '3',
            api_integrations: {
                gemini_api_key: 'mock-key'
            }
        };
        fs.writeFileSync(path.join(TMP_DIR, 'config.json'), JSON.stringify(mockConfig));

        const emailBody = `To: danger@domain.com\r\nSubject: Daily Fire Danger\r\n\r\nZone 3 Forecast Fire Danger High today.`;

        const data = await fetchAndParse(`${BASE_URL}/api/process_email.php?test=true`, {
            method: 'POST',
            body: emailBody
        });

        expect(data.type).toBe('danger');
        expect(data.data.level).toBe('High');

        mockConfig.fire_danger_zone = '7';
        fs.writeFileSync(path.join(TMP_DIR, 'config.json'), JSON.stringify(mockConfig));
    });

    test('handles HTML content in email body (strip_tags)', async () => {
        const emailBody = `To: danger@domain.com\r\nSubject: Daily Fire Danger\r\n\r\n<p>Zone 7 Forecast Fire Danger <b>Moderate</b> today.</p>`;

        const data = await fetchAndParse(`${BASE_URL}/api/process_email.php?test=true`, {
            method: 'POST',
            body: emailBody
        });

        expect(data.type).toBe('danger');
        expect(data.data.level).toBe('Moderate');
    });

    test('handles case-insensitive matching for headers', async () => {
        const emailBody = `to: danger@domain.com\r\nsubject: daily fire danger\r\n\r\nZone 7: Low today.`;

        const data = await fetchAndParse(`${BASE_URL}/api/process_email.php?test=true`, {
            method: 'POST',
            body: emailBody
        });

        expect(data.type).toBe('danger');
        expect(data.data.level).toBe('Low');
    });
});
