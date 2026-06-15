<?php
// ============================================================
// ALMS — Mailer Helper
// Replace mail() with PHPMailer SMTP for production
// ============================================================

function sendMail(string $to, string $subject, string $body): bool {
    $headers  = "From: noreply@fcahptib.edu.ng\r\n";
    $headers .= "Reply-To: support@fcahptib.edu.ng\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: ALMS/1.0\r\n";

    return @mail($to, $subject, $body, $headers);
}
