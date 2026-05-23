<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260523000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Documents and document_files (Phase 2)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            CREATE TABLE documents (
                id BIGSERIAL PRIMARY KEY,
                category VARCHAR(32) NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT DEFAULT NULL,
                document_date VARCHAR(64) DEFAULT NULL,
                document_year INTEGER DEFAULT NULL,
                date_precision VARCHAR(16) NOT NULL DEFAULT 'year',
                place VARCHAR(255) DEFAULT NULL,
                archive_reference VARCHAR(255) DEFAULT NULL,
                metadata JSON DEFAULT NULL,
                created_by_id INTEGER NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                deleted_by_id INTEGER DEFAULT NULL,
                CONSTRAINT fk_doc_created_by FOREIGN KEY (created_by_id) REFERENCES users(id),
                CONSTRAINT fk_doc_deleted_by FOREIGN KEY (deleted_by_id) REFERENCES users(id) ON DELETE SET NULL
            )
        SQL);

        $this->addSql('CREATE INDEX idx_doc_category ON documents (category)');
        $this->addSql('CREATE INDEX idx_doc_deleted ON documents (deleted_at)');
        $this->addSql('CREATE INDEX idx_doc_created_by ON documents (created_by_id)');
        $this->addSql('CREATE INDEX idx_doc_year ON documents (document_year)');

        $this->addSql(<<<SQL
            CREATE TABLE document_files (
                id BIGSERIAL PRIMARY KEY,
                document_id BIGINT NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                stored_path VARCHAR(512) NOT NULL,
                mime_type VARCHAR(128) NOT NULL,
                size_bytes BIGINT NOT NULL,
                uploaded_by_id INTEGER NOT NULL,
                uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT fk_df_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
                CONSTRAINT fk_df_uploader FOREIGN KEY (uploaded_by_id) REFERENCES users(id)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_df_document ON document_files (document_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE document_files');
        $this->addSql('DROP TABLE documents');
    }
}
