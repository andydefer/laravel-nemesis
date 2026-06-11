<?php

// src/Services/NemesisAuthenticationService.php

declare(strict_types=1);

namespace Kani\Nemesis\Services;

use AndyDefer\DataValidator\Services\MetadataValidator;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\PhpServices\Services\RecordTransformableService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Kani\Nemesis\Contracts\Configs\NemesisConfigInterface;
use Kani\Nemesis\Contracts\MustNemesis;
use Kani\Nemesis\Enums\ErrorCode;
use Kani\Nemesis\Models\NemesisToken;
use Kani\Nemesis\Records\AuthenticationResultRecord;
use Kani\Nemesis\Records\NemesisTokenRecord;
use Kani\Nemesis\ValueObjects\AuthenticationResultVO;

class NemesisAuthenticationService
{

    public function __construct(
        private readonly NemesisConfigInterface $config,
        private readonly NemesisService $nemesisService,
        private readonly RecordTransformableService $recordTransformableService,
        private readonly DatabaseManager $db,
        private readonly MetadataValidator $metadataValidator,
        private readonly HydrationService $hydration,
    ) {}

    public function authenticate(Request $request, ?string $requiredAbility = null): AuthenticationResultVO
    {
        // Extract token
        $token = $this->extractToken($request);

        if ($token === null) {
            return $this->hydration->hydrate(AuthenticationResultVO::class, [
                'success' => false,
                'error_code' => ErrorCode::MISSING_TOKEN,
                'token_record' => null,
                'additional_data' => null,
            ]);
        }

        // Find token in database
        $tokenModel = $this->findToken($token);

        if ($tokenModel === null) {
            return $this->hydration->hydrate(AuthenticationResultVO::class, [
                'success' => false,
                'error_code' => ErrorCode::INVALID_TOKEN,
                'token_record' => null,
                'additional_data' => null,
            ]);
        }

        // Check expiration
        if ($tokenModel->isExpired()) {
            return $this->hydration->hydrate(AuthenticationResultVO::class, [
                'success' => false,
                'error_code' => ErrorCode::TOKEN_EXPIRED,
                'token_record' => null,
                'additional_data' => null,
            ]);
        }

        // Check origin restriction
        $originResult = $this->checkOriginRestriction($tokenModel, $request);
        if ($originResult !== null) {
            return $originResult;
        }

        // Check ability
        if ($requiredAbility !== null && !$this->nemesisService->can($tokenModel, $requiredAbility)) {
            return $this->hydration->hydrate(AuthenticationResultVO::class, [
                'success' => false,
                'error_code' => ErrorCode::INSUFFICIENT_PERMISSIONS,
                'token_record' => null,
                'additional_data' => new StrictDataObject([
                    'required_ability' => $requiredAbility,
                ]),
            ]);
        }

        // Get authenticatable model from tokenable_type and tokenable_id
        $authenticatable = $this->getAuthenticatableFromToken($tokenModel);

        if ($authenticatable === null) {
            return $this->hydration->hydrate(AuthenticationResultVO::class, [
                'success' => false,
                'error_code' => ErrorCode::INVALID_TOKEN,
                'token_record' => null,
                'additional_data' => null,
            ]);
        }

        // Validate authenticatable implements required interface
        if (!$authenticatable instanceof MustNemesis) {
            return $this->hydration->hydrate(AuthenticationResultVO::class, [
                'success' => false,
                'error_code' => ErrorCode::INVALID_AUTHENTICATABLE_MODEL,
                'token_record' => null,
                'additional_data' => new StrictDataObject([
                    'model_class' => get_class($authenticatable),
                    'expected_interface' => MustNemesis::class,
                ]),
            ]);
        }

        // Update token usage and add tracking metadata
        $this->nemesisService->updateLastUsed($tokenModel);

        // Add tracking metadata
        $trackingMetadata = $this->metadataValidator->process([
            'last_auth_ip' => $request->ip(),
            'last_auth_ua' => $request->userAgent(),
            'auth_count' => ($tokenModel->metadata['auth_count'] ?? 0) + 1,
        ]);

        if ($trackingMetadata !== null) {
            $this->nemesisService->mergeMetadata($tokenModel, $trackingMetadata);
        }

        // Convert token model to record
        $tokenRecord = $this->recordTransformableService->toRecord($tokenModel, NemesisTokenRecord::class);

        return $this->hydration->hydrate(AuthenticationResultVO::class, [
            'success' => true,
            'error_code' => null,
            'token_record' => $tokenRecord,
            'additional_data' => null,
        ]);
    }

    public function authenticateToRecord(Request $request, ?string $requiredAbility = null): AuthenticationResultRecord
    {
        return $this->authenticate($request, $requiredAbility)->getValue();
    }

    public function getFormattedAuthenticatable(mixed $authenticatable): ?AbstractRecord
    {
        if (!$authenticatable instanceof MustNemesis) {
            return null;
        }

        return $authenticatable->nemesisFormat();
    }

    private function getAuthenticatableFromToken(NemesisToken $tokenModel): mixed
    {
        $tokenableType = $tokenModel->tokenable_type;
        $tokenableId = $tokenModel->tokenable_id;

        if ($tokenableType === null || $tokenableId === null) {
            return null;
        }

        if (!class_exists($tokenableType)) {
            return null;
        }

        $modelInstance = new $tokenableType();
        $table = $modelInstance->getTable();

        $result = $this->db->table($table)
            ->where('id', $tokenableId)
            ->whereNull('deleted_at')
            ->first();

        if ($result === null) {
            return null;
        }

        $eloquentModel = new $tokenableType();
        $eloquentModel->forceFill((array) $result);
        $eloquentModel->exists = true;

        return $eloquentModel;
    }

    private function extractToken(Request $request): ?string
    {
        // ✅ Utilisation de la nouvelle API avec middlewareConfig()
        if ($this->config->isUsingCustomHeader()) {
            $token = $request->header($this->config->middlewareConfig()->token_header);
            if ($token !== null && trim($token) !== '') {
                return $token;
            }
        }

        $token = $request->bearerToken();

        return $token !== null && trim($token) !== '' ? $token : null;
    }

    private function findToken(string $rawToken): ?NemesisToken
    {
        // ✅ Utilisation de la nouvelle API avec tokenConfig()
        $hashedToken = hash($this->config->tokenConfig()->hash_algorithm, $rawToken);

        return $this->nemesisService->findByHash($hashedToken);
    }

    private function checkOriginRestriction(NemesisToken $tokenModel, Request $request): ?AuthenticationResultVO
    {
        // ✅ Utilisation de la nouvelle API avec middlewareConfig()
        if (!$this->config->middlewareConfig()->validate_origin) {
            return null;
        }

        $origin = $request->headers->get('Origin');

        if ($origin === null) {
            return null;
        }

        if (!$this->nemesisService->canUseFromOrigin($tokenModel, $origin)) {
            return $this->hydration->hydrate(AuthenticationResultVO::class, [
                'success' => false,
                'error_code' => ErrorCode::ORIGIN_NOT_ALLOWED,
                'token_record' => null,
                'additional_data' => new StrictDataObject([
                    'origin' => $origin,
                ]),
            ]);
        }

        return null;
    }
}
