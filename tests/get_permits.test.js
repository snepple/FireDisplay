const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const http = require('http');

const TMP_DIR = path.join(__dirname, 'tmp_get_permits');
const TMP_API_DIR = path.join(TMP_DIR, 'api');
const TMP_DATA_DIR = path.join(TMP_DIR, 'data');
const PORT = 8007;
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

    fs.copyFileSync(path.join(__dirname, '../api/get_permits.php'), path.join(TMP_API_DIR, 'get_permits.php'));
    fs.copyFileSync(path.join(__dirname, '../api/db.php'), path.join(TMP_API_DIR, 'db.php'));

    phpServer = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', TMP_DIR]);
    await waitForServer(`${BASE_URL}/api/get_permits.php`);
});

afterAll((done) => {
    if (phpServer) phpServer.kill();
    if (fs.existsSync(TMP_DIR)) fs.rmSync(TMP_DIR, { recursive: true, force: true });
    done();
});

describe('get_permits.php', () => {

    test('returns correct headers', async () => {
        const response = await fetch(`${BASE_URL}/api/get_permits.php`);
        expect(response.headers.get('Access-Control-Allow-Origin')).toBe('*');
        expect(response.headers.get('Content-Type')).toContain('application/json');
    });

    test('returns empty array when DB is empty', async () => {
        const setupScript = path.join(TMP_API_DIR, 'setup_db.php');
        fs.writeFileSync(setupScript, `<?php
        require_once 'db.php';
        $pdo = getDbConnection();
        $pdo->exec("DELETE FROM permits");
        `);
        await fetch(`${BASE_URL}/api/setup_db.php`);

        const response = await fetch(`${BASE_URL}/api/get_permits.php`);
        expect(response.status).toBe(200);
        const data = await response.json();
        expect(data).toEqual([]);
    });

    test('filters expired permits and returns only active ones', async () => {
        const setupScript = path.join(TMP_API_DIR, 'setup_db.php');
        fs.writeFileSync(setupScript, `<?php
        require_once 'db.php';
        $pdo = getDbConnection();
        $pdo->exec("DELETE FROM permits");
        $stmt = $pdo->prepare("INSERT INTO permits (permit_number, address, expires, details) VALUES (?, ?, ?, ?)");
        $stmt->execute(['1', 'Addr 1', date('Y-m-d H:i:s', strtotime('-1 day')), '{"id": 1, "name": "John Doe"}']);
        $stmt->execute(['2', 'Addr 2', date('Y-m-d H:i:s', strtotime('+1 day')), '{"id": 2, "name": "Jane Smith"}']);
        `);
        await fetch(`${BASE_URL}/api/setup_db.php`);

        const response = await fetch(`${BASE_URL}/api/get_permits.php`);
        expect(response.status).toBe(200);
        const data = await response.json();

        expect(data).toHaveLength(1);
        expect(data[0].id).toBe(2);
        expect(data[0].name).toBe("Jane Smith");
    });

    test('returns empty array when all permits are expired', async () => {
        const setupScript = path.join(TMP_API_DIR, 'setup_db.php');
        fs.writeFileSync(setupScript, `<?php
        require_once 'db.php';
        $pdo = getDbConnection();
        $pdo->exec("DELETE FROM permits");
        $stmt = $pdo->prepare("INSERT INTO permits (permit_number, address, expires, details) VALUES (?, ?, ?, ?)");
        $stmt->execute(['1', 'Addr 1', date('Y-m-d H:i:s', strtotime('-1 day')), '{"id": 1, "name": "John Doe"}']);
        $stmt->execute(['2', 'Addr 2', date('Y-m-d H:i:s', strtotime('-2 day')), '{"id": 2, "name": "Jane Smith"}']);
        `);
        await fetch(`${BASE_URL}/api/setup_db.php`);

        const response = await fetch(`${BASE_URL}/api/get_permits.php`);
        expect(response.status).toBe(200);
        const data = await response.json();

        expect(data).toEqual([]);
    });
});
