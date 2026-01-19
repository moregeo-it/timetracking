<?php
declare(strict_types=1);

namespace OCA\TimeTracking\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

// Load composer autoloader for TCPDF and other dependencies
require_once __DIR__ . '/../../vendor/autoload.php';

class Application extends App implements IBootstrap {
    public const APP_ID = 'timetracking';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        // Register services, event listeners, etc.
    }

    public function boot(IBootContext $context): void {
        // Boot logic if needed
    }
}

