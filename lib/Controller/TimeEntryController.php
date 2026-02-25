<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Controller;

use DateTime;
use DateTimeZone;
use OCA\TimeTracking\Db\TimeEntry;
use OCA\TimeTracking\Db\TimeEntryMapper;
use OCA\TimeTracking\Db\ProjectMapper;
use OCA\TimeTracking\Db\EmployeeSettingsMapper;
use OCA\TimeTracking\Db\PublicHolidayMapper;
use OCA\TimeTracking\Service\ComplianceService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class TimeEntryController extends Controller {
    private TimeEntryMapper $mapper;
    private ProjectMapper $projectMapper;
    private EmployeeSettingsMapper $employeeSettingsMapper;
    private PublicHolidayMapper $publicHolidayMapper;
    private ComplianceService $complianceService;
    private string $userId;

    public function __construct(
        string $appName,
        IRequest $request,
        TimeEntryMapper $mapper,
        ProjectMapper $projectMapper,
        EmployeeSettingsMapper $employeeSettingsMapper,
        PublicHolidayMapper $publicHolidayMapper,
        ComplianceService $complianceService,
        string $userId
    ) {
        parent::__construct($appName, $request);
        $this->mapper = $mapper;
        $this->projectMapper = $projectMapper;
        $this->employeeSettingsMapper = $employeeSettingsMapper;
        $this->publicHolidayMapper = $publicHolidayMapper;
        $this->complianceService = $complianceService;
        $this->userId = $userId;
    }

    /**
     * Check if the user is allowed to log hours on a specific date.
     * Directors and freelancers can log hours anytime.
     * Other employees cannot log hours on Sundays or public holidays.
     * 
     * @param int $timestamp Unix timestamp of the entry
     * @return array|null Returns null if allowed, or error array if not allowed
     */
    private function checkDateRestriction(int $timestamp): ?array {
        // Get employee settings
        try {
            $settings = $this->employeeSettingsMapper->findByUserId($this->userId);
            $employmentType = $settings->getEmploymentType();
        } catch (\Exception $e) {
            // No settings found, assume regular employee (restricted)
            $employmentType = 'contract';
        }
        
        // Directors and freelancers can log hours anytime
        if (in_array($employmentType, ['director', 'freelance'])) {
            return null;
        }
        
        // Check if it's a Sunday (day 7 in ISO-8601)
        $date = new DateTime('@' . $timestamp);
        $date->setTimezone(new DateTimeZone('Europe/Berlin'));
        $dayOfWeek = (int)$date->format('N');
        
        if ($dayOfWeek === 7) {
            return [
                'error' => 'Zeiterfassung an Sonntagen ist nicht erlaubt',
                'code' => 'SUNDAY_NOT_ALLOWED'
            ];
        }
        
        // Check if it's a public holiday
        if ($this->publicHolidayMapper->isHoliday($date)) {
            return [
                'error' => 'Zeiterfassung an Feiertagen ist nicht erlaubt',
                'code' => 'HOLIDAY_NOT_ALLOWED'
            ];
        }
        
        return null;
    }

    /**
     * Parse an ISO 8601 datetime string to a Unix timestamp
     * The input must include timezone information to be unambiguous
     * 
     * @param string $isoDateTime ISO 8601 datetime (e.g., "2026-01-19T10:45:00+01:00" or "2026-01-19T09:45:00Z")
     * @return int Unix timestamp
     */
    private function parseIsoToTimestamp(string $isoDateTime): int {
        $dt = new DateTime($isoDateTime);
        return $dt->getTimestamp();
    }

    /**
     * @NoAdminRequired
     * 
     * @param string|null $startDate ISO 8601 date/datetime with timezone for range start
     * @param string|null $endDate ISO 8601 date/datetime with timezone for range end
     * @param int|null $projectId Optional project filter
     */
    public function index(?string $startDate = null, ?string $endDate = null, ?int $projectId = null): DataResponse {
        // Parse date strings to timestamps
        // For date-only strings, we use the client's intended local start/end of day
        $startTs = null;
        $endTs = null;
        
        if ($startDate) {
            // If only date provided (YYYY-MM-DD), assume start of day in UTC
            if (strlen($startDate) === 10) {
                $startTs = (new DateTime($startDate . 'T00:00:00Z'))->getTimestamp();
            } else {
                $startTs = $this->parseIsoToTimestamp($startDate);
            }
        }
        
        if ($endDate) {
            // If only date provided (YYYY-MM-DD), assume end of day in UTC
            if (strlen($endDate) === 10) {
                $endTs = (new DateTime($endDate . 'T23:59:59Z'))->getTimestamp();
            } else {
                $endTs = $this->parseIsoToTimestamp($endDate);
            }
        }
        
        if ($projectId) {
            return new DataResponse($this->mapper->findByProject($projectId, $startTs, $endTs));
        }
        
        return new DataResponse($this->mapper->findByUser($this->userId, $startTs, $endTs));
    }

    /**
     * @NoAdminRequired
     */
    public function show(int $id): DataResponse {
        try {
            return new DataResponse($this->mapper->find($id));
        } catch (\Exception $e) {
            return new DataResponse(['error' => 'Time entry not found'], 404);
        }
    }

    /**
     * @NoAdminRequired
     * 
     * Create a new time entry.
     * 
     * @param int $projectId Project ID
     * @param string $startTime ISO 8601 datetime with timezone (e.g., "2026-01-19T10:45:00+01:00")
     * @param string|null $endTime ISO 8601 datetime with timezone (optional)
     * @param string|null $description Optional description
     * @param bool|null $billable Whether the entry is billable (default: true)
     */
    public function create(int $projectId, string $startTime, ?string $endTime = null,
                          ?string $description = null, ?bool $billable = true): DataResponse {
        // Check if project requires a description
        try {
            $project = $this->projectMapper->find($projectId);
            if ($project->getRequireDescription() && empty(trim($description ?? ''))) {
                return new DataResponse([
                    'error' => 'Dieses Projekt erfordert eine Beschreibung',
                    'code' => 'DESCRIPTION_REQUIRED'
                ], 400);
            }
        } catch (\Exception $e) {
            return new DataResponse(['error' => 'Project not found'], 404);
        }

        $startTs = $this->parseIsoToTimestamp($startTime);
        
        // Check date restrictions (Sundays and public holidays)
        $dateRestriction = $this->checkDateRestriction($startTs);
        if ($dateRestriction !== null) {
            return new DataResponse($dateRestriction, 400);
        }
        
        if ($endTime) {
            $endTs = $this->parseIsoToTimestamp($endTime);
            
            // Check for overlapping entries (only for completed entries with end time)
            if ($this->mapper->hasOverlappingEntry($this->userId, $startTs, $endTs)) {
                return new DataResponse([
                    'error' => 'Time entry overlaps with an existing entry'
                ], 409);
            }
            
            $duration = ($endTs - $startTs) / 60;
        } else {
            $endTs = null;
            $duration = null;
        }
        
        $entry = new TimeEntry();
        $entry->setProjectId($projectId);
        $entry->setUserId($this->userId);
        $entry->setStartTimestamp($startTs);
        
        if ($endTs !== null) {
            $entry->setEndTimestamp($endTs);
        }
        
        $entry->setDescription($description);
        $entry->setBillable($billable ?? true);
        $entry->setCreatedAt(new \DateTime());
        $entry->setUpdatedAt(new \DateTime());
        
        return new DataResponse($this->mapper->insert($entry));
    }

    /**
     * @NoAdminRequired
     * 
     * Update an existing time entry.
     * 
     * @param int $id Entry ID
     * @param string $startTime ISO 8601 datetime with timezone
     * @param string|null $endTime ISO 8601 datetime with timezone (optional)
     * @param int|null $projectId Optional project ID
     * @param string|null $description Optional description
     * @param bool|null $billable Whether the entry is billable
     */
    public function update(int $id, string $startTime, ?string $endTime = null,
                          ?int $projectId = null, ?string $description = null, ?bool $billable = null): DataResponse {
        try {
            $entry = $this->mapper->find($id);
            
            // Check if user owns this entry
            if ($entry->getUserId() !== $this->userId) {
                return new DataResponse(['error' => 'Unauthorized'], 403);
            }
            
            // Get employment type to check if user is a director
            $isDirector = false;
            try {
                $settings = $this->employeeSettingsMapper->findByUserId($this->userId);
                $isDirector = ($settings->getEmploymentType() === 'director');
            } catch (\Exception $e) {
                // No settings found, assume not a director
            }
            
            // Check if entry is from the current month (only current month entries can be edited)
            // Directors can edit entries from any month
            if (!$isDirector) {
                $entryDate = new DateTime('@' . $entry->getStartTimestamp());
                $entryDate->setTimezone(new DateTimeZone('Europe/Berlin'));
                $now = new DateTime('now', new DateTimeZone('Europe/Berlin'));
                
                if ($entryDate->format('Y-m') !== $now->format('Y-m')) {
                    return new DataResponse([
                        'error' => 'Einträge aus vergangenen Monaten können nicht bearbeitet werden',
                        'code' => 'PAST_MONTH_EDIT_NOT_ALLOWED'
                    ], 400);
                }
            }
            
            $startTs = $this->parseIsoToTimestamp($startTime);
            
            // Check date restrictions (Sundays and public holidays)
            $dateRestriction = $this->checkDateRestriction($startTs);
            if ($dateRestriction !== null) {
                return new DataResponse($dateRestriction, 400);
            }
            
            if ($endTime) {
                $endTs = $this->parseIsoToTimestamp($endTime);
                
                // Check for overlapping entries (exclude current entry)
                if ($this->mapper->hasOverlappingEntry($this->userId, $startTs, $endTs, $id)) {
                    return new DataResponse([
                        'error' => 'Time entry overlaps with an existing entry'
                    ], 409);
                }
                
                $entry->setEndTimestamp($endTs);
                $duration = ($endTs - $startTs) / 60;
            }
            
            $entry->setStartTimestamp($startTs);
            
            if ($projectId !== null) {
                $entry->setProjectId($projectId);
            }

            // Check if the (possibly updated) project requires a description
            $effectiveProjectId = $projectId ?? $entry->getProjectId();
            try {
                $project = $this->projectMapper->find($effectiveProjectId);
                $effectiveDescription = $description !== null ? $description : $entry->getDescription();
                if ($project->getRequireDescription() && empty(trim($effectiveDescription ?? ''))) {
                    return new DataResponse([
                        'error' => 'Dieses Projekt erfordert eine Beschreibung',
                        'code' => 'DESCRIPTION_REQUIRED'
                    ], 400);
                }
            } catch (\Exception $e) {
                // Project not found - skip validation
            }

            if ($description !== null) {
                $entry->setDescription($description);
            }
            if ($billable !== null) {
                $entry->setBillable($billable);
            }
            $entry->setUpdatedAt(new \DateTime());
            
            return new DataResponse($this->mapper->update($entry));
        } catch (\Exception $e) {
            return new DataResponse(['error' => 'Time entry not found'], 404);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function delete(int $id): DataResponse {
        try {
            $entry = $this->mapper->find($id);
            
            // Check if user owns this entry
            if ($entry->getUserId() !== $this->userId) {
                return new DataResponse(['error' => 'Unauthorized'], 403);
            }
            
            // Get employment type to check if user is a director
            $isDirector = false;
            try {
                $settings = $this->employeeSettingsMapper->findByUserId($this->userId);
                $isDirector = ($settings->getEmploymentType() === 'director');
            } catch (\Exception $e) {
                // No settings found, assume not a director
            }
            
            // Check if entry is from the current month (only current month entries can be deleted)
            // Directors can delete entries from any month
            if (!$isDirector) {
                $entryDate = new DateTime('@' . $entry->getStartTimestamp());
                $entryDate->setTimezone(new DateTimeZone('Europe/Berlin'));
                $now = new DateTime('now', new DateTimeZone('Europe/Berlin'));
                
                if ($entryDate->format('Y-m') !== $now->format('Y-m')) {
                    return new DataResponse([
                        'error' => 'Einträge aus vergangenen Monaten können nicht gelöscht werden',
                        'code' => 'PAST_MONTH_DELETE_NOT_ALLOWED'
                    ], 400);
                }
            }
            
            $this->mapper->delete($entry);
            return new DataResponse(['success' => true]);
        } catch (\Exception $e) {
            return new DataResponse(['error' => 'Time entry not found'], 404);
        }
    }

    /**
     * @NoAdminRequired
     * 
     * Start a new timer. Optionally specify a custom start time.
     * 
     * @param int|null $projectId Optional project ID
     * @param string|null $description Optional description
     * @param string|null $startTime Optional ISO 8601 datetime for custom start time (e.g., "2026-02-13T08:30:00+01:00"). Defaults to now.
     */
    public function startTimer(?int $projectId = null, ?string $description = null, ?string $startTime = null): DataResponse {
        // Check if there's already a running timer
        $running = $this->mapper->findRunningTimer($this->userId);
        if ($running) {
            return new DataResponse(['error' => 'Timer already running'], 400);
        }
        
        if ($startTime !== null) {
            $startTs = $this->parseIsoToTimestamp($startTime);
            // Don't allow start time in the future
            if ($startTs > time()) {
                return new DataResponse(['error' => 'Startzeit darf nicht in der Zukunft liegen'], 400);
            }
        } else {
            $startTs = time();
        }
        
        // Check date restrictions (Sundays and public holidays)
        $dateRestriction = $this->checkDateRestriction($startTs);
        if ($dateRestriction !== null) {
            return new DataResponse($dateRestriction, 400);
        }
        
        $entry = new TimeEntry();
        if ($projectId) {
            $entry->setProjectId($projectId);
        }
        $entry->setUserId($this->userId);
        $entry->setStartTimestamp($startTs);
        $entry->setDescription($description);
        $entry->setBillable(true);
        $entry->setCreatedAt(new \DateTime());
        $entry->setUpdatedAt(new \DateTime());
        
        return new DataResponse($this->mapper->insert($entry));
    }

    /**
     * @NoAdminRequired
     * 
     * Stop the running timer. The end time is the current server time (stored as UTC timestamp).
     */
    public function stopTimer(?int $projectId = null, ?string $description = null, ?bool $billable = true): DataResponse {
        $running = $this->mapper->findRunningTimer($this->userId);
        if (!$running) {
            return new DataResponse(['error' => 'No running timer'], 400);
        }
        
        // Update project/description if provided (required if not set at start)
        if ($projectId !== null) {
            $running->setProjectId($projectId);
        }
        if ($description !== null) {
            $running->setDescription($description);
        }
        if ($billable !== null) {
            $running->setBillable($billable);
        }
        
        // Validate that project is set
        if (!$running->getProjectId()) {
            return new DataResponse(['error' => 'Project is required'], 400);
        }
        
        $nowTs = time(); // Current Unix timestamp (timezone-independent)
        $startTs = $running->getStartTimestamp();
        
        // Check for overlapping entries (exclude current entry)
        if ($this->mapper->hasOverlappingEntry($this->userId, $startTs, $nowTs, $running->getId())) {
            return new DataResponse([
                'error' => 'Time entry overlaps with an existing entry'
            ], 409);
        }
        
        $running->setEndTimestamp($nowTs);
        
        $duration = ($nowTs - $startTs) / 60;
        $running->setUpdatedAt(new \DateTime());
        
        return new DataResponse($this->mapper->update($running));
    }

    /**
     * @NoAdminRequired
     * 
     * Cancel the running timer. The timer entry is deleted without saving.
     */
    public function cancelTimer(): DataResponse {
        $running = $this->mapper->findRunningTimer($this->userId);
        if (!$running) {
            return new DataResponse(['error' => 'No running timer'], 400);
        }
        
        $this->mapper->delete($running);
        return new DataResponse(['success' => true]);
    }

    /**
     * @NoAdminRequired
     * 
     * Check compliance for the current day (or a specific date).
     * Returns violations and warnings based on German labor law (ArbZG).
     * 
     * @param string|null $date Optional date in YYYY-MM-DD format (defaults to today)
     */
    public function checkDailyCompliance(?string $date = null): DataResponse {
        // Parse date or use today
        if ($date) {
            $checkDate = new DateTime($date, new DateTimeZone('Europe/Berlin'));
        } else {
            $checkDate = new DateTime('now', new DateTimeZone('Europe/Berlin'));
        }
        
        $result = $this->complianceService->checkDailyCompliance($this->userId, $checkDate);
        
        return new DataResponse($result);
    }
}

