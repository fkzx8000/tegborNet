<?php


require_once __DIR__ . '/../includes/functions.php';

$current_user_id = get_current_user_id();
$display_name = $current_user_id ? get_display_name($current_user_id) : '';
$current_role = get_current_role();
?>

<div class="sidebar">
    <div class="sidebar-header">
        <h1><?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?></h1>
    </div>
    <nav>
        <ul class="nav-menu">
            <?php if (is_logged_in()): ?>
                <li class="nav-item">
                    <a href="<?php echo get_site_url(); ?>/index.php" class="nav-link">
                        <i class="material-icons">home</i> דף הבית
                    </a>
                </li>

                <?php if (is_admin()): ?>
                    <li class="nav-item">
                        <a href="<?php echo get_site_url(); ?>/pages/manage_users.php" class="nav-link">
                            <i class="material-icons">admin_panel_settings</i> ניהול משתמשים
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo get_site_url(); ?>/pages/manage_videos.php" class="nav-link">
                            <i class="material-icons">movie</i> ניהול וידאו
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo get_site_url(); ?>/pages/admin_manage_coordinator_mentors.php" class="nav-link">
                            <i class="material-icons">group</i> ניהול רכזי מתגברים
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo get_site_url(); ?>/pages/manage_courses.php" class="nav-link">
                            <i class="material-icons">class</i> ניהול קורסים
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo get_site_url(); ?>/pages/admin_manage_users.php" class="nav-link">
                            <i class="material-icons">group</i> ניהול סיסמאות
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php if (is_mentor()): ?>
                    <li class="nav-item">
                        <a href="<?php echo get_site_url(); ?>/pages/mentor_log_tutoring.php" class="nav-link">
                            <i class="material-icons">note_add</i> רישום תגבור
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo get_site_url(); ?>/pages/mentor_history.php" class="nav-link">
                            <i class="material-icons">history</i> היסטוריית תגבורים
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo get_site_url(); ?>/pages/fill_mentor_details.php" class="nav-link">
                            <i class="material-icons">account_circle</i> עדכון פרטים
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php if (is_coordinator()): ?>
                    <li class="nav-item">
                        <a href="<?php echo get_site_url(); ?>/pages/coordinator_reports_monthly.php" class="nav-link">
                            <i class="material-icons">assessment</i> דוחות רכז - חודשי
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo get_site_url(); ?>/pages/coordinator_reports_semester.php" class="nav-link">
                            <i class="material-icons">insert_chart</i> דוחות רכז - סמסטריים
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo get_site_url(); ?>/pages/Manage_Mentor_Details.php" class="nav-link">
                            <i class="material-icons">manage_accounts</i> ניהול פרטי מתגבר
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo get_site_url(); ?>/pages/view_mentors.php" class="nav-link">
                            <i class="material-icons">people</i> צפייה במתגברים
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo get_site_url(); ?>/pages/coordinator_promote_guest.php" class="nav-link">
                            <i class="material-icons">people</i> הוספת מתגברים
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo get_site_url(); ?>/pages/coordinator_bi_report.php" class="nav-link">
                            <i class="material-icons">assessment</i> דוחות רכז - BI
                        </a>
                    </li>
                    <!-- הוספת קישורים לניהול מנחים ופרויקטים -->
                    <li class="nav-item">
                        <a href="<?php echo get_site_url(); ?>/pages/manage_projects.php" class="nav-link">
                            <i class="material-icons">assignment</i> ניהול פרויקטים
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo get_site_url(); ?>/pages/manage_mentors.php" class="nav-link">
                            <i class="material-icons">people</i> ניהול מנחים
                        </a>
                    </li>
                    <li class="nav-item"><a href="/pages/view_mentors_priorities.php" class="btn btn-primary">
                        <i class="fas fa-list-ol"></i> צפייה בעדיפויות שופטים
                    </a></li>
                <?php endif; ?>
                
                <li class="nav-item">
                    <a href="<?php echo get_site_url(); ?>/addons/sum.html" class="nav-link">
                        <i class="material-icons">summarize</i> תקציר
                    </a>
                </li>   
                <li class="nav-item">
                    <a href="<?php echo get_site_url(); ?>/api/auth.php?action=logout" class="nav-link btn-danger">
                        <i class="material-icons">logout</i> התנתקות
                    </a>
                </li>
            <?php else: ?>
                <li class="nav-item">
                    <a href="<?php echo get_site_url(); ?>/pages/login.php" class="nav-link">
                        <i class="material-icons">login</i> התחברות
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo get_site_url(); ?>/pages/register.php" class="nav-link">
                        <i class="material-icons">person_add</i> הרשמה
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo get_site_url(); ?>/addons/sum.html" class="nav-link">
                        <i class="material-icons">summarize</i> תקציר
                    </a>
                </li>        
            <?php endif; ?>
        </ul>
    </nav>
</div>