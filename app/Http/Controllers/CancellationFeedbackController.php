<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\CancellationFeedback;
use App\Traits\HandlesApiResponses;
use Illuminate\Support\Facades\Log;

class CancellationFeedbackController extends Controller
{
    use HandlesApiResponses;
    public function CreateFeedBack(Request $request){
        \Log::info($request->toArray());
        $validated = $request->validate([
            'reason' => 'required|string',
            'desired_features' => 'nullable|string',
            'other_feedback' => 'nullable|string',
        ]);
        $user = Auth::user();

        if (!$user) {
            return $this->errorResponse(
                message: 'User not authenticated.',
                status: 401,
                code: 'UNAUTHORIZED'
            );
        }

        $reason = $validated['reason'] ?? "";
        $desired_features = $validated['desired_features'] ?? "";
        $other_feedback = $validated['other_feedback'] ?? "";

        DB::beginTransaction();

        try {
            $query = CancellationFeedback::insert([
                'user_id'=>$user->id,
                'reason'=>$reason,
                'desired_features'=>$desired_features,
                'other_feedback'=>$other_feedback,
                'status'=>'active',
                'created_at'=>now(),
            ]);

            if($query){
                \Log::info("feedback inserted");

                DB::commit();
                if ($query) {
                    DB::commit();
                    return $this->successResponse(
                        message: 'Feedback submitted successfully.'
                    );
                }

            }
            else{
                \Log::info("somethng went wrong");
                DB::rollback();
                return $this->errorResponse(
                    message: 'Failed to save feedback.',
                    status: 500,
                    code: 'INSERT_FAILED'
                );
            }
        } catch (\Throwable $th) {
            //throw $th;
            \Log::info([$th]);
             return $this->errorResponse(
                message: 'An unexpected error occurred while saving feedback.',
                status: 500,
                code: 'EXCEPTION',
                debug: [
                    'file' => $th->getFile(),
                    'line' => $th->getLine(),
                    'message' => $th->getMessage(),
                ]
            );
        }
    }
}
