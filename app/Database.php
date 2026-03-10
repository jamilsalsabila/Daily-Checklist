<?php

declare(strict_types=1);

final class Database
{
    private PDO $pdo;
    private const DEFAULT_TEMPLATE_SLUG = 'floor-captain-control-sheet';

    public function __construct(string $databasePath)
    {
        ensure_writable_file($databasePath);

        $this->pdo = new PDO('sqlite:' . $databasePath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->migrate();
        $this->ensureDefaultTemplate();
        ensure_writable_file($databasePath);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function insertSubmission(array $payload): string
    {
        $code = 'FCS-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $activeTemplate = $this->getActiveTemplate();
        $templateId = $payload['template_id'] ?? ($activeTemplate['id'] ?? null);
        $templateVersionId = $payload['template_version_id'] ?? ($activeTemplate['current_version_id'] ?? null);

        $statement = $this->pdo->prepare(
            'INSERT INTO submissions (
                submission_code, tanggal, floor_captain, opening_checks_json, team_control_json,
                service_control_json, floor_awareness_json, customer_experience_json,
                closing_control_json, operational_notes_json, responses_json, signature_strokes_json,
                signature_preview, template_id, template_version_id, created_at
            ) VALUES (
                :submission_code, :tanggal, :floor_captain, :opening_checks_json, :team_control_json,
                :service_control_json, :floor_awareness_json, :customer_experience_json,
                :closing_control_json, :operational_notes_json, :responses_json, :signature_strokes_json,
                :signature_preview, :template_id, :template_version_id, :created_at
            )'
        );

        $statement->execute([
            ':submission_code' => $code,
            ':tanggal' => $payload['tanggal'],
            ':floor_captain' => $payload['floor_captain'],
            ':opening_checks_json' => json_encode($payload['opening_checks'], JSON_UNESCAPED_UNICODE),
            ':team_control_json' => json_encode($payload['team_control'], JSON_UNESCAPED_UNICODE),
            ':service_control_json' => json_encode($payload['service_control'], JSON_UNESCAPED_UNICODE),
            ':floor_awareness_json' => json_encode($payload['floor_awareness'], JSON_UNESCAPED_UNICODE),
            ':customer_experience_json' => json_encode($payload['customer_experience'], JSON_UNESCAPED_UNICODE),
            ':closing_control_json' => json_encode($payload['closing_control'], JSON_UNESCAPED_UNICODE),
            ':operational_notes_json' => json_encode($payload['operational_notes'], JSON_UNESCAPED_UNICODE),
            ':responses_json' => json_encode($payload['responses'] ?? [], JSON_UNESCAPED_UNICODE),
            ':signature_strokes_json' => json_encode($payload['signature_strokes'], JSON_UNESCAPED_UNICODE),
            ':signature_preview' => $payload['signature_preview'],
            ':template_id' => $templateId,
            ':template_version_id' => $templateVersionId,
            ':created_at' => date('c'),
        ]);

        return $code;
    }

    public function findSubmissionByCode(string $code): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM submissions WHERE submission_code = :code LIMIT 1');
        $statement->execute([':code' => $code]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        foreach ([
            'opening_checks_json' => 'opening_checks',
            'team_control_json' => 'team_control',
            'service_control_json' => 'service_control',
            'floor_awareness_json' => 'floor_awareness',
            'customer_experience_json' => 'customer_experience',
            'closing_control_json' => 'closing_control',
            'operational_notes_json' => 'operational_notes',
            'signature_strokes_json' => 'signature_strokes',
            'responses_json' => 'responses',
        ] as $source => $target) {
            $row[$target] = json_decode((string) $row[$source], true) ?: [];
        }

        if (($row['responses'] ?? []) === [] && class_exists('TemplateSchema')) {
            $schema = null;
            $versionId = (int) ($row['template_version_id'] ?? 0);
            if ($versionId > 0) {
                $version = $this->findTemplateVersionById($versionId);
                if ($version !== null) {
                    $schema = TemplateSchema::fromTemplate($version);
                }
            }
            if ($schema === null) {
                $schema = TemplateSchema::defaults();
            }
            $row['responses'] = TemplateSchema::responsesFromLegacy($schema, $row);
        }

        return $row;
    }

    public function listSubmissions(?string $date = null, string $search = ''): array
    {
        $sql = 'SELECT submission_code, tanggal, floor_captain, template_id, template_version_id, created_at FROM submissions';
        $conditions = [];
        $params = [];

        if ($date !== null && $date !== '') {
            $conditions[] = 'tanggal = :tanggal';
            $params[':tanggal'] = $date;
        }

        if ($search !== '') {
            $conditions[] = '(submission_code LIKE :search OR floor_captain LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY tanggal DESC, id DESC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function updateSubmissionByCode(string $code, array $payload): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE submissions SET
                tanggal = :tanggal,
                floor_captain = :floor_captain,
                opening_checks_json = :opening_checks_json,
                team_control_json = :team_control_json,
                service_control_json = :service_control_json,
                floor_awareness_json = :floor_awareness_json,
                customer_experience_json = :customer_experience_json,
                closing_control_json = :closing_control_json,
                operational_notes_json = :operational_notes_json,
                responses_json = :responses_json,
                signature_strokes_json = :signature_strokes_json,
                signature_preview = :signature_preview
            WHERE submission_code = :submission_code'
        );

        $statement->execute([
            ':submission_code' => $code,
            ':tanggal' => $payload['tanggal'],
            ':floor_captain' => $payload['floor_captain'],
            ':opening_checks_json' => json_encode($payload['opening_checks'], JSON_UNESCAPED_UNICODE),
            ':team_control_json' => json_encode($payload['team_control'], JSON_UNESCAPED_UNICODE),
            ':service_control_json' => json_encode($payload['service_control'], JSON_UNESCAPED_UNICODE),
            ':floor_awareness_json' => json_encode($payload['floor_awareness'], JSON_UNESCAPED_UNICODE),
            ':customer_experience_json' => json_encode($payload['customer_experience'], JSON_UNESCAPED_UNICODE),
            ':closing_control_json' => json_encode($payload['closing_control'], JSON_UNESCAPED_UNICODE),
            ':operational_notes_json' => json_encode($payload['operational_notes'], JSON_UNESCAPED_UNICODE),
            ':responses_json' => json_encode($payload['responses'] ?? [], JSON_UNESCAPED_UNICODE),
            ':signature_strokes_json' => json_encode($payload['signature_strokes'], JSON_UNESCAPED_UNICODE),
            ':signature_preview' => $payload['signature_preview'],
        ]);

        return $statement->rowCount() > 0;
    }

    public function deleteSubmissionByCode(string $code): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM submissions WHERE submission_code = :code');
        $statement->execute([':code' => $code]);
        return $statement->rowCount() > 0;
    }

    public function listTemplates(): array
    {
        $statement = $this->pdo->query(
            'SELECT t.id, t.slug, t.name, t.description, t.is_active, t.current_version_id, v.version as current_version, v.schema_json, t.created_at, t.updated_at
             FROM checklist_templates t
             LEFT JOIN checklist_template_versions v ON v.id = t.current_version_id
             ORDER BY t.is_active DESC, t.name ASC'
        );
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listPublishedTemplates(): array
    {
        $statement = $this->pdo->query(
            'SELECT t.id, t.slug, t.name, t.description, t.is_active, t.current_version_id, v.version as current_version, v.schema_json, t.created_at, t.updated_at
             FROM checklist_templates t
             LEFT JOIN checklist_template_versions v ON v.id = t.current_version_id
             WHERE t.is_active = 1
             ORDER BY t.name ASC'
        );
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['schema'] = json_decode((string) ($row['schema_json'] ?? ''), true) ?: [];
        }
        unset($row);
        return $rows;
    }

    public function getActiveTemplate(): ?array
    {
        $statement = $this->pdo->query(
            'SELECT t.id, t.slug, t.name, t.description, t.current_version_id, v.version as current_version, v.schema_json
             FROM checklist_templates t
             LEFT JOIN checklist_template_versions v ON v.id = t.current_version_id
             WHERE t.is_active = 1
             ORDER BY t.id ASC
             LIMIT 1'
        );
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['schema'] = json_decode((string) ($row['schema_json'] ?? ''), true) ?: [];
        return $row;
    }

    public function findTemplateBySlug(string $slug, bool $publishedOnly = false): ?array
    {
        $sql = 'SELECT t.id, t.slug, t.name, t.description, t.is_active, t.current_version_id, v.version as current_version, v.schema_json, t.created_at, t.updated_at
                FROM checklist_templates t
                LEFT JOIN checklist_template_versions v ON v.id = t.current_version_id
                WHERE t.slug = :slug';
        if ($publishedOnly) {
            $sql .= ' AND t.is_active = 1';
        }
        $sql .= ' LIMIT 1';

        $statement = $this->pdo->prepare($sql);
        $statement->execute([':slug' => $slug]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['schema'] = json_decode((string) ($row['schema_json'] ?? ''), true) ?: [];
        return $row;
    }

    public function activateTemplate(int $templateId): bool
    {
        $target = $this->findTemplateRowById($templateId);
        if ($target === null) {
            return false;
        }

        $statement = $this->pdo->prepare('UPDATE checklist_templates SET is_active = 1, updated_at = :updated_at WHERE id = :id');
        $statement->execute([
            ':updated_at' => date('c'),
            ':id' => $templateId,
        ]);
        return $statement->rowCount() > 0;
    }

    public function deactivateTemplate(int $templateId): bool
    {
        $target = $this->findTemplateRowById($templateId);
        if ($target === null) {
            return false;
        }

        $statement = $this->pdo->prepare('UPDATE checklist_templates SET is_active = 0, updated_at = :updated_at WHERE id = :id');
        $statement->execute([
            ':updated_at' => date('c'),
            ':id' => $templateId,
        ]);
        return $statement->rowCount() > 0;
    }

    public function findTemplateById(int $templateId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT t.id, t.slug, t.name, t.description, t.is_active, t.current_version_id, v.version as current_version, v.schema_json, t.created_at, t.updated_at
             FROM checklist_templates t
             LEFT JOIN checklist_template_versions v ON v.id = t.current_version_id
             WHERE t.id = :id
             LIMIT 1'
        );
        $statement->execute([':id' => $templateId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['schema'] = json_decode((string) ($row['schema_json'] ?? ''), true) ?: [];
        return $row;
    }

    public function findTemplateVersionById(int $versionId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT tv.id, tv.template_id, tv.version, tv.schema_json, tv.created_at, t.name as template_name, t.slug
             FROM checklist_template_versions tv
             INNER JOIN checklist_templates t ON t.id = tv.template_id
             WHERE tv.id = :id
             LIMIT 1'
        );
        $statement->execute([':id' => $versionId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['schema'] = json_decode((string) ($row['schema_json'] ?? ''), true) ?: [];
        return $row;
    }

    public function createTemplate(string $slug, string $name, string $description, array $schema, bool $activate = false): int
    {
        $now = date('c');
        $this->pdo->beginTransaction();
        try {
            $insert = $this->pdo->prepare(
                'INSERT INTO checklist_templates (slug, name, description, is_active, created_at, updated_at)
                 VALUES (:slug, :name, :description, :is_active, :created_at, :updated_at)'
            );
            $insert->execute([
                ':slug' => $slug,
                ':name' => $name,
                ':description' => $description,
                ':is_active' => $activate ? 1 : 0,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);

            $templateId = (int) $this->pdo->lastInsertId();
            $versionId = $this->createTemplateVersion($templateId, 1, $schema);

            $update = $this->pdo->prepare(
                'UPDATE checklist_templates
                 SET current_version_id = :version_id, updated_at = :updated_at
                 WHERE id = :id'
            );
            $update->execute([
                ':version_id' => $versionId,
                ':updated_at' => $now,
                ':id' => $templateId,
            ]);

            $this->pdo->commit();
            return $templateId;
        } catch (Throwable $throwable) {
            $this->pdo->rollBack();
            throw $throwable;
        }
    }

    public function updateTemplate(int $templateId, string $slug, string $name, string $description, array $schema): bool
    {
        $existing = $this->findTemplateRowById($templateId);
        if ($existing === null) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare('SELECT COALESCE(MAX(version), 0) AS max_version FROM checklist_template_versions WHERE template_id = :template_id');
            $statement->execute([':template_id' => $templateId]);
            $nextVersion = ((int) ($statement->fetch(PDO::FETCH_ASSOC)['max_version'] ?? 0)) + 1;
            $versionId = $this->createTemplateVersion($templateId, $nextVersion, $schema);

            $update = $this->pdo->prepare(
                'UPDATE checklist_templates
                 SET slug = :slug,
                     name = :name,
                     description = :description,
                     current_version_id = :current_version_id,
                     updated_at = :updated_at
                 WHERE id = :id'
            );
            $update->execute([
                ':slug' => $slug,
                ':name' => $name,
                ':description' => $description,
                ':current_version_id' => $versionId,
                ':updated_at' => date('c'),
                ':id' => $templateId,
            ]);

            $this->pdo->commit();
            return $update->rowCount() > 0;
        } catch (Throwable $throwable) {
            $this->pdo->rollBack();
            throw $throwable;
        }
    }

    public function deleteTemplate(int $templateId): bool
    {
        $template = $this->findTemplateRowById($templateId);
        if ($template === null) {
            return false;
        }

        if ((int) ($template['is_active'] ?? 0) === 1) {
            return false;
        }

        $countStatement = $this->pdo->prepare('SELECT COUNT(*) as total FROM submissions WHERE template_id = :template_id');
        $countStatement->execute([':template_id' => $templateId]);
        $total = (int) ($countStatement->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        if ($total > 0) {
            return false;
        }

        $delete = $this->pdo->prepare('DELETE FROM checklist_templates WHERE id = :id');
        $delete->execute([':id' => $templateId]);
        return $delete->rowCount() > 0;
    }

    public function isTemplateSlugAvailable(string $slug, ?int $excludeTemplateId = null): bool
    {
        $sql = 'SELECT id FROM checklist_templates WHERE slug = :slug';
        $params = [':slug' => $slug];
        if ($excludeTemplateId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $params[':exclude_id'] = $excludeTemplateId;
        }
        $sql .= ' LIMIT 1';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetch(PDO::FETCH_ASSOC) === false;
    }

    private function findTemplateRowById(int $templateId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM checklist_templates WHERE id = :id LIMIT 1');
        $statement->execute([':id' => $templateId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function migrate(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS checklist_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                description TEXT NOT NULL DEFAULT "",
                is_active INTEGER NOT NULL DEFAULT 0,
                current_version_id INTEGER,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS checklist_template_versions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                template_id INTEGER NOT NULL,
                version INTEGER NOT NULL,
                schema_json TEXT NOT NULL,
                is_published INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL,
                UNIQUE(template_id, version),
                FOREIGN KEY(template_id) REFERENCES checklist_templates(id) ON DELETE CASCADE
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS submissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                submission_code TEXT NOT NULL UNIQUE,
                tanggal TEXT NOT NULL,
                floor_captain TEXT NOT NULL,
                opening_checks_json TEXT NOT NULL,
                team_control_json TEXT NOT NULL,
                service_control_json TEXT NOT NULL,
                floor_awareness_json TEXT NOT NULL,
                customer_experience_json TEXT NOT NULL,
                closing_control_json TEXT NOT NULL,
                operational_notes_json TEXT NOT NULL,
                responses_json TEXT NOT NULL DEFAULT "[]",
                signature_strokes_json TEXT NOT NULL,
                signature_preview TEXT NOT NULL,
                template_id INTEGER,
                template_version_id INTEGER,
                created_at TEXT NOT NULL
            )'
        );

        $this->ensureColumnExists('submissions', 'template_id', 'INTEGER');
        $this->ensureColumnExists('submissions', 'template_version_id', 'INTEGER');
        $this->ensureColumnExists('submissions', 'responses_json', 'TEXT NOT NULL DEFAULT "[]"');
    }

    private function ensureColumnExists(string $table, string $column, string $definition): void
    {
        $statement = $this->pdo->query('PRAGMA table_info(' . $table . ')');
        $columns = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($columns as $row) {
            if (($row['name'] ?? '') === $column) {
                return;
            }
        }

        $this->pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    }

    private function ensureDefaultTemplate(): void
    {
        $statement = $this->pdo->prepare('SELECT id, current_version_id FROM checklist_templates WHERE slug = :slug LIMIT 1');
        $statement->execute([':slug' => self::DEFAULT_TEMPLATE_SLUG]);
        $existing = $statement->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            if ((int) ($existing['current_version_id'] ?? 0) > 0) {
                return;
            }
            $versionId = $this->createTemplateVersion((int) $existing['id'], 1, $this->defaultTemplateSchema());
            $update = $this->pdo->prepare(
                'UPDATE checklist_templates
                 SET current_version_id = :version_id, is_active = 1, updated_at = :updated_at
                 WHERE id = :id'
            );
            $update->execute([
                ':version_id' => $versionId,
                ':updated_at' => date('c'),
                ':id' => (int) $existing['id'],
            ]);
            return;
        }

        $now = date('c');
        $insert = $this->pdo->prepare(
            'INSERT INTO checklist_templates (slug, name, description, is_active, created_at, updated_at)
             VALUES (:slug, :name, :description, 1, :created_at, :updated_at)'
        );
        $insert->execute([
            ':slug' => self::DEFAULT_TEMPLATE_SLUG,
            ':name' => 'Floor Captain Control Sheet',
            ':description' => 'Template default checklist operasional harian.',
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $templateId = (int) $this->pdo->lastInsertId();
        $versionId = $this->createTemplateVersion($templateId, 1, $this->defaultTemplateSchema());

        $update = $this->pdo->prepare(
            'UPDATE checklist_templates
             SET current_version_id = :version_id, updated_at = :updated_at
             WHERE id = :id'
        );
        $update->execute([
            ':version_id' => $versionId,
            ':updated_at' => date('c'),
            ':id' => $templateId,
        ]);
    }

    private function createTemplateVersion(int $templateId, int $version, array $schema): int
    {
        $insert = $this->pdo->prepare(
            'INSERT INTO checklist_template_versions (template_id, version, schema_json, is_published, created_at)
             VALUES (:template_id, :version, :schema_json, 1, :created_at)'
        );
        $insert->execute([
            ':template_id' => $templateId,
            ':version' => $version,
            ':schema_json' => json_encode($schema, JSON_UNESCAPED_UNICODE),
            ':created_at' => date('c'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function defaultTemplateSchema(): array
    {
        return TemplateSchema::defaults();
    }
}
