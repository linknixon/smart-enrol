<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'mail-gw.ncedges.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'admissions-mutoleress@ncedges.com';
        $mail->Password   = 'Uganda@23';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('admissions-mutoleress@ncedges.com', 'Admissions Office');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
    } catch (Exception $e) {
        // silent fail for now
    }
}
