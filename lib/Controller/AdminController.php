<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Controller;

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

    public function __construct(
        string $appName,
        IRequest $request,
        string $userId,
        IGroupManager $groupManager,
        IUserManager $userManager,
        TimeEntryMapper $timeEntryMapper
    ) {
        parent::__construct($appName, $request);
        $this->userId = $userId;
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
        $this->timeEntryMapper = $timeEntryMapper;
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
     * Get time entries for a specific user (admin only)
     * @NoAdminRequired
     */
    public function timeEntries(?string $userId = null): DataResponse {
        if (!$this->groupManager->isAdmin($this->userId)) {
            return new DataResponse(['error' => 'Unauthorized'], 403);
        }
        
        if (!$userId) {
            return new DataResponse(['error' => 'userId required'], 400);
        }

        $entries = $this->timeEntryMapper->findByUser($userId);
        return new DataResponse(array_map(function ($entry) {
            return $entry->jsonSerialize();
        }, $entries));
    }
}
