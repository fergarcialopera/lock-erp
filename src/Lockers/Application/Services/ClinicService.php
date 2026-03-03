<?php

namespace Src\Lockers\Application\Services;

use Src\Lockers\Application\Contracts\ClinicRepositoryInterface;

class ClinicService
{
    public function __construct(
        private readonly ClinicRepositoryInterface $clinicRepository
    ) {
    }

    public function get(string $clinicId): ?object
    {
        return $this->clinicRepository->findById($clinicId);
    }

    public function updateSettings(string $clinicId, int $openLatencyMs): void
    {
        $clinic = $this->clinicRepository->findById($clinicId);

        $settings = $clinic && $clinic->settings
            ? (array) json_decode($clinic->settings, true)
            : [];
        $settings['open_latency_ms'] = $openLatencyMs;

        $this->clinicRepository->updateSettings($clinicId, $settings);
    }
}
