<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Remove unused duration_minutes column from tt_entries table
 */
class Version1006Date20260120000000 extends SimpleMigrationStep {
    
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('tt_entries')) {
            $table = $schema->getTable('tt_entries');
            if ($table->hasColumn('duration_minutes')) {
                $table->dropColumn('duration_minutes');
                return $schema;
            }
        }

        return null;
    }
}
