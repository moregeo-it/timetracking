<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Controller;

use OCA\TimeTracking\Db\EmployeeSettings;
use OCA\TimeTracking\Db\EmployeeSettingsMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IGroupManager;

class EmployeeSettingsController extends Controller {
    private EmployeeSettingsMapper $mapper;
    private string $userId;
    private IGroupManager $groupManager;

    public function __construct(
        string $appName,
        IRequest $request,
        EmployeeSettingsMapper $mapper,
        IGroupManager $groupManager,
        string $userId
    ) {
        parent::__construct($appName, $request);
        $this->mapper = $mapper;
        $this->groupManager = $groupManager;
        $this->userId = $userId;
    }

    /**
     * @NoAdminRequired
     */
    public function get(): DataResponse {
        $settings = $this->mapper->findByUserId($this->userId);
        
        if (!$settings) {
            // Create default settings
            $settings = new EmployeeSettings();
            $settings->setUserId($this->userId);
            $settings->setEmploymentType('contract');
            $settings->setWeeklyHours(40.0);
            $settings->setVacationDaysPerYear(20);
            $settings->setCreatedAt(new \DateTime());
            $settings->setUpdatedAt(new \DateTime());
            $settings = $this->mapper->insert($settings);
        }
        
        // Hide hourly rate for non-admins
        $data = $settings->jsonSerialize();
        if (!$this->groupManager->isAdmin($this->userId)) {
            unset($data['hourlyRate']);
        }
        
        return new DataResponse($data);
    }

    /**
     * @NoAdminRequired
     */
    /**
     * @NoAdminRequired
     */
    public function getUser(string $userId): DataResponse {
        // Allow users to view their own settings or admins to view any
        if ($userId !== $this->userId && !$this->groupManager->isAdmin($this->userId)) {
            return new DataResponse(['error' => 'Unauthorized'], 403);
        }
        
        $settings = $this->mapper->findByUserId($userId);
        
        if (!$settings) {
            // Return default settings
            return new DataResponse([
                'userId' => $userId,
                'employmentType' => 'contract',
                'weeklyHours' => 40,
                'maxTotalHours' => null,
                'vacationDaysPerYear' => 20,
                'hourlyRate' => null,
                'federalState' => '',
                'employmentStart' => '',
            ]);
        }
        
        return new DataResponse($settings);
    }

    /**
     * @NoAdminRequired
     */
    public function update(
        string $employmentType,
        float $weeklyHours,
        ?float $maxTotalHours = null,
        ?int $vacationDaysPerYear = null,
        ?float $hourlyRate = null,
        ?string $federalState = null,
        ?string $employmentStart = null,
        ?string $targetUserId = null
    ): DataResponse {
        // Only admins can update settings
        if (!$this->groupManager->isAdmin($this->userId)) {
            return new DataResponse(['error' => 'Only administrators can modify employee settings'], 403);
        }
        
        // Admin can update any user's settings, default to their own
        $userIdToUpdate = $targetUserId ?? $this->userId;
        
        $settings = $this->mapper->findByUserId($userIdToUpdate);
        
        if (!$settings) {
            $settings = new EmployeeSettings();
            $settings->setUserId($userIdToUpdate);
            $settings->setCreatedAt(new \DateTime());
        }
        
        // Validate employment type
        if (!in_array($employmentType, ['contract', 'freelance', 'mini_job'])) {
            return new DataResponse(['error' => 'Invalid employment type'], 400);
        }
        
        $settings->setEmploymentType($employmentType);
        $settings->setWeeklyHours($weeklyHours);
        $settings->setMaxTotalHours($maxTotalHours);
        $settings->setHourlyRate($hourlyRate);
        
        // For freelance workers, vacation days don't apply
        if ($employmentType === 'freelance') {
            $settings->setVacationDaysPerYear(0);
        } else {
            $settings->setVacationDaysPerYear($vacationDaysPerYear ?? 20);
        }
        
        $settings->setFederalState($federalState);
        
        if ($employmentStart) {
            $settings->setEmploymentStart(new \DateTime($employmentStart));
        }
        
        $settings->setUpdatedAt(new \DateTime());
        
        if ($settings->getId()) {
            $result = $this->mapper->update($settings);
        } else {
            $result = $this->mapper->insert($settings);
        }
        
        return new DataResponse($result);
    }
}

