<?php


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';


if (is_logged_in()) {
    header("Location: " . get_site_url());
    exit();
}


$page_title = 'רישום';
$additional_css = ['login.css'];


$skip_sidebar = true;


include __DIR__ . '/../templates/header.php';
?>

<div class="login-container">
    <h1>רישום למערכת</h1>

    <div class="login-card">
        <form action="<?php echo get_site_url(); ?>/api/auth.php?action=register" method="post" id="register-form">
            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">

            <div class="form-group">
                <label for="username">שם משתמש:</label>
                <input type="text" id="username" name="username" required maxlength="50" pattern="[A-Za-z0-9]+"
                    title="אנא השתמש באותיות באנגלית ומספרים בלבד">
            </div>

            <div class="form-group">
                <label for="password">סיסמה:</label>
                <input type="password" id="password" name="password" required minlength="8" maxlength="100">
                <small class="form-text">הסיסמה צריכה להכיל לפחות 8 תווים</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">אימות סיסמה:</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                    maxlength="100">
            </div>

            <button type="submit" class="btn btn-primary">הירשם</button>

            <div class="form-footer">
                <p>כבר יש לך חשבון? <a href="<?php echo get_site_url(); ?>/pages/login.php">התחבר כאן</a></p>
            </div>
        </form>
    </div>

    <div class="back-link">
        <a href="<?php echo get_site_url(); ?>" class="btn btn-secondary">חזרה לדף הבית</a>
    </div>
</div>

<script>
    document.getElementById('register-form').addEventListener('submit', function (e) {

        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;

        if (password !== confirmPassword) {
            e.preventDefault();
            alert('הסיסמאות אינן תואמות');
            return false;
        }

        e.preventDefault();

        const formData = new FormData(this);

        fetch(this.action, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {

                    const redirectUrl = data.redirect || '<?php echo get_site_url(); ?>/pages/login.php';
                    if (redirectUrl.startsWith('http://') ||
                        redirectUrl.startsWith('https://') ||
                        redirectUrl.startsWith('/')) {
                        window.location.href = redirectUrl;
                    } else {
                        console.error('URL הפניה לא חוקי:', redirectUrl);
                        window.location.href = '<?php echo get_site_url(); ?>/pages/login.php';
                    }
                } else {

                    const safeMessage = (data.message || 'שגיאה בתהליך הרישום').replace(/[<>]/g, '');
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