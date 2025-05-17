<?php


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';


if (is_logged_in()) {
    header("Location: " . get_site_url());
    exit();
}


$page_title = 'התחברות';
$additional_css = ['login.css'];


$skip_sidebar = true;


include __DIR__ . '/../templates/header.php';
?>

<div class="login-container">
    <h1>התחברות למערכת</h1>

    <div class="login-card">
        <form action="<?php echo get_site_url(); ?>/api/auth.php?action=login" method="post" id="login-form">
            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">

            <div class="form-group">
                <label for="username">שם משתמש:</label>
                <input type="text" id="username" name="username" required maxlength="50" pattern="[A-Za-z0-9]+"
                    title="אנא השתמש באותיות באנגלית ומספרים בלבד">
            </div>

            <div class="form-group">
                <label for="password">סיסמה:</label>
                <input type="password" id="password" name="password" required minlength="8" maxlength="100">
            </div>

            <button type="submit" class="btn btn-primary">התחבר</button>

            <div class="form-footer">
                <p>אין לך חשבון? <a href="<?php echo get_site_url(); ?>/pages/register.php">הירשם כאן</a></p>
            </div>
        </form>
    </div>

    <div class="back-link">
        <a href="<?php echo get_site_url(); ?>" class="btn btn-secondary">חזרה לדף הבית</a>
    </div>
</div>

<script>
    document.getElementById('login-form').addEventListener('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);

        fetch(this.action, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {

                    const redirectUrl = data.redirect || '<?php echo get_site_url(); ?>';
                    if (redirectUrl.startsWith('http://') ||
                        redirectUrl.startsWith('https://') ||
                        redirectUrl.startsWith('/')) {
                        window.location.href = redirectUrl;
                    } else {
                        console.error('URL הפניה לא חוקי:', redirectUrl);
                        window.location.href = '<?php echo get_site_url(); ?>';
                    }
                } else {

                    const safeMessage = (data.message || 'שגיאה בהתחברות').replace(/[<>]/g, '');
                    alert(safeMessage);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('שגיאה בתקשורת עם השרת');
            });
    });
</script>

<?php

include __DIR__ . '/../templates/footer.php';
?>