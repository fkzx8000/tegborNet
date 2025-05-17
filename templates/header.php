<?php


$page_title = isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME;
$include_rtl = isset($include_rtl) ? $include_rtl : true;
?>
<!DOCTYPE html>
<html lang="<?php echo $include_rtl ? 'he' : 'en'; ?>" <?php echo $include_rtl ? 'dir="rtl"' : ''; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- גופנים -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- סגנון ראשי -->
    <link rel="stylesheet" href="<?php echo get_site_url(); ?>/assets/css/main.css">
    
    <?php 
    // לאפשר הכללה של קבצי CSS נוספים
    if (isset($additional_css)) {
        foreach ($additional_css as $css_file) {
            // סינון - להתיר רק אותיות, מספרים, מקפים, קווים תחתונים ונקודות בשם הקובץ
            if (preg_match('/^[a-zA-Z0-9_\-\.]+\.css$/', $css_file)) {
                echo '<link rel="stylesheet" href="' . get_site_url() . '/assets/css/' . htmlspecialchars($css_file, ENT_QUOTES, 'UTF-8') . '">';
            } else {
                // לוג שגיאה - אופציונלי
                error_log("ניסיון להכליל קובץ CSS לא חוקי: " . $css_file);
            }        }
    }
    ?>
    
    <?php 
    // לאפשר הכללה של קבצי JavaScript
    if (isset($additional_js_head)) {
        foreach ($additional_js_head as $js_file) {
            // סינון - להתיר רק אותיות, מספרים, מקפים, קווים תחתונים ונקודות בשם הקובץ
            if (preg_match('/^[a-zA-Z0-9_\-\.]+\.js$/', $js_file)) {
                echo '<script src="' . get_site_url() . '/assets/js/' . htmlspecialchars($js_file, ENT_QUOTES, 'UTF-8') . '"></script>';
            } else {
                // לוג שגיאה - אופציונלי
                error_log("ניסיון להכליל קובץ JavaScript לא חוקי: " . $js_file);
            }
                }
    }
    ?>
    
    <?php
    // לאפשר הזרקת סגנונות CSS נוספים ישירות לראש הדף
    if (isset($additional_styles)) {
        // סינון מאפיינים מסוכנים בסגנונות CSS
        $safe_styles = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $additional_styles);
        $safe_styles = preg_replace('/javascript:/i', '', $safe_styles);
        $safe_styles = preg_replace('/expression\s*\(/i', '', $safe_styles);
        $safe_styles = preg_replace('/behavior\s*:/i', '', $safe_styles);
        $safe_styles = preg_replace('/import\s*:/i', 'not-import:', $safe_styles);
        
        echo '<style>' . $safe_styles . '</style>';
        }
    ?>
</head>
<body>
    <?php 
    if (!isset($skip_sidebar) || !$skip_sidebar) {
        include_once __DIR__ . '/sidebar.php';
    }
    ?>
    
    <div class="content">
        <?php 
        include_once __DIR__ . '/messages.php'; 
        ?>