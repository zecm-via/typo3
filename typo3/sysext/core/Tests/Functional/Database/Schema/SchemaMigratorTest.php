<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace TYPO3\CMS\Core\Tests\Functional\Database\Schema;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\Type;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Schema\SchemaDiff;
use TYPO3\CMS\Core\Database\Schema\SchemaMigrator;
use TYPO3\CMS\Core\Database\Schema\SqlReader;
use TYPO3\CMS\Core\Database\Schema\TableDiff;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class SchemaMigratorTest extends FunctionalTestCase
{
    private SqlReader $sqlReader;
    private ConnectionPool $connectionPool;
    private AbstractSchemaManager $schemaManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sqlReader = $this->get(SqlReader::class);
        $this->connectionPool = $this->get(ConnectionPool::class);
        $this->schemaManager = $this->connectionPool->getConnectionByName('Default')->createSchemaManager();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Clean up for next test
        if ($this->schemaManager->tablesExist(['a_test_table'])) {
            $this->schemaManager->dropTable('a_test_table');
        }
        if ($this->schemaManager->tablesExist(['zzz_deleted_a_test_table'])) {
            $this->schemaManager->dropTable('zzz_deleted_a_test_table');
        }
        if ($this->schemaManager->tablesExist(['another_test_table'])) {
            $this->schemaManager->dropTable('another_test_table');
        }
    }

    /**
     * Create the base table for all migration tests
     */
    private function prepareTestTable(SchemaMigrator $schemaMigrator): void
    {
        $sqlCode = file_get_contents(__DIR__ . '/../Fixtures/newTable.sql');
        $schemaMigrator->install($this->sqlReader->getCreateTableStatementArray($sqlCode));
    }

    /**
     * Helper to return the Doctrine Table object for the test table
     */
    private function getTableDetails(): Table
    {
        return $this->schemaManager->introspectTable('a_test_table');
    }

    /**
     * @test
     */
    public function createNewTable(): void
    {
        $subject = $this->get(SchemaMigrator::class);
        $statements = $this->sqlReader->getCreateTableStatementArray(file_get_contents(__DIR__ . '/../Fixtures/newTable.sql'));
        $updateSuggestions = $subject->getUpdateSuggestions($statements);
        $result = $subject->migrate(
            $statements,
            $updateSuggestions[ConnectionPool::DEFAULT_CONNECTION_NAME]['create_table']
        );
        self::assertCount(6, $this->getTableDetails()->getColumns());
    }

    /**
     * @test
     */
    public function createNewTableIfNotExists(): void
    {
        $subject = $this->get(SchemaMigrator::class);
        $statements = $this->sqlReader->getCreateTableStatementArray(file_get_contents(__DIR__ . '/../Fixtures/ifNotExists.sql'));
        $updateSuggestions = $subject->getUpdateSuggestions($statements);
        $subject->migrate(
            $statements,
            $updateSuggestions[ConnectionPool::DEFAULT_CONNECTION_NAME]['create_table']
        );
        self::assertTrue($this->schemaManager->tablesExist(['another_test_table']));
    }

    /**
     * @test
     */
    public function addNewColumns(): void
    {
        $subject = $this->get(SchemaMigrator::class);
        $this->prepareTestTable($subject);
        $statements = $this->sqlReader->getCreateTableStatementArray(file_get_contents(__DIR__ . '/../Fixtures/addColumnsToTable.sql'));
        $updateSuggestions = $subject->getUpdateSuggestions($statements);
        $subject->migrate(
            $statements,
            $updateSuggestions[ConnectionPool::DEFAULT_CONNECTION_NAME]['add']
        );
        self::assertCount(7, $this->getTableDetails()->getColumns());
        self::assertTrue($this->getTableDetails()->hasColumn('title'));
        self::assertTrue($this->getTableDetails()->hasColumn('description'));
    }

    /**
     * @test
     */
    public function changeExistingColumn(): void
    {
        $subject = $this->get(SchemaMigrator::class);
        $this->prepareTestTable($subject);
        $statements = $this->sqlReader->getCreateTableStatementArray(file_get_contents(__DIR__ . '/../Fixtures/changeExistingColumn.sql'));
        $updateSuggestions = $subject->getUpdateSuggestions($statements);
        self::assertEquals(50, $this->getTableDetails()->getColumn('title')->getLength());
        self::assertEmpty($this->getTableDetails()->getColumn('title')->getDefault());
        $subject->migrate(
            $statements,
            $updateSuggestions[ConnectionPool::DEFAULT_CONNECTION_NAME]['change']
        );
        self::assertEquals(100, $this->getTableDetails()->getColumn('title')->getLength());
        self::assertEquals('Title', $this->getTableDetails()->getColumn('title')->getDefault());
    }

    /**
     * @test
     */
    public function notNullWithoutDefaultValue(): void
    {
        $subject = $this->get(SchemaMigrator::class);
        $this->prepareTestTable($subject);
        $statements = $this->sqlReader->getCreateTableStatementArray(file_get_contents(__DIR__ . '/../Fixtures/notNullWithoutDefaultValue.sql'));
        $updateSuggestions = $subject->getUpdateSuggestions($statements);
        $subject->migrate(
            $statements,
            $updateSuggestions[ConnectionPool::DEFAULT_CONNECTION_NAME]['add']
        );
        self::assertTrue($this->getTableDetails()->getColumn('aTestField')->getNotnull());
    }

    /**
     * @test
     */
    public function defaultNullWithoutNotNull(): void
    {
        $subject = $this->get(SchemaMigrator::class);
        $this->prepareTestTable($subject);
        $statements = $this->sqlReader->getCreateTableStatementArray(file_get_contents(__DIR__ . '/../Fixtures/defaultNullWithoutNotNull.sql'));
        $updateSuggestions = $subject->getUpdateSuggestions($statements);
        $subject->migrate(
            $statements,
            $updateSuggestions[ConnectionPool::DEFAULT_CONNECTION_NAME]['add']
        );
        self::assertFalse($this->getTableDetails()->getColumn('aTestField')->getNotnull());
        self::assertNull($this->getTableDetails()->getColumn('aTestField')->getDefault());
    }

    /**
     * @test
     */
    public function renameUnusedField(): void
    {
        $subject = $this->get(SchemaMigrator::class);
        $this->prepareTestTable($subject);
        $statements = $this->sqlReader->getCreateTableStatementArray(file_get_contents(__DIR__ . '/../Fixtures/unusedColumn.sql'));
        $updateSuggestions = $subject->getUpdateSuggestions($statements, true);
        $subject->migrate(
            $statements,
            $updateSuggestions[ConnectionPool::DEFAULT_CONNECTION_NAME]['change']
        );
        self::assertFalse($this->getTableDetails()->hasColumn('hidden'));
        self::assertTrue($this->getTableDetails()->hasColumn('zzz_deleted_hidden'));
    }

    /**
     * @test
     */
    public function renameUnusedTable(): void
    {
        $subject = $this->get(SchemaMigrator::class);
        $this->prepareTestTable($subject);
        $statements = $this->sqlReader->getCreateTableStatementArray(file_get_contents(__DIR__ . '/../Fixtures/unusedTable.sql'));
        $updateSuggestions = $subject->getUpdateSuggestions($statements, true);
        $subject->migrate(
            $statements,
            $updateSuggestions[ConnectionPool::DEFAULT_CONNECTION_NAME]['change_table']
        );
        self::assertNotContains('a_test_table', $this->schemaManager->listTableNames());
        self::assertContains('zzz_deleted_a_test_table', $this->schemaManager->listTableNames());
    }

    /**
     * @test
     */
    public function dropUnusedField(): void
    {
        $subject = $this->get(SchemaMigrator::class);
        $this->prepareTestTable($subject);
        $connection = $this->connectionPool->getConnectionForTable('a_test_table');
        $fromSchema = $this->schemaManager->introspectSchema();
        $tableDiff = new TableDiff(
            oldTable: $fromSchema->getTable('a_test_table'),
            addedColumns: ['zzz_deleted_testfield' => new Column('zzz_deleted_testfield', Type::getType('integer'), ['notnull' => false])],
            modifiedColumns: [],
            droppedColumns: [],
            renamedColumns: [],
            addedIndexes: [],
            modifiedIndexes: [],
            droppedIndexes: [],
            renamedIndexes: [],
            addedForeignKeys: [],
            modifiedForeignKeys: [],
            droppedForeignKeys: [],
            tableOptions: [],
        );
        $schemaDiff = new SchemaDiff(
            createdSchemas: [],
            droppedSchemas: [],
            createdTables: [],
            alteredTables: ['a_test_table' => $tableDiff],
            droppedTables: [],
            createdSequences: [],
            alteredSequences: [],
            droppedSequences: [],
        );
        foreach ($connection->getDatabasePlatform()->getAlterSchemaSQL($schemaDiff) as $statement) {
            $connection->executeStatement($statement);
        }
        self::assertTrue($this->getTableDetails()->hasColumn('zzz_deleted_testfield'));
        $statements = $this->sqlReader->getCreateTableStatementArray(file_get_contents(__DIR__ . '/../Fixtures/newTable.sql'));
        $updateSuggestions = $subject->getUpdateSuggestions($statements, true);
        $subject->migrate(
            $statements,
            $updateSuggestions[ConnectionPool::DEFAULT_CONNECTION_NAME]['drop']
        );
        self::assertFalse($this->getTableDetails()->hasColumn('zzz_deleted_testfield'));
    }

    /**
     * @test
     */
    public function dropUnusedTable(): void
    {
        $subject = $this->get(SchemaMigrator::class);
        $this->prepareTestTable($subject);
        $this->schemaManager->renameTable('a_test_table', 'zzz_deleted_a_test_table');
        self::assertNotContains('a_test_table', $this->schemaManager->listTableNames());
        self::assertContains('zzz_deleted_a_test_table', $this->schemaManager->listTableNames());
        $statements = $this->sqlReader->getCreateTableStatementArray(file_get_contents(__DIR__ . '/../Fixtures/newTable.sql'));
        $updateSuggestions = $subject->getUpdateSuggestions($statements, true);
        $subject->migrate(
            $statements,
            $updateSuggestions[ConnectionPool::DEFAULT_CONNECTION_NAME]['drop_table']
        );
        self::assertNotContains('a_test_table', $this->schemaManager->listTableNames());
        self::assertNotContains('zzz_deleted_a_test_table', $this->schemaManager->listTableNames());
    }

    /**
     * @test
     * @group not-postgres
     * @group not-sqlite
     */
    public function installPerformsOnlyAddAndCreateOperations(): void
    {
        $subject = $this->get(SchemaMigrator::class);
        $this->prepareTestTable($subject);
        $statements = $this->sqlReader->getCreateTableStatementArray(file_get_contents(__DIR__ . '/../Fixtures/addCreateChange.sql'));
        $subject->install($statements, true);
        self::assertContains('another_test_table', $this->schemaManager->listTableNames());
        self::assertTrue($this->getTableDetails()->hasColumn('title'));
        self::assertTrue($this->getTableDetails()->hasIndex('title'));
        self::assertTrue($this->getTableDetails()->getIndex('title')->isUnique());
        self::assertNotInstanceOf(BigIntType::class, $this->getTableDetails()->getColumn('pid')->getType());
    }

    /**
     * @test
     */
    public function installDoesNotAddIndexOnChangedColumn(): void
    {
        $subject = $this->get(SchemaMigrator::class);
        $this->prepareTestTable($subject);
        $statements = $this->sqlReader->getCreateTableStatementArray(file_get_contents(__DIR__ . '/../Fixtures/addIndexOnChangedColumn.sql'));
        $subject->install($statements, true);
        self::assertNotInstanceOf(TextType::class, $this->getTableDetails()->getColumn('title')->getType());
        self::assertFalse($this->getTableDetails()->hasIndex('title'));
    }

    /**
     * @test
     */
    public function changeExistingIndex(): void
    {
        $subject = $this->get(SchemaMigrator::class);
        $this->prepareTestTable($subject);
        $statements = $this->sqlReader->getCreateTableStatementArray(file_get_contents(__DIR__ . '/../Fixtures/changeExistingIndex.sql'));
        $updateSuggestions = $subject->getUpdateSuggestions($statements);
        $result = $subject->migrate(
            $statements,
            $updateSuggestions[ConnectionPool::DEFAULT_CONNECTION_NAME]['change']
        );
        $indexesAfterChange = $this->schemaManager->listTableIndexes('a_test_table');
        // indexes could be sorted differently thus we filter for index named "parent" only and
        // use that as index to retrieve the modified columns of that index
        $parentIndex = array_values(
            array_filter(
                array_keys($indexesAfterChange),
                static function ($key) {
                    return str_contains($key, 'parent');
                }
            )
        );
        $expectedColumnsOfChangedIndex = [
            'pid',
            'deleted',
        ];
        self::assertEquals($expectedColumnsOfChangedIndex, $indexesAfterChange[$parentIndex[0]]->getColumns());
    }

    /**
     * @test
     * @group not-postgres
     * @group not-sqlite
     */
    public function installCanPerformChangeOperations(): void
    {
        $subject = $this->get(SchemaMigrator::class);
        $this->prepareTestTable($subject);
        $statements = $this->sqlReader->getCreateTableStatementArray(file_get_contents(__DIR__ . '/../Fixtures/addCreateChange.sql'));
        $subject->install($statements);
        self::assertContains('another_test_table', $this->schemaManager->listTableNames());
        self::assertTrue($this->getTableDetails()->hasColumn('title'));
        self::assertTrue($this->getTableDetails()->hasIndex('title'));
        self::assertTrue($this->getTableDetails()->getIndex('title')->isUnique());
        self::assertInstanceOf(BigIntType::class, $this->getTableDetails()->getColumn('pid')->getType());
    }

    /**
     * @test
     * @group not-postgres
     */
    public function importStaticDataInsertsRecords(): void
    {
        $subject = $this->get(SchemaMigrator::class);
        $this->prepareTestTable($subject);
        $sqlCode = file_get_contents(__DIR__ . '/../Fixtures/importStaticData.sql');
        $connection = $this->connectionPool->getConnectionForTable('a_test_table');
        $statements = $this->sqlReader->getInsertStatementArray($sqlCode);
        $subject->importStaticData($statements);
        self::assertEquals(2, $connection->count('*', 'a_test_table', []));
    }

    /**
     * @test
     */
    public function importStaticDataIgnoresTableDefinitions(): void
    {
        $subject = $this->get(SchemaMigrator::class);
        $sqlCode = file_get_contents(__DIR__ . '/../Fixtures/importStaticData.sql');
        $statements = $this->sqlReader->getStatementArray($sqlCode);
        $subject->importStaticData($statements);
        self::assertNotContains('another_test_table', $this->schemaManager->listTableNames());
    }

    /**
     * @test
     * @group not-postgres
     * @group not-sqlite
     */
    public function changeTableEngine(): void
    {
        $subject = $this->get(SchemaMigrator::class);
        $this->prepareTestTable($subject);
        $statements = $this->sqlReader->getCreateTableStatementArray(file_get_contents(__DIR__ . '/../Fixtures/alterTableEngine.sql'));
        $updateSuggestions = $subject->getUpdateSuggestions($statements);
        $index = array_keys($updateSuggestions[ConnectionPool::DEFAULT_CONNECTION_NAME]['change'])[0];
        self::assertStringEndsWith(
            'ENGINE = MyISAM',
            $updateSuggestions[ConnectionPool::DEFAULT_CONNECTION_NAME]['change'][$index]
        );
        $subject->migrate(
            $statements,
            $updateSuggestions[ConnectionPool::DEFAULT_CONNECTION_NAME]['change']
        );
        $updateSuggestions = $subject->getUpdateSuggestions($statements);
        self::assertEmpty($updateSuggestions[ConnectionPool::DEFAULT_CONNECTION_NAME]['change']);
        self::assertEmpty($updateSuggestions[ConnectionPool::DEFAULT_CONNECTION_NAME]['change']);
    }
}
