<?php

namespace App\Repositories;

class FirebaseAdminPostRepository extends FirebaseRepository
{
    public const AUDIENCE_PUBLIC = 'public';
    public const AUDIENCE_USERS = 'users';

    /**
     * Posts newest first. With $publicOnly, only posts meant for the public landing page.
     */
    public function latest(?int $limit = null, bool $publicOnly = false): array
    {
        $posts = $this->all();

        if ($publicOnly) {
            $posts = array_values(array_filter(
                $posts,
                static fn (array $post): bool => ($post['audience'] ?? self::AUDIENCE_PUBLIC) === self::AUDIENCE_PUBLIC
            ));
        }

        usort($posts, static fn (array $first, array $second): int =>
            strcmp((string) ($second['created_at'] ?? ''), (string) ($first['created_at'] ?? ''))
        );

        return $limit === null ? $posts : array_slice($posts, 0, $limit);
    }

    /**
     * URL of the photo attached to a post (stored on the public disk), or null.
     */
    public static function imageUrl(array $post): ?string
    {
        $path = trim((string) ($post['image'] ?? ''));

        return $path === '' ? null : asset('storage/' . ltrim($path, '/'));
    }

    protected function nodeKey(): string
    {
        return 'admin_posts';
    }
}
