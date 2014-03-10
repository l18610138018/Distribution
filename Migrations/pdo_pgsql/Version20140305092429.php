<?php

namespace Claroline\CoreBundle\Migrations\pdo_pgsql;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated migration based on mapping information: modify it with caution
 *
 * Generation date: 2014/03/05 09:24:30
 */
class Version20140305092429 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql("
            ALTER TABLE claro_tools 
            ADD is_locked_for_admin BOOLEAN NOT NULL
        ");
        $this->addSql("
            ALTER TABLE claro_tools 
            ADD is_anonymous_excluded BOOLEAN NOT NULL
        ");
    }

    public function down(Schema $schema)
    {
        $this->addSql("
            ALTER TABLE claro_tools 
            DROP is_locked_for_admin
        ");
        $this->addSql("
            ALTER TABLE claro_tools 
            DROP is_anonymous_excluded
        ");
    }
}