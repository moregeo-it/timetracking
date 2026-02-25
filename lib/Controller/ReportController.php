<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Controller;

use DateTime;
use OCA\TimeTracking\Db\TimeEntryMapper;
use OCA\TimeTracking\Db\ProjectMapper;
use OCA\TimeTracking\Db\ProjectMultiplierMapper;
use OCA\TimeTracking\Db\CustomerMapper;
use OCA\TimeTracking\Db\EmployeeSettingsMapper;
use OCA\TimeTracking\Db\VacationMapper;
use OCA\TimeTracking\Db\SickDayMapper;
use OCA\TimeTracking\Db\PublicHolidayMapper;
use OCA\TimeTracking\Service\ComplianceService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IL10N;

class ReportController extends Controller {
    private TimeEntryMapper $timeEntryMapper;
    private ProjectMapper $projectMapper;
    private ProjectMultiplierMapper $projectMultiplierMapper;
    private CustomerMapper $customerMapper;
    private EmployeeSettingsMapper $employeeSettingsMapper;
    private VacationMapper $vacationMapper;
    private SickDayMapper $sickDayMapper;
    private PublicHolidayMapper $publicHolidayMapper;
    private ComplianceService $complianceService;
    private IGroupManager $groupManager;
    private IUserManager $userManager;
    private IL10N $l10n;
    private string $userId;

    public function __construct(
        string $appName,
        IRequest $request,
        TimeEntryMapper $timeEntryMapper,
        ProjectMapper $projectMapper,
        ProjectMultiplierMapper $projectMultiplierMapper,
        CustomerMapper $customerMapper,
        EmployeeSettingsMapper $employeeSettingsMapper,
        VacationMapper $vacationMapper,
        SickDayMapper $sickDayMapper,
        PublicHolidayMapper $publicHolidayMapper,
        ComplianceService $complianceService,
        IGroupManager $groupManager,
        IUserManager $userManager,
        IL10N $l10n,
        string $userId
    ) {
        parent::__construct($appName, $request);
        $this->timeEntryMapper = $timeEntryMapper;
        $this->projectMapper = $projectMapper;
        $this->projectMultiplierMapper = $projectMultiplierMapper;
        $this->customerMapper = $customerMapper;
        $this->employeeSettingsMapper = $employeeSettingsMapper;
        $this->vacationMapper = $vacationMapper;
        $this->sickDayMapper = $sickDayMapper;
        $this->publicHolidayMapper = $publicHolidayMapper;
        $this->complianceService = $complianceService;
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
        $this->l10n = $l10n;
        $this->userId = $userId;
    }

    private function isAdmin(): bool {
        return $this->groupManager->isAdmin($this->userId);
    }

    private function getDisplayName(string $userId): string {
        $user = $this->userManager->get($userId);
        return $user ? $user->getDisplayName() : $userId;
    }

    /**
     * Get the employment type for a user at a specific date.
     * Falls back to 'contract' if no settings found.
     * 
     * @param string $userId The user ID
     * @param DateTime|null $date The date to check (defaults to today)
     * @return string The employment type (director, contract, freelance, intern, student)
     */
    private function getEmploymentTypeForUser(string $userId, ?DateTime $date = null): string {
        $settings = $this->employeeSettingsMapper->findByUserIdAtDate($userId, $date ?? new DateTime());
        return $settings ? $settings->getEmploymentType() : 'contract';
    }

    /**
     * Convert DateTime to Unix timestamp for mapper queries
     */
    private function dateTimeToTimestamp(?DateTime $dateTime): ?int {
        return $dateTime ? $dateTime->getTimestamp() : null;
    }

    /**
     * Calculate date range based on period type
     */
    private function getDateRange(string $periodType, int $year, ?int $month = null, ?int $quarter = null, ?string $customStartDate = null, ?string $customEndDate = null): array {
        switch ($periodType) {
            case 'month':
                $startDate = new DateTime("$year-$month-01");
                $endDate = clone $startDate;
                $endDate->modify('last day of this month');
                break;
            case 'quarter':
                $startMonth = ($quarter - 1) * 3 + 1;
                $startDate = new DateTime("$year-$startMonth-01");
                $endDate = clone $startDate;
                $endDate->modify('+2 months');
                $endDate->modify('last day of this month');
                break;
            case 'year':
                $startDate = new DateTime("$year-01-01");
                $endDate = new DateTime("$year-12-31");
                break;
            case 'custom':
                $startDate = $customStartDate ? new DateTime($customStartDate) : null;
                $endDate = $customEndDate ? new DateTime($customEndDate) : null;
                if ($endDate) {
                    $endDate->setTime(23, 59, 59);
                }
                break;
            case 'total':
            default:
                $startDate = null;
                $endDate = null;
                break;
        }
        return [$startDate, $endDate];
    }

    /**
     * Calculate aggregated employee settings for a date range.
     * If the employee has multiple settings periods within the range,
     * this calculates weighted averages or sums as appropriate.
     * 
     * @param string $userId User ID
     * @param DateTime $startDate Start of the date range
     * @param DateTime $endDate End of the date range
     * @return array Aggregated settings with weighted values
     */
    private function getAggregatedSettings(string $userId, DateTime $startDate, DateTime $endDate): array {
        $settingsPeriods = $this->employeeSettingsMapper->findByUserIdInRange($userId, $startDate, $endDate);
        
        if (empty($settingsPeriods)) {
            // Fall back to current settings if no period-specific settings found
            $currentSettings = $this->employeeSettingsMapper->findByUserId($userId);
            if (!$currentSettings) {
                return [
                    'employmentType' => 'contract',
                    'weeklyHours' => 40.0,
                    'dailyHours' => 8.0,
                    'vacationDaysPerYear' => 20,
                    'hourlyRate' => null,
                    'maxTotalHours' => null,
                    'periods' => [],
                ];
            }
            return [
                'employmentType' => $currentSettings->getEmploymentType(),
                'weeklyHours' => $currentSettings->getWeeklyHours(),
                'dailyHours' => $currentSettings->getWeeklyHours() / 5,
                'vacationDaysPerYear' => $currentSettings->getVacationDaysPerYear(),
                'hourlyRate' => $currentSettings->getHourlyRate(),
                'maxTotalHours' => $currentSettings->getMaxTotalHours(),
                'periods' => [$currentSettings],
            ];
        }
        
        // Count total workdays in the date range
        $totalWorkdays = 0;
        $current = clone $startDate;
        while ($current <= $endDate) {
            $dayOfWeek = (int)$current->format('N');
            if ($dayOfWeek <= 5) { // Mon-Fri
                $totalWorkdays++;
            }
            $current->modify('+1 day');
        }
        
        if ($totalWorkdays === 0) {
            $totalWorkdays = 1; // Prevent division by zero
        }
        
        // Calculate weighted average of settings based on days covered
        $weightedWeeklyHours = 0;
        $weightedVacationDays = 0;
        $weightedHourlyRate = 0;
        $hasHourlyRate = false;
        $totalDaysCovered = 0;
        $employmentTypes = [];
        
        foreach ($settingsPeriods as $period) {
            // Determine the effective date range of this period within our report range
            $periodStart = $period->getValidFrom() ? max($period->getValidFrom(), $startDate) : $startDate;
            $periodEnd = $period->getValidTo() ? min($period->getValidTo(), $endDate) : $endDate;
            
            // Count workdays in this period
            $periodWorkdays = 0;
            $current = clone $periodStart;
            while ($current <= $periodEnd) {
                $dayOfWeek = (int)$current->format('N');
                if ($dayOfWeek <= 5) {
                    $periodWorkdays++;
                }
                $current->modify('+1 day');
            }
            
            $weight = $periodWorkdays / $totalWorkdays;
            $weightedWeeklyHours += $period->getWeeklyHours() * $weight;
            $weightedVacationDays += $period->getVacationDaysPerYear() * $weight;
            
            if ($period->getHourlyRate()) {
                $weightedHourlyRate += $period->getHourlyRate() * $weight;
                $hasHourlyRate = true;
            }
            
            $totalDaysCovered += $periodWorkdays;
            $employmentTypes[$period->getEmploymentType()] = ($employmentTypes[$period->getEmploymentType()] ?? 0) + $periodWorkdays;
        }
        
        // Determine dominant employment type
        $dominantType = 'contract';
        $maxDays = 0;
        foreach ($employmentTypes as $type => $days) {
            if ($days > $maxDays) {
                $dominantType = $type;
                $maxDays = $days;
            }
        }
        
        return [
            'employmentType' => $dominantType,
            'weeklyHours' => round($weightedWeeklyHours, 2),
            'dailyHours' => round($weightedWeeklyHours / 5, 2),
            'vacationDaysPerYear' => round($weightedVacationDays),
            'hourlyRate' => $hasHourlyRate ? round($weightedHourlyRate, 2) : null,
            'maxTotalHours' => count($settingsPeriods) === 1 ? $settingsPeriods[0]->getMaxTotalHours() : null,
            'periods' => $settingsPeriods,
        ];
    }

    /**
     * Resolve the date range for a 'total' period report for an employee.
     * Returns the earliest employment start date and today as end date.
     *
     * @param string $userId User ID
     * @return array [DateTime $rangeStart, DateTime $rangeEnd, array $allPeriods]
     */
    private function resolveTotalDateRange(string $userId): array {
        $allPeriods = $this->employeeSettingsMapper->findAllByUserId($userId);
        $earliestStart = null;
        foreach ($allPeriods as $period) {
            $start = $period->getEmploymentStart() ?? $period->getValidFrom();
            if ($start !== null && ($earliestStart === null || $start < $earliestStart)) {
                $earliestStart = $start;
            }
        }
        $rangeStart = $earliestStart ?? new DateTime('2020-01-01');
        $rangeEnd = new DateTime();
        $rangeEnd->setTime(23, 59, 59);
        return [$rangeStart, $rangeEnd, $allPeriods];
    }

    /**
     * Calculate expected working hours across multiple employment settings periods.
     * Counts Mon-Fri workdays in each period's effective date range and multiplies
     * by the period-specific daily hours (weeklyHours / 5).
     *
     * @param array $settingsPeriods Array of EmployeeSettings objects
     * @param DateTime $rangeStart Overall start date
     * @param DateTime $rangeEnd Overall end date
     * @return float Total expected hours, rounded to 2 decimal places
     */
    private function calculateExpectedHoursFromPeriods(array $settingsPeriods, DateTime $rangeStart, DateTime $rangeEnd): float {
        $totalExpected = 0;
        foreach ($settingsPeriods as $period) {
            $pStart = $period->getValidFrom() ?? $rangeStart;
            $pEnd = $period->getValidTo() ?? $rangeEnd;
            // Clamp to report range
            $effectiveStart = ($pStart > $rangeStart) ? $pStart : $rangeStart;
            $effectiveEnd = ($pEnd < $rangeEnd) ? $pEnd : $rangeEnd;
            if ($effectiveStart > $effectiveEnd) {
                continue;
            }
            $workdays = 0;
            $current = clone $effectiveStart;
            while ($current <= $effectiveEnd) {
                if ((int)$current->format('N') <= 5) {
                    $workdays++;
                }
                $current->modify('+1 day');
            }
            $totalExpected += $workdays * ($period->getWeeklyHours() / 5);
        }
        return round($totalExpected, 2);
    }

    /**
     * Calculate credited hours for vacations, sick days, and public holidays in a date range
     * 
     * Under German law (EFZG §3), employers must continue paying wages during sick leave
     * for up to 6 weeks (42 calendar days) per illness case. Sick days are credited
     * similarly to vacation days in working time reports.
     * 
     * @param string $userId User ID to check vacations/sick days for
     * @param DateTime $startDate Start of the date range
     * @param DateTime $endDate End of the date range
     * @param float $dailyHours Average daily working hours (weeklyHours / 5)
     * @param string $employmentType Employment type of the user (used to exclude sick days and public holidays for interns/freelancers)
     * @return array Array with vacation days, sick days, holiday days, and total credited hours
     */
    private function calculateCreditedHours(string $userId, DateTime $startDate, DateTime $endDate, float $dailyHours, string $employmentType = 'contract'): array {
        $vacationDays = 0;
        $sickDaysCount = 0;
        $publicHolidayDays = 0;
        $vacationDates = [];
        $sickDates = [];
        $holidayDates = [];
        
        // Get approved vacations in the date range
        // Use the stored days count directly — some employees work on Saturdays
        $vacations = $this->vacationMapper->findByDateRange($userId, $startDate, $endDate);
        foreach ($vacations as $vacation) {
            if ($vacation->getStatus() !== 'approved') {
                continue;
            }
            
            // Credit the entered number of days directly
            $vacationDays += $vacation->getDays();
            
            // Collect individual dates for the daily summary display
            $vacStart = $vacation->getStartDate();
            $vacEnd = $vacation->getEndDate();
            $current = clone $vacStart;
            while ($current <= $vacEnd) {
                if ($current >= $startDate && $current <= $endDate) {
                    $dateStr = $current->format('Y-m-d');
                    if (!in_array($dateStr, $vacationDates)) {
                        $vacationDates[] = $dateStr;
                    }
                }
                $current->modify('+1 day');
            }
        }
        
        // Get sick days in the date range
        // Use the stored days count directly — some employees work on Saturdays
        // Sick days for interns and freelancers do NOT add to credited hours
        $includeSickDays = !in_array($employmentType, ['intern', 'freelance']);
        if ($includeSickDays) {
            $sickDayEntries = $this->sickDayMapper->findByDateRange($userId, $startDate, $endDate);
            foreach ($sickDayEntries as $sickDay) {
                // Credit the entered number of days directly
                $sickDaysCount += $sickDay->getDays();
                
                // Collect individual dates for the daily summary display
                $sickStart = $sickDay->getStartDate();
                $sickEnd = $sickDay->getEndDate();
                $current = clone $sickStart;
                while ($current <= $sickEnd) {
                    if ($current >= $startDate && $current <= $endDate) {
                        $dateStr = $current->format('Y-m-d');
                        if (!in_array($dateStr, $sickDates) && !in_array($dateStr, $vacationDates)) {
                            $sickDates[] = $dateStr;
                        }
                    }
                    $current->modify('+1 day');
                }
            }
        }
        
        // Get public holidays in the date range (Mon-Sat, as some employees work Saturdays)
        // Public holidays for interns and freelancers do NOT add to credited hours
        $includePublicHolidays = !in_array($employmentType, ['intern', 'freelance']);
        if ($includePublicHolidays) {
            $holidays = $this->publicHolidayMapper->findByDateRange($startDate, $endDate);
            foreach ($holidays as $holiday) {
                $holidayDate = $holiday->getDate();
                $dayOfWeek = (int)$holidayDate->format('N');
                $dateStr = $holidayDate->format('Y-m-d');
                
                // Count Mon-Sat (exclude only Sundays) and don't double-count
                if ($dayOfWeek <= 6 && !in_array($dateStr, $holidayDates) && !in_array($dateStr, $vacationDates) && !in_array($dateStr, $sickDates)) {
                    $holidayDates[] = $dateStr;
                    $publicHolidayDays++;
                }
            }
        }
        
        $totalCreditedDays = $vacationDays + $sickDaysCount + $publicHolidayDays;
        $totalCreditedHours = round($totalCreditedDays * $dailyHours, 2);
        
        return [
            'vacationDays' => $vacationDays,
            'vacationHours' => round($vacationDays * $dailyHours, 2),
            'vacationDates' => $vacationDates,
            'sickDays' => $sickDaysCount,
            'sickDayHours' => round($sickDaysCount * $dailyHours, 2),
            'sickDates' => $sickDates,
            'publicHolidayDays' => $publicHolidayDays,
            'publicHolidayHours' => round($publicHolidayDays * $dailyHours, 2),
            'holidayDates' => $holidayDates,
            'totalCreditedDays' => $totalCreditedDays,
            'totalCreditedHours' => $totalCreditedHours,
        ];
    }

    /**
     * Get period label for display
     */
    private function getPeriodLabel(string $periodType, int $year, ?int $month = null, ?int $quarter = null, ?string $customStartDate = null, ?string $customEndDate = null): string {
        switch ($periodType) {
            case 'month':
                $monthName = $this->getLocalizedMonthName($month);
                return $monthName . ' ' . $year;
            case 'quarter':
                return "Q$quarter $year";
            case 'year':
                return (string)$year;
            case 'total':
                return $this->l10n->t('Gesamt');
            case 'project_period':
                return $this->l10n->t('Projektzeitraum');
            case 'custom':
                $start = $customStartDate ? (new DateTime($customStartDate))->format('d.m.Y') : '';
                $end = $customEndDate ? (new DateTime($customEndDate))->format('d.m.Y') : '';
                return "$start - $end";
            default:
                return '';
        }
    }

    /**
     * Get localized month name using IntlDateFormatter
     */
    private function getLocalizedMonthName(int $month): string {
        $locale = $this->l10n->getLanguageCode();
        $date = new DateTime("2000-$month-01");
        
        if (class_exists('IntlDateFormatter')) {
            $formatter = new \IntlDateFormatter(
                $locale,
                \IntlDateFormatter::NONE,
                \IntlDateFormatter::NONE,
                null,
                null,
                'MMMM'
            );
            return $formatter->format($date);
        }
        
        // Fallback if Intl extension is not available
        return $date->format('F');
    }

    /**
     * Customer report - Admin only
     * Uses employee category multipliers from project settings for hours calculation.
     */
    public function customerReport(int $customerId, string $periodType, int $year, ?int $month = null, ?int $quarter = null): DataResponse {
        if (!$this->isAdmin()) {
            return new DataResponse(['error' => 'Forbidden'], 403);
        }
        try {
            $customer = $this->customerMapper->find($customerId);
            $projects = $this->projectMapper->findByCustomer($customerId);
            
            [$startDate, $endDate] = $this->getDateRange($periodType, $year, $month, $quarter);
            
            $report = [
                'customer' => $customer,
                'period' => [
                    'type' => $periodType,
                    'label' => $this->getPeriodLabel($periodType, $year, $month, $quarter),
                    'year' => $year,
                    'month' => $month,
                    'quarter' => $quarter,
                    'startDate' => $startDate?->format('Y-m-d'),
                    'endDate' => $endDate?->format('Y-m-d'),
                ],
                'projects' => [],
                'totals' => [
                    'hours' => 0,
                    'billableHours' => 0,
                    'actualHours' => 0,
                    'actualBillableHours' => 0,
                    'amount' => 0,
                ],
            ];
            
            $totalActualHours = 0;
            $totalActualBillableHours = 0;
            
            foreach ($projects as $project) {
                $entries = $this->timeEntryMapper->findByProject(
                    $project->getId(),
                    $this->dateTimeToTimestamp($startDate),
                    $this->dateTimeToTimestamp($endDate)
                );
                
                $projectHours = 0;
                $billableHours = 0;
                $adjustedHours = 0;
                $adjustedBillableHours = 0;
                
                foreach ($entries as $entry) {
                    $hours = ($entry->getDurationMinutes() ?? 0) / 60;
                    $projectHours += $hours;
                    
                    // Get the employment type for this user at the entry date
                    $entryDate = new DateTime('@' . $entry->getStartTimestamp());
                    $employmentType = $this->getEmploymentTypeForUser($entry->getUserId(), $entryDate);
                    $multiplier = $this->projectMultiplierMapper->getMultiplierValue($project->getId(), $employmentType);
                    
                    $adjustedHours += $hours * $multiplier;
                    
                    if ($entry->getBillable()) {
                        $billableHours += $hours;
                        $adjustedBillableHours += $hours * $multiplier;
                    }
                }
                
                // Calculate amount based on adjusted billable hours
                $amount = $adjustedBillableHours * ($project->getHourlyRate() ?? 0);
                
                if ($projectHours > 0) {
                    $report['projects'][] = [
                        'project' => $project,
                        'hours' => round($adjustedHours, 2),
                        'billableHours' => round($adjustedBillableHours, 2),
                        'actualHours' => round($projectHours, 2),
                        'actualBillableHours' => round($billableHours, 2),
                        'hourlyRate' => $project->getHourlyRate(),
                        'amount' => round($amount, 2),
                        'entryCount' => count($entries),
                    ];
                }
                
                $report['totals']['hours'] += $adjustedHours;
                $report['totals']['billableHours'] += $adjustedBillableHours;
                $totalActualHours += $projectHours;
                $totalActualBillableHours += $billableHours;
                $report['totals']['amount'] += $amount;
            }
            
            $report['totals']['hours'] = round($report['totals']['hours'], 2);
            $report['totals']['billableHours'] = round($report['totals']['billableHours'], 2);
            $report['totals']['actualHours'] = round($totalActualHours, 2);
            $report['totals']['actualBillableHours'] = round($totalActualBillableHours, 2);
            $report['totals']['amount'] = round($report['totals']['amount'], 2);
            
            return new DataResponse($report);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * All customers report - Admin only
     * Returns a summary of all customers with their totals
     */
    public function allCustomersReport(string $periodType, int $year, ?int $month = null, ?int $quarter = null): DataResponse {
        if (!$this->isAdmin()) {
            return new DataResponse(['error' => 'Forbidden'], 403);
        }
        
        try {
            $customers = $this->customerMapper->findAll();
            [$startDate, $endDate] = $this->getDateRange($periodType, $year, $month, $quarter);
            
            $report = [
                'period' => [
                    'type' => $periodType,
                    'label' => $this->getPeriodLabel($periodType, $year, $month, $quarter),
                    'year' => $year,
                    'month' => $month,
                    'quarter' => $quarter,
                    'startDate' => $startDate?->format('Y-m-d'),
                    'endDate' => $endDate?->format('Y-m-d'),
                ],
                'customers' => [],
                'totals' => [
                    'hours' => 0,
                    'billableHours' => 0,
                    'actualHours' => 0,
                    'actualBillableHours' => 0,
                    'amount' => 0,
                ],
            ];
            
            foreach ($customers as $customer) {
                $projects = $this->projectMapper->findByCustomer($customer->getId());
                
                $customerHours = 0;
                $customerBillableHours = 0;
                $customerActualHours = 0;
                $customerActualBillableHours = 0;
                $customerAmount = 0;
                $projectCount = 0;
                $projectsData = [];
                
                foreach ($projects as $project) {
                    $entries = $this->timeEntryMapper->findByProject(
                        $project->getId(),
                        $this->dateTimeToTimestamp($startDate),
                        $this->dateTimeToTimestamp($endDate)
                    );
                    
                    $projectHours = 0;
                    $projectBillableHours = 0;
                    $projectActualHours = 0;
                    $projectActualBillableHours = 0;
                    
                    foreach ($entries as $entry) {
                        $hours = ($entry->getDurationMinutes() ?? 0) / 60;
                        $projectActualHours += $hours;
                        $customerActualHours += $hours;
                        
                        // Get the employment type for this user at the entry date
                        $entryDate = new DateTime('@' . $entry->getStartTimestamp());
                        $employmentType = $this->getEmploymentTypeForUser($entry->getUserId(), $entryDate);
                        $multiplier = $this->projectMultiplierMapper->getMultiplierValue($project->getId(), $employmentType);
                        
                        $projectHours += $hours * $multiplier;
                        $customerHours += $hours * $multiplier;
                        
                        if ($entry->getBillable()) {
                            $projectActualBillableHours += $hours;
                            $customerActualBillableHours += $hours;
                            $projectBillableHours += $hours * $multiplier;
                            $customerBillableHours += $hours * $multiplier;
                        }
                    }
                    
                    if (count($entries) > 0) {
                        $projectCount++;
                        $projectAmount = $projectBillableHours * ($project->getHourlyRate() ?? 0);
                        $customerAmount += $projectAmount;
                        
                        $projectsData[] = [
                            'project' => $project,
                            'hours' => round($projectHours, 2),
                            'billableHours' => round($projectBillableHours, 2),
                            'actualHours' => round($projectActualHours, 2),
                            'actualBillableHours' => round($projectActualBillableHours, 2),
                            'hourlyRate' => $project->getHourlyRate(),
                            'amount' => round($projectAmount, 2),
                        ];
                    }
                }
                
                if ($customerActualHours > 0) {
                    $report['customers'][] = [
                        'customer' => $customer,
                        'hours' => round($customerHours, 2),
                        'billableHours' => round($customerBillableHours, 2),
                        'actualHours' => round($customerActualHours, 2),
                        'actualBillableHours' => round($customerActualBillableHours, 2),
                        'amount' => round($customerAmount, 2),
                        'projectCount' => $projectCount,
                        'projects' => $projectsData,
                    ];
                    
                    $report['totals']['hours'] += $customerHours;
                    $report['totals']['billableHours'] += $customerBillableHours;
                    $report['totals']['actualHours'] += $customerActualHours;
                    $report['totals']['actualBillableHours'] += $customerActualBillableHours;
                    $report['totals']['amount'] += $customerAmount;
                }
            }
            
            $report['totals']['hours'] = round($report['totals']['hours'], 2);
            $report['totals']['billableHours'] = round($report['totals']['billableHours'], 2);
            $report['totals']['actualHours'] = round($report['totals']['actualHours'], 2);
            $report['totals']['actualBillableHours'] = round($report['totals']['actualBillableHours'], 2);
            $report['totals']['amount'] = round($report['totals']['amount'], 2);
            
            // Sort by amount descending
            usort($report['customers'], function ($a, $b) {
                return $b['amount'] <=> $a['amount'];
            });
            
            return new DataResponse($report);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Backwards compatibility for old route - Admin only
     */
    public function customerMonthly(int $customerId, int $year, int $month): DataResponse {
        return $this->customerReport($customerId, 'month', $year, $month);
    }

    /**
     * Project report - Admin only
     * Uses employee category multipliers from project settings for hours calculation.
     */
    public function projectReport(int $projectId, string $periodType, int $year, ?int $month = null, ?int $quarter = null): DataResponse {
        if (!$this->isAdmin()) {
            return new DataResponse(['error' => 'Forbidden'], 403);
        }
        try {
            $project = $this->projectMapper->find($projectId);
            $customer = $this->customerMapper->find($project->getCustomerId());
            
            // Special handling for project period
            if ($periodType === 'project_period') {
                $startDate = $project->getStartDate() ? new DateTime($project->getStartDate()) : null;
                $endDate = $project->getEndDate() ? new DateTime($project->getEndDate()) : null;
            } else {
                [$startDate, $endDate] = $this->getDateRange($periodType, $year, $month, $quarter);
            }
            
            $entries = $this->timeEntryMapper->findByProject(
                $projectId,
                $this->dateTimeToTimestamp($startDate),
                $this->dateTimeToTimestamp($endDate)
            );
            
            $periodLabel = $periodType === 'project_period' 
                ? $this->l10n->t('Projektzeitraum') . ' (' . ($project->getStartDate() ?? '?') . ' - ' . ($project->getEndDate() ?? '?') . ')'
                : $this->getPeriodLabel($periodType, $year, $month, $quarter);
            
            $report = [
                'project' => $project,
                'customer' => $customer,
                'period' => [
                    'type' => $periodType,
                    'label' => $periodLabel,
                    'year' => $year,
                    'month' => $month,
                    'quarter' => $quarter,
                    'startDate' => $startDate?->format('Y-m-d'),
                    'endDate' => $endDate?->format('Y-m-d'),
                ],
                'entries' => [],
                'totals' => [
                    'hours' => 0,
                    'billableHours' => 0,
                    'actualHours' => 0,
                    'actualBillableHours' => 0,
                    'amount' => 0,
                ],
            ];
            
            $userSummary = [];
            $actualTotalHours = 0;
            $actualTotalBillableHours = 0;
            
            foreach ($entries as $entry) {
                $hours = ($entry->getDurationMinutes() ?? 0) / 60;
                $userId = $entry->getUserId();
                
                // Get the employment type for this user at the entry date
                $entryDate = new DateTime('@' . $entry->getStartTimestamp());
                $employmentType = $this->getEmploymentTypeForUser($userId, $entryDate);
                $multiplier = $this->projectMultiplierMapper->getMultiplierValue($project->getId(), $employmentType);
                
                $adjustedHours = $hours * $multiplier;
                
                if (!isset($userSummary[$userId])) {
                    $userSummary[$userId] = [
                        'userId' => $userId,
                        'displayName' => $this->getDisplayName($userId),
                        'hours' => 0,
                        'billableHours' => 0,
                        'actualHours' => 0,
                        'actualBillableHours' => 0,
                        'entryCount' => 0,
                    ];
                }
                
                $userSummary[$userId]['hours'] += $adjustedHours;
                $userSummary[$userId]['actualHours'] += $hours;
                $userSummary[$userId]['entryCount']++;
                
                if ($entry->getBillable()) {
                    $userSummary[$userId]['billableHours'] += $adjustedHours;
                    $userSummary[$userId]['actualBillableHours'] += $hours;
                }
                
                $report['totals']['hours'] += $adjustedHours;
                $actualTotalHours += $hours;
                if ($entry->getBillable()) {
                    $report['totals']['billableHours'] += $adjustedHours;
                    $actualTotalBillableHours += $hours;
                }
            }
            
            $report['totals']['actualHours'] = round($actualTotalHours, 2);
            $report['totals']['actualBillableHours'] = round($actualTotalBillableHours, 2);
            $report['totals']['amount'] = $report['totals']['billableHours'] * ($project->getHourlyRate() ?? 0);
            
            // Add budget usage if available (using adjusted hours)
            if ($project->getBudgetHours()) {
                $report['budget'] = [
                    'budgetHours' => $project->getBudgetHours(),
                    'usedHours' => round($report['totals']['hours'], 2),
                    'actualUsedHours' => round($actualTotalHours, 2),
                    'remainingHours' => round($project->getBudgetHours() - $report['totals']['hours'], 2),
                    'usagePercent' => round(($report['totals']['hours'] / $project->getBudgetHours()) * 100, 1),
                ];
            }
            
            foreach ($userSummary as &$summary) {
                $summary['hours'] = round($summary['hours'], 2);
                $summary['billableHours'] = round($summary['billableHours'], 2);
                $summary['actualHours'] = round($summary['actualHours'], 2);
                $summary['actualBillableHours'] = round($summary['actualBillableHours'], 2);
            }
            
            // Build comment summary: group entries by description, sum hours
            $commentSummary = [];
            foreach ($entries as $entry) {
                $description = trim($entry->getDescription() ?? '');
                if ($description === '') {
                    $description = $this->l10n->t('(Kein Kommentar)');
                }
                $hours = ($entry->getDurationMinutes() ?? 0) / 60;
                if (!isset($commentSummary[$description])) {
                    $commentSummary[$description] = [
                        'description' => $description,
                        'actualHours' => 0,
                        'billableHours' => 0,
                        'entryCount' => 0,
                    ];
                }
                $commentSummary[$description]['actualHours'] += $hours;
                if ($entry->getBillable()) {
                    $commentSummary[$description]['billableHours'] += $hours;
                }
                $commentSummary[$description]['entryCount']++;
            }
            foreach ($commentSummary as &$cs) {
                $cs['actualHours'] = round($cs['actualHours'], 2);
                $cs['billableHours'] = round($cs['billableHours'], 2);
            }
            // Sort by actual hours descending
            usort($commentSummary, function ($a, $b) {
                return $b['actualHours'] <=> $a['actualHours'];
            });
            $report['commentSummary'] = array_values($commentSummary);
            
            $report['userSummary'] = array_values($userSummary);
            $report['totals']['hours'] = round($report['totals']['hours'], 2);
            $report['totals']['billableHours'] = round($report['totals']['billableHours'], 2);
            $report['totals']['amount'] = round($report['totals']['amount'], 2);
            
            return new DataResponse($report);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * Backwards compatibility for old route - Admin only
     */
    public function projectMonthly(int $projectId, int $year, int $month): DataResponse {
        return $this->projectReport($projectId, 'month', $year, $month);
    }

    /**
     * Employee report - Admin only
     */
    public function employeeReport(string $userId, string $periodType, int $year, ?int $month = null, ?int $quarter = null, ?string $startDate = null, ?string $endDate = null): DataResponse {
        if (!$this->isAdmin()) {
            return new DataResponse(['error' => 'Forbidden'], 403);
        }
        [$rangeStart, $rangeEnd] = $this->getDateRange($periodType, $year, $month, $quarter, $startDate, $endDate);
        
        $settings = $this->employeeSettingsMapper->findByUserId($userId);
        
        // For 'total' period type, resolve date range from employment settings (start of employment to today)
        $allSettingsPeriods = null;
        if ($periodType === 'total') {
            [$rangeStart, $rangeEnd, $allSettingsPeriods] = $this->resolveTotalDateRange($userId);
        }
        
        $entries = $this->timeEntryMapper->findByUser(
            $userId,
            $this->dateTimeToTimestamp($rangeStart),
            $this->dateTimeToTimestamp($rangeEnd)
        );
        
        // Build period label — for 'total', include the resolved date range
        $periodLabel = $this->getPeriodLabel($periodType, $year, $month, $quarter, $startDate, $endDate);
        if ($periodType === 'total' && $rangeStart && $rangeEnd) {
            $periodLabel = $this->l10n->t('Gesamt') . ' (' . $rangeStart->format('d.m.Y') . ' – ' . $rangeEnd->format('d.m.Y') . ')';
        }
        
        $report = [
            'userId' => $userId,
            'employeeSettings' => $settings,
            'period' => [
                'type' => $periodType,
                'label' => $periodLabel,
                'year' => $year,
                'month' => $month,
                'quarter' => $quarter,
                'startDate' => $rangeStart?->format('Y-m-d'),
                'endDate' => $rangeEnd?->format('Y-m-d'),
            ],
            'dailySummary' => [],
            'projectSummary' => [],
            'totals' => [
                'hours' => 0,
                'workDays' => 0,
            ],
        ];
        
        $dailyHours = [];
        $projectHours = [];
        
        foreach ($entries as $entry) {
            $hours = ($entry->getDurationMinutes() ?? 0) / 60;
            $startTs = $entry->getStartTimestamp();
            $date = $startTs ? date('Y-m-d', $startTs) : null;
            $projectId = $entry->getProjectId();
            
            if (!isset($dailyHours[$date])) {
                $dailyHours[$date] = 0;
            }
            $dailyHours[$date] += $hours;
            
            if (!isset($projectHours[$projectId])) {
                $projectHours[$projectId] = 0;
            }
            $projectHours[$projectId] += $hours;
            
            $report['totals']['hours'] += $hours;
        }
        
        foreach ($dailyHours as $date => $hours) {
            $report['dailySummary'][] = [
                'date' => $date,
                'hours' => round($hours, 2),
                'type' => 'work',
            ];
            if ($hours > 0) {
                $report['totals']['workDays']++;
            }
        }
        
        foreach ($projectHours as $projectId => $hours) {
            try {
                $project = $this->projectMapper->find($projectId);
                $report['projectSummary'][] = [
                    'project' => $project,
                    'hours' => round($hours, 2),
                ];
            } catch (\Exception $e) {
                // Project might be deleted
            }
        }
        
        // Add additional calculations based on employment type
        if ($settings) {
            // Calculate daily hours based on 5-day work week
            $dailyContractHours = $settings->getWeeklyHours() / 5;
            
            if ($settings->getEmploymentType() === 'freelance' && $settings->getMaxTotalHours()) {
                // For freelancers with hour contingent
                $allEntries = $this->timeEntryMapper->findByUser($userId, null, null); // null timestamps = no date filter
                $totalHoursAllTime = 0;
                foreach ($allEntries as $entry) {
                    $totalHoursAllTime += ($entry->getDurationMinutes() ?? 0) / 60;
                }
                $report['totals']['totalHoursAllTime'] = round($totalHoursAllTime, 2);
                $report['totals']['maxTotalHours'] = $settings->getMaxTotalHours();
                $report['totals']['remainingHours'] = round($settings->getMaxTotalHours() - $totalHoursAllTime, 2);
                $report['totals']['percentageUsed'] = round(($totalHoursAllTime / $settings->getMaxTotalHours()) * 100, 1);
            } else {
                // For contract employees
                $report['totals']['weeklyHours'] = $settings->getWeeklyHours();
                
                // Calculate expected hours based on period
                switch ($periodType) {
                    case 'month':
                        $report['totals']['expectedHours'] = round($settings->getWeeklyHours() * 4.33, 2);
                        break;
                    case 'quarter':
                        $report['totals']['expectedHours'] = round($settings->getWeeklyHours() * 4.33 * 3, 2);
                        break;
                    case 'year':
                        $report['totals']['expectedHours'] = round($settings->getWeeklyHours() * 52, 2);
                        break;
                    case 'total':
                        // Calculate expected hours across all employment settings periods
                        // (handles different weekly hours in different periods correctly)
                        $periods = $allSettingsPeriods ?? $this->employeeSettingsMapper->findAllByUserId($userId);
                        $report['totals']['expectedHours'] = $this->calculateExpectedHoursFromPeriods($periods, $rangeStart, $rangeEnd);
                        break;
                }
                
                // Calculate credited hours for vacations and public holidays
                if ($rangeStart && $rangeEnd) {
                    $creditedHours = $this->calculateCreditedHours($userId, $rangeStart, $rangeEnd, $dailyContractHours, $settings->getEmploymentType());
                    $report['totals']['creditedHours'] = $creditedHours;
                    
                    // Add credited days (vacation, sick, holiday) to dailySummary
                    $existingDates = array_column($report['dailySummary'], 'date');
                    foreach ($creditedHours['vacationDates'] ?? [] as $date) {
                        if (!in_array($date, $existingDates)) {
                            $report['dailySummary'][] = [
                                'date' => $date,
                                'hours' => $dailyContractHours,
                                'type' => 'vacation',
                            ];
                        } else {
                            // Mark existing work day also as vacation
                            foreach ($report['dailySummary'] as &$day) {
                                if ($day['date'] === $date) {
                                    $day['type'] = 'vacation';
                                    break;
                                }
                            }
                            unset($day);
                        }
                    }
                    foreach ($creditedHours['sickDates'] ?? [] as $date) {
                        if (!in_array($date, $existingDates)) {
                            $report['dailySummary'][] = [
                                'date' => $date,
                                'hours' => $dailyContractHours,
                                'type' => 'sick',
                            ];
                        } else {
                            foreach ($report['dailySummary'] as &$day) {
                                if ($day['date'] === $date) {
                                    $day['type'] = 'sick';
                                    break;
                                }
                            }
                            unset($day);
                        }
                    }
                    foreach ($creditedHours['holidayDates'] ?? [] as $date) {
                        if (!in_array($date, $existingDates)) {
                            $report['dailySummary'][] = [
                                'date' => $date,
                                'hours' => $dailyContractHours,
                                'type' => 'holiday',
                            ];
                        } else {
                            foreach ($report['dailySummary'] as &$day) {
                                if ($day['date'] === $date) {
                                    $day['type'] = 'holiday';
                                    break;
                                }
                            }
                            unset($day);
                        }
                    }
                    
                    // Sort dailySummary by date
                    usort($report['dailySummary'], function($a, $b) {
                        return strcmp($a['date'], $b['date']);
                    });
                    
                    // Calculate effective hours (worked + credited)
                    $workedHours = $report['totals']['hours'];
                    $totalCredited = $creditedHours['totalCreditedHours'];
                    $report['totals']['effectiveHours'] = round($workedHours + $totalCredited, 2);
                    
                    // Calculate balance (effective - expected)
                    if (isset($report['totals']['expectedHours'])) {
                        $report['totals']['balance'] = round(
                            $report['totals']['effectiveHours'] - $report['totals']['expectedHours'], 
                            2
                        );
                    }
                }
            }
            
            // Add hourly rate and revenue calculation if available
            if ($settings->getHourlyRate()) {
                $report['totals']['hourlyRate'] = $settings->getHourlyRate();
                $report['totals']['revenue'] = round($report['totals']['hours'] * $settings->getHourlyRate(), 2);
            }
        }
        
        $report['totals']['hours'] = round($report['totals']['hours'], 2);
        
        return new DataResponse($report);
    }

    /**
     * All employees report - Admin only
     */
    public function allEmployeesReport(string $periodType, int $year, ?int $month = null, ?int $quarter = null, ?string $startDate = null, ?string $endDate = null): DataResponse {
        if (!$this->isAdmin()) {
            return new DataResponse(['error' => 'Forbidden'], 403);
        }
        [$rangeStart, $rangeEnd] = $this->getDateRange($periodType, $year, $month, $quarter, $startDate, $endDate);
        
        $startTs = $this->dateTimeToTimestamp($rangeStart);
        $endTs = $this->dateTimeToTimestamp($rangeEnd);
        
        // Get all users
        $allUsers = $this->userManager->search('', 1000, 0);
        
        $report = [
            'period' => [
                'type' => $periodType,
                'label' => $this->getPeriodLabel($periodType, $year, $month, $quarter, $startDate, $endDate),
                'year' => $year,
                'month' => $month,
                'quarter' => $quarter,
                'startDate' => $rangeStart?->format('Y-m-d'),
                'endDate' => $rangeEnd?->format('Y-m-d'),
            ],
            'employees' => [],
            'totals' => [
                'hours' => 0,
                'workDays' => 0,
                'revenue' => 0,
            ],
        ];
        
        foreach ($allUsers as $user) {
            $userId = $user->getUID();
            $entries = $this->timeEntryMapper->findByUser($userId, $startTs, $endTs);
            
            if (empty($entries)) {
                continue; // Skip users with no entries in this period
            }
            
            $settings = $this->employeeSettingsMapper->findByUserId($userId);
            $hours = 0;
            $dailyHours = [];
            
            foreach ($entries as $entry) {
                $entryHours = ($entry->getDurationMinutes() ?? 0) / 60;
                $hours += $entryHours;
                
                $startTs2 = $entry->getStartTimestamp();
                $date = $startTs2 ? date('Y-m-d', $startTs2) : null;
                if ($date && !isset($dailyHours[$date])) {
                    $dailyHours[$date] = true;
                }
            }
            
            $employeeData = [
                'userId' => $userId,
                'displayName' => $this->getDisplayName($userId),
                'hours' => round($hours, 2),
                'workDays' => count($dailyHours),
                'entryCount' => count($entries),
            ];
            
            // Add hourly rate and revenue if available
            if ($settings && $settings->getHourlyRate()) {
                $employeeData['hourlyRate'] = $settings->getHourlyRate();
                $employeeData['revenue'] = round($hours * $settings->getHourlyRate(), 2);
                $report['totals']['revenue'] += $employeeData['revenue'];
            }
            
            // Add employment type info and credited hours
            if ($settings) {
                $employeeData['employmentType'] = $settings->getEmploymentType();
                $employeeData['weeklyHours'] = $settings->getWeeklyHours();
                
                // Resolve date range for 'total' period type per employee
                $empRangeStart = $rangeStart;
                $empRangeEnd = $rangeEnd;
                $empAllPeriods = null;
                if ($periodType === 'total') {
                    [$empRangeStart, $empRangeEnd, $empAllPeriods] = $this->resolveTotalDateRange($userId);
                }
                
                // Calculate credited hours for non-freelance employees
                if ($settings->getEmploymentType() !== 'freelance' && $empRangeStart && $empRangeEnd) {
                    $dailyContractHours = $settings->getWeeklyHours() / 5;
                    $creditedHours = $this->calculateCreditedHours($userId, $empRangeStart, $empRangeEnd, $dailyContractHours, $settings->getEmploymentType());
                    $employeeData['creditedHours'] = $creditedHours;
                    $employeeData['effectiveHours'] = round($hours + $creditedHours['totalCreditedHours'], 2);
                    
                    // For 'total' period, also calculate expected hours and balance per employee
                    if ($periodType === 'total') {
                        $periods = $empAllPeriods ?? [$settings];
                        $expectedHours = $this->calculateExpectedHoursFromPeriods($periods, $empRangeStart, $empRangeEnd);
                        $employeeData['expectedHours'] = $expectedHours;
                        $employeeData['balance'] = round($employeeData['effectiveHours'] - $expectedHours, 2);
                    }
                }
            }
            
            $report['employees'][] = $employeeData;
            $report['totals']['hours'] += $hours;
            $report['totals']['workDays'] += count($dailyHours);
        }
        
        $report['totals']['hours'] = round($report['totals']['hours'], 2);
        $report['totals']['revenue'] = round($report['totals']['revenue'], 2);
        
        // Sort by hours descending
        usort($report['employees'], function ($a, $b) {
            return $b['hours'] <=> $a['hours'];
        });
        
        return new DataResponse($report);
    }

    /**
     * Backwards compatibility for old route - Admin only
     */
    public function employeeMonthly(string $userId, int $year, int $month): DataResponse {
        return $this->employeeReport($userId, 'month', $year, $month);
    }

    /**
     * All employees compliance report - Admin only
     * Checks compliance for all employees and returns a summary
     */
    public function allComplianceReport(string $periodType, int $year, ?int $month = null): DataResponse {
        if (!$this->isAdmin()) {
            return new DataResponse(['error' => 'Forbidden'], 403);
        }

        if ($periodType === 'month') {
            $startDate = new DateTime("$year-$month-01");
            $endDate = clone $startDate;
            $endDate->modify('last day of this month');
            $periodLabel = $this->getPeriodLabel('month', $year, $month);
        } else {
            $startDate = new DateTime("$year-01-01");
            $endDate = new DateTime("$year-12-31");
            $periodLabel = (string)$year;
        }

        // Get all users
        $allUsers = $this->userManager->search('', 1000, 0);
        
        $employees = [];
        $totalViolations = 0;
        $totalWarnings = 0;
        $allCompliant = true;

        foreach ($allUsers as $user) {
            $userId = $user->getUID();
            
            // Check if user has any time entries in this period
            $startTs = $startDate->getTimestamp();
            $endTs = $endDate->getTimestamp();
            $entries = $this->timeEntryMapper->findByUser($userId, $startTs, $endTs);
            
            if (empty($entries)) {
                continue; // Skip users with no entries in this period
            }

            $employeeResult = [
                'userId' => $userId,
                'displayName' => $this->getDisplayName($userId),
            ];

            // Check if user is exempt (directors and freelancers are not subject to ArbZG)
            try {
                $settings = $this->employeeSettingsMapper->findByUserId($userId);
                $employmentType = $settings ? $settings->getEmploymentType() : null;
                
                if ($employmentType === 'director') {
                    $employeeResult['exempt'] = true;
                    $employeeResult['exemptReason'] = $this->l10n->t('Geschäftsführer') . ' (§18 Abs. 1 Nr. 1 ArbZG)';
                    $employeeResult['compliant'] = true;
                    $employeeResult['violationCount'] = 0;
                    $employeeResult['warningCount'] = 0;
                    $employeeResult['violations'] = [];
                    $employeeResult['warnings'] = [];
                    $employeeResult['statistics'] = [
                        'totalHours' => 0,
                        'averageDailyHours' => 0,
                        'maxDailyHours' => 0,
                    ];
                    $employees[] = $employeeResult;
                    continue;
                }
                
                if ($employmentType === 'freelance') {
                    $employeeResult['exempt'] = true;
                    $employeeResult['exemptReason'] = $this->l10n->t('Freiberufler') . ' - ' . $this->l10n->t('kein Arbeitnehmer');
                    $employeeResult['compliant'] = true;
                    $employeeResult['violationCount'] = 0;
                    $employeeResult['warningCount'] = 0;
                    $employeeResult['violations'] = [];
                    $employeeResult['warnings'] = [];
                    $employeeResult['statistics'] = [
                        'totalHours' => 0,
                        'averageDailyHours' => 0,
                        'maxDailyHours' => 0,
                    ];
                    $employees[] = $employeeResult;
                    continue;
                }
            } catch (\Exception $e) {
                // Settings not found, continue with normal compliance check
            }

            // Run compliance check for this employee
            $result = $this->complianceService->checkCompliance($userId, $startDate, $endDate);
            
            $employeeResult['exempt'] = false;
            $employeeResult['compliant'] = $result['compliant'];
            $employeeResult['violationCount'] = $result['violationCount'];
            $employeeResult['warningCount'] = $result['warningCount'];
            $employeeResult['violations'] = $result['violations'];
            $employeeResult['warnings'] = $result['warnings'];
            $employeeResult['statistics'] = $result['statistics'];

            if (!$result['compliant']) {
                $allCompliant = false;
            }
            $totalViolations += $result['violationCount'];
            $totalWarnings += $result['warningCount'];

            $employees[] = $employeeResult;
        }

        // Sort: non-compliant first, then by violation count
        usort($employees, function ($a, $b) {
            if ($a['exempt'] !== $b['exempt']) {
                return $a['exempt'] ? 1 : -1; // Non-exempt first
            }
            if ($a['compliant'] !== $b['compliant']) {
                return $a['compliant'] ? 1 : -1; // Non-compliant first
            }
            return $b['violationCount'] <=> $a['violationCount'];
        });

        return new DataResponse([
            'period' => [
                'type' => $periodType,
                'label' => $periodLabel,
                'year' => $year,
                'month' => $month,
            ],
            'employees' => $employees,
            'summary' => [
                'totalEmployees' => count($employees),
                'allCompliant' => $allCompliant,
                'totalViolations' => $totalViolations,
                'totalWarnings' => $totalWarnings,
                'exemptCount' => count(array_filter($employees, fn($e) => $e['exempt'])),
                'compliantCount' => count(array_filter($employees, fn($e) => !$e['exempt'] && $e['compliant'])),
                'nonCompliantCount' => count(array_filter($employees, fn($e) => !$e['exempt'] && !$e['compliant'])),
            ],
        ]);
    }

    /**
     * Monthly overview of all customers with projects and employees - Admin only
     */
    public function monthlyOverview(int $year, int $month): DataResponse {
        if (!$this->isAdmin()) {
            return new DataResponse(['error' => 'Forbidden'], 403);
        }
        $startDate = new DateTime("$year-$month-01");
        $endDate = clone $startDate;
        $endDate->modify('last day of this month');
        
        $customers = $this->customerMapper->findAll();
        $allProjects = $this->projectMapper->findAll();
        
        $overview = [
            'period' => [
                'year' => $year,
                'month' => $month,
                'startDate' => $startDate->format('Y-m-d'),
                'endDate' => $endDate->format('Y-m-d'),
            ],
            'customers' => [],
            'totals' => [
                'hours' => 0,
            ],
        ];
        
        foreach ($customers as $customer) {
            $customerProjects = array_filter($allProjects, fn($p) => $p->getCustomerId() === $customer->getId());
            
            if (empty($customerProjects)) {
                continue;
            }
            
            $customerData = [
                'customer' => $customer,
                'projects' => [],
                'totals' => [
                    'hours' => 0,
                ],
            ];
            
            foreach ($customerProjects as $project) {
                $entries = $this->timeEntryMapper->findByProject(
                    $project->getId(),
                    $this->dateTimeToTimestamp($startDate),
                    $this->dateTimeToTimestamp($endDate)
                );
                
                if (empty($entries)) {
                    continue;
                }
                
                $projectData = [
                    'project' => $project,
                    'employees' => [],
                    'totals' => [
                        'hours' => 0,
                    ],
                ];
                
                $employeeHours = [];
                
                foreach ($entries as $entry) {
                    $hours = ($entry->getDurationMinutes() ?? 0) / 60;
                    $userId = $entry->getUserId();
                    
                    if (!isset($employeeHours[$userId])) {
                        $employeeHours[$userId] = 0;
                    }
                    $employeeHours[$userId] += $hours;
                    $projectData['totals']['hours'] += $hours;
                }
                
                foreach ($employeeHours as $userId => $hours) {
                    $projectData['employees'][] = [
                        'userId' => $userId,
                        'displayName' => $this->getDisplayName($userId),
                        'hours' => round($hours, 2),
                    ];
                }
                
                // Sort employees by hours descending
                usort($projectData['employees'], fn($a, $b) => $b['hours'] <=> $a['hours']);
                
                $projectData['totals']['hours'] = round($projectData['totals']['hours'], 2);
                $customerData['projects'][] = $projectData;
                $customerData['totals']['hours'] += $projectData['totals']['hours'];
            }
            
            if (!empty($customerData['projects'])) {
                // Sort projects by hours descending
                usort($customerData['projects'], fn($a, $b) => $b['totals']['hours'] <=> $a['totals']['hours']);
                
                $customerData['totals']['hours'] = round($customerData['totals']['hours'], 2);
                $overview['customers'][] = $customerData;
                $overview['totals']['hours'] += $customerData['totals']['hours'];
            }
        }
        
        // Sort customers by hours descending
        usort($overview['customers'], fn($a, $b) => $b['totals']['hours'] <=> $a['totals']['hours']);
        $overview['totals']['hours'] = round($overview['totals']['hours'], 2);
        
        return new DataResponse($overview);
    }

    /**
     * Overview of all customers with projects and employees - supports different period types
     */
    public function overview(string $periodType = 'month', ?int $year = null, ?int $month = null): DataResponse {
        if (!$this->isAdmin()) {
            return new DataResponse(['error' => 'Forbidden'], 403);
        }
        
        $now = new DateTime();
        
        switch ($periodType) {
            case 'month':
                $year = $year ?? (int)$now->format('Y');
                $month = $month ?? (int)$now->format('n');
                $startDate = new DateTime("$year-$month-01");
                $endDate = clone $startDate;
                $endDate->modify('last day of this month');
                $periodLabel = $this->getLocalizedMonthName($month) . ' ' . $year;
                break;
                
            case 'year':
                $year = $year ?? (int)$now->format('Y');
                $startDate = new DateTime("$year-01-01");
                $endDate = new DateTime("$year-12-31");
                $periodLabel = (string)$year;
                break;
                
            case 'total':
                // All time - use a very early start date
                $startDate = new DateTime('2000-01-01');
                $endDate = new DateTime('2099-12-31');
                $periodLabel = $this->l10n->t('Gesamt');
                $year = null;
                $month = null;
                break;
                
            default:
                return new DataResponse(['error' => 'Invalid period type'], 400);
        }
        
        $customers = $this->customerMapper->findAll();
        $allProjects = $this->projectMapper->findAll();
        
        $overview = [
            'period' => [
                'type' => $periodType,
                'year' => $year,
                'month' => $month,
                'label' => $periodLabel,
                'startDate' => $startDate->format('Y-m-d'),
                'endDate' => $endDate->format('Y-m-d'),
            ],
            'customers' => [],
            'totals' => [
                'hours' => 0,
            ],
        ];
        
        foreach ($customers as $customer) {
            $customerProjects = array_filter($allProjects, fn($p) => $p->getCustomerId() === $customer->getId());
            
            if (empty($customerProjects)) {
                continue;
            }
            
            $customerData = [
                'customer' => $customer,
                'projects' => [],
                'totals' => [
                    'hours' => 0,
                ],
            ];
            
            foreach ($customerProjects as $project) {
                $entries = $this->timeEntryMapper->findByProject(
                    $project->getId(),
                    $this->dateTimeToTimestamp($startDate),
                    $this->dateTimeToTimestamp($endDate)
                );
                
                if (empty($entries)) {
                    continue;
                }
                
                $projectData = [
                    'project' => $project,
                    'employees' => [],
                    'totals' => [
                        'hours' => 0,
                    ],
                ];
                
                $employeeHours = [];
                
                foreach ($entries as $entry) {
                    $hours = ($entry->getDurationMinutes() ?? 0) / 60;
                    $userId = $entry->getUserId();
                    
                    if (!isset($employeeHours[$userId])) {
                        $employeeHours[$userId] = 0;
                    }
                    $employeeHours[$userId] += $hours;
                    $projectData['totals']['hours'] += $hours;
                }
                
                foreach ($employeeHours as $userId => $hours) {
                    $projectData['employees'][] = [
                        'userId' => $userId,
                        'displayName' => $this->getDisplayName($userId),
                        'hours' => round($hours, 2),
                    ];
                }
                
                usort($projectData['employees'], fn($a, $b) => $b['hours'] <=> $a['hours']);
                
                $projectData['totals']['hours'] = round($projectData['totals']['hours'], 2);
                $customerData['projects'][] = $projectData;
                $customerData['totals']['hours'] += $projectData['totals']['hours'];
            }
            
            if (!empty($customerData['projects'])) {
                usort($customerData['projects'], fn($a, $b) => $b['totals']['hours'] <=> $a['totals']['hours']);
                
                $customerData['totals']['hours'] = round($customerData['totals']['hours'], 2);
                $overview['customers'][] = $customerData;
                $overview['totals']['hours'] += $customerData['totals']['hours'];
            }
        }
        
        usort($overview['customers'], fn($a, $b) => $b['totals']['hours'] <=> $a['totals']['hours']);
        $overview['totals']['hours'] = round($overview['totals']['hours'], 2);
        
        return new DataResponse($overview);
    }
}

