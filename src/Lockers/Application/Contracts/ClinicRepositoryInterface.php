<?php

namespace Src\Lockers\Application\Contracts;

interface ClinicRepositoryInterface
{
    public function findById(string $id): ?object;

    public function updateSettings(string $id, array $settings): void;
}
