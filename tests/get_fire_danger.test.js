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
    if (fs.existsSync(TMP_DIR)) fs.rmSync(TMP_DIR, { recursive: true, force: true });
    fs.mkdirSync(TMP_API_DIR, { recursive: true });
    fs.mkdirSync(TMP_DATA_DIR, { recursive: true });

    fs.copyFileSync(path.join(__dirname, '../api/get_fire_danger.php'), path.join(TMP_API_DIR, 'get_fire_danger.php'));
    fs.copyFileSync(path.join(__dirname, '../api/db.php'), path.join(TMP_API_DIR, 'db.php'));

    phpServer = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', TMP_DIR]);
    await waitForServer(`${BASE_URL}/api/get_fire_danger.php`);
});

afterAll((done) => {
    if (phpServer) phpServer.kill();
    if (fs.existsSync(TMP_DIR)) fs.rmSync(TMP_DIR, { recursive: true, force: true });
    done();
});

describe('get_fire_danger.php', () => {

    test('returns correct headers', async () => {
        const response = await fetch(`${BASE_URL}/api/get_fire_danger.php`);
        expect(response.headers.get('Access-Control-Allow-Origin')).toBe('*');
        expect(response.headers.get('Content-Type')).toContain('application/json');
    });

    test('returns default data when DB setting is missing', async () => {
        const setupScript = path.join(TMP_API_DIR, 'setup_db.php');
        fs.writeFileSync(setupScript, `<?php
        require_once 'db.php';
        $pdo = getDbConnection();
        $pdo->exec("DELETE FROM settings WHERE setting_key = 'fire_danger'");
        `);
        await fetch(`${BASE_URL}/api/setup_db.php`);

        const response = await fetch(`${BASE_URL}/api/get_fire_danger.php`);
        expect(response.status).toBe(200);
        const data = await response.json();
        expect(data).toEqual({
            level: "Unknown",
            updated_at: ""
        });
    });

    test('returns data from settings DB when it exists', async () => {
        const mockData = {
            level: "Low",
            updated_at: "2023-10-27 10:00:00"
        };
        const setupScript = path.join(TMP_API_DIR, 'setup_db.php');
        fs.writeFileSync(setupScript, `<?php
        require_once 'db.php';
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('fire_danger', ?)");
        $stmt->execute(['` + JSON.stringify(mockData) + `']);
        `);
        await fetch(`${BASE_URL}/api/setup_db.php`);

        const response = await fetch(`${BASE_URL}/api/get_fire_danger.php`);
        expect(response.status).toBe(200);
        const data = await response.json();
        expect(data).toEqual(mockData);
    });
});
