<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260523000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Book covers, full-text search, annotations (Phase 4.5)';
    }

    public function up(Schema $schema): void
    {
        // === Обложки ===
        $this->addSql('ALTER TABLE books ADD COLUMN cover_path VARCHAR(255) DEFAULT NULL');

        // === Полнотекстовый поиск ===
        $this->addSql('ALTER TABLE book_files ADD COLUMN extracted_text TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE book_files ADD COLUMN text_status VARCHAR(16) NOT NULL DEFAULT \'pending\'');
        // tsvector сгенерирован из extracted_text. Простая «simple» конфигурация — без стемминга,
        // работает одинаково для русского/английского/любого языка, годится для архивного контента,
        // где языков несколько и они смешаны.
        $this->addSql("ALTER TABLE book_files ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (to_tsvector('simple', COALESCE(extracted_text, ''))) STORED");
        $this->addSql('CREATE INDEX idx_bf_search ON book_files USING GIN (search_vector)');

        // === Аннотации (закладки/заметки) ===
        $this->addSql(<<<SQL
            CREATE TABLE book_annotations (
                id BIGSERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL,
                book_id BIGINT NOT NULL,
                type VARCHAR(16) NOT NULL,
                content TEXT NOT NULL,
                pdf_page INTEGER DEFAULT NULL,
                epub_cfi VARCHAR(512) DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT fk_ann_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_ann_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX idx_ann_user_book ON book_annotations (user_id, book_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE book_annotations');
        $this->addSql('DROP INDEX idx_bf_search');
        $this->addSql('ALTER TABLE book_files DROP COLUMN search_vector');
        $this->addSql('ALTER TABLE book_files DROP COLUMN text_status');
        $this->addSql('ALTER TABLE book_files DROP COLUMN extracted_text');
        $this->addSql('ALTER TABLE books DROP COLUMN cover_path');
    }
}
