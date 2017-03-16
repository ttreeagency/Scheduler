<?php
namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Base migration
 */
class Version20160308124216 extends AbstractMigration
{

    /**
     * @param Schema $schema
     * @return void
     */
    public function up(Schema $schema)
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != "postgresql");

        $this->addSql("CREATE TABLE ttree_scheduler_domain_model_task (persistence_object_identifier VARCHAR(40) NOT NULL, status INT NOT NULL, expression VARCHAR(255) NOT NULL, implementation VARCHAR(255) NOT NULL, arguments TEXT NOT NULL, argumentshash VARCHAR(255) NOT NULL, creationdate TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, lastexecution TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, nextexecution TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(persistence_object_identifier))");
        $this->addSql("CREATE UNIQUE INDEX flow_identity_ttree_scheduler_domain_model_task ON ttree_scheduler_domain_model_task (expression, implementation, argumentshash)");
        $this->addSql("COMMENT ON COLUMN ttree_scheduler_domain_model_task.arguments IS '(DC2Type:array)'");
    }

    /**
     * @param Schema $schema
     * @return void
     */
    public function down(Schema $schema)
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != "postgresql");

        $this->addSql("DROP TABLE ttree_scheduler_domain_model_task");
    }
}
