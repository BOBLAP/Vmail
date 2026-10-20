<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional local star colour cycling, enabled by default';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD appearance_star_colors BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE message ADD star_color SMALLINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE message_thread ADD star_color SMALLINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP appearance_star_colors');
        $this->addSql('ALTER TABLE message DROP star_color');
        $this->addSql('ALTER TABLE message_thread DROP star_color');
    }
}
