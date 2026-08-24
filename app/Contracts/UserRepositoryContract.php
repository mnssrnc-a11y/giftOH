<?php

namespace App\Contracts;

/**
 * UserRepositoryContract
 *
 * Defines the contract for user data repositories (Firebase or SQL).
 * All implementations must follow this interface for consistency.
 *
 * @package App\Contracts
 */
interface UserRepositoryContract
{
    /**
     * Find a user by their unique identifier.
     *
     * @param string|int $id
     * @return array|null
     */
    public function findById(string|int $id): ?array;

    /**
     * Find a user by their email address.
     *
     * @param string $email
     * @return array|null
     */
    public function findByEmail(string $email): ?array;

    /**
     * Create a new user record.
     *
     * @param array $data
     * @return array
     */
    public function create(array $data): array;

    /**
     * Update an existing user record.
     *
     * @param string|int $id
     * @param array $data
     * @return array|bool
     */
    public function update(string|int $id, array $data): array|bool;

    /**
     * Delete a user record.
     *
     * @param string|int $id
     * @return bool
     */
    public function delete(string|int $id): bool;

    /**
     * Retrieve all users.
     *
     * @return array
     */
    public function all(): array;
}
