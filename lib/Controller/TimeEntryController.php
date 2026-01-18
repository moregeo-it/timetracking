<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Controller;

use DateTime;
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
     * @NoAdminRequired
     */
    public function index(?string $startDate = null, ?string $endDate = null, ?int $projectId = null): DataResponse {
        $start = $startDate ? new DateTime($startDate) : null;
        $end = $endDate ? new DateTime($endDate) : null;
        
        if ($projectId) {
            return new DataResponse($this->mapper->findByProject($projectId, $start, $end));
        }
        
        return new DataResponse($this->mapper->findByUser($this->userId, $start, $end));
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
     */
    public function create(int $projectId, string $date, string $startTime, ?string $endTime = null,
                          ?string $description = null, ?bool $billable = true): DataResponse {
        $entry = new TimeEntry();
        $entry->setProjectId($projectId);
        $entry->setUserId($this->userId);
        $entry->setDate(new DateTime($date));
        $entry->setStartTime(new DateTime($startTime));
        
        if ($endTime) {
            $end = new DateTime($endTime);
            $entry->setEndTime($end);
            
            $start = new DateTime($startTime);
            $duration = ($end->getTimestamp() - $start->getTimestamp()) / 60;
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
     */
    public function update(int $id, string $startTime, ?string $endTime = null,
                          ?string $description = null, ?bool $billable = null): DataResponse {
        try {
            $entry = $this->mapper->find($id);
            
            // Check if user owns this entry
            if ($entry->getUserId() !== $this->userId) {
                return new DataResponse(['error' => 'Unauthorized'], 403);
            }
            
            $entry->setStartTime(new DateTime($startTime));
            
            if ($endTime) {
                $end = new DateTime($endTime);
                $entry->setEndTime($end);
                
                $start = new DateTime($startTime);
                $duration = ($end->getTimestamp() - $start->getTimestamp()) / 60;
                $entry->setDurationMinutes((int)$duration);
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
            
            $this->mapper->delete($entry);
            return new DataResponse(['success' => true]);
        } catch (\Exception $e) {
            return new DataResponse(['error' => 'Time entry not found'], 404);
        }
    }

    /**
     * @NoAdminRequired
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
        $entry->setDate(new DateTime());
        $entry->setStartTime(new DateTime());
        $entry->setDescription($description);
        $entry->setBillable(true);
        $entry->setCreatedAt(new \DateTime());
        $entry->setUpdatedAt(new \DateTime());
        
        return new DataResponse($this->mapper->insert($entry));
    }

    /**
     * @NoAdminRequired
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
        
        $now = new DateTime();
        $running->setEndTime($now);
        
        $duration = ($now->getTimestamp() - $running->getStartTime()->getTimestamp()) / 60;
        $running->setDurationMinutes((int)$duration);
        $running->setUpdatedAt(new \DateTime());
        
        return new DataResponse($this->mapper->update($running));
    }
}

