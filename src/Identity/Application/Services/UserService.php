<?php

namespace Src\Identity\Application\Services;

use Illuminate\Support\Facades\Hash;
use Src\Identity\Application\Contracts\UserRepositoryInterface;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    public function list(string $clinicId, bool $activeOnly = true): array
    {
        $users = $this->userRepository->listByClinic($clinicId, $activeOnly);

        return array_map(fn (object $u) => $this->excludePassword($u), $users);
    }

    public function find(string $id, string $clinicId): ?object
    {
        $user = $this->userRepository->findByIdAndClinic($id, $clinicId);

        return $user ? $this->excludePassword($user) : null;
    }

    public function create(string $clinicId, array $data): object
    {
        if ($this->userRepository->existsByEmail($data['email'])) {
            throw new \DomainException('A user with this email already exists');
        }

        $user = $this->userRepository->create([
            'clinic_id' => $clinicId,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => true,
        ]);

        return $this->excludePassword($user);
    }

    public function update(string $id, string $clinicId, array $data): ?object
    {
        $user = $this->userRepository->findByIdAndClinic($id, $clinicId);

        if (!$user) {
            return null;
        }

        if (isset($data['email']) && $data['email'] !== $user->email) {
            if ($this->userRepository->existsByEmail($data['email'], $id)) {
                throw new \DomainException('A user with this email already exists');
            }
        }

        $updateData = array_intersect_key($data, array_flip(['name', 'email', 'role', 'is_active']));
        if (!empty($data['password'] ?? null)) {
            $updateData['password'] = Hash::make($data['password']);
        }

        if (!empty($updateData)) {
            $this->userRepository->update($id, $clinicId, $updateData);
        }

        $user = $this->userRepository->findByIdAndClinic($id, $clinicId);

        return $this->excludePassword($user);
    }

    public function deactivate(string $id, string $clinicId): bool
    {
        $user = $this->userRepository->findByIdAndClinic($id, $clinicId);

        if (!$user) {
            return false;
        }

        $this->userRepository->deactivate($id, $clinicId);

        return true;
    }

    private function excludePassword(object $user): object
    {
        $arr = (array) $user;
        unset($arr['password']);
        return (object) $arr;
    }

}
