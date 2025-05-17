<?php

require_once __DIR__ . '/../includes/session.php';

// הצגת הודעת הצלחה אם קיימת
if (has_success_message()) {
    $success_message = get_success_message();
    echo '<div class="message success">' . htmlspecialchars($success_message) . '</div>';
}

// הצגת הודעת שגיאה אם קיימת
if (has_error_message()) {
    $error_message = get_error_message();
    echo '<div class="message error">' . htmlspecialchars($error_message) . '</div>';
}