<?php

namespace App\Support;

/**
 * Kode error terstandarisasi untuk seluruh API.
 *
 * Pola: {HTTP_CODE}{SUB_CODE}
 * - 400xxx : Bad Request
 * - 401xxx : Unauthenticated
 * - 403xxx : Forbidden
 * - 404xxx : Not Found
 * - 422xxx : Validation Error
 * - 500xxx : Server Error
 */
class ErrorCode
{
    // ── 400 Bad Request ──────────────────────────────────────────────
    public const MISSING_HEADERS       = 400001;
    public const MISSING_SIGN_HEADERS  = 400002;

    // ── 401 Unauthenticated ──────────────────────────────────────────
    public const UNAUTHENTICATED       = 401000;
    public const INVALID_CLIENT        = 401001;
    public const TOKEN_EXPIRED         = 401002;
    public const INVALID_TOKEN         = 401003;
    public const STALE_TIMESTAMP       = 401007;
    public const INVALID_SIGNATURE     = 401008;
    public const INVALID_SYM_SIGNATURE = 401010;

    // ── 403 Forbidden ────────────────────────────────────────────────
    public const FORBIDDEN_SCOPE       = 403001;

    // ── 404 Not Found ────────────────────────────────────────────────
    public const DATA_NOT_FOUND        = 404001;

    // ── 422 Validation Error ─────────────────────────────────────────
    public const VALIDATION_FAILED     = 422001;

    // ── 500 Server Error ─────────────────────────────────────────────
    public const INTERNAL_ERROR        = 500000;
    public const EXTERNAL_API_ERROR    = 500001;
    public const EXTERNAL_HTTP_ERROR   = 500002;

    // ── 503 Service Unavailable ──────────────────────────────────────
    public const SERVICE_UNAVAILABLE   = 503001;
}

