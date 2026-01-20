<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Remove unused federal_state column from tt_emp_settings table
 */
class Version1007Date20260120000001 extends SimpleMigrationStep {
    
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('tt_emp_settings')) {
            $table = $schema->getTable('tt_emp_settings');
            if ($table->hasColumn('federal_state')) {
                $table->dropColumn('federal_state');
                return $schema;
            }
        }

        return null;
    }
}
