<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260523000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Books, book_files, reading_progress (Phase 4)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            CREATE TABLE books (
                id BIGSERIAL PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                authors VARCHAR(255) DEFAULT NULL,
                year INTEGER DEFAULT NULL,
                publisher VARCHAR(255) DEFAULT NULL,
                language VARCHAR(8) DEFAULT NULL,
                genre VARCHAR(255) DEFAULT NULL,
                isbn VARCHAR(20) DEFAULT NULL,
                description TEXT DEFAULT NULL,
                created_by_id INTEGER NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                deleted_by_id INTEGER DEFAULT NULL,
                CONSTRAINT fk_book_created_by FOREIGN KEY (created_by_id) REFERENCES users(id),
                CONSTRAINT fk_book_deleted_by FOREIGN KEY (deleted_by_id) REFERENCES users(id) ON DELETE SET NULL
            )
        SQL);
        $this->addSql('CREATE INDEX idx_book_deleted ON books (deleted_at)');
        $this->addSql('CREATE INDEX idx_book_year ON books (year)');
        $this->addSql('CREATE INDEX idx_book_language ON books (language)');

        $this->addSql(<<<SQL
            CREATE TABLE book_files (
                id BIGSERIAL PRIMARY KEY,
                book_id BIGINT NOT NULL,
                format VARCHAR(16) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                stored_path VARCHAR(512) NOT NULL,
                mime_type VARCHAR(128) NOT NULL,
                size_bytes BIGINT NOT NULL,
                uploaded_by_id INTEGER NOT NULL,
                uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT fk_bf_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
                CONSTRAINT fk_bf_uploader FOREIGN KEY (uploaded_by_id) REFERENCES users(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_bf_book ON book_files (book_id)');

        $this->addSql(<<<SQL
            CREATE TABLE reading_progress (
                id BIGSERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL,
                book_id BIGINT NOT NULL,
                pdf_page INTEGER DEFAULT NULL,
                pdf_total_pages INTEGER DEFAULT NULL,
                epub_cfi VARCHAR(512) DEFAULT NULL,
                percent INTEGER DEFAULT NULL,
                total_seconds INTEGER NOT NULL DEFAULT 0,
                last_read_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT fk_rp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_rp_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
                CONSTRAINT uq_progress_user_book UNIQUE (user_id, book_id)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE reading_progress');
        $this->addSql('DROP TABLE book_files');
        $this->addSql('DROP TABLE books');
    }
}
