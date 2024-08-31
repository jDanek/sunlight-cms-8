<?php

namespace Sunlight\Database;

use Sunlight\Database\Database as DB;
use Sunlight\Util\Filesystem;
use Sunlight\Util\TemporaryFile;

/**
 * Database dumper
 *
 * Dumps given tables and/or rows to a temporary SQL dump file.
 */
class SqlDumper
{
    private array $tables = [];
    private bool $dumpData = true;
    private bool $dumpTables = true;
    private ?int $maxPacketSize = null;

    /**
     * Dump tables and/or data
     *
     * @throws DatabaseException on failure
     */
    function dump(): TemporaryFile
    {
        $tmpFile = Filesystem::createTmpFile();
        $handle = fopen($tmpFile, 'wb');

        if ($this->dumpTables) {
            $this->dumpTables($handle);
        }

        if ($this->dumpData) {
            $this->dumpData($handle);
        }

        fclose($handle);

        return $tmpFile;
    }

    /**
     * Add table to dump
     */
    function addTable(string $table): void
    {
        $this->tables[] = $table;
    }

    /**
     * Add tables to dump
     *
     * @param string[] $tables
     */
    function addTables(array $tables): void
    {
        foreach ($tables as $table) {
            $this->tables[] = $table;
        }
    }

    /**
     * Set whether data should be dumped
     */
    function setDumpData(bool $dumpData): void
    {
        $this->dumpData = $dumpData;
    }

    /**
     * Set whether table definitions should be dumped
     */
    function setDumpTables(bool $dumpTables): void
    {
        $this->dumpTables = $dumpTables;
    }

    /**
     * Get max packet size
     */
    function getMaxPacketSize(): int
    {
        if ($this->maxPacketSize === null) {
            // determine max packet size
            $maxAllowedPacket = DB::queryRow('SHOW VARIABLES WHERE Variable_name=\'max_allowed_packet\'');

            if ($maxAllowedPacket === false) {
                throw new DatabaseException('Could not determine value of the "max_allowed_packet" variable');
            }

            // use 16MB or the server's value, if smaller
            $this->maxPacketSize = min((int) $maxAllowedPacket['Value'], 16777216);
        }

        return $this->maxPacketSize;
    }

    /**
     * Set max packet size
     */
    function setMaxPacketSize(?int $maxPacketSize): void
    {
        $this->maxPacketSize = $maxPacketSize;
    }

    /**
     * Dump tables
     *
     * @param resource $handle
     */
    private function dumpTables($handle): void
    {
        foreach ($this->tables as $table) {
            $createTable = DB::queryRow('SHOW CREATE TABLE `' . $table . '`');

            if ($createTable === false || !isset($createTable['Create Table'])) {
                throw new DatabaseException(sprintf('SHOW CREATE TABLE failed for "%s"', $table));
            }

            fwrite($handle, $createTable['Create Table']);
            fwrite($handle, ";\n");
        }
    }

    /**
     * Dump data
     *
     * @param resource $handle
     */
    private function dumpData($handle): void
    {
        foreach ($this->tables as $table) {
            $columns = $this->getTableColumns($table);
            $result = DB::query('SELECT * FROM `' . $table . '`');

            $this->dumpTableData($handle, $table, $columns, $result);
        }
    }

    /**
     * Dump table data
     *
     * @param resource $handle
     */
    private function dumpTableData($handle, string $table, array $columns, $result): void
    {
        $columnList = DB::idtList(array_keys($columns));
        $insertStatement = 'INSERT INTO `' . $table . '` (' . $columnList . ') VALUES ';
        $insertStatementSize = strlen($insertStatement);

        $currentQuerySize = 0;
        $maxPacketSize = $this->getMaxPacketSize();
        $writtenInsertSyntax = false;
        $isFirstRowStatement = false;

        while ($rowx = DB::row($result)) {
            // write initial insert statement
            if (!$writtenInsertSyntax) {
                $currentQuerySize += fwrite($handle, $insertStatement);
                $writtenInsertSyntax = true;
                $isFirstRowStatement = true;
            }

            // compose row
            $rowStatement = '(';
            $isFirstColumn = true;

            foreach ($columns as $column => $columnOptions) {
                // get value
                if (array_key_exists($column, $rowx)) {
                    $value = $rowx[$column];
                } else {
                    $value = $columnOptions[1];
                }

                // cast
                if ($value !== null) {
                    $value = match ($columnOptions[0]) {
                        'integer' => (int)$value,
                        'string' => (string)$value,
                        default => throw new \LogicException(sprintf('Invalid column type "%s"', $columnOptions[0])),
                    };
                }

                // add to row
                if ($isFirstColumn) {
                    $isFirstColumn = false;
                } else {
                    $rowStatement .= ',';
                }

                $rowStatement .= DB::val($value);
            }

            $rowStatement .= ')';

            // check row statement size
            $rowStatementSize = strlen($rowStatement);
            $requiredBytes = $rowStatementSize + ($isFirstRowStatement ? 0 : 1);

            if ($currentQuerySize + $requiredBytes > $maxPacketSize) {
                // not enough bytes left
                if ($isFirstRowStatement || $insertStatementSize + $rowStatementSize > $currentQuerySize) {
                    // impossible to fit
                    throw new DatabaseException(sprintf(
                        'Encountered row in table "%s" that is too big for maximum packet size of %d bytes',
                        $table,
                        $maxPacketSize
                    ));
                }

                // start new insert statement
                fwrite($handle, ";\n");
                $currentQuerySize = fwrite($handle, $insertStatement);
                $isFirstRowStatement = true;
            }

            // write row
            if ($isFirstRowStatement) {
                $isFirstRowStatement = false;
            } else {
                $currentQuerySize += fwrite($handle, ',');
            }

            $currentQuerySize += fwrite($handle, $rowStatement);
        }

        // close existing insert statement
        if ($writtenInsertSyntax) {
            fwrite($handle, ";\n");
        }
    }

    /**
     * Get table columns
     */
    private function getTableColumns(string $table): array
    {
        $columns = [];
        $result = DB::query('SHOW COLUMNS FROM `' . $table . '`');

        while ($row = DB::row($result)) {
            if (($parentPos = strpos($row['Type'], '(')) !== false) {
                $type = substr($row['Type'], 0, $parentPos);
            } else {
                $type = $row['Type'];
            }

            $type = match (strtolower($type)) {
                'integer', 'int' => 'integer',
                default => 'string',
            };

            $columns[$row['Field']] = [$type, $row['Default']];
        }

        return $columns;
    }
}
