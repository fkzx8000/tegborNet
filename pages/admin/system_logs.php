<?php


require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/logger.php';


require_permission(['admin'], "גישה נדחתה. מנהלים בלבד.");


$action_filter = isset($_GET['action']) ? $_GET['action'] : '';
$user_filter = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$page_size = 50;
$offset = ($page - 1) * $page_size;


$where_clauses = [];
$params = [];
$types = '';

if (!empty($action_filter)) {
    $where_clauses[] = "sl.action = ?";
    $params[] = $action_filter;
    $types .= 's';
}

if ($user_filter > 0) {
    $where_clauses[] = "sl.user_id = ?";
    $params[] = $user_filter;
    $types .= 'i';
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = "WHERE " . implode(' AND ', $where_clauses);
}


$count_sql = "SELECT COUNT(*) as total FROM system_logs sl $where_sql";
$count_result = empty($params) ? db_fetch_one($count_sql) : db_fetch_one($count_sql, $types, $params);
$total_records = $count_result['total'];
$total_pages = ceil($total_records / $page_size);


$params[] = $offset;
$params[] = $page_size;
$types .= 'ii';


$logs_sql = "SELECT sl.*, u.username as user_username
            FROM system_logs sl
            LEFT JOIN users u ON sl.user_id = u.id
            $where_sql
            ORDER BY sl.log_time DESC
            LIMIT ?, ?";
$logs = empty($where_clauses) ? 
    db_fetch_all($logs_sql, 'ii', [$offset, $page_size]) : 
    db_fetch_all($logs_sql, $types, $params);


$actions_sql = "SELECT DISTINCT action FROM system_logs ORDER BY action";
$action_types = db_fetch_all($actions_sql);


$users_sql = "SELECT DISTINCT u.id, u.username 
              FROM system_logs sl
              JOIN users u ON sl.user_id = u.id
              ORDER BY u.username";
$user_list = db_fetch_all($users_sql);


$page_title = 'רישומי מערכת';
$additional_css = ['admin.css'];


include __DIR__ . '/../../templates/header.php';
?>

<div class="dashboard-header">
    <div class="dashboard-title">
        <h2>רישומי מערכת</h2>
    </div>
</div>

<div class="dashboard-card">
    <h3 class="card-title">סינון רישומים</h3>
    
    <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="filter-form">
        <div class="form-group">
            <label for="action">סוג פעולה:</label>
            <select name="action" id="action">
                <option value="">כל הפעולות</option>
                <?php foreach ($action_types as $action_type): ?>
                    <option value="<?php echo htmlspecialchars($action_type['action']); ?>" 
                            <?php echo ($action_filter === $action_type['action']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($action_type['action']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="user_id">משתמש:</label>
            <select name="user_id" id="user_id">
                <option value="0">כל המשתמשים</option>
                <?php foreach ($user_list as $user): ?>
                    <option value="<?php echo $user['id']; ?>" 
                            <?php echo ($user_filter === $user['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['username']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <button type="submit" class="btn btn-primary">סנן</button>
        <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">נקה סינון</a>
    </form>
</div>

<div class="dashboard-card">
    <h3 class="card-title">רישומי מערכת (<?php echo $total_records; ?> רשומות)</h3>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>זמן</th>
                    <th>משתמש</th>
                    <th>פעולה</th>
                    <th>כתובת IP</th>
                    <th>פרטים</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logs)): ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i:s', strtotime($log['log_time'])); ?></td>
                            <td><?php echo htmlspecialchars($log['username'] ?? $log['user_username'] ?? 'לא מזוהה'); ?></td>
                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                            <td>
                            <?php 
if (!empty($log['details'])) {
    
    $safe_details = htmlspecialchars($log['details']);
    
    
    if (is_valid_json($log['details'])) {
        try {
            $details = json_decode($log['details'], true);
            if ($details !== null) {
                
                $formatted_json = json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                if ($formatted_json !== false) {
                    echo '<pre>' . htmlspecialchars($formatted_json) . '</pre>';
                } else {
                    
                    echo $safe_details;
                }
            } else {
                echo $safe_details;
            }
        } catch (Exception $e) {
            
            echo $safe_details;
        }
    } else {
        echo $safe_details;
    }
} else {
    echo '-';
}
?>
                                if (!empty($log['details'])) {
                                    if (is_valid_json($log['details'])) {
                                        $details = json_decode($log['details'], true);
                                        echo '<pre>' . htmlspecialchars(json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
                                    } else {
                                        echo htmlspecialchars($log['details']);
                                    }
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5">לא נמצאו רישומים.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <span>עמוד:</span>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['page' => $i]))); ?>" 
                   class="<?php echo ($page === $i) ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php

include __DIR__ . '/../../templates/footer.php';
?>