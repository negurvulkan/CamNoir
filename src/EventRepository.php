<?php

class EventRepository
{
    public function findBySlug(string $slug): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM events WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);
        $event = $stmt->fetch();
        return $event ?: null;
    }

    public function findAll(): array
    {
        $stmt = Database::connection()->query('SELECT * FROM events ORDER BY created_at DESC');
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO events (slug, name, description, max_photos_per_session, auto_delete_days, frame_branding_text, auto_approve_photos, created_at, updated_at)'
            . ' VALUES (:slug, :name, :description, :max_photos_per_session, :auto_delete_days, :frame_branding_text, :auto_approve_photos, NOW(), NOW())'
        );
        $stmt->execute([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'max_photos_per_session' => $data['max_photos_per_session'],
            'auto_delete_days' => $data['auto_delete_days'],
            'frame_branding_text' => $data['frame_branding_text'] ?? null,
            'auto_approve_photos' => $data['auto_approve_photos'] ?? 0,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE events SET slug=:slug, name=:name, description=:description, max_photos_per_session=:max_photos_per_session, auto_delete_days=:auto_delete_days, frame_branding_text=:frame_branding_text, auto_approve_photos=:auto_approve_photos, updated_at=NOW() WHERE id=:id'
        );
        $stmt->execute([
            'id' => $id,
            'slug' => $data['slug'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'max_photos_per_session' => $data['max_photos_per_session'],
            'auto_delete_days' => $data['auto_delete_days'],
            'frame_branding_text' => $data['frame_branding_text'] ?? null,
            'auto_approve_photos' => $data['auto_approve_photos'] ?? 0,
        ]);
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM events WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $event = $stmt->fetch();
        return $event ?: null;
    }
}
