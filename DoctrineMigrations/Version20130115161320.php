<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration,
    Doctrine\DBAL\Schema\Schema;

class Version20130115161320 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $auto_inc_keyword = "";
        if ($this->connection->getDatabasePlatform()->getName() == "mysql") {
            $auto_inc_keyword = "AUTO_INCREMENT ";
        }
        print("AIK >" . $auto_inc_keyword . "<\n");
        $this->addSql("CREATE TABLE Process (id INT $auto_inc_keyword, initialState LONGTEXT NOT NULL, daemonName VARCHAR(255) NOT NULL, daemonClass VARCHAR(255) NOT NULL, groupName VARCHAR(255) NOT NULL, procName VARCHAR(255) NULL, command LONGTEXT NULL, PRIMARY KEY(id ASC))");
    }

    public function down(Schema $schema)
    {
        $this->addSql("DROP TABLE Process");
    }
}
