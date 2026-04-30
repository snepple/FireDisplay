const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const http = require('http');

const TMP_DIR = path.join(__dirname, 'tmp_speak');
const TMP_API_DIR = path.join(TMP_DIR, 'api');
const PORT = 8004;
const MOCK_TTS_PORT = 8005;
const BASE_URL = `http://127.0.0.1:${PORT}`;

let phpServer;
let mockTtsServer;
let mockTtsResponseStatus = 200;
let mockTtsResponseBody = JSON.stringify({ audioContent: Buffer.from("mock_audio_content").toString('base64') });
let phpErrorLog = '';

beforeAll((done) => {
    // 1. Create tmp directory structure
    if (fs.existsSync(TMP_DIR)) {
        fs.rmSync(TMP_DIR, { recursive: true, force: true });
    }
    fs.mkdirSync(TMP_API_DIR, { recursive: true });

    // 2. Start Mock Google TTS API Server
    mockTtsServer = http.createServer((req, res) => {
        let body = '';
        req.on('data', chunk => {
            body += chunk.toString();
        });
        req.on('end', () => {
            res.writeHead(mockTtsResponseStatus, { 'Content-Type': 'application/json' });
            res.end(mockTtsResponseBody);
        });
    });
    mockTtsServer.listen(MOCK_TTS_PORT);

    // 3. Copy and patch api/speak.php to point to mock server
    const srcFile = path.join(__dirname, '../api/speak.php');
    let speakPhpContent = fs.readFileSync(srcFile, 'utf8');
    // Replace the hardcoded google API url with our mock
    speakPhpContent = speakPhpContent.replace(
        "'https://texttospeech.googleapis.com/v1/text:synthesize?key=' . $apiKey",
        `'http://127.0.0.1:${MOCK_TTS_PORT}/v1/text:synthesize?key=' . $apiKey`
    );
    // Replace md5 logic with a unique one for testing if needed, but not strictly required
    const destFile = path.join(TMP_API_DIR, 'speak.php');
    fs.writeFileSync(destFile, speakPhpContent);

    // Provide a mock security_check.php since it is required by speak.php
    const securityCheckDest = path.join(TMP_API_DIR, 'security_check.php');
    fs.writeFileSync(securityCheckDest, "<?php function verify_dashboard_token() {} ?>");

    // 4. Create a mock config.json
    const mockConfig = {
        api_integrations: {
            google_tts_api_key: 'test_api_key'
        }
    };
    fs.writeFileSync(path.join(TMP_DIR, 'config.json'), JSON.stringify(mockConfig));

    // 5. Spawn PHP server
    phpServer = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', TMP_DIR]);

    // Capture stderr to check error_log
    phpServer.stderr.on('data', (data) => {
        phpErrorLog += data.toString();
    });

    // Wait for the servers to start
    setTimeout(done, 1000);
});

afterAll((done) => {
    // 1. Kill PHP server
    if (phpServer) {
        phpServer.kill();
    }

    // 2. Stop mock TTS server
    if (mockTtsServer) {
        mockTtsServer.close();
    }

    // 3. Remove tmp directory
    if (fs.existsSync(TMP_DIR)) {
        fs.rmSync(TMP_DIR, { recursive: true, force: true });
    }

    done();
});

beforeEach(() => {
    phpErrorLog = ''; // Reset error log for each test
    mockTtsResponseStatus = 200; // Reset mock status
    mockTtsResponseBody = JSON.stringify({ audioContent: Buffer.from("mock_audio_content").toString('base64') });

    // Clear TTS cache directory if it exists
    const cacheDir = path.join(TMP_DIR, 'data/tts_cache');
    if (fs.existsSync(cacheDir)) {
        fs.rmSync(cacheDir, { recursive: true, force: true });
    }
});

describe('speak.php', () => {

    test('returns 500 when missing config file', async () => {
        // Temporarily rename config.json
        fs.renameSync(path.join(TMP_DIR, 'config.json'), path.join(TMP_DIR, 'config.json.bak'));

        const response = await fetch(`${BASE_URL}/api/speak.php`, {
            method: 'POST',
            body: JSON.stringify({ text: 'hello' })
        });

        expect(response.status).toBe(500);
        const text = await response.text();
        expect(text).toBe('Config file missing.');

        // Restore config.json
        fs.renameSync(path.join(TMP_DIR, 'config.json.bak'), path.join(TMP_DIR, 'config.json'));
    });

    test('returns 500 when API key is missing', async () => {
        // Create config with empty key
        const emptyKeyConfig = { api_integrations: { google_tts_api_key: '' } };
        fs.writeFileSync(path.join(TMP_DIR, 'config.json'), JSON.stringify(emptyKeyConfig));

        const response = await fetch(`${BASE_URL}/api/speak.php`, {
            method: 'POST',
            body: JSON.stringify({ text: 'hello' })
        });

        expect(response.status).toBe(500);
        const text = await response.text();
        expect(text).toBe('TTS API Key not configured.');

        // Restore normal config
        const mockConfig = { api_integrations: { google_tts_api_key: 'test_api_key' } };
        fs.writeFileSync(path.join(TMP_DIR, 'config.json'), JSON.stringify(mockConfig));
    });

    test('returns 400 when text is missing', async () => {
        const response = await fetch(`${BASE_URL}/api/speak.php`, {
            method: 'POST',
            body: JSON.stringify({})
        });

        expect(response.status).toBe(400);
        const text = await response.text();
        expect(text).toBe('No text provided.');
    });

    test('returns 200 and audio content on success (happy path)', async () => {
        const response = await fetch(`${BASE_URL}/api/speak.php`, {
            method: 'POST',
            body: JSON.stringify({ text: 'hello world' })
        });

        expect(response.status).toBe(200);
        const buffer = await response.arrayBuffer();
        const str = Buffer.from(buffer).toString('utf8');
        expect(str).toBe('mock_audio_content');

        // Verify caching works
        const cacheDir = path.join(TMP_DIR, 'data/tts_cache');
        expect(fs.existsSync(cacheDir)).toBe(true);
        const files = fs.readdirSync(cacheDir);
        expect(files.length).toBe(1);
        expect(fs.readFileSync(path.join(cacheDir, files[0]), 'utf8')).toBe('mock_audio_content');
    });

    test('returns 500 and logs error on Google TTS API failure', async () => {
        // Set mock to return 500
        mockTtsResponseStatus = 500;
        mockTtsResponseBody = JSON.stringify({ error: 'Internal Server Error' });

        const response = await fetch(`${BASE_URL}/api/speak.php`, {
            method: 'POST',
            body: JSON.stringify({ text: 'error text' })
        });

        expect(response.status).toBe(500);
        const text = await response.text();
        expect(text).toBe('Failed to synthesize speech.');

        // Verify error log was written
        expect(phpErrorLog).toContain('Google TTS API Error: {"error":"Internal Server Error"}');
    });
});
