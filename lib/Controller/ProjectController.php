<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Controller;

use OCA\TimeTracking\Db\Project;
use OCA\TimeTracking\Db\ProjectMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IGroupManager;

class ProjectController extends Controller {
    private ProjectMapper $mapper;
    private IGroupManager $groupManager;
    private string $userId;

    public function __construct(
        string $appName,
        IRequest $request,
        ProjectMapper $mapper,
        IGroupManager $groupManager,
        string $userId
    ) {
        parent::__construct($appName, $request);
        $this->mapper = $mapper;
        $this->groupManager = $groupManager;
        $this->userId = $userId;
    }

    private function isAdmin(): bool {
        return $this->groupManager->isAdmin($this->userId);
    }

    /**
     * @NoAdminRequired
     */
    public function index(?int $customerId = null): DataResponse {
        if ($customerId) {
            return new DataResponse($this->mapper->findByCustomer($customerId));
        }
        return new DataResponse($this->mapper->findAll());
    }

    /**
     * @NoAdminRequired
     */
    public function show(int $id): DataResponse {
        try {
            return new DataResponse($this->mapper->find($id));
        } catch (\Exception $e) {
            return new DataResponse(['error' => 'Project not found'], 404);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function create(int $customerId, string $name, ?string $description = null,
                          ?float $hourlyRate = null, ?float $budgetHours = null): DataResponse {
        if (!$this->isAdmin()) {
            return new DataResponse(['error' => 'Only administrators can create projects'], 403);
        }
        $project = new Project();
        $project->setCustomerId($customerId);
        $project->setName($name);
        $project->setDescription($description);
        $project->setHourlyRate($hourlyRate);
        $project->setBudgetHours($budgetHours);
        $project->setActive(true);
        $project->setCreatedAt(new \DateTime());
        $project->setUpdatedAt(new \DateTime());
        
        return new DataResponse($this->mapper->insert($project));
    }

    /**
     * @NoAdminRequired
     */
    public function update(int $id, string $name, ?string $description = null,
                          ?float $hourlyRate = null, ?float $budgetHours = null, ?bool $active = null): DataResponse {
        if (!$this->isAdmin()) {
            return new DataResponse(['error' => 'Only administrators can update projects'], 403);
        }
        
        try {
            $project = $this->mapper->find($id);
            $project->setName($name);
            $project->setDescription($description);
            $project->setHourlyRate($hourlyRate);
            $project->setBudgetHours($budgetHours);
            if ($active !== null) {
                $project->setActive($active);
            }
            $project->setUpdatedAt(new \DateTime());
            
            return new DataResponse($this->mapper->update($project));
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
            $this->mapper->delete($project);
            return new DataResponse(['success' => true]);
        } catch (\Exception $e) {
            return new DataResponse(['error' => 'Project not found'], 404);
        }
    }
}

