<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Controller;

use OCA\TimeTracking\Db\DefaultMultiplierMapper;
use OCA\TimeTracking\Db\TimeEntry;
use OCA\TimeTracking\Db\TimeEntryMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;

class AdminController extends Controller {
    private string $userId;
    private IGroupManager $groupManager;
    private IUserManager $userManager;
    private TimeEntryMapper $timeEntryMapper;
    private DefaultMultiplierMapper $defaultMultiplierMapper;

    public function __construct(
        string $appName,
        IRequest $request,
        string $userId,
        IGroupManager $groupManager,
        IUserManager $userManager,
        TimeEntryMapper $timeEntryMapper,
        DefaultMultiplierMapper $defaultMultiplierMapper
    ) {
        parent::__construct($appName, $request);
        $this->userId = $userId;
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
        $this->timeEntryMapper = $timeEntryMapper;
        $this->defaultMultiplierMapper = $defaultMultiplierMapper;
    }

    /**
     * Get all users (admin only)
     * @NoAdminRequired
     */
    public function users(): DataResponse {
        if (!$this->groupManager->isAdmin($this->userId)) {
            return new DataResponse(['error' => 'Unauthorized'], 403);
        }

        $users = [];
        
        // Use search to get all users (more reliable than callForAllUsers)
        $searchResult = $this->userManager->search('', 1000, 0);
        foreach ($searchResult as $user) {
            $users[] = [
                'id' => $user->getUID(),
                'displayName' => $user->getDisplayName(),
                'email' => $user->getEMailAddress(),
            ];
        }

        // Sort by displayName
        usort($users, function ($a, $b) {
            return strcasecmp($a['displayName'], $b['displayName']);
        });

        return new DataResponse($users);
    }

    /**
     * Parse an ISO 8601 datetime string to a Unix timestamp
     * @param string $isoDateTime
     * @return int
     */
    private function parseIsoToTimestamp(string $isoDateTime): int {
        return (new \DateTime($isoDateTime))->getTimestamp();
    }

    /**
     * Get time entries for a specific user (admin only)
     * @NoAdminRequired
     */
    public function timeEntries(?string $userId = null, ?string $startDate = null, ?string $endDate = null): DataResponse {
        if (!$this->groupManager->isAdmin($this->userId)) {
            return new DataResponse(['error' => 'Unauthorized'], 403);
        }
        
        if (!$userId) {
            return new DataResponse(['error' => 'userId required'], 400);
        }

        $startTs = null;
        $endTs = null;

        if ($startDate) {
            $startTs = strlen($startDate) === 10
                ? (new \DateTime($startDate . 'T00:00:00Z'))->getTimestamp()
                : $this->parseIsoToTimestamp($startDate);
        }
        if ($endDate) {
            $endTs = strlen($endDate) === 10
                ? (new \DateTime($endDate . 'T23:59:59Z'))->getTimestamp()
                : $this->parseIsoToTimestamp($endDate);
        }

        $entries = $this->timeEntryMapper->findByUser($userId, $startTs, $endTs);
        return new DataResponse(array_map(function ($entry) {
            return $entry->jsonSerialize();
        }, $entries));
    }

    /**
     * Get default multipliers (admin only)
     * @NoAdminRequired
     */
    public function getDefaultMultipliers(): DataResponse {
        if (!$this->groupManager->isAdmin($this->userId)) {
            return new DataResponse(['error' => 'Unauthorized'], 403);
        }

        return new DataResponse($this->defaultMultiplierMapper->getDefaultsAsArray());
    }

    /**
     * Update default multipliers (admin only)
     * @NoAdminRequired
     */
    public function updateDefaultMultipliers(array $multipliers): DataResponse {
        if (!$this->groupManager->isAdmin($this->userId)) {
            return new DataResponse(['error' => 'Unauthorized'], 403);
        }

        $this->defaultMultiplierMapper->setDefaults($multipliers);
        return new DataResponse($this->defaultMultiplierMapper->getDefaultsAsArray());
    }

    /**
     * Create a time entry for any user (admin only)
     * @NoAdminRequired
     */
    public function createTimeEntry(string $userId, int $projectId, string $startTime,
                                    ?string $endTime = null, ?string $description = null,
                                    ?bool $billable = true): DataResponse {
        if (!$this->groupManager->isAdmin($this->userId)) {
            return new DataResponse(['error' => 'Unauthorized'], 403);
        }

        $startTs = $this->parseIsoToTimestamp($startTime);
        $endTs = $endTime ? $this->parseIsoToTimestamp($endTime) : null;

        if ($endTs && $this->timeEntryMapper->hasOverlappingEntry($userId, $startTs, $endTs)) {
            return new DataResponse(['error' => 'Time entry overlaps with an existing entry'], 409);
        }

        $entry = new TimeEntry();
        $entry->setProjectId($projectId);
        $entry->setUserId($userId);
        $entry->setStartTimestamp($startTs);
        if ($endTs !== null) {
            $entry->setEndTimestamp($endTs);
        }
        $entry->setDescription($description);
        $entry->setBillable($billable ?? true);
        $entry->setCreatedAt(new \DateTime());
        $entry->setUpdatedAt(new \DateTime());

        return new DataResponse($this->timeEntryMapper->insert($entry));
    }

    /**
     * Update a time entry for any user (admin only)
     * Admins bypass the current-month and ownership restrictions.
     * @NoAdminRequired
     */
    public function updateTimeEntry(int $id, string $startTime, ?string $endTime = null,
                                    ?int $projectId = null, ?string $description = null,
                                    ?bool $billable = null): DataResponse {
        if (!$this->groupManager->isAdmin($this->userId)) {
            return new DataResponse(['error' => 'Unauthorized'], 403);
        }

        try {
            $entry = $this->timeEntryMapper->find($id);

            $startTs = $this->parseIsoToTimestamp($startTime);
            $entry->setStartTimestamp($startTs);

            if ($endTime) {
                $endTs = $this->parseIsoToTimestamp($endTime);

                // Overlap check using the entry owner's userId (not the admin's)
                if ($this->timeEntryMapper->hasOverlappingEntry($entry->getUserId(), $startTs, $endTs, $id)) {
                    return new DataResponse(['error' => 'Time entry overlaps with an existing entry'], 409);
                }

                $entry->setEndTimestamp($endTs);
            }

            if ($projectId !== null) {
                $entry->setProjectId($projectId);
            }
            if ($description !== null) {
                $entry->setDescription($description);
            }
            if ($billable !== null) {
                $entry->setBillable($billable);
            }
            $entry->setUpdatedAt(new \DateTime());

            return new DataResponse($this->timeEntryMapper->update($entry));
        } catch (\Exception $e) {
            return new DataResponse(['error' => 'Time entry not found'], 404);
        }
    }

    /**
     * Delete a time entry for any user (admin only)
     * @NoAdminRequired
     */
    public function deleteTimeEntry(int $id): DataResponse {
        if (!$this->groupManager->isAdmin($this->userId)) {
            return new DataResponse(['error' => 'Unauthorized'], 403);
        }

        try {
            $entry = $this->timeEntryMapper->find($id);
            $this->timeEntryMapper->delete($entry);
            return new DataResponse(['success' => true]);
        } catch (\Exception $e) {
            return new DataResponse(['error' => 'Time entry not found'], 404);
        }
    }
}
