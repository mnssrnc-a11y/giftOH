<?php

namespace App\Contracts;

/**
 * FundingRepositoryContract
 *
 * Defines the contract for funding request data repositories.
 * Ensures consistent behavior across Firebase and SQL implementations.
 *
 * @package App\Contracts
 */
interface FundingRepositoryContract
{
    /**
     * Find a funding request by ID.
     *
     * @param string|int $id
     * @return array|null
     */
    public function findById(string|int $id): ?array;

    /**
     * Find all funding requests by user ID.
     *
     * @param string|int $userId
     * @return array
     */
    public function findByUserId(string|int $userId): array;

    /**
     * Find all funding requests by status.
     *
     * @param string|int $statusId
     * @return array
     */
    public function findByStatus(string|int $statusId): array;

    /**
     * Create a new funding request.
     *
     * @param array $data
     * @return array
     */
    public function create(array $data): array;

    /**
     * Update an existing funding request.
     *
     * @param string|int $id
     * @param array $data
     * @return array|bool
     */
    public function update(string|int $id, array $data): array|bool;

    /**
     * Retrieve all funding requests.
     *
     * @return array
     */
    public function all(): array;
}
