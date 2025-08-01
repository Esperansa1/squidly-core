<?php
declare(strict_types=1);

/**
 * Contract that every data-access repository must follow.
 *
 * @template T  Domain object returned by get()/getAll()
 */
interface RepositoryInterface
{
    /**
     * Create a new entity and return its numeric ID.
     *
     * @param array $data  Validated associative array
     */
    public function create(array $data): int;

    /**
     * Fetch a single entity by ID.
     *
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
     * @return bool  true on success, false when not found / wrong type
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete or trash an entity.
     *
     * @param bool $force  true = bypass trash
     * @return bool        true when deleted, false when not found / wrong type
     */
    public function delete(int $id, bool $force = false): bool;
}
