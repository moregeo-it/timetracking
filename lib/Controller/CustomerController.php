<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Controller;

use OCA\TimeTracking\Db\Customer;
use OCA\TimeTracking\Db\CustomerMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IGroupManager;

class CustomerController extends Controller {
    private CustomerMapper $mapper;
    private IGroupManager $groupManager;
    private string $userId;

    public function __construct(
        string $appName,
        IRequest $request,
        CustomerMapper $mapper,
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
    public function index(): DataResponse {
        return new DataResponse($this->mapper->findAll());
    }

    /**
     * @NoAdminRequired
     */
    public function show(int $id): DataResponse {
        try {
            return new DataResponse($this->mapper->find($id));
        } catch (\Exception $e) {
            return new DataResponse(['error' => 'Customer not found'], 404);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function create(string $name, ?string $currency = 'EUR'): DataResponse {
        if (!$this->isAdmin()) {
            return new DataResponse(['error' => 'Only administrators can create customers'], 403);
        }
        $customer = new Customer();
        $customer->setName($name);
        $customer->setActive(true);
        $customer->setCurrency($currency ?? 'EUR');
        $customer->setCreatedAt(new \DateTime());
        $customer->setUpdatedAt(new \DateTime());
        
        return new DataResponse($this->mapper->insert($customer));
    }

    /**
     * @NoAdminRequired
     */
    public function update(int $id, ?string $name = null, ?bool $active = null, ?string $currency = null): DataResponse {
        if (!$this->isAdmin()) {
            return new DataResponse(['error' => 'Only administrators can update customers'], 403);
        }
        
        try {
            $customer = $this->mapper->find((int)$id);
            if ($name !== null) {
                $customer->setName($name);
            }
            if ($active !== null) {
                $customer->setActive($active);
            }
            if ($currency !== null) {
                $customer->setCurrency($currency);
            }
            $customer->setUpdatedAt(new \DateTime());
            
            return new DataResponse($this->mapper->update($customer));
        } catch (\Exception $e) {
            return new DataResponse(['error' => 'Customer not found: ' . $e->getMessage()], 404);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function delete(int $id): DataResponse {
        if (!$this->isAdmin()) {
            return new DataResponse(['error' => 'Only administrators can delete customers'], 403);
        }
        try {
            $customer = $this->mapper->find($id);
            $this->mapper->delete($customer);
            return new DataResponse(['success' => true]);
        } catch (\Exception $e) {
            return new DataResponse(['error' => 'Customer not found'], 404);
        }
    }
}

