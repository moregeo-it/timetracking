<?php

declare(strict_types=1);

namespace OCA\TimeTracking\Controller;

use DateTime;
use OCA\TimeTracking\Db\SickDay;
use OCA\TimeTracking\Db\SickDayMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IL10N;

class SickDayController extends Controller {
    private $userId;
    private $sickDayMapper;
    private $groupManager;
    private $userManager;
    private $l10n;

    public function __construct(
        string $appName,
        IRequest $request,
        IUserSession $userSession,
        SickDayMapper $sickDayMapper,
        IGroupManager $groupManager,
        IUserManager $userManager,
        IL10N $l10n
    ) {
        parent::__construct($appName, $request);
        $this->userId = $userSession->getUser()->getUID();
        $this->sickDayMapper = $sickDayMapper;
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
        $this->l10n = $l10n;
    }

    private function isAdmin(): bool {
        return $this->groupManager->isAdmin($this->userId);
    }

    /**
     * @NoAdminRequired
     */
    public function index(?int $year = null, ?string $userId = null): DataResponse {
        $isAdmin = $this->isAdmin();
        
        if ($userId !== null && $isAdmin) {
            $sickDays = $this->sickDayMapper->findByUser($userId, $year);
        } else {
            $sickDays = $this->sickDayMapper->findAll($year);
        }
        
        $result = [];
        foreach ($sickDays as $sickDay) {
            $data = $sickDay->jsonSerialize();
            // Hide notes if not owner and not admin
            if ($sickDay->getUserId() !== $this->userId && !$isAdmin) {
                $data['notes'] = null;
            }
            $user = $this->userManager->get($sickDay->getUserId());
            $data['displayName'] = $user ? $user->getDisplayName() : $sickDay->getUserId();
            $data['canEdit'] = ($sickDay->getUserId() === $this->userId || $isAdmin);
            $data['canDelete'] = ($sickDay->getUserId() === $this->userId || $isAdmin);
            $result[] = $data;
        }
        
        return new DataResponse($result);
    }

    /**
     * @NoAdminRequired
     */
    public function show(int $id): DataResponse {
        try {
            $sickDay = $this->sickDayMapper->find($id);
            $data = $sickDay->jsonSerialize();
            
            if ($sickDay->getUserId() !== $this->userId && !$this->isAdmin()) {
                $data['notes'] = null;
            }
            
            $user = $this->userManager->get($sickDay->getUserId());
            $data['displayName'] = $user ? $user->getDisplayName() : $sickDay->getUserId();
            
            return new DataResponse($data);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function create(
        string $startDate,
        string $endDate,
        int $days,
        ?string $notes = null
    ): DataResponse {
        try {
            $start = new DateTime($startDate);
            $end = new DateTime($endDate);
            
            if ($this->sickDayMapper->hasOverlappingSickDay($this->userId, $start, $end)) {
                return new DataResponse([
                    'error' => 'Sick day entry overlaps with an existing entry'
                ], 409);
            }
            
            $sickDay = new SickDay();
            $sickDay->setUserId($this->userId);
            $sickDay->setStartDate($start);
            $sickDay->setEndDate($end);
            $sickDay->setDays($days);
            $sickDay->setNotes($notes);
            $sickDay->setCreatedAt(new DateTime());
            $sickDay->setUpdatedAt(new DateTime());

            $result = $this->sickDayMapper->insert($sickDay);
            
            return new DataResponse($result);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function update(
        int $id,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $days = null,
        ?string $notes = null
    ): DataResponse {
        try {
            $sickDay = $this->sickDayMapper->find($id);
            
            $isOwner = $sickDay->getUserId() === $this->userId;
            $isAdmin = $this->isAdmin();
            
            if (!$isOwner && !$isAdmin) {
                return new DataResponse(['error' => 'Unauthorized'], 403);
            }

            $effectiveStart = $startDate !== null ? new DateTime($startDate) : $sickDay->getStartDate();
            $effectiveEnd = $endDate !== null ? new DateTime($endDate) : $sickDay->getEndDate();
            
            if ($this->sickDayMapper->hasOverlappingSickDay($sickDay->getUserId(), $effectiveStart, $effectiveEnd, $id)) {
                return new DataResponse([
                    'error' => 'Sick day entry overlaps with an existing entry'
                ], 409);
            }

            if ($startDate !== null) {
                $sickDay->setStartDate($effectiveStart);
            }
            if ($endDate !== null) {
                $sickDay->setEndDate($effectiveEnd);
            }
            if ($days !== null) {
                $sickDay->setDays($days);
            }
            if ($notes !== null) {
                $sickDay->setNotes($notes);
            }
            
            $sickDay->setUpdatedAt(new DateTime());
            
            $result = $this->sickDayMapper->update($sickDay);
            return new DataResponse($result);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function destroy(int $id): DataResponse {
        try {
            $sickDay = $this->sickDayMapper->find($id);
            
            $isOwner = $sickDay->getUserId() === $this->userId;
            $isAdmin = $this->isAdmin();
            
            if (!$isOwner && !$isAdmin) {
                return new DataResponse(['error' => 'Unauthorized'], 403);
            }

            $this->sickDayMapper->delete($sickDay);
            return new DataResponse(['success' => true]);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * Get sick day summary/statistics for a user in a year
     * @NoAdminRequired
     */
    public function summary(int $year, ?string $userId = null): DataResponse {
        try {
            $targetUserId = $this->userId;
            if ($userId !== null && $this->isAdmin()) {
                $targetUserId = $userId;
            }
            
            $totalDays = $this->sickDayMapper->getTotalDaysUsed($targetUserId, $year);
            
            return new DataResponse([
                'year' => $year,
                'totalDays' => $totalDays,
            ]);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 400);
        }
    }
}
