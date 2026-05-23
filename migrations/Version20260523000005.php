<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260523000005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Photo album with person tags (Phase 5)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            CREATE TABLE persons (
                id BIGSERIAL PRIMARY KEY,
                full_name VARCHAR(255) NOT NULL,
                aliases VARCHAR(255) DEFAULT NULL,
                gender VARCHAR(32) NOT NULL DEFAULT 'unknown',
                birth_date VARCHAR(64) DEFAULT NULL,
                birth_year INTEGER DEFAULT NULL,
                birth_place VARCHAR(255) DEFAULT NULL,
                death_date VARCHAR(64) DEFAULT NULL,
                death_year INTEGER DEFAULT NULL,
                death_place VARCHAR(255) DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                created_by_id INTEGER NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                deleted_by_id INTEGER DEFAULT NULL,
                CONSTRAINT fk_person_created_by FOREIGN KEY (created_by_id) REFERENCES users(id),
                CONSTRAINT fk_person_deleted_by FOREIGN KEY (deleted_by_id) REFERENCES users(id) ON DELETE SET NULL
            )
        SQL);
        $this->addSql('CREATE INDEX idx_person_name ON persons (full_name)');
        $this->addSql('CREATE INDEX idx_person_deleted ON persons (deleted_at)');

        $this->addSql(<<<SQL
            CREATE TABLE photos (
                id BIGSERIAL PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                description TEXT DEFAULT NULL,
                taken_date VARCHAR(64) DEFAULT NULL,
                taken_year INTEGER DEFAULT NULL,
                place VARCHAR(255) DEFAULT NULL,
                image_path VARCHAR(512) NOT NULL,
                mime_type VARCHAR(128) NOT NULL,
                size_bytes BIGINT NOT NULL,
                image_width INTEGER DEFAULT NULL,
                image_height INTEGER DEFAULT NULL,
                created_by_id INTEGER NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                deleted_by_id INTEGER DEFAULT NULL,
                CONSTRAINT fk_photo_created_by FOREIGN KEY (created_by_id) REFERENCES users(id),
                CONSTRAINT fk_photo_deleted_by FOREIGN KEY (deleted_by_id) REFERENCES users(id) ON DELETE SET NULL
            )
        SQL);
        $this->addSql('CREATE INDEX idx_photo_deleted ON photos (deleted_at)');
        $this->addSql('CREATE INDEX idx_photo_year ON photos (taken_year)');

        $this->addSql(<<<SQL
            CREATE TABLE photo_tags (
                id BIGSERIAL PRIMARY KEY,
                photo_id BIGINT NOT NULL,
                person_id BIGINT NOT NULL,
                x NUMERIC(6, 5) NOT NULL,
                y NUMERIC(6, 5) NOT NULL,
                width NUMERIC(6, 5) NOT NULL,
                height NUMERIC(6, 5) NOT NULL,
                created_by_id INTEGER NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT fk_pt_photo FOREIGN KEY (photo_id) REFERENCES photos(id) ON DELETE CASCADE,
                CONSTRAINT fk_pt_person FOREIGN KEY (person_id) REFERENCES persons(id) ON DELETE CASCADE,
                CONSTRAINT fk_pt_creator FOREIGN KEY (created_by_id) REFERENCES users(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_pt_photo ON photo_tags (photo_id)');
        $this->addSql('CREATE INDEX idx_pt_person ON photo_tags (person_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE photo_tags');
        $this->addSql('DROP TABLE photos');
        $this->addSql('DROP TABLE persons');
    }
}
