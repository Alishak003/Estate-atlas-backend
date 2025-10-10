<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InvestmentCalculation;
use App\Traits\HandlesApiResponses;
use App\Traits\ChecksRequestTimeout;
use App\Traits\AuthenticatedUserCheck;

class InvestmentCalculatorController extends Controller
{
    use HandlesApiResponses, ChecksRequestTimeout, AuthenticatedUserCheck;

    public function getAllCalculations(Request $request)
    {
        // Optional: Start timing
        $startTime = microtime(true);

        // Optional: Require JWT auth
        $this->checkIfAuthenticatedAndUser();

        if (!in_array($request->method(), ['GET'])) {
            throw new \App\Exceptions\MethodNotAllowedException();
        }

        try {
            $calculations = InvestmentCalculation::latest()->paginate(10); // or ->get() if you don’t want pagination

            $this->checkRequestTimeout($startTime);

            return $this->successResponse($calculations, 'Investment calculations retrieved');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to fetch calculations', 500, 'FETCH_ERROR', $e->getMessage(), [], [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }

    public function getLatestCalculation(Request $request)
    {
        $startTime = microtime(true);
        $this->checkIfAuthenticatedAndUser();
        if (!in_array($request->method(), ['GET'])) {
            throw new \App\Exceptions\MethodNotAllowedException();
        }
        try {
            $latest = InvestmentCalculation::latest()->first();
            $this->checkRequestTimeout($startTime);
            if (!$latest) {
                return $this->errorResponse('No investment records found', 404, 'NOT_FOUND');
            }
            return $this->successResponse($latest, 'Latest investment calculation retrieved');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to fetch latest calculation', 500, 'LATEST_FETCH_ERROR', $e->getMessage(), [], [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }



    public function calculate(Request $request)
    {
        // Optional: Start timing the request
        $startTime = microtime(true);

        // Optional: Enforce authentication
        $this->checkIfAuthenticatedAndUser();

        if (!in_array($request->method(), ['POST'])) {
            throw new \App\Exceptions\MethodNotAllowedException();
        }

        // Validate the incoming request
        $validated = $request->validate([
            'property_value' => 'required|numeric|min:1',
            'down_payment' => 'required|numeric|between:0,100',
            'interest_rate' => 'required|numeric|min:0',
            'loan_term' => 'required|integer|min:1',
            'monthly_rental_income' => 'required|numeric|min:0',
            'monthly_expenses' => 'required|numeric|min:0',
        ]);

        try {
            // Calculation
            $loanAmount = $validated['property_value'] * (1 - $validated['down_payment'] / 100);
            $monthlyInterest = $validated['interest_rate'] / 100 / 12;
            $totalPayments = $validated['loan_term'] * 12;

            $monthlyMortgage = $monthlyInterest == 0
                ? $loanAmount / $totalPayments
                : $loanAmount * ($monthlyInterest * pow(1 + $monthlyInterest, $totalPayments)) / (pow(1 + $monthlyInterest, $totalPayments) - 1);

            $monthlyNetIncome = $validated['monthly_rental_income'] - $validated['monthly_expenses'] - $monthlyMortgage;
            $annualCashFlow = $monthlyNetIncome * 12;
            $initialInvestment = $validated['property_value'] * ($validated['down_payment'] / 100);
            $roi = $initialInvestment > 0 ? ($annualCashFlow / $initialInvestment) * 100 : 0;

            // Store in DB
            $record = InvestmentCalculation::create([
                ...$validated,
                'monthly_mortgage' => round($monthlyMortgage, 2),
                'annual_cash_flow' => round($annualCashFlow, 2),
                'roi_percent' => round($roi, 2),
            ]);

            // Request time check (optional)
            $this->checkRequestTimeout($startTime);

            return $this->successResponse($record, 'Investment calculation completed');
        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong', 500, 'CALCULATION_ERROR', $e->getMessage(), [], [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }
}
