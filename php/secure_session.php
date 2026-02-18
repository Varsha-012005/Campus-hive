<?php
// Include this at the top of all protected pages
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
    exit();
}

// You can add additional security checks here, like:
// - Checking last activity time for session timeout
// - Verifying user agent hasn't changed
// - Checking IP address (though this can cause problems with mobile users)

// Example session timeout (30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: ../login.html?timeout=1");
    exit();
}
$_SESSION['last_activity'] = time();
?>