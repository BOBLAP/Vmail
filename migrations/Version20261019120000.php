<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the label chip appearance preference';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE \"user\" ADD appearance_label_style VARCHAR(16) DEFAULT 'subtle' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP appearance_label_style');
    }
}
