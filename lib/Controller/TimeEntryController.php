<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Controller;

use DateTime;
use DateTimeZone;
use OCA\TimeTracking\Db\TimeEntry;
use OCA\TimeTracking\Db\TimeEntryMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class TimeEntryController extends Controller {
    private TimeEntryMapper $mapper;
    private string $userId;

    public function __construct(string $appName, IRequest $request, TimeEntryMapper $mapper, string $userId) {
        parent::__construct($appName, $request);
        $this->mapper = $mapper;
        $this->userId = $userId;
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
        $startTs = $this->parseIsoToTimestamp($startTime);
        
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
            $entry->setDurationMinutes((int)$duration);
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
     * @param string|null $description Optional description
     * @param bool|null $billable Whether the entry is billable
     */
    public function update(int $id, string $startTime, ?string $endTime = null,
                          ?string $description = null, ?bool $billable = null): DataResponse {
        try {
            $entry = $this->mapper->find($id);
            
            // Check if user owns this entry
            if ($entry->getUserId() !== $this->userId) {
                return new DataResponse(['error' => 'Unauthorized'], 403);
            }
            
            $startTs = $this->parseIsoToTimestamp($startTime);
            
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
                $entry->setDurationMinutes((int)$duration);
            }
            
            $entry->setStartTimestamp($startTs);
            
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
            
            $this->mapper->delete($entry);
            return new DataResponse(['success' => true]);
        } catch (\Exception $e) {
            return new DataResponse(['error' => 'Time entry not found'], 404);
        }
    }

    /**
     * @NoAdminRequired
     * 
     * Start a new timer. The start time is the current server time (stored as UTC timestamp).
     */
    public function startTimer(?int $projectId = null, ?string $description = null): DataResponse {
        // Check if there's already a running timer
        $running = $this->mapper->findRunningTimer($this->userId);
        if ($running) {
            return new DataResponse(['error' => 'Timer already running'], 400);
        }
        
        $entry = new TimeEntry();
        if ($projectId) {
            $entry->setProjectId($projectId);
        }
        $entry->setUserId($this->userId);
        $entry->setStartTimestamp(time()); // Current Unix timestamp (timezone-independent)
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
        $running->setDurationMinutes((int)$duration);
        $running->setUpdatedAt(new \DateTime());
        
        return new DataResponse($this->mapper->update($running));
    }
}

