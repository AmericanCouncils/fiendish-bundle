<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration,
    Doctrine\DBAL\Schema\Schema;

class Version20130115161320 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $tbl = $schema->createTable("ProcessEntity");
        $tbl->addColumn("id", "integer", ["unsigned" => true, "autoincrement" => true]);
        $tbl->addColumn("groupName", "string");
        $tbl->addColumn("procName", "string");
        $tbl->addColumn("command", "text");
        $tbl->addColumn("heartbeat", "integer", ["unsigned" => true, "notnull" => false]);
        $tbl->setPrimaryKey(["id"]);
        $tbl->addUniqueIndex(["groupName", "procName"]);
    }

    public function down(Schema $schema)
    {
        $this->addSql("DROP TABLE ProcessEntity");
    }
}
