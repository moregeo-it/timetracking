<?php

declare(strict_types=1);

namespace OCA\TimeTracking\Controller;

use DateTime;
use OCA\TimeTracking\Db\Vacation;
use OCA\TimeTracking\Db\VacationMapper;
use OCA\TimeTracking\Db\EmployeeSettingsMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class VacationController extends Controller {
    private $userId;
    private $vacationMapper;
    private $employeeSettingsMapper;

    public function __construct(
        string $appName,
        IRequest $request,
        IUserSession $userSession,
        VacationMapper $vacationMapper,
        EmployeeSettingsMapper $employeeSettingsMapper
    ) {
        parent::__construct($appName, $request);
        $this->userId = $userSession->getUser()->getUID();
        $this->vacationMapper = $vacationMapper;
        $this->employeeSettingsMapper = $employeeSettingsMapper;
    }

    /**
     * @NoAdminRequired
     */
    public function index(?int $year = null): DataResponse {
        $vacations = $this->vacationMapper->findByUser($this->userId, $year);
        return new DataResponse($vacations);
    }

    /**
     * @NoAdminRequired
     */
    public function show(int $id): DataResponse {
        try {
            $vacation = $this->vacationMapper->find($id);
            
            // Only allow user to see their own vacations
            if ($vacation->getUserId() !== $this->userId) {
                return new DataResponse(['error' => 'Unauthorized'], 403);
            }
            
            return new DataResponse($vacation);
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
            $vacation = new Vacation();
            $vacation->setUserId($this->userId);
            $vacation->setStartDate(new DateTime($startDate));
            $vacation->setEndDate(new DateTime($endDate));
            $vacation->setDays($days);
            $vacation->setStatus('pending');
            $vacation->setNotes($notes);
            $vacation->setCreatedAt(new DateTime());
            $vacation->setUpdatedAt(new DateTime());

            $result = $this->vacationMapper->insert($vacation);
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
            $vacation = $this->vacationMapper->find($id);
            
            // Only allow user to update their own vacations
            if ($vacation->getUserId() !== $this->userId) {
                return new DataResponse(['error' => 'Unauthorized'], 403);
            }
            
            // Only allow updates if status is pending
            if ($vacation->getStatus() !== 'pending') {
                return new DataResponse(['error' => 'Cannot update approved or rejected vacation'], 400);
            }

            if ($startDate !== null) {
                $vacation->setStartDate(new DateTime($startDate));
            }
            if ($endDate !== null) {
                $vacation->setEndDate(new DateTime($endDate));
            }
            if ($days !== null) {
                $vacation->setDays($days);
            }
            if ($notes !== null) {
                $vacation->setNotes($notes);
            }
            
            $vacation->setUpdatedAt(new DateTime());
            
            $result = $this->vacationMapper->update($vacation);
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
            $vacation = $this->vacationMapper->find($id);
            
            // Only allow user to delete their own vacations
            if ($vacation->getUserId() !== $this->userId) {
                return new DataResponse(['error' => 'Unauthorized'], 403);
            }
            
            // Only allow deletion if status is pending
            if ($vacation->getStatus() !== 'pending') {
                return new DataResponse(['error' => 'Cannot delete approved or rejected vacation'], 400);
            }

            $this->vacationMapper->delete($vacation);
            return new DataResponse(['success' => true]);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function balance(int $year): DataResponse {
        try {
            // Get employee settings to determine vacation days per year
            $settings = $this->employeeSettingsMapper->findByUserId($this->userId);
            $totalDays = $settings ? $settings->getVacationDaysPerYear() : 20;
            
            // Calculate used days (approved vacations)
            $usedDays = $this->vacationMapper->getTotalDaysUsed($this->userId, $year);
            
            // Calculate pending days
            $pendingVacations = $this->vacationMapper->findByUser($this->userId, $year);
            $pendingDays = 0;
            foreach ($pendingVacations as $vacation) {
                if ($vacation->getStatus() === 'pending') {
                    $pendingDays += $vacation->getDays();
                }
            }
            
            return new DataResponse([
                'year' => $year,
                'totalDays' => $totalDays,
                'usedDays' => $usedDays,
                'pendingDays' => $pendingDays,
                'remainingDays' => $totalDays - $usedDays - $pendingDays,
                'availableDays' => $totalDays - $usedDays,
            ]);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function calendar(int $year, int $month): DataResponse {
        try {
            $startDate = new DateTime("$year-$month-01");
            $endDate = clone $startDate;
            $endDate->modify('last day of this month');
            
            $vacations = $this->vacationMapper->findByDateRange($this->userId, $startDate, $endDate);
            
            return new DataResponse($vacations);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 400);
        }
    }
}

