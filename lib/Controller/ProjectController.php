<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Controller;

use OCA\TimeTracking\Db\EmployeeSettings;
use OCA\TimeTracking\Db\Project;
use OCA\TimeTracking\Db\ProjectMapper;
use OCA\TimeTracking\Db\ProjectMultiplier;
use OCA\TimeTracking\Db\ProjectMultiplierMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IGroupManager;

class ProjectController extends Controller {
    private ProjectMapper $mapper;
    private ProjectMultiplierMapper $multiplierMapper;
    private IGroupManager $groupManager;
    private string $userId;

    public function __construct(
        string $appName,
        IRequest $request,
        ProjectMapper $mapper,
        ProjectMultiplierMapper $multiplierMapper,
        IGroupManager $groupManager,
        string $userId
    ) {
        parent::__construct($appName, $request);
        $this->mapper = $mapper;
        $this->multiplierMapper = $multiplierMapper;
        $this->groupManager = $groupManager;
        $this->userId = $userId;
    }

    private function isAdmin(): bool {
        return $this->groupManager->isAdmin($this->userId);
    }

    /**
     * Checks if a project's end date has passed and deactivates it if necessary
     */
    private function checkAndDeactivateExpiredProject(Project $project): Project {
        if ($project->getActive() && $project->getEndDate()) {
            $endDate = new \DateTime($project->getEndDate());
            $today = new \DateTime('today');
            
            if ($endDate < $today) {
                $project->setActive(false);
                $project->setUpdatedAt(new \DateTime());
                $this->mapper->update($project);
            }
        }
        return $project;
    }

    /**
     * Get project data with multipliers included
     */
    private function getProjectWithMultipliers(Project $project, bool $includeHourlyRate = true): array {
        $data = $project->jsonSerialize();
        if (!$includeHourlyRate) {
            unset($data['hourlyRate']);
        }
        // Add multipliers from normalized table
        $data['multipliers'] = $this->multiplierMapper->getMultipliersAsArray($project->getId());
        return $data;
    }

    /**
     * @NoAdminRequired
     */
    public function index(?int $customerId = null): DataResponse {
        $isAdmin = $this->isAdmin();
        $projects = $customerId ? $this->mapper->findByCustomer($customerId) : $this->mapper->findAll();
        $result = array_map(function ($project) use ($isAdmin) {
            // Check and deactivate if end date has passed
            $project = $this->checkAndDeactivateExpiredProject($project);
            return $this->getProjectWithMultipliers($project, $isAdmin);
        }, $projects);
        return new DataResponse($result);
    }

    /**
     * @NoAdminRequired
     */
    public function show(int $id): DataResponse {
        try {
            $project = $this->mapper->find($id);
            
            // Check and deactivate if end date has passed
            $project = $this->checkAndDeactivateExpiredProject($project);
            
            return new DataResponse($this->getProjectWithMultipliers($project, $this->isAdmin()));
        } catch (\Exception $e) {
            return new DataResponse(['error' => 'Project not found'], 404);
        }
    }

    /**
     * Validate and normalize a multiplier value.
     * Multiplier must be > 0 and <= 2, default is 1.0
     */
    private function validateMultiplier(?float $value): float {
        if ($value === null) {
            return 1.0;
        }
        return max(0.01, min(2.0, $value));
    }

    /**
     * @NoAdminRequired
     */
    public function create(int $customerId, string $name, ?string $description = null,
                          ?float $hourlyRate = null, ?float $budgetHours = null,
                          ?string $startDate = null, ?string $endDate = null,
                          ?array $multipliers = null): DataResponse {
        if (!$this->isAdmin()) {
            return new DataResponse(['error' => 'Only administrators can create projects'], 403);
        }
        $project = new Project();
        $project->setCustomerId($customerId);
        $project->setName($name);
        $project->setDescription($description);
        $project->setHourlyRate($hourlyRate);
        $project->setBudgetHours($budgetHours);
        $project->setStartDate($startDate);
        $project->setEndDate($endDate);
        $project->setActive(true);
        $project->setCreatedAt(new \DateTime());
        $project->setUpdatedAt(new \DateTime());
        
        $insertedProject = $this->mapper->insert($project);
        
        // Save multipliers to normalized table
        if ($multipliers !== null) {
            $this->multiplierMapper->setMultipliers($insertedProject->getId(), $multipliers);
        }
        
        return new DataResponse($this->getProjectWithMultipliers($insertedProject));
    }

    /**
     * @NoAdminRequired
     */
    public function update(int $id, string $name, ?string $description = null,
                          ?float $hourlyRate = null, ?float $budgetHours = null,
                          ?string $startDate = null, ?string $endDate = null, ?bool $active = null,
                          ?array $multipliers = null): DataResponse {
        if (!$this->isAdmin()) {
            return new DataResponse(['error' => 'Only administrators can update projects'], 403);
        }
        
        try {
            $project = $this->mapper->find($id);
            $project->setName($name);
            $project->setDescription($description);
            $project->setHourlyRate($hourlyRate);
            $project->setBudgetHours($budgetHours);
            $project->setStartDate($startDate);
            $project->setEndDate($endDate);
            if ($active !== null) {
                $project->setActive($active);
            }
            $project->setUpdatedAt(new \DateTime());
            
            $updatedProject = $this->mapper->update($project);
            
            // Update multipliers in normalized table
            if ($multipliers !== null) {
                $this->multiplierMapper->setMultipliers($id, $multipliers);
            }
            
            return new DataResponse($this->getProjectWithMultipliers($updatedProject));
        } catch (\Exception $e) {
            return new DataResponse(['error' => 'Project not found'], 404);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function delete(int $id): DataResponse {
        if (!$this->isAdmin()) {
            return new DataResponse(['error' => 'Only administrators can delete projects'], 403);
        }
        try {
            $project = $this->mapper->find($id);
            // Delete multipliers first
            $this->multiplierMapper->deleteByProject($id);
            $this->mapper->delete($project);
            return new DataResponse(['success' => true]);
        } catch (\Exception $e) {
            return new DataResponse(['error' => 'Project not found'], 404);
        }
    }
}

