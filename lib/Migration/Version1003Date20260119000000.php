<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migration to:
 * - Remove date column from tt_entries (redundant with start_time)
 * - Convert start_time and end_time to BIGINT (Unix timestamps for timezone independence)
 */
class Version1003Date20260119000000 extends SimpleMigrationStep {
    
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('tt_entries')) {
            $table = $schema->getTable('tt_entries');
            
            // Add new timestamp columns (BIGINT for Unix timestamps - timezone independent)
            if (!$table->hasColumn('start_timestamp')) {
                $table->addColumn('start_timestamp', Types::BIGINT, [
                    'notnull' => false,
                    'unsigned' => true,
                ]);
            }
            
            if (!$table->hasColumn('end_timestamp')) {
                $table->addColumn('end_timestamp', Types::BIGINT, [
                    'notnull' => false,
                    'unsigned' => true,
                ]);
            }
        }

        return $schema;
    }
}
