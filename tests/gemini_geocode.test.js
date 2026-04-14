const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const TMP_DIR = path.join(__dirname, 'tmp');
const TMP_API_DIR = path.join(TMP_DIR, 'api');
const PORT = 8001;
const BASE_URL = `http://127.0.0.1:${PORT}`;

let phpServer;

beforeAll((done) => {
    // 1. Create tmp directory structure
    if (fs.existsSync(TMP_DIR)) {
        fs.rmSync(TMP_DIR, { recursive: true, force: true });
    }
    fs.mkdirSync(TMP_API_DIR, { recursive: true });

    // 2. Copy api/gemini_geocode.php to tmp/api/
    const srcFile = path.join(__dirname, '../api/gemini_geocode.php');
    const destFile = path.join(TMP_API_DIR, 'gemini_geocode.php');
    fs.copyFileSync(srcFile, destFile);

    // 3. Create mock config.json with empty API key
    const mockConfig = {
        api_integrations: {
            gemini_api_key: ''
        }
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

describe('gemini_geocode.php', () => {
    test('returns null when API key is missing/empty', async () => {
        const response = await fetch(`${BASE_URL}/api/gemini_geocode.php?address=Oakland`);
        const data = await response.json();

        expect(response.status).toBe(200); // Because the script returns normal 200 with null body
        expect(data).toBeNull();
    });

    test('returns null when config.json is completely missing', async () => {
        // Remove config.json entirely for this test
        fs.unlinkSync(path.join(TMP_DIR, 'config.json'));

        const response = await fetch(`${BASE_URL}/api/gemini_geocode.php?address=Oakland`);
        const data = await response.json();

        expect(response.status).toBe(200);
        expect(data).toBeNull();
    });
});
