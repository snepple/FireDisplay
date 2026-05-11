const { spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const TMP_DIR = path.join(__dirname, 'tmp_logger');
const TMP_API_DIR = path.join(TMP_DIR, 'api');
const TMP_DATA_DIR = path.join(TMP_DIR, 'data');
const HELPER_PATH = path.join(TMP_DIR, 'logger_helper.php');
const LOG_FILE = path.join(TMP_DATA_DIR, 'system.log');

beforeAll(() => {
    // 1. Create tmp directory structure
    if (fs.existsSync(TMP_DIR)) {
        fs.rmSync(TMP_DIR, { recursive: true, force: true });
    }
    fs.mkdirSync(TMP_API_DIR, { recursive: true });
    fs.mkdirSync(TMP_DATA_DIR, { recursive: true });

    // 2. Copy api/logger.php to tmp/api/
    const srcLogger = path.join(__dirname, '../api/logger.php');
    const destLogger = path.join(TMP_API_DIR, 'logger.php');
    fs.copyFileSync(srcLogger, destLogger);

    // 3. Copy tests/logger_helper.php to tmp/
    const srcHelper = path.join(__dirname, 'logger_helper.php');
    fs.copyFileSync(srcHelper, HELPER_PATH);
});

afterAll(() => {
    // Remove tmp directory
    if (fs.existsSync(TMP_DIR)) {
        fs.rmSync(TMP_DIR, { recursive: true, force: true });
    }
});

beforeEach(() => {
    // Clear log file before each test
    if (fs.existsSync(LOG_FILE)) {
        fs.unlinkSync(LOG_FILE);
    }
    if (fs.existsSync(LOG_FILE + '.old')) {
        fs.unlinkSync(LOG_FILE + '.old');
    }
});

describe('logger.php (sys_log)', () => {

    test('creates log file and writes a valid JSON entry', () => {
        const component = 'TestApp';
        const message = 'Starting up';
        const status = 'success';

        spawnSync('php', [HELPER_PATH, component, message, status]);

        expect(fs.existsSync(LOG_FILE)).toBe(true);
        const content = fs.readFileSync(LOG_FILE, 'utf8').trim();
        const entry = JSON.parse(content);

        expect(entry.component).toBe(component);
        expect(entry.message).toBe(message);
        expect(entry.status).toBe(status);
        expect(entry.timestamp).toMatch(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/);
        expect(entry.details).toEqual([]);
    });

    test('logs details as an array/object', () => {
        const details = { user_id: 123, action: 'login' };
        const detailsJson = JSON.stringify(details);

        spawnSync('php', [HELPER_PATH, 'Auth', 'User logged in', 'info', detailsJson]);

        const content = fs.readFileSync(LOG_FILE, 'utf8').trim();
        const entry = JSON.parse(content);
        expect(entry.details).toEqual(details);
    });

    test('appends to existing log file', () => {
        spawnSync('php', [HELPER_PATH, 'Comp1', 'Msg1']);
        spawnSync('php', [HELPER_PATH, 'Comp2', 'Msg2']);

        const content = fs.readFileSync(LOG_FILE, 'utf8').trim();
        const lines = content.split('\n');
        expect(lines).toHaveLength(2);

        const entry1 = JSON.parse(lines[0]);
        const entry2 = JSON.parse(lines[1]);

        expect(entry1.component).toBe('Comp1');
        expect(entry2.component).toBe('Comp2');
    });

    test('rotates log file when it exceeds 5MB', () => {
        // Create a file slightly larger than 5MB (5 * 1024 * 1024 bytes)
        const fiveMB = 5 * 1024 * 1024;
        const dummyData = Buffer.alloc(fiveMB + 100, 'X');
        fs.writeFileSync(LOG_FILE, dummyData);

        // Call sys_log, it should trigger rotation
        spawnSync('php', [HELPER_PATH, 'Rotator', 'Should rotate']);

        // Check if old file exists
        expect(fs.existsSync(LOG_FILE + '.old')).toBe(true);
        expect(fs.statSync(LOG_FILE + '.old').size).toBeGreaterThanOrEqual(fiveMB + 100);

        // Check if new file contains only the new entry
        const content = fs.readFileSync(LOG_FILE, 'utf8').trim();
        const entry = JSON.parse(content);
        expect(entry.component).toBe('Rotator');
        expect(fs.statSync(LOG_FILE).size).toBeLessThan(1000);
    });

    test('sets correct file permissions (0666)', () => {
        spawnSync('php', [HELPER_PATH, 'Perms', 'Checking perms']);

        const stats = fs.statSync(LOG_FILE);
        // 0666 in octal is 438 in decimal.
        // We check the last 9 bits (permissions)
        const perms = stats.mode & 0o777;

        // Note: on some systems, umask might prevent exact 0666.
        // But the PHP code explicitly calls chmod(0666).
        // Let's see what we get in this environment.
        expect(perms).toBe(0o666);
    });
});
