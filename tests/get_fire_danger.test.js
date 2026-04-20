const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const http = require('http');

const TMP_DIR = path.join(__dirname, 'tmp_get_fire_danger');
const TMP_API_DIR = path.join(TMP_DIR, 'api');
const TMP_DATA_DIR = path.join(TMP_DIR, 'data');
const PORT = 8006;
const BASE_URL = `http://127.0.0.1:${PORT}`;

let phpServer;

const waitForServer = (url, timeout = 5000) => {
    const start = Date.now();
    return new Promise((resolve, reject) => {
        const check = () => {
            http.get(url, (res) => {
                resolve();
            }).on('error', (err) => {
                if (Date.now() - start > timeout) {
                    reject(new Error(`Server at ${url} did not start in time`));
                } else {
                    setTimeout(check, 100);
                }
            });
        };
        check();
    });
};

beforeAll(async () => {
    // 1. Create tmp directory structure
    if (fs.existsSync(TMP_DIR)) {
        fs.rmSync(TMP_DIR, { recursive: true, force: true });
    }
    fs.mkdirSync(TMP_API_DIR, { recursive: true });
    fs.mkdirSync(TMP_DATA_DIR, { recursive: true });

    // 2. Copy api/get_fire_danger.php to tmp directory
    const srcFile = path.join(__dirname, '../api/get_fire_danger.php');
    const destFile = path.join(TMP_API_DIR, 'get_fire_danger.php');
    fs.copyFileSync(srcFile, destFile);

    // 3. Spawn PHP server
    phpServer = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', TMP_DIR]);

    // Wait for the server to start by polling
    await waitForServer(`${BASE_URL}/api/get_fire_danger.php`);
});

afterAll((done) => {
    // 1. Kill PHP server
    if (phpServer) {
        phpServer.kill();
    }

    // 2. Remove tmp directory
    if (fs.existsSync(TMP_DIR)) {
        fs.rmSync(TMP_DIR, { recursive: true, force: true });
    }

    done();
});

describe('get_fire_danger.php', () => {

    test('returns correct headers', async () => {
        const response = await fetch(`${BASE_URL}/api/get_fire_danger.php`);
        expect(response.headers.get('Access-Control-Allow-Origin')).toBe('*');
        expect(response.headers.get('Content-Type')).toContain('application/json');
    });

    test('returns default data when fire_danger.json is missing', async () => {
        // Ensure file is missing
        const jsonFile = path.join(TMP_DATA_DIR, 'fire_danger.json');
        if (fs.existsSync(jsonFile)) {
            fs.unlinkSync(jsonFile);
        }

        const response = await fetch(`${BASE_URL}/api/get_fire_danger.php`);
        expect(response.status).toBe(200);
        const data = await response.json();
        expect(data).toEqual({
            level: "Unknown",
            updated_at: ""
        });
    });

    test('returns data from fire_danger.json when it exists', async () => {
        const mockData = {
            level: "Low",
            updated_at: "2023-10-27 10:00:00"
        };
        const jsonFile = path.join(TMP_DATA_DIR, 'fire_danger.json');
        fs.writeFileSync(jsonFile, JSON.stringify(mockData));

        const response = await fetch(`${BASE_URL}/api/get_fire_danger.php`);
        expect(response.status).toBe(200);
        const data = await response.json();
        expect(data).toEqual(mockData);
    });
});
