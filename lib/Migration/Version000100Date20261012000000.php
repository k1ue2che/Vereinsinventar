<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Vereinsinventar\Migration;
use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
final class Version000100Date20261012000000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();
        if ($schema->hasTable('vi_nodes')) {
            return null;
        }
        $table = $schema->createTable('vi_nodes');
        $table->addColumn('id', 'bigint', ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('group_id', 'string', ['length' => 255, 'notnull' => true]);
        $table->addColumn('parent_id', 'bigint', ['default' => 0, 'notnull' => true]);
        $table->addColumn('kind', 'string', ['length' => 10, 'notnull' => true]);
        $table->addColumn('name', 'string', ['length' => 200, 'notnull' => true]);
        $table->addColumn('quantity', 'integer', ['default' => 1, 'notnull' => true]);
        $table->addColumn('inventory_no', 'string', ['length' => 100, 'default' => '', 'notnull' => true]);
        $table->addColumn('location', 'string', ['length' => 200, 'default' => '', 'notnull' => true]);
        $table->addColumn('notes', 'text', ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['group_id', 'parent_id'], 'vi_group_parent');
        return $schema;
    }
}
