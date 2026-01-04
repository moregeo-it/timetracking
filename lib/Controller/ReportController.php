<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Controller;

use DateTime;
use OCA\TimeTracking\Db\TimeEntryMapper;
use OCA\TimeTracking\Db\ProjectMapper;
use OCA\TimeTracking\Db\CustomerMapper;
use OCA\TimeTracking\Db\EmployeeSettingsMapper;
use OCA\TimeTracking\Service\ComplianceService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class ReportController extends Controller {
    private TimeEntryMapper $timeEntryMapper;
    private ProjectMapper $projectMapper;
    private CustomerMapper $customerMapper;
    private EmployeeSettingsMapper $employeeSettingsMapper;
    private ComplianceService $complianceService;

    public function __construct(
        string $appName,
        IRequest $request,
        TimeEntryMapper $timeEntryMapper,
        ProjectMapper $projectMapper,
        CustomerMapper $customerMapper,
        EmployeeSettingsMapper $employeeSettingsMapper,
        ComplianceService $complianceService
    ) {
        parent::__construct($appName, $request);
        $this->timeEntryMapper = $timeEntryMapper;
        $this->projectMapper = $projectMapper;
        $this->customerMapper = $customerMapper;
        $this->employeeSettingsMapper = $employeeSettingsMapper;
        $this->complianceService = $complianceService;
    }

    /**
     * @NoAdminRequired
     */
    public function customerMonthly(int $customerId, int $year, int $month): DataResponse {
        try {
            $customer = $this->customerMapper->find($customerId);
            $projects = $this->projectMapper->findByCustomer($customerId);
            
            $startDate = new DateTime("$year-$month-01");
            $endDate = clone $startDate;
            $endDate->modify('last day of this month');
            
            $report = [
                'customer' => $customer,
                'period' => [
                    'year' => $year,
                    'month' => $month,
                    'startDate' => $startDate->format('Y-m-d'),
                    'endDate' => $endDate->format('Y-m-d'),
                ],
                'projects' => [],
                'totals' => [
                    'hours' => 0,
                    'billableHours' => 0,
                    'amount' => 0,
                ],
            ];
            
            foreach ($projects as $project) {
                $entries = $this->timeEntryMapper->findByProject($project->getId(), $startDate, $endDate);
                
                $projectHours = 0;
                $billableHours = 0;
                
                foreach ($entries as $entry) {
                    $hours = ($entry->getDurationMinutes() ?? 0) / 60;
                    $projectHours += $hours;
                    if ($entry->getBillable()) {
                        $billableHours += $hours;
                    }
                }
                
                $amount = $billableHours * ($project->getHourlyRate() ?? 0);
                
                $report['projects'][] = [
                    'project' => $project,
                    'hours' => round($projectHours, 2),
                    'billableHours' => round($billableHours, 2),
                    'hourlyRate' => $project->getHourlyRate(),
                    'amount' => round($amount, 2),
                    'entryCount' => count($entries),
                ];
                
                $report['totals']['hours'] += $projectHours;
                $report['totals']['billableHours'] += $billableHours;
                $report['totals']['amount'] += $amount;
            }
            
            $report['totals']['hours'] = round($report['totals']['hours'], 2);
            $report['totals']['billableHours'] = round($report['totals']['billableHours'], 2);
            $report['totals']['amount'] = round($report['totals']['amount'], 2);
            
            return new DataResponse($report);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function projectMonthly(int $projectId, int $year, int $month): DataResponse {
        try {
            $project = $this->projectMapper->find($projectId);
            $customer = $this->customerMapper->find($project->getCustomerId());
            
            $startDate = new DateTime("$year-$month-01");
            $endDate = clone $startDate;
            $endDate->modify('last day of this month');
            
            $entries = $this->timeEntryMapper->findByProject($projectId, $startDate, $endDate);
            
            $report = [
                'project' => $project,
                'customer' => $customer,
                'period' => [
                    'year' => $year,
                    'month' => $month,
                    'startDate' => $startDate->format('Y-m-d'),
                    'endDate' => $endDate->format('Y-m-d'),
                ],
                'entries' => [],
                'totals' => [
                    'hours' => 0,
                    'billableHours' => 0,
                    'amount' => 0,
                ],
            ];
            
            $userSummary = [];
            
            foreach ($entries as $entry) {
                $hours = ($entry->getDurationMinutes() ?? 0) / 60;
                $userId = $entry->getUserId();
                
                if (!isset($userSummary[$userId])) {
                    $userSummary[$userId] = [
                        'userId' => $userId,
                        'hours' => 0,
                        'billableHours' => 0,
                        'entryCount' => 0,
                    ];
                }
                
                $userSummary[$userId]['hours'] += $hours;
                $userSummary[$userId]['entryCount']++;
                
                if ($entry->getBillable()) {
                    $userSummary[$userId]['billableHours'] += $hours;
                }
                
                $report['totals']['hours'] += $hours;
                if ($entry->getBillable()) {
                    $report['totals']['billableHours'] += $hours;
                }
            }
            
            $report['totals']['amount'] = $report['totals']['billableHours'] * ($project->getHourlyRate() ?? 0);
            
            foreach ($userSummary as &$summary) {
                $summary['hours'] = round($summary['hours'], 2);
                $summary['billableHours'] = round($summary['billableHours'], 2);
            }
            
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
     * @NoAdminRequired
     */
    public function employeeMonthly(string $userId, int $year, int $month): DataResponse {
        $startDate = new DateTime("$year-$month-01");
        $endDate = clone $startDate;
        $endDate->modify('last day of this month');
        
        $settings = $this->employeeSettingsMapper->findByUserId($userId);
        $entries = $this->timeEntryMapper->findByUser($userId, $startDate, $endDate);
        
        $report = [
            'userId' => $userId,
            'employeeSettings' => $settings,
            'period' => [
                'year' => $year,
                'month' => $month,
                'startDate' => $startDate->format('Y-m-d'),
                'endDate' => $endDate->format('Y-m-d'),
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
            $date = $entry->getDate()->format('Y-m-d');
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
            if ($settings->getEmploymentType() === 'freelance' && $settings->getMaxTotalHours()) {
                // For freelancers with hour contingent
                $allEntries = $this->timeEntryMapper->findByUser($userId, null, null);
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
                $report['totals']['expectedMonthlyHours'] = round($settings->getWeeklyHours() * 4.33, 2); // Average weeks per month
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
     * @NoAdminRequired
     */
    public function complianceCheck(string $userId, int $year, int $month): DataResponse {
        $startDate = new DateTime("$year-$month-01");
        $endDate = clone $startDate;
        $endDate->modify('last day of this month');
        
        return new DataResponse(
            $this->complianceService->checkCompliance($userId, $startDate, $endDate)
        );
    }
}

