<?php
// instructor/includes/notifications.php
require __DIR__ . '/../../includes/db.php';

function getInstructorNotificationCounts($pdo, $instructor_id) {
    $counts = [
        'upcoming_exams' => 0,
        'today_exams' => 0,
        'unread_announcements' => 0,
        'pending_tasks' => 0,
        'total_assignments' => 0
    ];
    
    try {
        $current_date = date('Y-m-d');
        
        // Get all exam assignments
        $stmt = $pdo->prepare("
            SELECT exam_id, exam_date, start_time, is_published 
            FROM exam_schedules 
            WHERE supervisor_id = ?
        ");
        $stmt->execute([$instructor_id]);
        $exams = $stmt->fetchAll();
        
        // Calculate counts
        $total_assignments = count($exams);
        $upcoming_exams = 0;
        $today_exams = 0;
        $unpublished_exams = 0;
        
        foreach($exams as $exam) {
            $exam_date = $exam['exam_date'];
            
            if($exam_date > $current_date) {
                $upcoming_exams++;
            } elseif($exam_date == $current_date) {
                $today_exams++;
            }
            
            if($exam['is_published'] == 0) {
                $unpublished_exams++;
            }
        }
        
        // Unread announcements (last 7 days)
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM announcements 
            WHERE status = 'published' 
            AND publish_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            AND announcement_id NOT IN (
                SELECT announcement_id FROM announcement_views 
                WHERE user_id = $instructor_id
            )
        ");
        $unread_announcements = $stmt->fetchColumn();
        
        $counts = [
            'upcoming_exams' => $upcoming_exams,
            'today_exams' => $today_exams,
            'unread_announcements' => $unread_announcements,
            'unpublished_exams' => $unpublished_exams,
            'total_assignments' => $total_assignments
        ];
        
    } catch (PDOException $e) {
        error_log("Error fetching instructor notification counts: " . $e->getMessage());
    }
    
    return $counts;
}

// Function to get urgent exams (within 24 hours)
function getUrgentExamsCount($pdo, $instructor_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM exam_schedules 
            WHERE supervisor_id = ? 
            AND exam_date = CURDATE()
            AND start_time > CURTIME()
        ");
        $stmt->execute([$instructor_id]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}
?>