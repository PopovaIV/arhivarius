<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260523000007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'External links and chat (Phases 7 + 8)';
    }

    public function up(Schema $schema): void
    {
        // === Ссылки ===
        $this->addSql(<<<SQL
            CREATE TABLE external_links (
                id BIGSERIAL PRIMARY KEY,
                category VARCHAR(32) NOT NULL,
                title VARCHAR(255) NOT NULL,
                url VARCHAR(2048) NOT NULL,
                description TEXT DEFAULT NULL,
                tags VARCHAR(255) DEFAULT NULL,
                created_by_id INTEGER NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                deleted_by_id INTEGER DEFAULT NULL,
                CONSTRAINT fk_link_created_by FOREIGN KEY (created_by_id) REFERENCES users(id),
                CONSTRAINT fk_link_deleted_by FOREIGN KEY (deleted_by_id) REFERENCES users(id) ON DELETE SET NULL
            )
        SQL);
        $this->addSql('CREATE INDEX idx_link_category ON external_links (category)');
        $this->addSql('CREATE INDEX idx_link_deleted ON external_links (deleted_at)');

        // === Чат ===
        $this->addSql(<<<SQL
            CREATE TABLE chat_channels (
                id BIGSERIAL PRIMARY KEY,
                type VARCHAR(16) NOT NULL,
                direct_key VARCHAR(40) DEFAULT NULL UNIQUE,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);

        $this->addSql(<<<SQL
            CREATE TABLE chat_channel_participants (
                chat_channel_id BIGINT NOT NULL,
                user_id INTEGER NOT NULL,
                PRIMARY KEY (chat_channel_id, user_id),
                CONSTRAINT fk_ccp_channel FOREIGN KEY (chat_channel_id) REFERENCES chat_channels(id) ON DELETE CASCADE,
                CONSTRAINT fk_ccp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        SQL);

        $this->addSql(<<<SQL
            CREATE TABLE chat_messages (
                id BIGSERIAL PRIMARY KEY,
                channel_id BIGINT NOT NULL,
                author_id INTEGER NOT NULL,
                body TEXT NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                CONSTRAINT fk_msg_channel FOREIGN KEY (channel_id) REFERENCES chat_channels(id) ON DELETE CASCADE,
                CONSTRAINT fk_msg_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX idx_msg_channel_id ON chat_messages (channel_id, id)');
        $this->addSql('CREATE INDEX idx_msg_deleted ON chat_messages (deleted_at)');

        $this->addSql(<<<SQL
            CREATE TABLE chat_read_state (
                id BIGSERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL,
                channel_id BIGINT NOT NULL,
                last_read_message_id BIGINT DEFAULT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT fk_crs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_crs_channel FOREIGN KEY (channel_id) REFERENCES chat_channels(id) ON DELETE CASCADE,
                CONSTRAINT uq_chat_read_state UNIQUE (user_id, channel_id)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE chat_read_state');
        $this->addSql('DROP TABLE chat_messages');
        $this->addSql('DROP TABLE chat_channel_participants');
        $this->addSql('DROP TABLE chat_channels');
        $this->addSql('DROP TABLE external_links');
    }
}
