<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'דף הבית';

$current_user_id = get_current_user_id();
$display_name = $current_user_id ? get_display_name($current_user_id) : '';
$user_role = get_current_role();
$additional_styles = "
    /* מיקום הסיידבר מימין והוספת padding לתוכן מימין */
    body {
      margin: 0;
      padding: 0 300px 0 0; /* מפנה 300px בצד ימין לתפריט הצד */
      background-color: #f5f5f5;
      font-family: 'Rubik', 'Heebo', 'Roboto', sans-serif;
    }

    /* סגנונות משופרים לכרטיסיות */
    .dashboard-card {
      border-radius: 12px;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
      transition: transform 0.2s, box-shadow 0.2s;
      overflow: hidden;
      margin-bottom: 1.8rem;
    }

    .dashboard-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    }

    .card-title {
      font-size: 1.5rem;
      border-bottom: 2px solid #f0f0f0;
      padding-bottom: 0.8rem;
      margin-bottom: 1.5rem;
      color: #2c3e50;
    }

    /* כפתורים משופרים */
    .btn {
      padding: 0.8rem 1.6rem;
      border-radius: 8px;
      font-weight: 600;
      transition: all 0.3s ease;
      margin: 0.5rem;
      border: none;
    }

    .btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .btn-primary {
      background: linear-gradient(135deg, #3498db, #2980b9);
    }

    .btn-secondary {
      background: linear-gradient(135deg, #95a5a6, #7f8c8d);
    }

    /* סגנון לכפתור הבלוג */
    .btn-blog {
      background: linear-gradient(135deg, #e74c3c, #c0392b);
      color: white;
      padding: 1rem 2rem;
      font-size: 1.2rem;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      max-width: 320px;
      margin: 1.5rem auto;
      position: relative;
      overflow: hidden;
    }

    .btn-blog::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(255, 255, 255, 0.1);
      transform: translateX(-100%);
      transition: transform 0.6s ease;
    }

    .btn-blog:hover::after {
      transform: translateX(0);
    }

    .btn-blog i, .btn-blog .emoji-mobile {
      margin-left: 0.8rem;
      font-size: 1.4rem;
    }

    /* מסגרת מיוחדת לאזור הבלוג */
    .blog-highlight {
      border: 2px dashed #e74c3c;
      border-radius: 12px;
      padding: 1.5rem;
      background-color: #fff8f8;
      margin-top: 1rem;
      margin-bottom: 2rem;
      text-align: center;
    }

    .blog-highlight h3 {
      color: #e74c3c;
      margin-bottom: 1rem;
      font-size: 1.6rem;
    }

    .blog-highlight p {
      margin-bottom: 1.5rem;
      font-size: 1.1rem;
      color: #555;
    }

    /* מחוון קטן שמושך תשומת לב */
    .pulse-dot {
      display: inline-block;
      width: 10px;
      height: 10px;
      background-color: #e74c3c;
      border-radius: 50%;
      margin-left: 8px;
      animation: pulse 1.5s infinite;
    }

    @keyframes pulse {
      0% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.7);
      }
      70% {
        transform: scale(1);
        box-shadow: 0 0 0 6px rgba(231, 76, 60, 0);
      }
      100% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(231, 76, 60, 0);
      }
    }

    /* התאמה למובייל – למסכים עם רוחב עד 600px */
    @media (max-width: 600px) {
      /* מסתירים את הסיידבר במסכים צרים */
      .sidebar {
        display: none;
      }
      /* מבטלים את ה-padding של body מימין כדי שהתוכן יתפרס למסך מלא */
      body {
        padding: 0;
      }
      
      .btn {
        padding: 14px 18px;  /* כפתורים גדולים יותר */
        font-size: 18px;
        margin: 6px;
      }
      .emoji-mobile {
        display: inline;
        margin-right: 5px;
      }

      .content {
        margin: 20px;
        padding: 10px;
      }

      .blog-highlight {
        padding: 1rem;
      }
    }
";

$broadcasts = [];
if (is_logged_in()) {
  $user_id = get_current_user_id();
  $role_name = get_current_role();
  $broadcasts = get_unread_broadcasts($user_id, $role_name);
}

$zoom_meeting = null;
if (is_logged_in()) {
  $conn = get_database_connection();
  $role_name = get_current_role();

  $meetingButtonSql = "
        SELECT meeting_link, meeting_time
        FROM zoom_meetings
        WHERE role_id = (SELECT id FROM roles WHERE role_name = ?)
        AND meeting_time >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        AND meeting_time <= DATE_ADD(NOW(), INTERVAL 30 MINUTE)
        ORDER BY meeting_time ASC
        LIMIT 1
    ";
  $meetingButtonStmt = $conn->prepare($meetingButtonSql);
  $meetingButtonStmt->bind_param("s", $role_name);
  $meetingButtonStmt->execute();
  $meetingButtonResult = $meetingButtonStmt->get_result();
  $zoom_meeting = $meetingButtonResult->fetch_assoc();
  $meetingButtonStmt->close();
}

include __DIR__ . '/templates/header.php';
?>

<div class="dashboard-header">
  <div class="dashboard-title">
    <h2>סקירת לוח בקרה</h2>
  </div>
  <?php if (is_logged_in()): ?>
    <div class="user-info">
      <div class="welcome">
        ברוכים השבים,
        <strong><?php echo htmlspecialchars($display_name); ?></strong>
      </div>
      <div class="role"><?php echo htmlspecialchars($user_role); ?> - גישה</div>
    </div>
  <?php endif; ?>
</div>



<div class="dashboard-card">
  <h3 class="card-title">פעולות מהירות</h3>
  <div class="action-buttons">
    <?php if (is_mentor()): ?>
      <a href="<?php echo get_site_url(); ?>/pages/mentor_log_tutoring.php" class="btn btn-primary">
        <span class="emoji-mobile">📝</span><i class="bi bi-pencil-square"></i> רישום מפגש תגבור
      </a>
      <a href="<?php echo get_site_url(); ?>/pages/mentor_history.php" class="btn btn-primary">
        <span class="emoji-mobile">📜</span><i class="bi bi-clock-history"></i> היסטוריה
      </a>
      <a href="<?php echo get_site_url(); ?>/pages/fill_mentor_details.php" class="btn btn-primary">
        <span class="emoji-mobile">👤</span><i class="bi bi-person-fill"></i> פרטי פרופיל
      </a>
    <?php endif; ?>

    <?php if (is_admin()): ?>
      <a href="<?php echo get_site_url(); ?>/pages/manage_users.php" class="btn btn-primary">
        <span class="emoji-mobile">⚙️</span><i class="bi bi-gear-fill"></i> לוח ניהול מנהל
      </a>
    <?php endif; ?>

    <?php if ($zoom_meeting): ?>
      <a href="<?php echo htmlspecialchars($zoom_meeting['meeting_link']); ?>" class="btn btn-primary" target="_blank"
        style="margin-top: 1rem;">
        <span class="emoji-mobile">🎥</span><i class="bi bi-camera-video-fill"></i> הצטרף לפגישת זום
      </a>
    <?php endif; ?>
  </div>
</div>

<?php if (!is_logged_in()): ?>
  <div class="dashboard-card">
    <h2 class="card-title">ברוכים הבאים</h2>
    <p>אנא התחבר כדי לגשת לכל הכלים.</p>
    <p>V-1.01</p>

    <div class="action-buttons" style="margin-top: 2rem;">
      <a href="<?php echo get_site_url(); ?>/pages/login.php" class="btn btn-primary">
        <span class="emoji-mobile">🔐</span><i class="bi bi-box-arrow-in-right"></i> התחבר
      </a>
      <a href="<?php echo get_site_url(); ?>/pages/register.php" class="btn btn-primary">
        <span class="emoji-mobile">📝</span><i class="bi bi-person-plus-fill"></i> הרשם
      </a>


    </div>
  </div>
<?php endif; ?>

<div class="dashboard-card">
  <h3 class="card-title"><i class="bi bi-bell-fill"></i> הודעות</h3>
  <?php if (!empty($broadcasts)): ?>
    <ul style="list-style: none; padding-right: 0;">
      <?php foreach ($broadcasts as $msg): ?>
        <li style="margin-bottom: 1.2rem; padding-bottom: 1rem; border-bottom: 1px solid #eee;">
          <p style="font-weight: bold; margin-bottom: 0.5rem; color: #3498db;">
            <i class="bi bi-person-fill"></i> מאת: <?php echo htmlspecialchars($msg['sender_username']); ?> -
            <span style="color: #777;"><i class="bi bi-calendar-event"></i>
              <?php echo date('d/m/Y H:i', strtotime($msg['created_at'])); ?></span>
          </p>
          <p style="background: #f9f9f9; padding: 0.8rem; border-radius: 8px;">
            <?php echo htmlspecialchars($msg['message']); ?>
          </p>
          <form action="<?php echo get_site_url(); ?>/api/broadcast.php" method="post">
            <input type="hidden" name="broadcast_id" value="<?php echo $msg['broadcast_id']; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
            <button type="submit" name="mark_read" class="btn btn-secondary">
              <span class="emoji-mobile">✅</span><i class="bi bi-check-circle"></i> סמן כנקרא
            </button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <div style="text-align: center; padding: 2rem 0;">
      <i class="bi bi-inbox" style="font-size: 2.5rem; color: #aaa; display: block; margin-bottom: 1rem;"></i>
      <p style="color: #777;">אין הודעות חדשות.</p>
    </div>
  <?php endif; ?>
</div>

<?php

include __DIR__ . '/templates/footer.php';
?>