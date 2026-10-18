<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the message body viewport size separately from reading density';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE \"user\" ADD appearance_message_body_size VARCHAR(16) DEFAULT 'adaptive' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP appearance_message_body_size');
    }
}
