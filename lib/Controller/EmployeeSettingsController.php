<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Controller;

use DateTime;
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
     * 
     * Get the current settings for the logged-in user
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
            $settings->setValidFrom(new DateTime());
            $settings->setCreatedAt(new DateTime());
            $settings->setUpdatedAt(new DateTime());
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
     * 
     * Get all settings periods for a user
     */
    public function getUser(string $userId): DataResponse {
        // Allow users to view their own settings or admins to view any
        if ($userId !== $this->userId && !$this->groupManager->isAdmin($this->userId)) {
            return new DataResponse(['error' => 'Unauthorized'], 403);
        }
        
        // Return all periods for this user
        $allPeriods = $this->mapper->findAllByUserId($userId);
        
        if (empty($allPeriods)) {
            // Return default settings for the current period
            return new DataResponse([
                'current' => [
                    'userId' => $userId,
                    'employmentType' => 'contract',
                    'weeklyHours' => 40,
                    'maxTotalHours' => null,
                    'vacationDaysPerYear' => 20,
                    'hourlyRate' => null,
                    'employmentStart' => null,
                    'validFrom' => null,
                    'validTo' => null,
                ],
                'periods' => [],
            ]);
        }
        
        // Find the current (active) period
        $currentSettings = $this->mapper->findByUserId($userId);
        
        // Hide hourly rate for non-admins
        $periodsData = [];
        foreach ($allPeriods as $period) {
            $data = $period->jsonSerialize();
            if (!$this->groupManager->isAdmin($this->userId)) {
                unset($data['hourlyRate']);
            }
            $periodsData[] = $data;
        }
        
        $currentData = $currentSettings ? $currentSettings->jsonSerialize() : null;
        if ($currentData && !$this->groupManager->isAdmin($this->userId)) {
            unset($currentData['hourlyRate']);
        }
        
        return new DataResponse([
            'current' => $currentData,
            'periods' => $periodsData,
        ]);
    }

    /**
     * @NoAdminRequired
     * 
     * Update existing settings period or create new one (backwards compatible)
     */
    public function update(
        string $employmentType,
        float $weeklyHours,
        ?float $maxTotalHours = null,
        ?int $vacationDaysPerYear = null,
        ?float $hourlyRate = null,
        ?string $employmentStart = null,
        ?string $validFrom = null,
        ?string $validTo = null,
        ?string $targetUserId = null,
        ?int $periodId = null
    ): DataResponse {
        // Only admins can update settings
        if (!$this->groupManager->isAdmin($this->userId)) {
            return new DataResponse(['error' => 'Only administrators can modify employee settings'], 403);
        }
        
        // Validate employment type
        if (!in_array($employmentType, ['director', 'contract', 'freelance', 'student'])) {
            return new DataResponse(['error' => 'Invalid employment type'], 400);
        }
        
        // Admin can update any user's settings, default to their own
        $userIdToUpdate = $targetUserId ?? $this->userId;
        
        // Parse dates
        $validFromDate = $validFrom ? new DateTime($validFrom) : null;
        $validToDate = $validTo ? new DateTime($validTo) : null;
        
        if ($periodId) {
            // Update existing period
            $settings = $this->mapper->find($periodId);
            if ($settings->getUserId() !== $userIdToUpdate) {
                return new DataResponse(['error' => 'Period does not belong to this user'], 400);
            }
        } else {
            // Check if there's an existing open-ended period that needs to be closed
            $existingSettings = $this->mapper->findByUserId($userIdToUpdate);
            
            if ($existingSettings && $validFromDate) {
                // Close the previous period
                $this->mapper->closePreviousPeriod($userIdToUpdate, $validFromDate);
                
                // Create new period
                $settings = new EmployeeSettings();
                $settings->setUserId($userIdToUpdate);
                $settings->setCreatedAt(new DateTime());
            } elseif ($existingSettings) {
                // No validFrom specified, just update the current period
                $settings = $existingSettings;
            } else {
                // No existing settings, create new
                $settings = new EmployeeSettings();
                $settings->setUserId($userIdToUpdate);
                $settings->setCreatedAt(new DateTime());
            }
        }
        
        $settings->setEmploymentType($employmentType);
        $settings->setWeeklyHours($weeklyHours);
        $settings->setMaxTotalHours($maxTotalHours);
        $settings->setHourlyRate($hourlyRate);
        $settings->setValidFrom($validFromDate);
        $settings->setValidTo($validToDate);
        
        // For freelance workers, vacation days don't apply
        if ($employmentType === 'freelance') {
            $settings->setVacationDaysPerYear(0);
        } else {
            $settings->setVacationDaysPerYear($vacationDaysPerYear ?? 20);
        }
        
        if ($employmentStart) {
            $settings->setEmploymentStart(new DateTime($employmentStart));
        }
        
        $settings->setUpdatedAt(new DateTime());
        
        if ($settings->getId()) {
            $result = $this->mapper->update($settings);
        } else {
            $result = $this->mapper->insert($settings);
        }
        
        return new DataResponse($result);
    }

    /**
     * @NoAdminRequired
     * 
     * Create a new settings period for a user
     */
    public function create(
        string $employmentType,
        float $weeklyHours,
        string $validFrom,
        ?float $maxTotalHours = null,
        ?int $vacationDaysPerYear = null,
        ?float $hourlyRate = null,
        ?string $employmentStart = null,
        ?string $validTo = null,
        ?string $targetUserId = null
    ): DataResponse {
        // Only admins can create settings
        if (!$this->groupManager->isAdmin($this->userId)) {
            return new DataResponse(['error' => 'Only administrators can modify employee settings'], 403);
        }
        
        // Validate employment type
        if (!in_array($employmentType, ['director', 'contract', 'freelance', 'student'])) {
            return new DataResponse(['error' => 'Invalid employment type'], 400);
        }
        
        $userIdToCreate = $targetUserId ?? $this->userId;
        $validFromDate = new DateTime($validFrom);
        $validToDate = $validTo ? new DateTime($validTo) : null;
        
        // Close any existing open-ended period
        $this->mapper->closePreviousPeriod($userIdToCreate, $validFromDate);
        
        // Create new period
        $settings = new EmployeeSettings();
        $settings->setUserId($userIdToCreate);
        $settings->setEmploymentType($employmentType);
        $settings->setWeeklyHours($weeklyHours);
        $settings->setMaxTotalHours($maxTotalHours);
        $settings->setHourlyRate($hourlyRate);
        $settings->setValidFrom($validFromDate);
        $settings->setValidTo($validToDate);
        
        if ($employmentType === 'freelance') {
            $settings->setVacationDaysPerYear(0);
        } else {
            $settings->setVacationDaysPerYear($vacationDaysPerYear ?? 20);
        }
        
        if ($employmentStart) {
            $settings->setEmploymentStart(new DateTime($employmentStart));
        }
        
        $settings->setCreatedAt(new DateTime());
        $settings->setUpdatedAt(new DateTime());
        
        $result = $this->mapper->insert($settings);
        
        return new DataResponse($result);
    }

    /**
     * @NoAdminRequired
     * 
     * Delete a settings period
     */
    public function destroy(int $id): DataResponse {
        // Only admins can delete settings
        if (!$this->groupManager->isAdmin($this->userId)) {
            return new DataResponse(['error' => 'Only administrators can modify employee settings'], 403);
        }
        
        try {
            $settings = $this->mapper->find($id);
            $this->mapper->delete($settings);
            return new DataResponse(['success' => true]);
        } catch (\Exception $e) {
            return new DataResponse(['error' => 'Period not found'], 404);
        }
    }
}

