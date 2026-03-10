<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Ai\Evaluation\FeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Feedback Controller
 *
 * Handles user feedback submission for RAG queries.
 */
class FeedbackController extends Controller
{
    /**
     * Feedback service.
     */
    private FeedbackService $feedbackService;

    /**
     * Create a new controller instance.
     */
    public function __construct(?FeedbackService $feedbackService = null)
    {
        $this->feedbackService = $feedbackService ?? new FeedbackService();
    }

    /**
     * Store user feedback for a RAG query.
     *
     * POST /api/feedback
     *
     * Request body:
     * {
     *   "query_id": "string (required) - The query identifier",
     *   "rating": "string (required) - thumbs_up or thumbs_down",
     *   "comment": "string (optional) - User comment",
     *   "expected_answer": "string (optional) - What user expected",
     *   "category": "string (optional) - Feedback category"
     * }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query_id' => 'required|string|max:255',
            'rating' => 'required|string|in:thumbs_up,thumbs_down',
            'comment' => 'nullable|string|max:2000',
            'expected_answer' => 'nullable|string|max:5000',
            'category' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        // Check if user already provided feedback for this query
        if ($this->feedbackService->hasFeedback($request->input('query_id'), $user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'You have already provided feedback for this query.',
            ], 409);
        }

        $feedback = $this->feedbackService->submitFeedback(
            queryId: $request->input('query_id'),
            userId: $user->id,
            rating: $request->input('rating'),
            comment: $request->input('comment'),
            expectedAnswer: $request->input('expected_answer'),
            category: $request->input('category'),
        );

        if ($feedback === null) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit feedback. Please try again.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $feedback->id,
                'query_id' => $feedback->query_id,
                'rating' => $feedback->rating,
                'created_at' => $feedback->created_at->toIso8601String(),
            ],
            'message' => 'Feedback submitted successfully.',
        ], 201);
    }

    /**
     * Get feedback for a specific query.
     *
     * GET /api/feedback/{queryId}
     *
     * @param string $queryId
     * @return JsonResponse
     */
    public function show(string $queryId): JsonResponse
    {
        $user = auth()->user();
        $feedback = $this->feedbackService->getFeedbackForQuery($queryId);

        // Filter to only show current user's feedback unless admin
        if (!$user->isAdmin()) {
            $feedback = $feedback->where('user_id', $user->id);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'query_id' => $queryId,
                'feedback' => $feedback->map(fn ($f) => [
                    'id' => $f->id,
                    'user_id' => $f->user_id,
                    'rating' => $f->rating,
                    'comment' => $f->comment,
                    'created_at' => $f->created_at->toIso8601String(),
                ]),
                'count' => $feedback->count(),
            ],
        ]);
    }
}
