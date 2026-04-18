#!/bin/bash

# 1. Add CSRF token generation near top
sed -i 's/session_start();/session_start();\nif (empty($_SESSION["csrf_token"])) {\n    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));\n}/' admin.php

# 2. Add CSRF validation in POST blocks
# Since login POST is handled before the main POST block, we should validate it early.
# Find the first POST check and inject before it.
# Wait, let's just find `if ($_SERVER['REQUEST_METHOD'] === 'POST') {` and login check `if (isset($_POST['login'])) {`
