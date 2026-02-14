<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GoalCommentResource;
use App\Models\Goal;
use App\Models\GoalComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class GoalCommentController extends Controller
{
    /**
     * Display a listing of comments for a goal.
     */
    public function index(Goal $goal): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $comments = $goal->comments()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return GoalCommentResource::collection($comments);
    }

    /**
     * Store a newly created comment.
     */
    public function store(Request $request, Goal $goal): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $request->validate([
            'comment' => ['required', 'string', 'max:5000'],
            'is_private' => ['boolean'],
        ]);

        $comment = $goal->comments()->create([
            'user_id' => $request->user()->id,
            'comment' => $request->input('comment'),
            'is_private' => $request->boolean('is_private', false),
        ]);

        $comment->load('user');

        return (new GoalCommentResource($comment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified comment.
     */
    public function show(Goal $goal, GoalComment $comment): GoalCommentResource
    {
        Gate::authorize('can-manage-organization');

        $comment->load('user');

        return new GoalCommentResource($comment);
    }

    /**
     * Update the specified comment.
     */
    public function update(Request $request, Goal $goal, GoalComment $comment): GoalCommentResource
    {
        Gate::authorize('can-manage-organization');

        // Only the author can edit their comment
        if ($comment->user_id !== $request->user()->id) {
            abort(403, 'You can only edit your own comments.');
        }

        $request->validate([
            'comment' => ['required', 'string', 'max:5000'],
            'is_private' => ['boolean'],
        ]);

        $comment->update([
            'comment' => $request->input('comment'),
            'is_private' => $request->boolean('is_private', $comment->is_private),
        ]);

        $comment->load('user');

        return new GoalCommentResource($comment);
    }

    /**
     * Remove the specified comment.
     */
    public function destroy(Request $request, Goal $goal, GoalComment $comment): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        // Only the author can delete their comment
        if ($comment->user_id !== $request->user()->id) {
            abort(403, 'You can only delete your own comments.');
        }

        $comment->delete();

        return response()->json([
            'message' => 'Comment deleted successfully.',
        ]);
    }
}
