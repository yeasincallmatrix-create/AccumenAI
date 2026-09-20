<?php

use App\Models\LabIntegration\LabAnalyzer;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Phase 9 lab analyzer integration: institute-wide channel.
Broadcast::channel('institute.{instituteId}.lab-analyzer', function ($user, $instituteId) {
    $userInstituteId = $user->institute_id
        ?? \App\Support\Workspace::membership()?->institution_id
        ?? \App\Support\TenantContext::id();

    return (int) $userInstituteId === (int) $instituteId
        && $user->hasPermission('medical.laboratory.analyzers.view');
});

// Phase 9 lab analyzer integration: per-analyzer channel.
Broadcast::channel('lab-analyzer.{analyzerId}', function ($user, $analyzerId) {
    $analyzer = LabAnalyzer::find($analyzerId);
    if (! $analyzer) {
        return false;
    }

    $userInstituteId = $user->institute_id
        ?? \App\Support\Workspace::membership()?->institution_id
        ?? \App\Support\TenantContext::id();

    return (int) $analyzer->institute_id === (int) $userInstituteId
        && $user->hasPermission('medical.laboratory.analyzers.view');
});
