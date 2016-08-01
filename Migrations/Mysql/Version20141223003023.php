<?php
namespace TYPO3\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Base migration
 */
class Version20141223003023 extends AbstractMigration
{

    /**
     * @param Schema $schema
     * @return void
     */
    public function up(Schema $schema)
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != "mysql");
        
        $this->addSql("CREATE TABLE ttree_scheduler_domain_model_task (persistence_object_identifier VARCHAR(40) NOT NULL, status INT NOT NULL, expression VARCHAR(255) NOT NULL, implementation VARCHAR(255) NOT NULL, arguments LONGTEXT NOT NULL COMMENT '(DC2Type:array)', argumentshash VARCHAR(255) NOT NULL , creationdate DATETIME NOT NULL, lastexecution DATETIME DEFAULT NULL, nextexecution DATETIME DEFAULT NULL, PRIMARY KEY(persistence_object_identifier)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB");
    }

    /**
     * @param Schema $schema
     * @return void
     */
    public function down(Schema $schema)
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != "mysql");
        
        $this->addSql("DROP TABLE ttree_scheduler_domain_model_task");
    }
}
