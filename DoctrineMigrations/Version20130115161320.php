<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration,
    Doctrine\DBAL\Schema\Schema;

class Version20130115161320 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $tbl = $schema->createTable("Process");
        $tbl->addColumn("id", "integer", ["unsigned" => true, "autoincrement" => true]);
        $tbl->addColumn("initialState", "text");
        $tbl->addColumn("daemonName", "string");
        $tbl->addColumn("daemonClass", "string");
        $tbl->addColumn("groupName", "string");
        $tbl->addColumn("procName", "string", ["notnull" => false]);
        $tbl->addColumn("command", "text", ["notnull" => false]);
        $tbl->setPrimaryKey(["id"]);
    }

    public function down(Schema $schema)
    {
        $this->addSql("DROP TABLE Process");
    }
}
