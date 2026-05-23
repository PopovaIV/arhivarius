<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260523000006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Genealogy: relations + external tree url (Phase 6)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE persons ADD COLUMN external_tree_url VARCHAR(1024) DEFAULT NULL');

        $this->addSql(<<<SQL
            CREATE TABLE relations (
                id BIGSERIAL PRIMARY KEY,
                from_person_id BIGINT NOT NULL,
                to_person_id BIGINT NOT NULL,
                type VARCHAR(16) NOT NULL,
                start_date VARCHAR(64) DEFAULT NULL,
                end_date VARCHAR(64) DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                created_by_id INTEGER NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT fk_rel_from FOREIGN KEY (from_person_id) REFERENCES persons(id) ON DELETE CASCADE,
                CONSTRAINT fk_rel_to FOREIGN KEY (to_person_id) REFERENCES persons(id) ON DELETE CASCADE,
                CONSTRAINT fk_rel_creator FOREIGN KEY (created_by_id) REFERENCES users(id),
                CONSTRAINT uq_relation_triple UNIQUE (from_person_id, to_person_id, type),
                CONSTRAINT chk_rel_not_self CHECK (from_person_id <> to_person_id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_relation_from ON relations (from_person_id)');
        $this->addSql('CREATE INDEX idx_relation_to ON relations (to_person_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE relations');
        $this->addSql('ALTER TABLE persons DROP COLUMN external_tree_url');
    }
}
