<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Fix unique constraint on tt_emp_settings to allow multiple employment
 * periods per user. The old constraint only covered user_id, preventing
 * a second row even when the previous period has been closed.
 * The new composite index covers (user_id, valid_from).
 */
class Version1015Date20260402000000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $table = $schema->getTable('tt_emp_settings');

        if ($table->hasIndex('idx_employee_user_unique')) {
            $table->dropIndex('idx_employee_user_unique');
        }

        $table->addUniqueIndex(['user_id', 'valid_from'], 'idx_emp_user_from_uq');

        return $schema;
    }
}
