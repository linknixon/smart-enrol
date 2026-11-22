<?php
require '../config/db.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=Senior_Five_Admitted_Students.csv');

$output = fopen('php://output', 'w');

/* CSV Header */
fputcsv($output, [
    'Surname',
    'First Name',
    'Other Names',
    'Gender',
    'Age',
    'UCE Index Number',
    'Subject Combination',
    'Aggregates',
    'Division',
    'Parent Name',
    'Parent Phone',
    'Parent Email'
]);

/* Fetch admitted students only */
$stmt = $pdo->query(
    "SELECT * FROM s5_applications 
     WHERE application_status = 'admitted'
     ORDER BY surname ASC"
);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $row['surname'],
        $row['firstname'],
        $row['othernames'] ?? '',
        $row['sex'] ?? '',
        $row['age'],
        $row['uce_index'],
        $row['combination'],
        $row['aggregates'],
        $row['division'],
        $row['parent_name'],
        $row['parent_phone'],
        $row['parent_email']
    ]);
}

fclose($output);
exit;
