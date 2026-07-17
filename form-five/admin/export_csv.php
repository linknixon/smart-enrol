<?php
require '../config/db.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=admitted_s1_students.csv');

$output = fopen('php://output', 'w');

/* CSV Header */
fputcsv($output, [
    'Surname',
    'First Name',
    'Age',
    'Index Number',
    'Aggregates',
    'Division',
    'Parent Name',
    'Parent Phone',
    'Parent Email'
]);

/* Fetch admitted students only */
$stmt = $pdo->query(
    "SELECT * FROM s1_applications 
     WHERE application_status = 'admitted'
     ORDER BY surname ASC"
);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $row['surname'],
        $row['firstname'],
        $row['age'],
        $row['index_number'],
        $row['aggregates'],
        $row['division'],
        $row['parent_name'],
        $row['parent_phone'],
        $row['parent_email']
    ]);
}

fclose($output);
exit;
