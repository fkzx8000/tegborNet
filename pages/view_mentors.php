<?php


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';


require_permission(['coordinator'], "גישה נדחתה. רק רכזים מורשים.");


$coordinator_id = get_current_user_id();


$mentors_sql = "SELECT u.id AS mentor_id, u.username, 
               md.full_name, md.id_number, md.phone, md.email, 
               md.gender, md.study_year, md.department
               FROM coordinator_mentors cm
               JOIN users u ON cm.mentor_id = u.id
               LEFT JOIN mentor_details md ON u.id = md.mentor_id
               WHERE cm.coordinator_id = ?
               ORDER BY u.username ASC";
$mentors = db_fetch_all($mentors_sql, 'i', [$coordinator_id]);


$page_title = 'צפייה במתגברים';
$additional_css = ['coordinator.css'];


include __DIR__ . '/../templates/header.php';
?>

<div class="dashboard-header">
    <div class="dashboard-title">
        <h2>המתגברים שלך</h2>
    </div>
</div>

<div class="dashboard-card">
    <?php if (!empty($mentors)): ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>שם משתמש</th>
                        <th>שם מלא</th>
                        <th>מספר תעודת זהות</th>
                        <th>טלפון</th>
                        <th>דואר אלקטרוני</th>
                        <th>מגדר</th>
                        <th>שנת לימודים</th>
                        <th>מחלקה</th>
                        <th>פעולות</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mentors as $mentor): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($mentor['username']); ?></td>
                            <td>
                                <?php
                                if (!empty($mentor['full_name'])) {
                                    echo htmlspecialchars($mentor['full_name']);
                                } else {
                                    echo "<span class='text-muted'>עדיין לא מולאו פרטים</span>";
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($mentor['id_number'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($mentor['phone'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($mentor['email'] ?? ''); ?></td>
                            <td>
                                <?php
                                $gender_display = [
                                    'Male' => 'זכר',
                                    'Female' => 'נקבה',
                                    'Other' => 'אחר'
                                ];
                                echo htmlspecialchars($gender_display[$mentor['gender']] ?? $mentor['gender'] ?? '');
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($mentor['study_year'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($mentor['department'] ?? ''); ?></td>
                            <td>
                                <a href="Manage_Mentor_Details.php?mentor_id=<?php echo $mentor['mentor_id']; ?>"
                                    class="btn btn-primary btn-small">
                                    ערוך פרטים
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="summary-section">
            <h3>סיכום מתגברים</h3>
            <p>סה"כ מתגברים: <strong><?php echo count($mentors); ?></strong></p>

            <?php

            $filled_details_count = 0;
            foreach ($mentors as $mentor) {
                if (!empty($mentor['full_name'])) {
                    $filled_details_count++;
                }
            }
            ?>

            <p>מתגברים שמילאו פרטים: <strong><?php echo $filled_details_count; ?></strong></p>
            <p>מתגברים שטרם מילאו פרטים: <strong><?php echo count($mentors) - $filled_details_count; ?></strong></p>

            <?php if (count($mentors) - $filled_details_count > 0): ?>
                <div class="alert-info">
                    <p>יש מתגברים שטרם מילאו את כל פרטיהם. כדאי לפנות אליהם ולבקש מהם להשלים את הפרטים.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="alert-warning">
            <p>לא נמצאו מתגברים המשויכים אליך. <a href="coordinator_promote_guest.php">לחץ כאן</a> להוספת מתגברים.</p>
        </div>
    <?php endif; ?>
</div>

<?php

include __DIR__ . '/../templates/footer.php';
?>