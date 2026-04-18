import re

with open('admin.php', 'r') as f:
    content = f.read()

subprocess = __import__("subprocess")
subprocess.run(['git', 'checkout', 'admin.php'])

with open('admin.php', 'r') as f:
    content = f.read()

# 1. Add CSRF token generation
csrf_gen = """session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// CSRF Validation for all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF token validation failed. Please refresh the page and try again.');
    }
}
"""
content = content.replace("session_start();", csrf_gen, 1)

# Fix the login form (echoed in PHP)
login_search = "echo \"<form method='POST' style='background: #ffffff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); width: 100%; max-width: 320px;'>\";"
login_replace = "echo \"<form method='POST' data-login='1' style='background: #ffffff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); width: 100%; max-width: 320px;'>\";" + "\n    echo \"<input type='hidden' name='csrf_token' value='\" . htmlspecialchars($_SESSION['csrf_token'] ?? '') . \"'>\";"
content = content.replace(login_search, login_replace)

# Instead of regex for <form>, let's just do targeted replaces for the specific forms we found
# 1. Main config form
content = content.replace('<form method="POST" id="mainConfigForm" onsubmit="runPreSubmitHooks()">', '<form method="POST" id="mainConfigForm" onsubmit="runPreSubmitHooks()">\n                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[\'csrf_token\'] ?? \'\') ?>">\n')

# 2. Audio uploads form
content = content.replace('<form method="POST" enctype="multipart/form-data">', '<form method="POST" enctype="multipart/form-data">\n                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[\'csrf_token\'] ?? \'\') ?>">\n')

# 3. Password change form
content = content.replace('<form method="post">', '<form method="post">\n                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[\'csrf_token\'] ?? \'\') ?>">\n')

# 4. Delete Chore Form
content = content.replace('<form id="delFormChore_<?= $evt[\'id\'] ?>" method="POST">', '<form id="delFormChore_<?= $evt[\'id\'] ?>" method="POST">\n                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[\'csrf_token\'] ?? \'\') ?>">')

# 5. Delete Event Form
content = content.replace('<form id="delFormEvt_<?= $evt[\'id\'] ?>" method="POST">', '<form id="delFormEvt_<?= $evt[\'id\'] ?>" method="POST">\n                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[\'csrf_token\'] ?? \'\') ?>">')

# 6. Delete Ann Form
content = content.replace('<form id="delFormAnn_<?= $ann[\'id\'] ?>" method="POST">', '<form id="delFormAnn_<?= $ann[\'id\'] ?>" method="POST">\n                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[\'csrf_token\'] ?? \'\') ?>">')


with open('admin.php', 'w') as f:
    f.write(content)
