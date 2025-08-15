<?php
declare(strict_types=1);

/**
 * Repository Interface
 * 
 * Contract that every data-access repository must follow.
 * Enhanced with search and filtering capabilities.
 *
 * @template T  Domain object returned by get()/getAll()
 */
interface RepositoryInterface
{
    /**
     * Create a new entity and return its numeric ID.
     *
     * @param array $data  Validated associative array
     * @return int The created entity ID
     * @throws InvalidArgumentException When required data is missing or invalid
     * @throws RuntimeException When creation fails
     */
    public function create(array $data): int;

    /**
     * Fetch a single entity by ID.
     *
     * @param int $id Entity ID
     * @return T|null  Null when ID not found / wrong post-type
     */
    public function get(int $id);

    /**
     * Fetch **all** published entities.
     *
     * @return T[]
     */
    public function getAll(): array;

    /**
     * Update an existing entity; accepts partial payload.
     *
     * @param int $id Entity ID
     * @param array $data Partial data to update
     * @return bool  true on success, false when not found / wrong type
     * @throws InvalidArgumentException When update data is invalid
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete or trash an entity.
     *
     * @param int $id Entity ID
     * @param bool $force  true = bypass trash, false = move to trash
     * @return bool  true when deleted, false when not found / wrong type
     * @throws ResourceInUseException When entity has dependencies
     * @throws RuntimeException When deletion fails
     */
    public function delete(int $id, bool $force = false): bool;

    /**
     * Find entities by criteria with pagination.
     *
     * @param array $criteria Associative array of search criteria
     * @param int|null $limit Maximum number of results (null = no limit)
     * @param int $offset Number of results to skip
     * @return T[]
     */
    public function findBy(array $criteria, ?int $limit = null, int $offset = 0): array;

    /**
     * Count entities matching criteria.
     *
     * @param array $criteria Associative array of search criteria
     * @return int Number of matching entities
     */
    public function countBy(array $criteria): int;

    /**
     * Check if entity exists by ID.
     *
     * @param int $id Entity ID
     * @return bool True if entity exists
     */
    public function exists(int $id): bool;
}
