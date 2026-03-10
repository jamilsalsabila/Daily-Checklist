<?php

declare(strict_types=1);

final class Database
{
    private PDO $pdo;

    public function __construct(string $databasePath)
    {
        ensure_writable_file($databasePath);

        $this->pdo = new PDO('sqlite:' . $databasePath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->migrate();
        ensure_writable_file($databasePath);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function insertSubmission(array $payload): string
    {
        $code = 'FCS-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        $statement = $this->pdo->prepare(
            'INSERT INTO submissions (
                submission_code, tanggal, floor_captain, opening_checks_json, team_control_json,
                service_control_json, floor_awareness_json, customer_experience_json,
                closing_control_json, operational_notes_json, signature_strokes_json,
                signature_preview, created_at
            ) VALUES (
                :submission_code, :tanggal, :floor_captain, :opening_checks_json, :team_control_json,
                :service_control_json, :floor_awareness_json, :customer_experience_json,
                :closing_control_json, :operational_notes_json, :signature_strokes_json,
                :signature_preview, :created_at
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
            ':signature_strokes_json' => json_encode($payload['signature_strokes'], JSON_UNESCAPED_UNICODE),
            ':signature_preview' => $payload['signature_preview'],
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
        ] as $source => $target) {
            $row[$target] = json_decode((string) $row[$source], true) ?: [];
        }

        return $row;
    }

    public function listSubmissions(?string $date = null, string $search = ''): array
    {
        $sql = 'SELECT submission_code, tanggal, floor_captain, created_at FROM submissions';
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

    private function migrate(): void
    {
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
                signature_strokes_json TEXT NOT NULL,
                signature_preview TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }
}
