# Sentinel

## Security & Reliability Learnings
- **XSS in HTML attributes via json_encode:** When rendering user data (like logs) into HTML attributes such as `title`, use `htmlspecialchars` with `ENT_QUOTES`. Without it, single quotes inside JSON can escape the attribute in older PHP versions, leading to XSS vulnerabilities.
- **Log generation safety:** Before instrumenting logging with context variables (like sender or subject in email processing), ensure the variables are initialized and extracted. Trying to log undefined variables leads to `E_WARNING` notices and empty data in logs.
