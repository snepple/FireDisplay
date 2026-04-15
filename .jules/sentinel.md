## 2025-04-15 - [Hardcoded Secret in TTS Endpoint]
**Vulnerability:** A hardcoded Google TTS API key (`GOOGLE_API_KEY`) was present directly in the `api/speak.php` file.
**Learning:** Hardcoding sensitive API keys in the source code leaks the credential to anyone who views the codebase and requires manual code changes to cycle keys.
**Prevention:** Always externalize secrets to a configuration file (`config.json` or environment variables), and explicitly exclude the secrets when rendering or exporting that configuration through API endpoints.
