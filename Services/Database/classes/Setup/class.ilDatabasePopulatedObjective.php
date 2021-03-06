<?php

/* Copyright (c) 2019 Richard Klees <richard.klees@concepts-and-training.de> Extended GPL, see docs/LICENSE */

use ILIAS\Setup;

class ilDatabasePopulatedObjective extends \ilDatabaseObjective
{
    const MIN_NUMBER_OF_ILIAS_TABLES = 200; // educated guess

    public function getHash() : string
    {
        return hash("sha256", implode("-", [
            self::class,
            $this->config->getHost(),
            $this->config->getPort(),
            $this->config->getDatabase()
        ]));
    }

    public function getLabel() : string
    {
        return "The database is populated with ILIAS-tables.";
    }

    public function isNotable() : bool
    {
        return true;
    }

    public function getPreconditions(Setup\Environment $environment) : array
    {
        if ($environment->getResource(Setup\Environment::RESOURCE_DATABASE)) {
            return [];
        }
        return [
            new \ilDatabaseExistsObjective($this->config)
        ];
    }

    public function achieve(Setup\Environment $environment) : Setup\Environment
    {
        $db = $environment->getResource(Setup\Environment::RESOURCE_DATABASE);

        $path_to_db_dump = $this->config->getPathToDBDump();
        if (!is_file(realpath($path_to_db_dump)) ||
            !is_readable(realpath($path_to_db_dump))) {
            throw new Setup\UnachievableException(
                "Cannot read database dump file: $path_to_db_dump"
            );
        }

        $sql = file_get_contents(realpath($path_to_db_dump));
        $statement = $db->prepareManip($sql);
        $db->execute($statement);

        return $environment;
    }

    /**
     * @inheritDoc
     */
    public function isApplicable(Setup\Environment $environment) : bool
    {
        $db = $environment->getResource(Setup\Environment::RESOURCE_DATABASE);

        return !$this->isDatabasePopulated($db);
    }

    protected function isDatabasePopulated(\ilDBInterface $db)
    {
        $probe_tables = ['usr_data', 'object_data', 'object_reference'];
        $number_of_probe_tables = count($probe_tables);
        $tables = $db->listTables();
        $number_of_tables = count($tables);

        return
            $number_of_tables > self::MIN_NUMBER_OF_ILIAS_TABLES
            && count(array_intersect($tables, $probe_tables)) == $number_of_probe_tables;
    }
}
