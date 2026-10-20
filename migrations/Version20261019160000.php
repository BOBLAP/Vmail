<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store message list panel background opacity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD appearance_message_panel_opacity INT DEFAULT 100 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP appearance_message_panel_opacity');
    }
}
