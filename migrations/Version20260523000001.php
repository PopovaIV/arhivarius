<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260523000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: users, activity_log';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            CREATE TABLE users (
                id SERIAL PRIMARY KEY,
                username VARCHAR(60) NOT NULL UNIQUE,
                display_name VARCHAR(120) NOT NULL,
                email VARCHAR(180) DEFAULT NULL,
                roles JSON NOT NULL,
                password VARCHAR(255) NOT NULL,
                active BOOLEAN NOT NULL DEFAULT TRUE,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_by_id INTEGER DEFAULT NULL,
                CONSTRAINT fk_users_created_by FOREIGN KEY (created_by_id) REFERENCES users(id) ON DELETE SET NULL
            )
        SQL);

        $this->addSql(<<<SQL
            CREATE TABLE activity_log (
                id BIGSERIAL PRIMARY KEY,
                user_id INTEGER DEFAULT NULL,
                action VARCHAR(64) NOT NULL,
                entity_type VARCHAR(32) DEFAULT NULL,
                entity_id BIGINT DEFAULT NULL,
                metadata JSON DEFAULT NULL,
                ip VARCHAR(45) DEFAULT NULL,
                user_agent TEXT DEFAULT NULL,
                session_id VARCHAR(64) DEFAULT NULL,
                duration_seconds INTEGER DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        SQL);

        $this->addSql('CREATE INDEX idx_activity_user ON activity_log (user_id)');
        $this->addSql('CREATE INDEX idx_activity_created ON activity_log (created_at)');
        $this->addSql('CREATE INDEX idx_activity_action ON activity_log (action)');
        $this->addSql('CREATE INDEX idx_activity_session ON activity_log (session_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE activity_log');
        $this->addSql('DROP TABLE users');
    }
}
