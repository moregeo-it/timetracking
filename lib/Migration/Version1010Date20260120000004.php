<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add valid_from and valid_to columns to employee settings for time-period based settings
 */
class Version1010Date20260120000004 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('tt_emp_settings')) {
            $table = $schema->getTable('tt_emp_settings');
            
            // Add valid_from column (date when these settings become effective)
            if (!$table->hasColumn('valid_from')) {
                $table->addColumn('valid_from', Types::DATE, [
                    'notnull' => false,
                    'default' => null,
                ]);
            }
            
            // Add valid_to column (date when these settings end, null = indefinite/current)
            if (!$table->hasColumn('valid_to')) {
                $table->addColumn('valid_to', Types::DATE, [
                    'notnull' => false,
                    'default' => null,
                ]);
            }
            
            // Add index for efficient date-based queries
            // Note: We don't drop the unique constraint on user_id since 
            // we need to handle this in application logic now
        }

        return $schema;
    }
    
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
        // Migrate existing data: set valid_from to employment_start for existing records
        // This will be handled by application logic - existing records without valid_from
        // are treated as "current" settings
    }
}
