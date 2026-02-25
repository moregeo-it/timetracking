<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add require_description column to tt_projects.
 * When true, time entries for this project must have a non-empty description.
 * Default: false (description not required).
 */
class Version1014Date20260225000000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $table = $schema->getTable('tt_projects');

        if (!$table->hasColumn('require_description')) {
            $table->addColumn('require_description', Types::BOOLEAN, [
                'notnull' => false,
                'default' => false,
            ]);
        }

        return $schema;
    }
}
