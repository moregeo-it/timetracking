<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Service;

use DateTime;
use OCA\TimeTracking\Db\TimeEntryMapper;

/**
 * Service for checking compliance with German labor law (Arbeitszeitgesetz)
 */
class ComplianceService {
    private TimeEntryMapper $timeEntryMapper;
    
    // German labor law constants
    private const MAX_DAILY_HOURS = 10;
    private const STANDARD_DAILY_HOURS = 8;
    private const MAX_WEEKLY_HOURS = 48;
    private const MIN_REST_PERIOD_HOURS = 11;
    private const MAX_CONTINUOUS_WORK_HOURS = 6;
    
    public function __construct(TimeEntryMapper $timeEntryMapper) {
        $this->timeEntryMapper = $timeEntryMapper;
    }
    
    public function checkCompliance(string $userId, DateTime $startDate, DateTime $endDate): array {
        $entries = $this->timeEntryMapper->findByUser($userId, $startDate, $endDate);
        
        $violations = [];
        $warnings = [];
        $dailyHours = [];
        $weeklyHours = [];
        
        // Group entries by date
        foreach ($entries as $entry) {
            $date = $entry->getDate()->format('Y-m-d');
            $hours = ($entry->getDurationMinutes() ?? 0) / 60;
            
            if (!isset($dailyHours[$date])) {
                $dailyHours[$date] = 0;
            }
            $dailyHours[$date] += $hours;
        }
        
        // Check daily hours
        foreach ($dailyHours as $date => $hours) {
            if ($hours > self::MAX_DAILY_HOURS) {
                $violations[] = [
                    'type' => 'DAILY_HOURS_EXCEEDED',
                    'date' => $date,
                    'hours' => round($hours, 2),
                    'limit' => self::MAX_DAILY_HOURS,
                    'severity' => 'high',
                    'message' => "Tägliche Arbeitszeit von {$hours} Stunden überschreitet das Maximum von 10 Stunden (§3 ArbZG)",
                ];
            } elseif ($hours > self::STANDARD_DAILY_HOURS) {
                $warnings[] = [
                    'type' => 'DAILY_HOURS_EXTENDED',
                    'date' => $date,
                    'hours' => round($hours, 2),
                    'limit' => self::STANDARD_DAILY_HOURS,
                    'severity' => 'medium',
                    'message' => "Tägliche Arbeitszeit von {$hours} Stunden überschreitet die Regelarbeitszeit von 8 Stunden. Ausgleich erforderlich (§3 ArbZG)",
                ];
            }
        }
        
        // Calculate weekly hours
        $weekStart = clone $startDate;
        while ($weekStart <= $endDate) {
            $weekEnd = clone $weekStart;
            $weekEnd->modify('+6 days');
            
            $weekHours = 0;
            for ($i = 0; $i < 7; $i++) {
                $checkDate = clone $weekStart;
                $checkDate->modify("+$i days");
                $dateStr = $checkDate->format('Y-m-d');
                
                if (isset($dailyHours[$dateStr])) {
                    $weekHours += $dailyHours[$dateStr];
                }
            }
            
            if ($weekHours > self::MAX_WEEKLY_HOURS) {
                $violations[] = [
                    'type' => 'WEEKLY_HOURS_EXCEEDED',
                    'weekStart' => $weekStart->format('Y-m-d'),
                    'weekEnd' => $weekEnd->format('Y-m-d'),
                    'hours' => round($weekHours, 2),
                    'limit' => self::MAX_WEEKLY_HOURS,
                    'severity' => 'high',
                    'message' => "Wöchentliche Arbeitszeit von {$weekHours} Stunden überschreitet das Maximum von 48 Stunden (§3 ArbZG)",
                ];
            }
            
            $weekStart->modify('+7 days');
        }
        
        // Check for Sunday work
        foreach ($dailyHours as $date => $hours) {
            $dateObj = new DateTime($date);
            if ($dateObj->format('N') == 7 && $hours > 0) { // 7 = Sunday
                $warnings[] = [
                    'type' => 'SUNDAY_WORK',
                    'date' => $date,
                    'hours' => round($hours, 2),
                    'severity' => 'medium',
                    'message' => "Sonntagsarbeit am {$date}. Ersatzruhetag erforderlich (§9 ArbZG)",
                ];
            }
        }
        
        $summary = [
            'compliant' => count($violations) === 0,
            'violationCount' => count($violations),
            'warningCount' => count($warnings),
            'violations' => $violations,
            'warnings' => $warnings,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'statistics' => [
                'totalDays' => count($dailyHours),
                'averageDailyHours' => count($dailyHours) > 0 ? round(array_sum($dailyHours) / count($dailyHours), 2) : 0,
                'maxDailyHours' => count($dailyHours) > 0 ? round(max($dailyHours), 2) : 0,
                'totalHours' => round(array_sum($dailyHours), 2),
            ],
        ];
        
        return $summary;
    }
}

