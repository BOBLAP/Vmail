<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store message panel inversion and contrast';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD appearance_invert_message_panel BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD appearance_message_panel_contrast INT DEFAULT 70 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP appearance_invert_message_panel');
        $this->addSql('ALTER TABLE "user" DROP appearance_message_panel_contrast');
    }
}
