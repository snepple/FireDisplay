const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const http = require('http');

const TMP_DIR = path.join(__dirname, 'tmp_get_permits');
const TMP_API_DIR = path.join(TMP_DIR, 'api');
const TMP_DATA_DIR = path.join(TMP_DIR, 'data');
const PORT = 8007; // Use a different port to avoid conflicts
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

    // 2. Copy api/get_permits.php to tmp directory
    const srcFile = path.join(__dirname, '../api/get_permits.php');
    const destFile = path.join(TMP_API_DIR, 'get_permits.php');
    fs.copyFileSync(srcFile, destFile);
    const secSrc = path.join(__dirname, '../api/security_check.php');
    const secDest = path.join(TMP_API_DIR, 'security_check.php');
    if (fs.existsSync(secSrc)) fs.copyFileSync(secSrc, secDest);

    // 3. Spawn PHP server
    phpServer = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', TMP_DIR]);

    // Wait for the server to start by polling
    await waitForServer(`${BASE_URL}/api/get_permits.php`);
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

describe('get_permits.php', () => {

    test('returns correct headers', async () => {
        const response = await fetch(`${BASE_URL}/api/get_permits.php`);
        expect(response.headers.get('Access-Control-Allow-Origin')).toBe('*');
        expect(response.headers.get('Content-Type')).toContain('application/json');
    });

    test('returns empty array when permits.json is missing', async () => {
        // Ensure file is missing
        const jsonFile = path.join(TMP_DATA_DIR, 'permits.json');
        if (fs.existsSync(jsonFile)) {
            fs.unlinkSync(jsonFile);
        }

        const response = await fetch(`${BASE_URL}/api/get_permits.php`);
        expect(response.status).toBe(200);
        const data = await response.json();
        expect(data).toEqual([]);
    });

    test('filters expired permits and returns only active ones', async () => {
        const now = Date.now();
        // Create an expired permit (1 hour ago)
        const expiredDate = new Date(now - 3600 * 1000).toISOString();
        // Create an active permit (1 hour from now)
        const activeDate = new Date(now + 3600 * 1000).toISOString();

        const mockData = [
            {
                id: 1,
                name: "John Doe",
                expires: expiredDate
            },
            {
                id: 2,
                name: "Jane Smith",
                expires: activeDate
            }
        ];

        const jsonFile = path.join(TMP_DATA_DIR, 'permits.json');
        fs.writeFileSync(jsonFile, JSON.stringify(mockData));

        const response = await fetch(`${BASE_URL}/api/get_permits.php`);
        expect(response.status).toBe(200);
        const data = await response.json();

        // Should only return the active permit
        expect(data).toHaveLength(1);
        expect(data[0].id).toBe(2);
        expect(data[0].name).toBe("Jane Smith");
    });

    test('returns empty array when all permits are expired', async () => {
        const now = Date.now();
        // Create expired permits (1 hour ago and 2 hours ago)
        const expiredDate1 = new Date(now - 3600 * 1000).toISOString();
        const expiredDate2 = new Date(now - 7200 * 1000).toISOString();

        const mockData = [
            {
                id: 1,
                name: "John Doe",
                expires: expiredDate1
            },
            {
                id: 2,
                name: "Jane Smith",
                expires: expiredDate2
            }
        ];

        const jsonFile = path.join(TMP_DATA_DIR, 'permits.json');
        fs.writeFileSync(jsonFile, JSON.stringify(mockData));

        const response = await fetch(`${BASE_URL}/api/get_permits.php`);
        expect(response.status).toBe(200);
        const data = await response.json();

        // Should return empty array
        expect(data).toEqual([]);
    });
});
