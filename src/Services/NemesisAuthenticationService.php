<?php

declare(strict_types=1);

namespace Kani\Nemesis\Services;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\PhpServices\Services\RecordTransformableService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Kani\Nemesis\Config\NemesisConfig;
use Kani\Nemesis\Contracts\CanBeFormatted;
use Kani\Nemesis\Enums\ErrorCode;
use Kani\Nemesis\Models\NemesisToken;
use Kani\Nemesis\Records\AuthenticationResultRecord;
use Kani\Nemesis\Records\NemesisTokenRecord;
use Kani\Nemesis\ValueObjects\AuthenticationResultVO;

final class NemesisAuthenticationService
{
    public function __construct(
        private readonly NemesisConfig $config,
        private readonly NemesisService $nemesisService,
        private readonly RecordTransformableService $recordTransformableService,
        private readonly DatabaseManager $db,
    ) {}

    /**
     * Authenticate a request and return the result as a Value Object.
     */
    public function authenticate(Request $request, ?string $requiredAbility = null): AuthenticationResultVO
    {
        // Extract token
        $token = $this->extractToken($request);

        if ($token === null) {
            return AuthenticationResultVO::from([
                'success' => false,
                'error_code' => ErrorCode::MISSING_TOKEN,
                'token_record' => null,
                'additional_data' => null,
            ]);
        }

        // Find token in database
        $tokenModel = $this->findToken($token);

        if ($tokenModel === null) {
            return AuthenticationResultVO::from([
                'success' => false,
                'error_code' => ErrorCode::INVALID_TOKEN,
                'token_record' => null,
                'additional_data' => null,
            ]);
        }

        // Check expiration
        if ($tokenModel->is_expired) {
            return AuthenticationResultVO::from([
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
            return AuthenticationResultVO::from([
                'success' => false,
                'error_code' => ErrorCode::INSUFFICIENT_PERMISSIONS,
                'token_record' => null,
                'additional_data' => StrictDataObject::from([
                    'required_ability' => $requiredAbility,
                ]),
            ]);
        }

        // Get authenticatable model from tokenable_type and tokenable_id
        $authenticatable = $this->getAuthenticatableFromToken($tokenModel);

        if ($authenticatable === null) {
            return AuthenticationResultVO::from([
                'success' => false,
                'error_code' => ErrorCode::INVALID_TOKEN,
                'token_record' => null,
                'additional_data' => null,
            ]);
        }

        // Validate authenticatable implements required interface
        if (!$authenticatable instanceof CanBeFormatted) {
            return AuthenticationResultVO::from([
                'success' => false,
                'error_code' => ErrorCode::INVALID_AUTHENTICATABLE_MODEL,
                'token_record' => null,
                'additional_data' => StrictDataObject::from([
                    'model_class' => get_class($authenticatable),
                    'expected_interface' => CanBeFormatted::class,
                ]),
            ]);
        }

        // Update token usage
        $this->nemesisService->updateLastUsed($tokenModel);

        // Convert token model to record
        $tokenRecord = $this->recordTransformableService->toRecord($tokenModel, NemesisTokenRecord::class);

        return AuthenticationResultVO::from([
            'success' => true,
            'error_code' => null,
            'token_record' => $tokenRecord,
            'additional_data' => null,
        ]);
    }

    /**
     * Authenticate and return the result as a Record.
     */
    public function authenticateToRecord(Request $request, ?string $requiredAbility = null): AuthenticationResultRecord
    {
        return $this->authenticate($request, $requiredAbility)->getValue();
    }

    /**
     * Get the formatted authenticatable record for response.
     */
    public function getFormattedAuthenticatable(mixed $authenticatable): ?AbstractRecord
    {
        if (!$authenticatable instanceof CanBeFormatted) {
            return null;
        }

        return $authenticatable->nemesisFormat();
    }

    /**
     * Get the authenticatable model from token using tokenable_type and tokenable_id.
     */
    private function getAuthenticatableFromToken(NemesisToken $tokenModel): mixed
    {
        $tokenableType = $tokenModel->tokenable_type;
        $tokenableId = $tokenModel->tokenable_id;

        if ($tokenableType === null || $tokenableId === null) {
            return null;
        }

        // Check if the model class exists
        if (!class_exists($tokenableType)) {
            return null;
        }

        // Get the table name from the model
        $modelInstance = new $tokenableType();
        $table = $modelInstance->getTable();

        // Use injected database connection
        $result = $this->db->table($table)
            ->where('id', $tokenableId)
            ->first();

        if ($result === null) {
            return null;
        }

        // Convert to Eloquent model
        $eloquentModel = new $tokenableType();
        $eloquentModel->forceFill((array) $result);
        $eloquentModel->exists = true;

        return $eloquentModel;
    }

    private function extractToken(Request $request): ?string
    {
        if ($this->config->isUsingCustomHeader()) {
            $token = $request->header($this->config->getTokenHeader());
            if ($token !== null && trim($token) !== '') {
                return $token;
            }
        }

        $token = $request->bearerToken();
        return $token !== null && trim($token) !== '' ? $token : null;
    }

    private function findToken(string $rawToken): ?NemesisToken
    {
        $hashedToken = hash($this->config->getHashAlgorithm(), $rawToken);
        return $this->nemesisService->findByHash($hashedToken);
    }

    private function checkOriginRestriction(NemesisToken $tokenModel, Request $request): ?AuthenticationResultVO
    {
        if (!$this->config->getValidateOrigin()) {
            return null;
        }

        $origin = $request->headers->get('Origin');

        if ($origin === null) {
            return null;
        }

        if (!$this->nemesisService->canUseFromOrigin($tokenModel, $origin)) {
            return AuthenticationResultVO::from([
                'success' => false,
                'error_code' => ErrorCode::ORIGIN_NOT_ALLOWED,
                'token_record' => null,
                'additional_data' => StrictDataObject::from([
                    'origin' => $origin,
                ]),
            ]);
        }

        return null;
    }
}
