<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Services;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Contracts\MustNemesis;
use AndyDefer\Nemesis\Contracts\Services\NemesisAuthenticationInterface;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Enums\ErrorCode;
use AndyDefer\Nemesis\Models\NemesisToken;
use AndyDefer\Nemesis\Records\AuthenticationResultRecord;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use AndyDefer\Nemesis\ValueObjects\AuthenticationResultVO;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Service for authenticating requests using Nemesis tokens.
 *
 * Provides comprehensive authentication capabilities including token extraction,
 * validation, expiration checking, origin restrictions, permission verification,
 * and tracking metadata management.
 */
final class NemesisAuthenticationService implements NemesisAuthenticationInterface
{
    /**
     * Create a new NemesisAuthenticationService instance.
     *
     * @param  NemesisConfigInterface  $config  Configuration for token and middleware settings
     * @param  NemesisService  $nemesisService  Service for token management operations
     * @param  DatabaseManager  $db  Database manager for querying authenticatable models
     * @param  MetadataValidatorService  $metadataValidator  Validator for token metadata
     */
    public function __construct(
        private readonly NemesisConfigInterface $config,
        private readonly NemesisInterface $nemesisService,
        private readonly DatabaseManager $db,
        private readonly MetadataValidatorService $metadataValidator,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function authenticate(Request $request, ?string $requiredAbility = null): AuthenticationResultVO
    {
        $token = $this->extractToken($request);

        if ($token === null) {
            return $this->createErrorResult(
                errorCode: ErrorCode::MISSING_TOKEN,
                additionalData: null,
            );
        }

        $tokenModel = $this->findToken($token);

        if ($tokenModel === null) {
            return $this->createErrorResult(
                errorCode: ErrorCode::INVALID_TOKEN,
                additionalData: null,
            );
        }

        if ($tokenModel->isExpired()) {
            return $this->createErrorResult(
                errorCode: ErrorCode::TOKEN_EXPIRED,
                additionalData: null,
            );
        }

        $originResult = $this->checkOriginRestriction($tokenModel, $request);
        if ($originResult !== null) {
            return $originResult;
        }

        if ($requiredAbility !== null && ! $this->nemesisService->can($tokenModel, $requiredAbility)) {
            return $this->createErrorResult(
                errorCode: ErrorCode::INSUFFICIENT_PERMISSIONS,
                additionalData: new StrictDataObject([
                    'required_ability' => $requiredAbility,
                ]),
            );
        }

        $authenticatable = $this->getAuthenticatableFromToken($tokenModel);

        if ($authenticatable === null) {
            return $this->createErrorResult(
                errorCode: ErrorCode::INVALID_TOKEN,
                additionalData: null,
            );
        }

        if (! $authenticatable instanceof MustNemesis) {
            return $this->createErrorResult(
                errorCode: ErrorCode::INVALID_AUTHENTICATABLE_MODEL,
                additionalData: new StrictDataObject([
                    'model_class' => get_class($authenticatable),
                    'expected_interface' => MustNemesis::class,
                ]),
            );
        }

        $this->updateTokenUsage($tokenModel, $request);

        $tokenRecord = $this->convertTokenToRecord($tokenModel);

        return AuthenticationResultVO::from([
            'success' => true,
            'error_code' => null,
            'token_record' => $tokenRecord,
            'additional_data' => null,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function authenticateToRecord(Request $request, ?string $requiredAbility = null): AuthenticationResultRecord
    {
        return $this->authenticate($request, $requiredAbility)->getValue();
    }

    /**
     * {@inheritDoc}
     */
    public function getFormattedAuthenticatable(mixed $authenticatable): ?AbstractData
    {
        if (! $authenticatable instanceof MustNemesis) {
            return null;
        }

        return $authenticatable->nemesisFormat();
    }

    /**
     * Convert a token model to a record.
     *
     * @param  NemesisToken  $tokenModel  The token model to convert
     * @return NemesisTokenRecord The token record
     */
    private function convertTokenToRecord(NemesisToken $tokenModel): NemesisTokenRecord
    {
        return NemesisTokenRecord::from([
            'id' => $tokenModel->id,
            'name' => $tokenModel->name,
            'source' => $tokenModel->source,
            'token_hash' => $tokenModel->token_hash,
            'tokenable_type' => $tokenModel->tokenable_type,
            'tokenable_id' => $tokenModel->tokenable_id,
            'abilities' => $tokenModel->abilities,
            'metadata' => $tokenModel->metadata !== null ? StrictDataObject::from($tokenModel->metadata) : null,
            'expires_at' => $tokenModel->expires_at,
            'last_used_at' => $tokenModel->last_used_at,
            'created_at' => $tokenModel->created_at,
            'updated_at' => $tokenModel->updated_at,
            'deleted_at' => $tokenModel->deleted_at,
        ]);
    }

    /**
     * Create an error authentication result.
     *
     * @param  ErrorCode  $errorCode  The error code
     * @param  StrictDataObject|null  $additionalData  Additional error context
     * @return AuthenticationResultVO The error result
     */
    private function createErrorResult(ErrorCode $errorCode, ?StrictDataObject $additionalData): AuthenticationResultVO
    {
        return AuthenticationResultVO::from([
            'success' => false,
            'error_code' => $errorCode,
            'token_record' => null,
            'additional_data' => $additionalData,
        ]);
    }

    /**
     * Get the authenticatable model from a token.
     *
     * @param  NemesisToken  $tokenModel  The token model
     * @return Model|null The authenticatable model or null
     */
    private function getAuthenticatableFromToken(NemesisToken $tokenModel): ?Model
    {
        $tokenableType = $tokenModel->tokenable_type;
        $tokenableId = $tokenModel->tokenable_id;

        if ($tokenableType === null || $tokenableId === null) {
            return null;
        }

        if (! class_exists($tokenableType)) {
            return null;
        }

        $result = $this->queryAuthenticatable($tokenableType, $tokenableId);

        if ($result === null) {
            return null;
        }

        return $this->hydrateAuthenticatable($tokenableType, $result);
    }

    /**
     * Query the authenticatable model from the database.
     *
     * @param  string  $tokenableType  The model class
     * @param  int  $tokenableId  The model ID
     * @return object|null The query result or null
     */
    private function queryAuthenticatable(string $tokenableType, int $tokenableId): ?object
    {
        $modelInstance = new $tokenableType;
        $table = $modelInstance->getTable();

        return $this->db->table($table)
            ->where('id', $tokenableId)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Hydrate an authenticatable model from a database result.
     *
     * @param  string  $tokenableType  The model class
     * @param  object  $result  The database result
     * @return Model The hydrated model
     */
    private function hydrateAuthenticatable(string $tokenableType, object $result): Model
    {
        $eloquentModel = new $tokenableType;
        $eloquentModel->forceFill((array) $result);
        $eloquentModel->exists = true;

        return $eloquentModel;
    }

    /**
     * Extract the token from the request.
     *
     * @param  Request  $request  The HTTP request
     * @return string|null The extracted token or null
     */
    private function extractToken(Request $request): ?string
    {
        if ($this->config->isUsingCustomHeader()) {
            $headerName = $this->config->middlewareConfig()->token_header;
            $token = $request->header($headerName);

            if ($token !== null && trim($token) !== '') {
                return $token;
            }
        }

        $token = $request->bearerToken();

        return $token !== null && trim($token) !== '' ? $token : null;
    }

    /**
     * Find a token by its raw value.
     *
     * @param  string  $rawToken  The raw token
     * @return NemesisToken|null The token model or null
     */
    private function findToken(string $rawToken): ?NemesisToken
    {
        $hashAlgorithm = $this->config->tokenConfig()->hash_algorithm;
        $hashedToken = hash($hashAlgorithm, $rawToken);

        return $this->nemesisService->findByHash($hashedToken);
    }

    /**
     * Check if the token's origin is allowed.
     *
     * @param  NemesisToken  $tokenModel  The token model
     * @param  Request  $request  The HTTP request
     * @return AuthenticationResultVO|null Error result or null if valid
     */
    private function checkOriginRestriction(NemesisToken $tokenModel, Request $request): ?AuthenticationResultVO
    {
        if (! $this->config->middlewareConfig()->validate_origin) {
            return null;
        }

        $origin = $request->headers->get('Origin');

        if ($origin === null) {
            return null;
        }

        if (! $this->nemesisService->canUseFromOrigin($tokenModel, $origin)) {
            return $this->createErrorResult(
                errorCode: ErrorCode::ORIGIN_NOT_ALLOWED,
                additionalData: new StrictDataObject([
                    'origin' => $origin,
                ]),
            );
        }

        return null;
    }

    /**
     * Update token usage tracking metadata.
     *
     * @param  NemesisToken  $tokenModel  The token model
     * @param  Request  $request  The HTTP request
     */
    private function updateTokenUsage(NemesisToken $tokenModel, Request $request): void
    {
        $this->nemesisService->updateLastUsed($tokenModel);

        $trackingMetadata = $this->metadataValidator->process([
            'last_auth_ip' => $request->ip(),
            'last_auth_ua' => $request->userAgent(),
            'auth_count' => ($tokenModel->metadata['auth_count'] ?? 0) + 1,
        ]);

        if ($trackingMetadata !== null) {
            $this->nemesisService->mergeMetadata($tokenModel, $trackingMetadata);
        }
    }
}
