<?php

namespace App\Services;

use App\Contracts\FundingRepositoryContract;
use App\Contracts\UserRepositoryContract;
use App\Models\Funding;
use Illuminate\Support\Facades\Log;

/**
 * FundingService
 *
 * Handles all business logic related to funding requests.
 * Coordinates between repositories and external services.
 * Keeps controllers thin and focused on HTTP concerns.
 *
 * @package App\Services
 */
class FundingService
{
    public function __construct(
        private FundingRepositoryContract $fundingRepository,
        private UserRepositoryContract $userRepository,
        private AiScoringService $aiScoringService,
    ) {
    }

    /**
     * Create a new funding request.
     *
     * @param array $data
     * @return array
     */
    public function createRequest(array $data): array
    {
        try {
            // Validate user exists
            $user = $this->userRepository->findById($data['user_id']);
            if (!$user) {
                throw new \Exception('User not found');
            }

            // Create the funding request
            $fundingRequest = $this->fundingRepository->create($data);

            // Score the request asynchronously
            // TODO: Use queued job for large-scale operations
            $this->scoreRequest($fundingRequest['id']);

            Log::info('Funding request created', [
                'funding_id' => $fundingRequest['id'],
                'user_id' => $data['user_id'],
            ]);

            return $fundingRequest;
        } catch (\Exception $e) {
            Log::error('Failed to create funding request', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Score a funding request using AI.
     *
     * @param string|int $fundingId
     * @return array|bool
     */
    public function scoreRequest(string|int $fundingId): array|bool
    {
        try {
            $fundingRequest = Funding::findOrFail($fundingId);
            $aiScore = $this->aiScoringService->scoreFundingRequest($fundingRequest);

            if (!$aiScore) {
                Log::warning('AI scoring returned null', ['funding_id' => $fundingId]);
                return false;
            }

            // Update the funding request with AI scores
            return $this->fundingRepository->update($fundingId, [
                'ai_score' => $aiScore['total_score'],
                'ai_score_breakdown' => json_encode($aiScore['breakdown']),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to score funding request', [
                'funding_id' => $fundingId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Approve a funding request.
     *
     * @param string|int $fundingId
     * @param string|int $adminId
     * @param string|null $notes
     * @return array|bool
     */
    public function approveRequest(string|int $fundingId, string|int $adminId, ?string $notes = null): array|bool
    {
        try {
            $result = $this->fundingRepository->update($fundingId, [
                'status_id' => 'approved',
                'approved_by' => $adminId,
                'admin_notes' => $notes,
                'approved_at' => now()->toIso8601String(),
            ]);

            Log::info('Funding request approved', [
                'funding_id' => $fundingId,
                'approved_by' => $adminId,
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to approve funding request', [
                'funding_id' => $fundingId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Reject a funding request.
     *
     * @param string|int $fundingId
     * @param string|null $reason
     * @return array|bool
     */
    public function rejectRequest(string|int $fundingId, ?string $reason = null): array|bool
    {
        try {
            $result = $this->fundingRepository->update($fundingId, [
                'status_id' => 'rejected',
                'admin_notes' => $reason,
                'rejected_at' => now()->toIso8601String(),
            ]);

            Log::info('Funding request rejected', [
                'funding_id' => $fundingId,
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to reject funding request', [
                'funding_id' => $fundingId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get pending funding requests sorted by AI score.
     *
     * @return array
     */
    public function getPendingRequests(): array
    {
        try {
            $requests = $this->fundingRepository->findByStatus('pending');
            // Sort by AI score (highest first)
            usort($requests, function ($a, $b) {
                return ($b['ai_score'] ?? 0) <=> ($a['ai_score'] ?? 0);
            });
            return $requests;
        } catch (\Exception $e) {
            Log::error('Failed to get pending funding requests', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
