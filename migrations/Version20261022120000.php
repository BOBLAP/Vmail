<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Optional persistent IMAP retrieval lower date bound';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account ADD imap_retrieve_since DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account DROP imap_retrieve_since');
    }
}
