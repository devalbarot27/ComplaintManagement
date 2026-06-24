<?php

require_once __DIR__ . '/complaint_assignment_helpers.php';

function complaint_mail_from_address(): string
{
    return 'noreply@complaintmanagement.local';
}

function complaint_mail_headers(): string
{
    $fromAddress = complaint_mail_from_address();

    return 'From: Complaint Management <' . $fromAddress . ">\r\n"
        . 'Reply-To: ' . $fromAddress . "\r\n"
        . 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
        . 'X-Mailer: PHP/' . phpversion();
}

function complaint_mail_send(string $to, string $subject, string $message): bool
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    return mail($to, $subject, $message, complaint_mail_headers());
}

function complaint_fetch_complaint_for_mail(PDO $conn, int $complaintId): ?array
{
    $stmt = $conn->prepare('
        SELECT id, fab_number, customer_name, complaint_description, complaint_category_name
        FROM complaints
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $complaintId, PDO::PARAM_INT);
    $stmt->execute();

    $complaint = $stmt->fetch(PDO::FETCH_ASSOC);

    return $complaint ?: null;
}

function complaint_mail_complaint_lines(array $complaint, int $complaintId): array
{
    $categoryName = trim((string) ($complaint['complaint_category_name'] ?? ''));

    return [
        'Complaint ID: ' . $complaintId,
        'Fab Number: ' . ($complaint['fab_number'] ?? '-'),
        'Customer Name: ' . ($complaint['customer_name'] ?? '-'),
        'Complaint Category: ' . ($categoryName !== '' ? $categoryName : '-'),
        'Complaint Description: ' . ($complaint['complaint_description'] ?? '-'),
    ];
}

function complaint_assignment_notify_email(
    PDO $conn,
    int $complaintId,
    string $assignTo,
    string $assignedAt,
    string $remarks = '',
    bool $isReassignment = false
): bool {
    $complaint = complaint_fetch_complaint_for_mail($conn, $complaintId);
    if ($complaint === null) {
        return false;
    }

    $assignee = complaint_fetch_assignee_by_name($conn, $assignTo);
    $to = trim((string) ($assignee['email'] ?? ''));
    if ($to === '') {
        return false;
    }

    $assignTo = trim($assignTo);
    $assignedAtFormatted = date('d M Y, h:i A', strtotime($assignedAt));

    if ($isReassignment) {
        $subject = 'Complaint #' . $complaintId . ' reassigned to you';
        $intro = 'A complaint has been reassigned to you in Complaint Management.';
    } else {
        $subject = 'Complaint #' . $complaintId . ' assigned to you';
        $intro = 'A complaint has been assigned to you in Complaint Management.';
    }

    $lines = array_merge(
        [$intro, ''],
        complaint_mail_complaint_lines($complaint, $complaintId),
        [
            'Assigned To: ' . $assignTo,
            'Assigned At: ' . $assignedAtFormatted,
        ]
    );

    if ($remarks !== '') {
        $lines[] = 'Remarks: ' . $remarks;
    }

    return complaint_mail_send($to, $subject, implode("\r\n", $lines));
}

function complaint_closure_notify_email(
    PDO $conn,
    int $complaintId,
    string $closureRemarks = ''
): bool {
    $complaint = complaint_fetch_complaint_for_mail($conn, $complaintId);
    if ($complaint === null) {
        return false;
    }

    $assignment = complaint_fetch_latest_assignment($conn, $complaintId);
    if ($assignment === null) {
        return false;
    }

    $assignTo = trim((string) ($assignment['assign_complaint'] ?? ''));
    $assignee = complaint_fetch_assignee_by_name($conn, $assignTo);
    $to = trim((string) ($assignee['email'] ?? ''));
   
    if ($to === '') {
        return false;
    }

    $subject = 'Complaint #' . $complaintId . ' closed';

    $lines = array_merge(
        [
            'Your assigned complaint has been closed in Complaint Management.',
            'Call Closure: Yes',
            '',
        ],
        complaint_mail_complaint_lines($complaint, $complaintId),
        [
            'Assigned To: ' . ($assignTo !== '' ? $assignTo : '-'),
        ]
    );

    if ($closureRemarks !== '') {
        $lines[] = 'Closure Remarks: ' . $closureRemarks;
    }

    return complaint_mail_send($to, $subject, implode("\r\n", $lines));
}
