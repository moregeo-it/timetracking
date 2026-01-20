<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Remove recurring column from tt_public_holidays table
 * Holidays are now imported fully for each year
 */
class Version1009Date20260120000003 extends SimpleMigrationStep {
    
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('tt_public_holidays')) {
            $table = $schema->getTable('tt_public_holidays');
            if ($table->hasColumn('recurring')) {
                $table->dropColumn('recurring');
                return $schema;
            }
        }

        return null;
    }
}
