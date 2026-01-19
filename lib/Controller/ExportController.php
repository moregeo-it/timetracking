<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Controller;

use DateTime;
use OCA\TimeTracking\Db\TimeEntryMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\IRequest;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IConfig;
use TCPDF;

/**
 * Custom TCPDF class with footer
 */
class TimeTrackingPDF extends TCPDF {
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('dejavusans', '', 9);
        $this->Cell(0, 10, 'Seite ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, false, 'C');
    }
}

class ExportController extends Controller {
    private TimeEntryMapper $timeEntryMapper;
    private IGroupManager $groupManager;
    private IUserManager $userManager;
    private IConfig $config;
    private string $userId;

    private const GERMAN_MONTHS = [
        1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
        5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'
    ];

    public function __construct(
        string $appName,
        IRequest $request,
        TimeEntryMapper $timeEntryMapper,
        IGroupManager $groupManager,
        IUserManager $userManager,
        IConfig $config,
        string $userId
    ) {
        parent::__construct($appName, $request);
        $this->timeEntryMapper = $timeEntryMapper;
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
        $this->config = $config;
        $this->userId = $userId;
    }

    private function isAdmin(): bool {
        return $this->groupManager->isAdmin($this->userId);
    }

    private function getDisplayName(string $userId): string {
        $user = $this->userManager->get($userId);
        return $user ? $user->getDisplayName() : $userId;
    }

    private function formatDuration(int $minutes): string {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return sprintf('%d:%02d h', $hours, $mins);
    }

    private function getCompanyName(): string {
        return $this->config->getAppValue('timetracking', 'company_name', 'moreGeo GmbH');
    }

    /**
     * Group entries by date
     * 
     * @param array $entries Array of time entries
     * @return array Entries grouped by date key (Y-m-d)
     */
    private function groupEntriesByDate(array $entries): array {
        $entriesByDate = [];
        foreach ($entries as $entry) {
            $startTime = new DateTime();
            $startTime->setTimestamp($entry->getStartTimestamp());
            $dateKey = $startTime->format('Y-m-d');
            
            if (!isset($entriesByDate[$dateKey])) {
                $entriesByDate[$dateKey] = [];
            }
            $entriesByDate[$dateKey][] = $entry;
        }
        return $entriesByDate;
    }

    /**
     * Calculate daily work summary
     * 
     * @param array $dayEntries Entries for a single day
     * @return array|null Array with firstStart, lastEnd, totalWorkMinutes, breakMinutes or null if no valid entries
     */
    private function calculateDaySummary(array $dayEntries): ?array {
        $firstStart = null;
        $lastEnd = null;
        $totalWorkMinutes = 0;
        
        foreach ($dayEntries as $entry) {
            $entryStart = $entry->getStartTimestamp();
            $entryEnd = $entry->getEndTimestamp();
            
            if ($entryEnd === null) {
                continue; // Skip running timers
            }
            
            if ($firstStart === null || $entryStart < $firstStart) {
                $firstStart = $entryStart;
            }
            if ($lastEnd === null || $entryEnd > $lastEnd) {
                $lastEnd = $entryEnd;
            }
            
            $totalWorkMinutes += $entry->getDurationMinutes();
        }
        
        if ($firstStart === null || $lastEnd === null) {
            return null;
        }
        
        // Calculate break time (time between first start and last end minus work time)
        $totalSpanMinutes = ($lastEnd - $firstStart) / 60;
        $breakMinutes = max(0, $totalSpanMinutes - $totalWorkMinutes);
        
        return [
            'firstStart' => $firstStart,
            'lastEnd' => $lastEnd,
            'totalWorkMinutes' => $totalWorkMinutes,
            'breakMinutes' => (int)$breakMinutes,
        ];
    }

    /**
     * Add a monthly timesheet section to the PDF
     * 
     * @param TimeTrackingPDF $pdf The PDF object
     * @param string $employeeName Employee display name
     * @param string $periodLabel Period label (e.g., "Januar 2026")
     * @param array $entries Entries for this period
     * @param string $totalLabel Label for the total row
     * @param bool $showFullHeader Whether to show full header with company/employee info
     * @param bool $newPage Whether to start a new page
     * @return int Total minutes for this section
     */
    private function addTimesheetSection(TimeTrackingPDF $pdf, string $employeeName, string $periodLabel, array $entries, string $totalLabel = 'Gesamt', bool $showFullHeader = true, bool $newPage = true): int {
        if ($newPage) {
            $pdf->AddPage();
        }

        // Header
        if ($showFullHeader) {
            $pdf->SetFont('dejavusans', 'B', 16);
            $pdf->Cell(0, 10, 'Arbeitszeitnachweis', 0, 1, 'C');
            $pdf->Ln(5);

            $pdf->SetFont('dejavusans', '', 12);
            $pdf->Cell(0, 8, "Firma: " . $this->getCompanyName(), 0, 1);
            $pdf->Cell(0, 8, "Mitarbeiter: $employeeName", 0, 1);
            $pdf->Ln(5);
        }

        $pdf->SetFont('dejavusans', 'B', 12);
        $pdf->Cell(0, 8, $periodLabel, 0, 1, 'L');
        $pdf->Ln(2);

        // Table header
        $pdf->SetFont('dejavusans', 'B', 9);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(40, 7, 'Datum', 1, 0, 'C', true);
        $pdf->Cell(35, 7, 'Beginn', 1, 0, 'C', true);
        $pdf->Cell(35, 7, 'Ende', 1, 0, 'C', true);
        $pdf->Cell(30, 7, 'Pause', 1, 0, 'C', true);
        $pdf->Cell(30, 7, 'Arbeitszeit', 1, 1, 'C', true);

        // Group entries by date
        $entriesByDate = $this->groupEntriesByDate($entries);

        // Table rows
        $pdf->SetFont('dejavusans', '', 9);
        $totalMinutes = 0;

        foreach ($entriesByDate as $dateKey => $dayEntries) {
            $daySummary = $this->calculateDaySummary($dayEntries);
            if ($daySummary === null) {
                continue;
            }
            
            $dateObj = new DateTime($dateKey);
            $dateFormatted = $dateObj->format('d.m.Y');
            
            $startTimeObj = new DateTime();
            $startTimeObj->setTimestamp($daySummary['firstStart']);
            $endTimeObj = new DateTime();
            $endTimeObj->setTimestamp($daySummary['lastEnd']);
            
            $pdf->Cell(40, 6, $dateFormatted, 1, 0, 'C');
            $pdf->Cell(35, 6, $startTimeObj->format('H:i'), 1, 0, 'C');
            $pdf->Cell(35, 6, $endTimeObj->format('H:i'), 1, 0, 'C');
            $pdf->Cell(30, 6, $this->formatDuration($daySummary['breakMinutes']), 1, 0, 'C');
            $pdf->Cell(30, 6, $this->formatDuration($daySummary['totalWorkMinutes']), 1, 1, 'C');
            
            $totalMinutes += $daySummary['totalWorkMinutes'];
        }

        // Total row
        $pdf->SetFont('dejavusans', 'B', 9);
        $pdf->Cell(140, 7, $totalLabel, 1, 0, 'R');
        $pdf->Cell(30, 7, $this->formatDuration($totalMinutes), 1, 1, 'C');

        return $totalMinutes;
    }

    /**
     * Add signature section to the PDF
     * 
     * @param TimeTrackingPDF $pdf The PDF object
     */
    private function addSignatureSection(TimeTrackingPDF $pdf): void {
        $pdf->Ln(20);
        $pdf->SetFont('dejavusans', '', 10);
        
        $pdf->Cell(0, 6, 'Ich bestätige die Richtigkeit der vorstehenden Angaben.', 0, 1);
        $pdf->Ln(15);

        // Signature lines
        $pdf->Cell(80, 6, '________________________________', 0, 0, 'L');
        $pdf->Cell(10, 6, '', 0, 0);
        $pdf->Cell(80, 6, '________________________________', 0, 1, 'L');
        
        $pdf->Cell(80, 6, 'Ort, Datum', 0, 0, 'L');
        $pdf->Cell(10, 6, '', 0, 0);
        $pdf->Cell(80, 6, 'Unterschrift Mitarbeiter/in', 0, 1, 'L');
        
        $pdf->Ln(15);
        
        $pdf->Cell(80, 6, '________________________________', 0, 0, 'L');
        $pdf->Cell(10, 6, '', 0, 0);
        $pdf->Cell(80, 6, '________________________________', 0, 1, 'L');
        
        $pdf->Cell(80, 6, 'Ort, Datum', 0, 0, 'L');
        $pdf->Cell(10, 6, '', 0, 0);
        $pdf->Cell(80, 6, 'Unterschrift Geschäftsführung', 0, 1, 'L');
    }

    /**
     * Create and configure a new PDF document
     * 
     * @param string $title Document title
     * @return TimeTrackingPDF
     */
    private function createPdf(string $title): TimeTrackingPDF {
        $pdf = new TimeTrackingPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        $pdf->SetCreator('Nextcloud Zeiterfassung');
        $pdf->SetAuthor($this->getCompanyName());
        $pdf->SetTitle($title);
        $pdf->SetSubject('Arbeitszeitnachweis');
        $pdf->setPrintHeader(false);
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(true, 35);
        
        return $pdf;
    }

    /**
     * Generate the timesheet PDF
     * 
     * @param string $userId User ID
     * @param int $year Year
     * @param int|null $month Month (null for yearly report)
     * @return Response
     */
    private function generateTimesheet(string $userId, int $year, ?int $month = null): Response {
        // Check authorization - users can export their own, admins can export any
        if ($userId !== $this->userId && !$this->isAdmin()) {
            return new DataDownloadResponse('Unauthorized', 'error.txt', 'text/plain');
        }

        $employeeName = $this->getDisplayName($userId);
        $isYearly = ($month === null);

        // Determine date range
        if ($isYearly) {
            $startDate = new DateTime("$year-01-01");
            $endDate = new DateTime("$year-12-31");
            $periodLabel = (string)$year;
            $pdfTitle = "Arbeitszeitnachweis $year - $employeeName";
        } else {
            $monthName = self::GERMAN_MONTHS[$month];
            $startDate = new DateTime("$year-$month-01");
            $endDate = clone $startDate;
            $endDate->modify('last day of this month');
            $periodLabel = "$monthName $year";
            $pdfTitle = "Arbeitszeitnachweis $monthName $year - $employeeName";
        }
        $endDate->setTime(23, 59, 59);

        // Get time entries
        $entries = $this->timeEntryMapper->findByUser(
            $userId,
            $startDate->getTimestamp(),
            $endDate->getTimestamp()
        );

        // Sort entries by date
        usort($entries, function($a, $b) {
            return $a->getStartTimestamp() <=> $b->getStartTimestamp();
        });

        // Create PDF
        $pdf = $this->createPdf($pdfTitle);

        if ($isYearly) {
            // Group entries by month
            $entriesByMonth = [];
            foreach ($entries as $entry) {
                $startTime = new DateTime();
                $startTime->setTimestamp($entry->getStartTimestamp());
                $monthKey = $startTime->format('Y-m');
                
                if (!isset($entriesByMonth[$monthKey])) {
                    $entriesByMonth[$monthKey] = [];
                }
                $entriesByMonth[$monthKey][] = $entry;
            }

            $grandTotalMinutes = 0;
            $isFirstMonth = true;

            // Add first page with header
            $pdf->AddPage();
            
            // Header on first page
            $pdf->SetFont('dejavusans', 'B', 16);
            $pdf->Cell(0, 10, 'Arbeitszeitnachweis', 0, 1, 'C');
            $pdf->Ln(3);

            $pdf->SetFont('dejavusans', '', 12);
            $pdf->Cell(0, 8, "Firma: " . $this->getCompanyName(), 0, 1);
            $pdf->Cell(0, 8, "Mitarbeiter: $employeeName", 0, 1);
            $pdf->Cell(0, 8, "Jahr: $year", 0, 1);
            $pdf->Ln(5);

            // Add each month's entries continuously
            foreach ($entriesByMonth as $monthKey => $monthEntries) {
                $monthDate = new DateTime($monthKey . '-01');
                $m = (int)$monthDate->format('n');
                $monthName = self::GERMAN_MONTHS[$m];
                
                if (!$isFirstMonth) {
                    $pdf->Ln(4);
                }
                
                $monthTotal = $this->addTimesheetSection(
                    $pdf,
                    $employeeName,
                    "$monthName $year",
                    $monthEntries,
                    "Gesamt $monthName",
                    false,  // No full header
                    false   // No new page
                );
                $grandTotalMinutes += $monthTotal;
                $isFirstMonth = false;
            }

            // Add yearly summary page
            $pdf->AddPage();
            
            $pdf->SetFont('dejavusans', 'B', 14);
            $pdf->Cell(0, 8, "Jahresübersicht $year", 0, 1, 'L');
            $pdf->Ln(3);

            // Monthly summary table
            $pdf->SetFont('dejavusans', 'B', 9);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(85, 7, 'Monat', 1, 0, 'C', true);
            $pdf->Cell(85, 7, 'Arbeitszeit', 1, 1, 'C', true);

            $pdf->SetFont('dejavusans', '', 9);
            for ($m = 1; $m <= 12; $m++) {
                $monthKey = sprintf('%d-%02d', $year, $m);
                $monthMinutes = 0;
                
                if (isset($entriesByMonth[$monthKey])) {
                    foreach ($entriesByMonth[$monthKey] as $entry) {
                        if ($entry->getEndTimestamp() !== null) {
                            $monthMinutes += $entry->getDurationMinutes();
                        }
                    }
                }
                
                $pdf->Cell(85, 6, self::GERMAN_MONTHS[$m], 1, 0, 'L');
                $pdf->Cell(85, 6, $this->formatDuration($monthMinutes), 1, 1, 'C');
            }

            // Grand total row
            $pdf->SetFont('dejavusans', 'B', 9);
            $pdf->Cell(85, 7, 'Gesamtjahr', 1, 0, 'R');
            $pdf->Cell(85, 7, $this->formatDuration($grandTotalMinutes), 1, 1, 'C');

            // Add signature section
            $this->addSignatureSection($pdf);

            $filename = "Arbeitszeitnachweis_{$employeeName}_{$year}.pdf";
        } else {
            // Single month - add one page with signature
            $this->addTimesheetSection($pdf, $employeeName, $periodLabel, $entries);
            $this->addSignatureSection($pdf);
            
            $filename = "Arbeitszeitnachweis_{$employeeName}_{$periodLabel}.pdf";
        }

        // Output PDF
        $pdfContent = $pdf->Output('', 'S');
        $filename = preg_replace('/[^a-zA-Z0-9äöüÄÖÜß_\-\.]/', '_', $filename);

        return new DataDownloadResponse($pdfContent, $filename, 'application/pdf');
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * 
     * Export monthly timesheet as PDF for compliance with German labor law
     */
    public function monthlyTimesheet(string $userId, int $year, int $month): Response {
        return $this->generateTimesheet($userId, $year, $month);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * 
     * Export yearly timesheet as PDF for compliance with German labor law
     */
    public function yearlyTimesheet(string $userId, int $year): Response {
        return $this->generateTimesheet($userId, $year, null);
    }
}
