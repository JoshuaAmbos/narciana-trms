<?php

namespace App\Http\Requests;

/**
 * Legacy wrapper aliasing StoreTenantRequest for backwards compatibility.
 */
class TenantRequest extends StoreTenantRequest
{
    // Inherits identical validation rules and normalization
}
