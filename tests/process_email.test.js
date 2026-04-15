const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

describe('process_email.php', () => {
  let phpServer;
  let testConfigPath;

  beforeAll((done) => {
    testConfigPath = path.join(__dirname, '../config.json');

    // Create backup of config.json if it exists
    if (fs.existsSync(testConfigPath)) {
        fs.copyFileSync(testConfigPath, testConfigPath + '.bak');
    }

    const testConfig = {
      fire_danger_zone: '8',
      email_integration: {
        danger_address: 'danger',
        permit_address: 'permit'
      }
    };
    fs.writeFileSync(testConfigPath, JSON.stringify(testConfig));

    // Start PHP built-in server
    phpServer = spawn('php', ['-S', '127.0.0.1:8082', '-t', path.join(__dirname, '../')]);

    // Give the server a moment to start
    setTimeout(done, 1000);
  });

  afterAll(() => {
    // Stop PHP built-in server
    if (phpServer) {
      phpServer.kill();
    }

    // Restore original config
    if (fs.existsSync(testConfigPath + '.bak')) {
        fs.renameSync(testConfigPath + '.bak', testConfigPath);
    } else {
        if (fs.existsSync(testConfigPath)) {
            fs.unlinkSync(testConfigPath);
        }
    }
  });

  const sendTestEmail = async (emailContent) => {
    const response = await fetch(`http://127.0.0.1:8082/api/process_email.php?test=true`, {
      method: 'POST',
      headers: {
        'Content-Type': 'text/plain',
      },
      body: emailContent,
    });
    const text = await response.text();
    // Strip the CLI shebang from the response
    const jsonStr = text.substring(text.indexOf('{'));
    return JSON.parse(jsonStr);
  };

  describe('extractFireDanger', () => {
    it('should extract Extreme fire danger level', async () => {
      const emailContent = `To: danger@domain.com
Subject: Fire Danger
\r\n\r\n
Today's fire danger for Zone 8 is Extreme.`;

      const result = await sendTestEmail(emailContent);
      expect(result.type).toBe('danger');
      expect(result.data.level).toBe('Extreme');
    });

    it('should extract Very High fire danger level', async () => {
      const emailContent = `To: danger@domain.com
Subject: Fire Danger
\n\n
Zone 8 Forecast: Very High
Please be careful.`;

      const result = await sendTestEmail(emailContent);
      expect(result.type).toBe('danger');
      expect(result.data.level).toBe('Very High');
    });

    it('should extract High fire danger level with different formatting', async () => {
      const emailContent = `To: danger@domain.com
Subject: Fire Danger Update
\r\n\r\n
The rating for Zone 8 Fire Danger is HIGH today.`;

      const result = await sendTestEmail(emailContent);
      expect(result.type).toBe('danger');
      expect(result.data.level).toBe('High');
    });

    it('should extract Moderate fire danger level with tags', async () => {
      const emailContent = `To: danger@domain.com
Subject: Fire Danger Update
\n\n
<html><body>Zone 8 <b>Moderate</b> condition</body></html>`;

      const result = await sendTestEmail(emailContent);
      expect(result.type).toBe('danger');
      expect(result.data.level).toBe('Moderate');
    });

    it('should extract Low fire danger using fallback logic when regex fails', async () => {
      const emailContent = `To: danger@domain.com
Subject: Fire Danger
\n\n
The overall risk is Low today in your area.`;

      const result = await sendTestEmail(emailContent);
      expect(result.type).toBe('danger');
      expect(result.data.level).toBe('Low');
    });

    it('should extract fire danger from subject if body lacks clear level', async () => {
      const emailContent = `To: danger@domain.com
Subject: High Fire Danger Alert
\n\n
Please be advised of the conditions.`;

      const result = await sendTestEmail(emailContent);
      expect(result.type).toBe('danger');
      expect(result.data.level).toBe('High');
    });

    it('should return Unknown if no level is found', async () => {
      const emailContent = `To: danger@domain.com
Subject: Fire Danger Update
\n\n
The conditions are normal today.`;

      const result = await sendTestEmail(emailContent);
      expect(result.type).toBe('danger');
      expect(result.data.level).toBe('Unknown');
    });
  });
});
