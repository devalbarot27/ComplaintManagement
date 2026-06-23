<?php

function complaint_assignment_notify_email(
    PDO $conn,
    int $complaintId,
    string $assignTo,
    string $assignedAt,
    string $remarks = ''
): bool {
    $stmt = $conn->prepare('
        SELECT id, fab_number, customer_name, complaint_description
        FROM complaints
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $complaintId, PDO::PARAM_INT);
    $stmt->execute();

    $complaint = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$complaint) {
        return false;
    }

    $to = 'deval.barot27@gmail.com'; // Static email

    $subject = 'Complaint #' . $complaintId . ' assigned to ' . $assignTo;

    $assignedAtFormatted = date('d M Y, h:i A', strtotime($assignedAt));

    $lines = [
        'A complaint has been assigned in Complaint Management.',
        '',
        'Complaint ID: ' . $complaintId,
        'Fab Number: ' . ($complaint['fab_number'] ?? '-'),
        'Customer Name: ' . ($complaint['customer_name'] ?? '-'),
        'Assigned To: ' . $assignTo,
        'Assigned At: ' . $assignedAtFormatted,
        'Complaint Description: ' . ($complaint['complaint_description'] ?? '-'),
    ];

    if ($remarks !== '') {
        $lines[] = 'Remarks: ' . $remarks;
    }

    $message = implode("\r\n", $lines);

    $fromAddress = 'noreply@complaintmanagement.local';
    $headers = 'From: Complaint Management <' . $fromAddress . ">\r\n"
        . 'Reply-To: ' . $fromAddress . "\r\n"
        . 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
        . 'X-Mailer: PHP/' . phpversion();

   // return 1;
    return mail($to, $subject, $message, $headers);
}