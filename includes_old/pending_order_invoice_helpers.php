<?php
function pending_invoice_format_date(?string $value): string
{
    if ($value === null || trim($value) === '' || strpos(trim($value), '1970-01-01') === 0) {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('d M Y', $timestamp) : '-';
}

function pending_invoice_money_symbol(): string
{
    return html_entity_decode('&#8377;', ENT_QUOTES, 'UTF-8');
}

function pending_invoice_format_money($amount): string
{
    return pending_invoice_money_symbol() . ' ' . number_format((float) $amount, 2);
}

function pending_invoice_format_money_html($amount): string
{
    return '&#8377; ' . number_format((float) $amount, 2);
}

function pending_invoice_build_address_lines(array $row): array
{
    $lines = [];

    if (!empty(trim((string) ($row['custaddr'] ?? '')))) {
        foreach (preg_split('/\R+/', trim((string) $row['custaddr'])) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }
    }

    if (empty($lines)) {
        foreach (['st1', 'st2', 'city2', 'city', 'state', 'pin'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '') {
                $lines[] = $value;
            }
        }
    }

    return array_values(array_unique($lines));
}

function pending_invoice_fetch(PDO $conn, string $ordno): ?array
{
    $ordno = trim($ordno);
    if ($ordno === '') {
        return null;
    }

    try {
        $stmt = $conn->prepare("
            SELECT
                p.cuno,
                p.cuname,
                p.ordno,
                p.posno,
                p.orddt,
                p.indnodt,
                p.indentno,
                p.indentdate,
                p.itemcode,
                p.itemdesc,
                p.qty,
                p.unitvalue,
                p.delydt,
                p.pono,
                p.currency,
                c.st1,
                c.st2,
                c.city,
                c.city2,
                c.pin,
                c.state,
                c.gst,
                c.pan,
                c.custaddr
            FROM pendingordersnew p
            LEFT JOIN customer_address c ON trim(p.cuno) = trim(c.cuno)
            WHERE p.ordno = :ordno
            ORDER BY p.posno
        ");
        $stmt->bindValue(':ordno', $ordno);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return null;
    }

    if (empty($rows)) {
        return null;
    }

    $first = $rows[0];
    $lines = [];
    $grandTotal = 0.0;

    foreach ($rows as $row) {
        $qty = (float) $row['qty'];
        $unitValue = (float) $row['unitvalue'];
        $lineTotal = $qty * $unitValue;
        $grandTotal += $lineTotal;

        $lines[] = [
            'posno' => (int) $row['posno'],
            'itemcode' => trim((string) $row['itemcode']),
            'itemdesc' => trim((string) $row['itemdesc']),
            'qty' => $qty,
            'unitvalue' => $unitValue,
            'line_total' => $lineTotal,
        ];
    }

    return [
        'ordno' => trim((string) $first['ordno']),
        'orddt' => pending_invoice_format_date($first['orddt'] ?? null),
        'indentno' => trim((string) ($first['indentno'] ?? '')),
        'indentdate' => pending_invoice_format_date($first['indentdate'] ?? null),
        'pono' => trim((string) ($first['pono'] ?? '')),
        'delydt' => pending_invoice_format_date($first['delydt'] ?? null),
        'currency' => trim((string) ($first['currency'] ?? 'IND')),
        'customer' => [
            'code' => trim((string) $first['cuno']),
            'name' => trim((string) $first['cuname']),
            'address_lines' => pending_invoice_build_address_lines($first),
            'gst' => trim((string) ($first['gst'] ?? '')),
            'pan' => trim((string) ($first['pan'] ?? '')),
        ],
        'lines' => $lines,
        'grand_total' => $grandTotal,
    ];
}

function pending_invoice_logo_path(): string
{
    return __DIR__ . '/../uploads/vayu.png';
}

function pending_invoice_generate_pdf(array $invoice): void
{
    require_once __DIR__ . '/../libs/fpdf/tfpdf.php';
    require_once __DIR__ . '/../libs/fpdf/font/unifont/ttfonts.php';

    $pdf = new tFPDF('P', 'mm', 'A4');
   // $pdf->AddFont('DejaVuSans', '', 'DejaVuSans.ttf', true);
      $pdf->AddFont('DejaVuSans', '', 'DejaVuSans.ttf', true);
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 20);

    $logoX = 15;
    $logoY = 12;
    $logoW = 28;
    $logoH = 12;

    $logoPath = pending_invoice_logo_path();
    if (is_file($logoPath)) {
        $imageSize = @getimagesize($logoPath);
        if ($imageSize !== false && $imageSize[0] > 0) {
            $logoH = $logoW * ($imageSize[1] / $imageSize[0]);
        }
        $pdf->Image($logoPath, $logoX, $logoY, $logoW);
    }

    $textY = $logoY + $logoH + 3;
    $pdf->SetXY($logoX, $textY);
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 8, 'VAYU COMPRESSORS', 0, 1);
    $pdf->SetX($logoX);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, 'Pending Order Invoice', 0, 1);
    $leftBottomY = $pdf->GetY();

    $pdf->SetXY(140, 14);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(55, 7, 'INVOICE', 0, 1, 'R');
    $pdf->SetFont('Arial', '', 9);
    $rightX = 120;
    $rightW = 75;
    $pdf->SetX($rightX);
    $pdf->Cell($rightW, 5, 'Order No: ' . $invoice['ordno'], 0, 1, 'R');
    $pdf->SetX($rightX);
    $pdf->Cell($rightW, 5, 'Order Date: ' . $invoice['orddt'], 0, 1, 'R');
    $pdf->SetX($rightX);
    $pdf->Cell($rightW, 5, 'Generated: ' . date('d M Y'), 0, 1, 'R');
    $rightBottomY = $pdf->GetY();

    $pdf->SetY(max($leftBottomY, $rightBottomY) + 8);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, 'Bill To', 0, 1);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 5, $invoice['customer']['name'], 0, 1);
    $pdf->Cell(0, 5, 'Customer Code: ' . $invoice['customer']['code'], 0, 1);

    foreach ($invoice['customer']['address_lines'] as $line) {
        $pdf->Cell(0, 5, $line, 0, 1);
    }

    if ($invoice['customer']['gst'] !== '') {
        $pdf->Cell(0, 5, 'GST: ' . $invoice['customer']['gst'], 0, 1);
    }

    if ($invoice['customer']['pan'] !== '') {
        $pdf->Cell(0, 5, 'PAN: ' . $invoice['customer']['pan'], 0, 1);
    }

    $pdf->Ln(3);
    $pdf->SetFont('Arial', '', 9);
    $rowWidth = $pdf->GetPageWidth() - 30;
    $colWidth = $rowWidth / 3;
    $pdf->Cell($colWidth, 5, 'PO No: ' . ($invoice['pono'] !== '' ? $invoice['pono'] : '-'), 0, 0, 'L');
    $pdf->Cell($colWidth, 5, 'Indent No: ' . ($invoice['indentno'] !== '' ? $invoice['indentno'] : '-'), 0, 0, 'C');
    $pdf->Cell($colWidth, 5, 'Delivery Date: ' . $invoice['delydt'], 0, 1, 'R');

    $pdf->Ln(4);
    $pdf->SetFillColor(244, 70, 17);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(10, 8, '#', 1, 0, 'C', true);
    $pdf->Cell(28, 8, 'Item Code', 1, 0, 'L', true);
    $pdf->Cell(72, 8, 'Description', 1, 0, 'L', true);
    $pdf->Cell(18, 8, 'Qty', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Unit Price', 1, 0, 'R', true);
    $pdf->Cell(32, 8, 'Amount', 1, 1, 'R', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 9);
    $rowIndex = 1;

    foreach ($invoice['lines'] as $line) {
        $pdf->Cell(10, 8, (string) $rowIndex, 1, 0, 'C');
        $pdf->Cell(28, 8, $line['itemcode'], 1, 0, 'L');
        $pdf->Cell(72, 8, substr($line['itemdesc'], 0, 42), 1, 0, 'L');
        $pdf->Cell(18, 8, number_format($line['qty'], 2), 1, 0, 'C');
        $pdf->SetFont('DejaVuSans', '', 9);
        $pdf->Cell(30, 8, pending_invoice_format_money($line['unitvalue']), 1, 0, 'R');
        $pdf->Cell(32, 8, pending_invoice_format_money($line['line_total']), 1, 1, 'R');
        $pdf->SetFont('Arial', '', 9);
        $rowIndex++;
    }

    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(158, 8, 'Grand Total', 1, 0, 'R');
    $pdf->SetFont('DejaVuSans', '', 10);
    $pdf->Cell(32, 8, pending_invoice_format_money($invoice['grand_total']), 1, 1, 'R');

    $pdf->Ln(8);
    $pdf->SetFont('Arial', 'I', 8);

    $filename = 'Invoice-' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $invoice['ordno']) . '.pdf';
    $pdf->Output('D', $filename);
}