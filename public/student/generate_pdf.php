<?php
session_start();
require __DIR__ . '/../../includes/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student'){
    die("Access denied.");
}

$student_id = $_SESSION['user_id'];

// Fetch current user info
$user_stmt = $pdo->prepare("SELECT username, year FROM users WHERE user_id = ?");
$user_stmt->execute([$student_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

$student_year = $user['year'] ?? '';
$student_name = $user['username'] ?? 'Student';

// Determine student type
$is_freshman = false;
$is_extension = false;
$is_regular = false;

if (strtolower($student_year) === 'freshman' || $student_year === '1' || $student_year === 'first year') {
    $is_freshman = true;
} elseif (strtoupper(substr($student_year, 0, 1)) === 'E') {
    $is_extension = true;
} elseif (is_numeric($student_year) && $student_year >= 2 && $student_year <= 5) {
    $is_regular = true;
}

// Year equivalents mapping
$year_equivalents = [
    'freshman' => ['1', 'freshman', 'Freshman', 'FIRST YEAR', 'first year'],
    '1' => ['1', 'freshman', 'Freshman', 'FIRST YEAR', 'first year'],
    '2' => ['2', 'sophomore', 'Sophomore', 'SECOND YEAR', 'second year'],
    '3' => ['3', 'junior', 'Junior', 'THIRD YEAR', 'third year'],
    '4' => ['4', 'senior', 'Senior', 'FOURTH YEAR', 'fourth year'],
    '5' => ['5', 'fifth', 'Fifth', 'FIFTH YEAR', 'fifth year'],
    'E1' => ['E1', 'e1', 'Extension 1', 'extension 1', 'EXTENSION 1'],
    'E2' => ['E2', 'e2', 'Extension 2', 'extension 2', 'EXTENSION 2'],
    'E3' => ['E3', 'e3', 'Extension 3', 'extension 3', 'EXTENSION 3'],
    'E4' => ['E4', 'e4', 'Extension 4', 'extension 4', 'EXTENSION 4'],
    'E5' => ['E5', 'e5', 'Extension 5', 'extension 5', 'EXTENSION 5'],
];

// Function to get year equivalents
function getYearEquivalents($year, $year_equivalents) {
    $equivalents = [];
    foreach ($year_equivalents as $key => $values) {
        if (strtolower($year) == strtolower($key)) {
            $equivalents = array_merge($equivalents, $values);
        }
    }
    foreach ($year_equivalents as $values) {
        foreach ($values as $value) {
            if (strtolower($year) == strtolower($value)) {
                $equivalents = array_merge($equivalents, $values);
            }
        }
    }
    $equivalents[] = $year;
    return array_unique($equivalents);
}

// Get year search values
$year_search_values = getYearEquivalents($student_year, $year_equivalents);
$placeholders = str_repeat('?,', count($year_search_values) - 1) . '?';

// Fetch schedule data
$my_schedule = [];

if ($is_freshman) {
    // Freshman - use student_enrollments
    $schedules = $pdo->prepare("
        SELECT s.schedule_id, c.course_name, c.course_code, u.full_name AS instructor_name, 
               r.room_name, s.day, s.start_time, s.end_time, s.year as schedule_year
        FROM schedule s
        JOIN courses c ON s.course_id = c.course_id
        JOIN users u ON s.instructor_id = u.user_id
        JOIN rooms r ON s.room_id = r.room_id
        JOIN student_enrollments se ON s.schedule_id = se.schedule_id
        WHERE se.student_id = ? 
        AND se.status = 'enrolled'
        AND s.year IN ($placeholders)
        ORDER BY FIELD(s.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), s.start_time
    ");
    $params = array_merge([$student_id], $year_search_values);
    $schedules->execute($params);
    $my_schedule = $schedules->fetchAll();
} elseif ($is_regular || $is_extension) {
    // Regular/Extension - use enrollments
    $schedules = $pdo->prepare("
        SELECT s.schedule_id, c.course_name, c.course_code, u.full_name AS instructor_name, 
               r.room_name, s.day, s.start_time, s.end_time, s.year as schedule_year
        FROM schedule s
        JOIN courses c ON s.course_id = c.course_id
        JOIN users u ON s.instructor_id = u.user_id
        JOIN rooms r ON s.room_id = r.room_id
        JOIN enrollments e ON s.schedule_id = e.schedule_id
        WHERE e.student_id = ? 
        AND s.year IN ($placeholders)
        ORDER BY FIELD(s.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), s.start_time
    ");
    $params = array_merge([$student_id], $year_search_values);
    $schedules->execute($params);
    $my_schedule = $schedules->fetchAll();
}

// Include TCPDF library
require_once __DIR__ . '/../../includes/tcpdf/tcpdf.php';

// Create new PDF document
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Student Management System');
$pdf->SetAuthor('SMS');
$pdf->SetTitle('My Schedule - ' . $student_name);
$pdf->SetSubject('Class Schedule');
$pdf->SetKeywords('Schedule, Classes, Timetable');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(TRUE, 15);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', 'B', 20);
$pdf->SetTextColor(0, 0, 0);

// Title
$pdf->Cell(0, 10, 'MY CLASS SCHEDULE', 0, 1, 'C');
$pdf->Ln(5);

// Student information
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 7, 'Student: ' . $student_name, 0, 1);
$pdf->Cell(0, 7, 'Year: ' . $student_year, 0, 1);
$pdf->Cell(0, 7, 'Generated: ' . date('F j, Y g:i A'), 0, 1);
$pdf->Ln(10);

// Create table header
$pdf->SetFillColor(59, 130, 246); // Blue color
$pdf->SetTextColor(255, 255, 255);
$pdf->SetDrawColor(59, 130, 246);
$pdf->SetLineWidth(0.3);
$pdf->SetFont('helvetica', 'B', 11);

// Column widths
$w = array(50, 45, 30, 30, 40, 25);

// Header
$header = array('Course', 'Instructor', 'Room', 'Day', 'Time', 'Year');
for($i = 0; $i < count($header); $i++) {
    $pdf->Cell($w[$i], 10, $header[$i], 1, 0, 'C', 1);
}
$pdf->Ln();

// Table content
$pdf->SetFillColor(255, 255, 255);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 10);

$fill = false;
$today = date('l');

foreach($my_schedule as $row) {
    // Highlight today's classes
    if($row['day'] == $today) {
        $pdf->SetFillColor(254, 243, 199); // Light yellow for today
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }
    
    // Course name with code
    $courseText = $row['course_name'];
    if(!empty($row['course_code'])) {
        $courseText .= "\n[" . $row['course_code'] . "]";
    }
    
    // Time formatted
    $timeText = date('g:i A', strtotime($row['start_time'])) . "\nto\n" . date('g:i A', strtotime($row['end_time']));
    
    // MultiCell for course name (allows line breaks)
    $pdf->MultiCell($w[0], 10, $courseText, 1, 'L', $fill, 0);
    $pdf->MultiCell($w[1], 10, $row['instructor_name'], 1, 'L', $fill, 0);
    $pdf->Cell($w[2], 10, $row['room_name'], 1, 0, 'C', $fill);
    $pdf->Cell($w[3], 10, $row['day'], 1, 0, 'C', $fill);
    $pdf->MultiCell($w[4], 10, $timeText, 1, 'C', $fill, 0);
    $pdf->Cell($w[5], 10, $row['schedule_year'], 1, 0, 'C', $fill);
    $pdf->Ln();
    
    $fill = !$fill;
}

// Add summary at the bottom
if(count($my_schedule) > 0) {
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'I', 10);
    $pdf->Cell(0, 7, 'Total Classes: ' . count($my_schedule), 0, 1);
    
    // Count classes per day
    $dayCount = array();
    foreach($my_schedule as $row) {
        $day = $row['day'];
        if(!isset($dayCount[$day])) {
            $dayCount[$day] = 0;
        }
        $dayCount[$day]++;
    }
    
    $pdf->Cell(0, 7, 'Classes by day: ' . implode(', ', array_map(
        function($day, $count) { return "$day ($count)"; },
        array_keys($dayCount),
        array_values($dayCount)
    )), 0, 1);
}

// Footer note
$pdf->SetY(-20);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor(128, 128, 128);
$pdf->Cell(0, 10, 'Generated by Student Management System - ' . date('Y'), 0, 0, 'C');

// Output PDF as download
$pdf->Output('schedule_' . $student_name . '_' . date('Y-m-d') . '.pdf', 'D');

exit;