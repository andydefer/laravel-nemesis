<?php

// src/Helpers/NemesisHelper.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Helpers;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;

final class NemesisHelper
{
    public function __construct(
        private readonly Request $request,
        private readonly NemesisConfigInterface $config,
    ) {}

    public function getCurrentToken(): ?NemesisTokenRecord
    {
        $token = $this->request->input('current_nemesis_token');

        if ($token instanceof NemesisTokenRecord) {
            return $token;
        }

        return null;
    }

    public function getCurrentAuthenticatable(): ?Model
    {
        // ✅ Utilisation de la nouvelle API avec middlewareConfig()
        $parameterName = $this->config->middlewareConfig()->parameter_name;
        $authenticatable = $this->request->input($parameterName);

        if ($authenticatable instanceof Model) {
            return $authenticatable;
        }

        return null;
    }

    public function getCurrentAuthenticatableFormat(): ?AbstractRecord
    {
        // ✅ Utilisation de la nouvelle API avec middlewareConfig()
        $parameterName = $this->config->middlewareConfig()->parameter_name;
        $formatKey = $parameterName . '_format';
        $formatted = $this->request->input($formatKey);

        if ($formatted instanceof AbstractRecord) {
            return $formatted;
        }

        return null;
    }

    public function hasCurrentToken(): bool
    {
        return $this->getCurrentToken() !== null;
    }

    public function hasCurrentAuthenticatable(): bool
    {
        return $this->getCurrentAuthenticatable() !== null;
    }
}
