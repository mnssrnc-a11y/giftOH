// app/Contracts/UserRepositoryContract.php
<?php
interface UserRepositoryContract {
    public function findById(string|int $id): ?array;
    public function findByEmail(string $email): ?array;
    public function create(array $data): array;
}

// app/Repositories/FirebaseUserRepository implements UserRepositoryContract
// (Already exists, but now is the single source)