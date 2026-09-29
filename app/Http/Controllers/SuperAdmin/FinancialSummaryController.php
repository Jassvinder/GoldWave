<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\CompanyFinancialSummary;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** T-172 (28-09-2026) — Super Admin's company-wide money and metal summary (first version). */
class FinancialSummaryController extends Controller
{
    public function index(Request $request, CompanyFinancialSummary $summary): Response
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return Inertia::render('super-admin/financial-summary', [
            'summary' => $summary->build($filters['from'] ?? null, $filters['to'] ?? null),
            'filters' => ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null],
        ]);
    }
}
