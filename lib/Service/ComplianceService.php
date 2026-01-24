<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Service;

use DateTime;
use DateTimeZone;
use OCA\TimeTracking\Db\TimeEntryMapper;
use OCA\TimeTracking\Db\PublicHolidayMapper;

/**
 * Service for checking compliance with German labor law (Arbeitszeitgesetz)
 */
class ComplianceService {
    private TimeEntryMapper $timeEntryMapper;
    private PublicHolidayMapper $publicHolidayMapper;
    
    // German labor law constants (§3 and §4 ArbZG)
    private const MAX_DAILY_HOURS = 10;
    private const STANDARD_DAILY_HOURS = 8;
    private const MAX_WEEKLY_HOURS = 48;
    private const MIN_REST_PERIOD_HOURS = 11;
    private const MAX_CONTINUOUS_WORK_HOURS = 6;
    
    // Break requirements (§4 ArbZG)
    private const BREAK_THRESHOLD_6H = 6;    // After 6 hours: 30 min break required
    private const BREAK_THRESHOLD_9H = 9;    // After 9 hours: 45 min break required
    private const MIN_BREAK_AFTER_6H = 30;   // Minutes
    private const MIN_BREAK_AFTER_9H = 45;   // Minutes
    
    public function __construct(TimeEntryMapper $timeEntryMapper, PublicHolidayMapper $publicHolidayMapper) {
        $this->timeEntryMapper = $timeEntryMapper;
        $this->publicHolidayMapper = $publicHolidayMapper;
    }
    
    public function checkCompliance(string $userId, DateTime $startDate, DateTime $endDate): array {
        $entries = $this->timeEntryMapper->findByUser(
            $userId,
            $startDate->getTimestamp(),
            $endDate->getTimestamp()
        );
        
        $violations = [];
        $warnings = [];
        $dailyHours = [];
        $dailyWorkPeriods = [];
        
        // Group entries by date (derived from start timestamp)
        foreach ($entries as $entry) {
            $startTs = $entry->getStartTimestamp();
            $endTs = $entry->getEndTimestamp();
            if (!$startTs || !$endTs) {
                continue;
            }
            $date = date('Y-m-d', $startTs);
            $hours = ($entry->getDurationMinutes() ?? 0) / 60;
            
            if (!isset($dailyHours[$date])) {
                $dailyHours[$date] = 0;
                $dailyWorkPeriods[$date] = [];
            }
            $dailyHours[$date] += $hours;
            $dailyWorkPeriods[$date][] = [
                'start' => $startTs,
                'end' => $endTs,
            ];
        }
        
        // Check daily hours and breaks
        foreach ($dailyHours as $date => $hours) {
            if ($hours > self::MAX_DAILY_HOURS) {
                $violations[] = [
                    'type' => 'DAILY_HOURS_EXCEEDED',
                    'date' => $date,
                    'hours' => round($hours, 2),
                    'limit' => self::MAX_DAILY_HOURS,
                    'severity' => 'high',
                    'message' => "Tägliche Arbeitszeit von " . round($hours, 2) . " Stunden überschreitet das Maximum von 10 Stunden (§3 ArbZG)",
                ];
            } elseif ($hours > self::STANDARD_DAILY_HOURS) {
                $warnings[] = [
                    'type' => 'DAILY_HOURS_EXTENDED',
                    'date' => $date,
                    'hours' => round($hours, 2),
                    'limit' => self::STANDARD_DAILY_HOURS,
                    'severity' => 'medium',
                    'message' => "Tägliche Arbeitszeit von " . round($hours, 2) . " Stunden überschreitet die Regelarbeitszeit von 8 Stunden. Ausgleich erforderlich (§3 ArbZG)",
                ];
            }
            
            // Check break requirements for each day (§4 ArbZG)
            $workPeriods = $dailyWorkPeriods[$date];
            if (count($workPeriods) > 0) {
                usort($workPeriods, fn($a, $b) => $a['start'] <=> $b['start']);
                
                $totalBreakMinutes = 0;
                for ($i = 1; $i < count($workPeriods); $i++) {
                    $breakMinutes = ($workPeriods[$i]['start'] - $workPeriods[$i - 1]['end']) / 60;
                    if ($breakMinutes > 0) {
                        $totalBreakMinutes += $breakMinutes;
                    }
                }
                
                $requiredBreak = 0;
                if ($hours > self::BREAK_THRESHOLD_9H) {
                    $requiredBreak = self::MIN_BREAK_AFTER_9H;
                } elseif ($hours > self::BREAK_THRESHOLD_6H) {
                    $requiredBreak = self::MIN_BREAK_AFTER_6H;
                }
                
                if ($requiredBreak > 0 && $totalBreakMinutes < $requiredBreak) {
                    $violations[] = [
                        'type' => 'INSUFFICIENT_BREAK',
                        'date' => $date,
                        'hours' => round($hours, 2),
                        'requiredBreak' => $requiredBreak,
                        'actualBreak' => round($totalBreakMinutes, 0),
                        'severity' => 'high',
                        'message' => "Am {$date}: Bei " . round($hours, 2) . " Stunden Arbeitszeit sind mindestens {$requiredBreak} Minuten Pause vorgeschrieben. Nur " . round($totalBreakMinutes, 0) . " Minuten Pause genommen. (§4 ArbZG)",
                    ];
                }
            }
            
            // Check for Sunday work
            $dateObj = new DateTime($date);
            if ($dateObj->format('N') == 7 && $hours > 0) {
                $warnings[] = [
                    'type' => 'SUNDAY_WORK',
                    'date' => $date,
                    'hours' => round($hours, 2),
                    'severity' => 'medium',
                    'message' => "Sonntagsarbeit am {$date}. Ersatzruhetag erforderlich (§9 ArbZG)",
                ];
            }
            
            // Check for public holiday work
            if ($this->publicHolidayMapper->isHoliday($dateObj) && $hours > 0) {
                $warnings[] = [
                    'type' => 'HOLIDAY_WORK',
                    'date' => $date,
                    'hours' => round($hours, 2),
                    'severity' => 'medium',
                    'message' => "Feiertagsarbeit am {$date}. Ersatzruhetag erforderlich (§9 ArbZG)",
                ];
            }
        }
        
        // Check rest periods between consecutive working days (§5 ArbZG)
        $sortedDates = array_keys($dailyHours);
        sort($sortedDates);
        
        for ($i = 1; $i < count($sortedDates); $i++) {
            $prevDate = $sortedDates[$i - 1];
            $currDate = $sortedDates[$i];
            
            // Check if days are consecutive
            $prevDateObj = new DateTime($prevDate);
            $currDateObj = new DateTime($currDate);
            $daysDiff = (int)$prevDateObj->diff($currDateObj)->days;
            
            if ($daysDiff === 1) {
                // Get end of last work period on previous day
                $prevWorkPeriods = $dailyWorkPeriods[$prevDate];
                usort($prevWorkPeriods, fn($a, $b) => $a['end'] <=> $b['end']);
                $lastEndTs = end($prevWorkPeriods)['end'];
                
                // Get start of first work period on current day
                $currWorkPeriods = $dailyWorkPeriods[$currDate];
                usort($currWorkPeriods, fn($a, $b) => $a['start'] <=> $b['start']);
                $firstStartTs = $currWorkPeriods[0]['start'];
                
                // Calculate rest period in hours
                $restHours = ($firstStartTs - $lastEndTs) / 3600;
                
                if ($restHours < self::MIN_REST_PERIOD_HOURS) {
                    $violations[] = [
                        'type' => 'INSUFFICIENT_REST',
                        'date' => $currDate,
                        'previousDate' => $prevDate,
                        'restHours' => round($restHours, 2),
                        'required' => self::MIN_REST_PERIOD_HOURS,
                        'severity' => 'high',
                        'message' => "Nur " . round($restHours, 1) . " Stunden Ruhezeit zwischen {$prevDate} und {$currDate}. Mindestens 11 Stunden erforderlich (§5 ArbZG)",
                    ];
                }
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
                    'message' => "Wöchentliche Arbeitszeit von " . round($weekHours, 2) . " Stunden überschreitet das Maximum von 48 Stunden (§3 ArbZG)",
                ];
            }
            
            $weekStart->modify('+7 days');
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
    
    /**
     * Check compliance for a specific day with break requirements.
     * Returns warnings and violations for the current working day.
     * 
     * @param string $userId User ID
     * @param DateTime $date The date to check
     * @return array Compliance check results with violations and warnings
     */
    public function checkDailyCompliance(string $userId, DateTime $date): array {
        $startOfDay = clone $date;
        $startOfDay->setTime(0, 0, 0);
        $endOfDay = clone $date;
        $endOfDay->setTime(23, 59, 59);
        
        $entries = $this->timeEntryMapper->findByUser(
            $userId,
            $startOfDay->getTimestamp(),
            $endOfDay->getTimestamp()
        );
        
        $violations = [];
        $warnings = [];
        
        // Calculate total work time and identify breaks
        $workPeriods = [];
        $totalMinutes = 0;
        
        foreach ($entries as $entry) {
            $startTs = $entry->getStartTimestamp();
            $endTs = $entry->getEndTimestamp();
            
            if (!$startTs) {
                continue;
            }
            
            // For running timers, use current time as end
            if (!$endTs) {
                $endTs = time();
            }
            
            $workPeriods[] = [
                'start' => $startTs,
                'end' => $endTs,
            ];
            
            $totalMinutes += ($endTs - $startTs) / 60;
        }
        
        $totalHours = $totalMinutes / 60;
        $dateStr = $date->format('Y-m-d');
        
        // Check daily hour limits
        if ($totalHours > self::MAX_DAILY_HOURS) {
            $violations[] = [
                'type' => 'DAILY_HOURS_EXCEEDED',
                'date' => $dateStr,
                'hours' => round($totalHours, 2),
                'limit' => self::MAX_DAILY_HOURS,
                'severity' => 'high',
                'message' => "Tägliche Arbeitszeit von " . round($totalHours, 2) . " Stunden überschreitet das Maximum von 10 Stunden (§3 ArbZG)",
            ];
        } elseif ($totalHours > self::STANDARD_DAILY_HOURS) {
            $warnings[] = [
                'type' => 'DAILY_HOURS_EXTENDED',
                'date' => $dateStr,
                'hours' => round($totalHours, 2),
                'limit' => self::STANDARD_DAILY_HOURS,
                'severity' => 'medium',
                'message' => "Tägliche Arbeitszeit von " . round($totalHours, 2) . " Stunden überschreitet die Regelarbeitszeit von 8 Stunden. Ausgleich erforderlich (§3 ArbZG)",
            ];
        }
        
        // Check break requirements (§4 ArbZG)
        if (count($workPeriods) > 0) {
            // Sort work periods by start time
            usort($workPeriods, fn($a, $b) => $a['start'] <=> $b['start']);
            
            // Calculate breaks between work periods
            $totalBreakMinutes = 0;
            for ($i = 1; $i < count($workPeriods); $i++) {
                $breakMinutes = ($workPeriods[$i]['start'] - $workPeriods[$i - 1]['end']) / 60;
                if ($breakMinutes > 0) {
                    $totalBreakMinutes += $breakMinutes;
                }
            }
            
            // Determine required break time
            $requiredBreak = 0;
            if ($totalHours > self::BREAK_THRESHOLD_9H) {
                $requiredBreak = self::MIN_BREAK_AFTER_9H;
            } elseif ($totalHours > self::BREAK_THRESHOLD_6H) {
                $requiredBreak = self::MIN_BREAK_AFTER_6H;
            }
            
            // Check if break requirement is met
            if ($requiredBreak > 0 && $totalBreakMinutes < $requiredBreak) {
                $missingBreak = $requiredBreak - $totalBreakMinutes;
                $violations[] = [
                    'type' => 'INSUFFICIENT_BREAK',
                    'date' => $dateStr,
                    'hours' => round($totalHours, 2),
                    'requiredBreak' => $requiredBreak,
                    'actualBreak' => round($totalBreakMinutes, 0),
                    'severity' => 'high',
                    'message' => "Bei " . round($totalHours, 2) . " Stunden Arbeitszeit sind mindestens {$requiredBreak} Minuten Pause vorgeschrieben. Bisher nur " . round($totalBreakMinutes, 0) . " Minuten Pause. (§4 ArbZG)",
                ];
            }
            
            // Warn if approaching break threshold without a break
            if ($totalHours > 5.5 && $totalHours <= self::BREAK_THRESHOLD_6H && $totalBreakMinutes < 15) {
                $warnings[] = [
                    'type' => 'BREAK_SOON_REQUIRED',
                    'date' => $dateStr,
                    'hours' => round($totalHours, 2),
                    'severity' => 'low',
                    'message' => "Arbeitszeit nähert sich 6 Stunden. Ab 6 Stunden sind 30 Minuten Pause Pflicht (§4 ArbZG)",
                ];
            }
        }
        
        // Check for Sunday work
        if ($date->format('N') == 7 && $totalHours > 0) {
            $warnings[] = [
                'type' => 'SUNDAY_WORK',
                'date' => $dateStr,
                'hours' => round($totalHours, 2),
                'severity' => 'medium',
                'message' => "Sonntagsarbeit am {$dateStr}. Ersatzruhetag erforderlich (§9 ArbZG)",
            ];
        }
        
        // Check for public holiday work
        if ($this->publicHolidayMapper->isHoliday($date) && $totalHours > 0) {
            $warnings[] = [
                'type' => 'HOLIDAY_WORK',
                'date' => $dateStr,
                'hours' => round($totalHours, 2),
                'severity' => 'medium',
                'message' => "Feiertagsarbeit am {$dateStr}. Ersatzruhetag erforderlich (§9 ArbZG)",
            ];
        }
        
        // Check rest period from previous day (§5 ArbZG)
        $previousDay = clone $date;
        $previousDay->modify('-1 day');
        $prevStartOfDay = clone $previousDay;
        $prevStartOfDay->setTime(0, 0, 0);
        $prevEndOfDay = clone $previousDay;
        $prevEndOfDay->setTime(23, 59, 59);
        
        $prevEntries = $this->timeEntryMapper->findByUser(
            $userId,
            $prevStartOfDay->getTimestamp(),
            $prevEndOfDay->getTimestamp()
        );
        
        if (count($prevEntries) > 0 && count($workPeriods) > 0) {
            // Find the last end time of previous day
            $lastEndTs = 0;
            foreach ($prevEntries as $entry) {
                $endTs = $entry->getEndTimestamp();
                if ($endTs && $endTs > $lastEndTs) {
                    $lastEndTs = $endTs;
                }
            }
            
            if ($lastEndTs > 0) {
                // Find first start time of current day
                usort($workPeriods, fn($a, $b) => $a['start'] <=> $b['start']);
                $firstStartTs = $workPeriods[0]['start'];
                
                $restHours = ($firstStartTs - $lastEndTs) / 3600;
                
                if ($restHours < self::MIN_REST_PERIOD_HOURS) {
                    $violations[] = [
                        'type' => 'INSUFFICIENT_REST',
                        'date' => $dateStr,
                        'previousDate' => $previousDay->format('Y-m-d'),
                        'restHours' => round($restHours, 2),
                        'required' => self::MIN_REST_PERIOD_HOURS,
                        'severity' => 'high',
                        'message' => "Nur " . round($restHours, 1) . " Stunden Ruhezeit seit gestern. Mindestens 11 Stunden erforderlich (§5 ArbZG)",
                    ];
                }
            }
        }
        
        return [
            'compliant' => count($violations) === 0,
            'violationCount' => count($violations),
            'warningCount' => count($warnings),
            'violations' => $violations,
            'warnings' => $warnings,
            'date' => $dateStr,
            'statistics' => [
                'totalHours' => round($totalHours, 2),
                'totalBreakMinutes' => round($totalBreakMinutes ?? 0, 0),
                'workPeriods' => count($workPeriods),
            ],
        ];
    }
}

