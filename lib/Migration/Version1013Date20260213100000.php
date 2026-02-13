<?php
declare(strict_types=1);

namespace OCA\TimeTracking\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add sick_note_required_day column to tt_emp_settings.
 * 
 * Per German law (EFZG §5), the default is that employees must present a
 * Arbeitsunfähigkeitsbescheinigung (AU) by the 4th calendar day of illness.
 * However, the employer can require it earlier (from day 1).
 * This column stores the day number at which the AU is required.
 */
class Version1013Date20260213100000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $table = $schema->getTable('tt_emp_settings');

        if (!$table->hasColumn('sick_note_required_day')) {
            $table->addColumn('sick_note_required_day', Types::INTEGER, [
                'notnull' => false,
                'default' => 4,
                'unsigned' => true,
            ]);
        }

        return $schema;
    }
}
