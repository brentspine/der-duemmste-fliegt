<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240503134939 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE creator ADD voted_for_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE creator ADD CONSTRAINT FK_BC06EA63861D2E7D FOREIGN KEY (voted_for_id) REFERENCES creator (id)');
        $this->addSql('CREATE INDEX IDX_BC06EA63861D2E7D ON creator (voted_for_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE creator DROP FOREIGN KEY FK_BC06EA63861D2E7D');
        $this->addSql('DROP INDEX IDX_BC06EA63861D2E7D ON creator');
        $this->addSql('ALTER TABLE creator DROP voted_for_id');
    }
}
