import re

with open('admin.php', 'r') as f:
    content = f.read()

# 1. Add CSRF token generation
if "session_start();" in content:
    csrf_gen = """session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}"""
    content = content.replace("session_start();", csrf_gen, 1)

# 2. Add CSRF validation for POST requests generally.
# The only POST requests are login and the settings save operations.
# Let's add a global check at the top of the file, right after session generation, or right before handling login.
csrf_validation = """
// CSRF Validation for all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF token validation failed. Please refresh the page and try again.');
    }
}
"""
content = content.replace("$_SESSION['csrf_token'] = bin2hex(random_bytes(32));\n}", "$_SESSION['csrf_token'] = bin2hex(random_bytes(32));\n}\n" + csrf_validation, 1)

# 3. Add CSRF token hidden input to all forms.
# There are HTML forms and PHP echoed forms.

def add_csrf(match):
    form_str = match.group(1)
    if 'method="post"' in form_str.lower() or "method='post'" in form_str.lower() or 'method="POST"' in form_str or "method='POST'" in form_str:
        return form_str + '\n    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[\'csrf_token\'] ?? \'\') ?>">'
    return form_str

form_tag_pattern = re.compile(r'(<form[^>]*>)', re.IGNORECASE)
content = form_tag_pattern.sub(add_csrf, content)

# 4. Handle the specific echo login form which requires different escaping
login_form_search = "echo \"<form method='POST' style='background: #ffffff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); width: 100%; max-width: 320px;'>\\n    <input type=\\\"hidden\\\" name=\\\"csrf_token\\\" value=\\\"<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>\\\">\";"

# Actually, the regex above will replace it inside the PHP string literal like this:
# echo "<form method='POST' ...>
#     <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">";
# This will cause a PHP parse error because it injects HTML/PHP tags inside a PHP echo string.

# Let's undo that for the echo form and do it properly.
