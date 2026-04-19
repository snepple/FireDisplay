const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const TMP_DIR = path.join(__dirname, 'tmp_email');
const TMP_API_DIR = path.join(TMP_DIR, 'api');
const PORT = 8003;
const BASE_URL = `http://127.0.0.1:${PORT}`;

let phpServer;

beforeAll((done) => {
    // 1. Create tmp directory structure
    if (fs.existsSync(TMP_DIR)) {
        fs.rmSync(TMP_DIR, { recursive: true, force: true });
    }
    fs.mkdirSync(TMP_API_DIR, { recursive: true });

    // 2. Copy api/process_email.php to tmp/api/
    const srcFile = path.join(__dirname, '../api/process_email.php');
    const destFile = path.join(TMP_API_DIR, 'process_email.php');
    fs.copyFileSync(srcFile, destFile);

    // 3. Create a mock config.json so routing logic does not fail
    const mockConfig = {
        email_integration: {
            danger_address: 'danger@domain.com',
            permit_address: 'permit@domain.com'
        },
        fire_danger_zone: '8'
    };
    fs.writeFileSync(path.join(TMP_DIR, 'config.json'), JSON.stringify(mockConfig));

    // 4. Spawn PHP server
    phpServer = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', TMP_DIR]);

    // Wait for the server to start
    setTimeout(done, 1000);
});

afterAll(() => {
    // 1. Kill PHP server
    if (phpServer) {
        phpServer.kill();
    }

    // 2. Remove tmp directory
    if (fs.existsSync(TMP_DIR)) {
        fs.rmSync(TMP_DIR, { recursive: true, force: true });
    }
});

// Helper function to extract valid JSON from PHP output that includes the CLI shebang
async function fetchAndParse(url, options) {
    const response = await fetch(url, options);
    const text = await response.text();
    const jsonStart = text.indexOf('{');
    if (jsonStart !== -1) {
        return JSON.parse(text.substring(jsonStart));
    }
    throw new Error('No JSON found in response');
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

        // Expiration date check. 04/15/2024 parsed as UTC might change slightly based on timezone in PHP,
        // but it should be close to 2024-04-15
        expect(permit.expires.startsWith('2024-04-15')).toBe(true);
    });

    test('handles missing or malformed fields gracefully', async () => {
        const emailBody = `To: permit@domain.com\r\nSubject: Burn Permit\r\n\r\nPermission is hereby granted to:  (DOB: unknown)  Phone:   -- Email:  Date/Time Permit was Issued:
Address of Burn Location:  Burn Location on the Property:  Municipality/Unorganized Territory:
Burn Type:  Type of Item(s) to Burn:  Burn Requirements: `;

        const data = await fetchAndParse(`${BASE_URL}/api/process_email.php?test=true`, {
            method: 'POST',
            body: emailBody
        });

        expect(data.type).toBe('permit');

        const permit = data.data;
        expect(permit.name).toBe('');
        expect(permit.person_address).toBe('');
        expect(permit.phone).toBe('');
        expect(permit.email).toBe('');
        expect(permit.burn_location_address).toBe('');
        expect(permit.burn_location_property).toBe('');
        expect(permit.burn_type).toBe('');
        expect(permit.items_to_burn).toBe('');
    });

    test('provides defaults when regex fails entirely', async () => {
        const emailBody = `To: permit@domain.com\r\nSubject: Burn Permit\r\n\r\nSome completely random email body without the expected labels.`;

        const data = await fetchAndParse(`${BASE_URL}/api/process_email.php?test=true`, {
            method: 'POST',
            body: emailBody
        });

        expect(data.type).toBe('permit');

        const permit = data.data;
        expect(permit.name).toBe('Unknown');
        expect(permit.person_address).toBe('Unknown Address');
        expect(permit.phone).toBe('Unknown Phone');
        expect(permit.email).toBe('Unknown Email');
        expect(permit.burn_location_address).toBe('');
        expect(permit.burn_location_property).toBe('');
        expect(permit.burn_type).toBe('Open Burn');
        expect(permit.items_to_burn).toBe('');

        // Expiration date should default to +1 day. Check if it's somewhat valid ISO string
        expect(new Date(permit.expires).getTime()).not.toBeNaN();
    });
});

describe('process_email.php (Fire Danger)', () => {
    test('extracts fire danger from primary regex match', async () => {
        const emailBody = `To: danger@domain.com\r\nSubject: Daily Fire Danger\r\n\r\nZone 8 Forecast Fire Danger Extreme today.\r\nSome other text.`;

        const data = await fetchAndParse(`${BASE_URL}/api/process_email.php?test=true`, {
            method: 'POST',
            body: emailBody
        });

        expect(data.type).toBe('danger');
        expect(data.data.level).toBe('Extreme');
    });

    test('extracts fire danger from secondary regex match', async () => {
        const emailBody = `To: danger@domain.com\r\nSubject: Daily Fire Danger\r\n\r\nZone 8 Moderate.\r\nSome other text.`;

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

    test('returns Unknown when no level matches', async () => {
        const emailBody = `To: danger@domain.com\r\nSubject: Daily Update\r\n\r\nNo information is available today.`;

        const data = await fetchAndParse(`${BASE_URL}/api/process_email.php?test=true`, {
            method: 'POST',
            body: emailBody
        });

        expect(data.type).toBe('danger');
        expect(data.data.level).toBe('Unknown');
    });
});
