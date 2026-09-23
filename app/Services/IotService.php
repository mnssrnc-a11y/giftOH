<?php

namespace App\Services;

use App\Repositories\FirebaseIotBoxRepository;
use App\Repositories\FirebaseDonationRepository;
use App\Services\FundingService;

class IotService
{
    private const MISSED_CHECKS_BEFORE_OFFLINE = 4;
    private const HEARTBEAT_INTERVAL_SECONDS = 5;

    public function __construct(
        private FirebaseIotBoxRepository $boxRepository,
        private FirebaseDonationRepository $donationRepository,
        private FundingService $fundingService
    ) {
    }

    /**
     * Get all smart boxes from Firebase. Returns null if unavailable.
     */
    public function getBoxes(): ?array
    {
        return $this->boxRepository->getBoxes();
    }

    /**
     * Get total count of smart boxes. Returns null if Firebase is unreachable.
     */
    public function getSmartBoxCount(): ?int
    {
        $boxes = $this->getBoxes();

        return $boxes !== null ? count($boxes) : null;
    }

    /**
     * Get count of online smart boxes.
     */
    public function getOnlineBoxCount(): ?int
    {
        $boxes = $this->getBoxes();

        if ($boxes === null) {
            return null;
        }

        $offlineAfterSeconds = self::MISSED_CHECKS_BEFORE_OFFLINE * self::HEARTBEAT_INTERVAL_SECONDS;
        $now = now()->timestamp * 1000;

        return count(array_filter($boxes, static function (array $box) use ($now, $offlineAfterSeconds): bool {
            $lastHeartbeat = (int) ($box['heartbeat'] ?? 0);
            $lastSeen = (int) ($box['lastSeen'] ?? 0);

            if ($lastHeartbeat === 0 || $lastSeen === 0) {
                return false;
            }

            return ($now - $lastSeen) <= ($offlineAfterSeconds * 1000);
        }));
    }

    /**
     * Get total donation collected from smart boxes and direct donations.
     */
    public function getDonationOverview(): ?float
    {
        $boxes = $this->getBoxes();

        if ($boxes === null) {
            return null;
        }

        $boxTotal = 0.0;
        foreach ($boxes as $box) {
            $boxTotal += (float) ($box['total'] ?? 0);
        }

        try {
            $donations = $this->donationRepository->all();
            $directTotal = 0.0;
            foreach ($donations as $donation) {
                $directTotal += (float) ($donation['amount'] ?? 0);
            }
        } catch (\Throwable) {
            $directTotal = 0.0;
        }

        return $boxTotal + $directTotal;
    }

    /**
     * Get total funds available to the organization.
     */
    public function getTotalFunds(): ?float
    {
        return $this->getDonationOverview();
    }

    /**
     * Get available funds (Total funds minus approved funding allocations).
     */
    public function getAvailableFunds(): ?float
    {
        $total = $this->getTotalFunds();

        if ($total === null) {
            return null;
        }

        $approvedAmount = $this->fundingService->getTotalApprovedAmount();

        return max(0.0, $total - $approvedAmount);
    }

    /**
     * Get all dashboard metrics with null-handling and defaults.
     */
    public function getDashboardMetrics(): array
    {
        $boxes = $this->getBoxes();
        $smartBoxCount = $this->getSmartBoxCount();
        $onlineBoxCount = $this->getOnlineBoxCount();
        $donationOverview = $this->getDonationOverview();
        $totalFunds = $this->getTotalFunds();
        $availableFunds = $this->getAvailableFunds();

        $availablePercentage = ($totalFunds !== null && $totalFunds > 0)
            ? round(($availableFunds / $totalFunds) * 100)
            : 0;

        return [
            'boxes' => $boxes,
            'smartBoxCount' => $smartBoxCount,
            'onlineBoxCount' => $onlineBoxCount,
            'donationOverview' => $donationOverview,
            'totalFunds' => $totalFunds,
            'availableFunds' => $availableFunds,
            'availablePercentage' => $availablePercentage,
        ];
    }
}
