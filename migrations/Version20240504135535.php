<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240504135535 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494E2FC55A77');
        $this->addSql('DROP INDEX IDX_B6F7494E2FC55A77 ON question');
        $this->addSql('ALTER TABLE question ADD used TINYINT(1) NOT NULL, DROP answered_by_id, DROP answer_correct');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE question ADD answered_by_id INT DEFAULT NULL, ADD answer_correct TINYINT(1) DEFAULT NULL, DROP used');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494E2FC55A77 FOREIGN KEY (answered_by_id) REFERENCES creator (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_B6F7494E2FC55A77 ON question (answered_by_id)');
    }
}
