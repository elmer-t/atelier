<?php

namespace App\Mcp\Tools;

use App\Mcp\AgentAbilities;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Reply to a comment Thread in the conversation where feedback was raised. The reply is attributed to the agent; it can never mark a Thread Resolved.')]
class ReplyToThread extends AgentTool
{
    protected function requiredAbility(): string
    {
        return AgentAbilities::COMMENT_REPLY;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'thread' => $schema->integer()->description('The root Comment id of the Thread.')->required(),
            'body' => $schema->string()->description('The reply body.')->required(),
        ];
    }

    protected function perform(Request $request, User $agent): Response
    {
        $request->validate(['body' => 'required|string|max:5000']);

        $root = Comment::query()->roots()->find((int) $request->get('thread'));

        if ($root === null) {
            return Response::error('Thread not found.');
        }

        $reply = $root->artifact->comments()->create([
            'user_id' => $agent->id,
            'parent_id' => $root->id,
            'body' => (string) $request->get('body'),
        ]);

        return Response::json(['comment_id' => $reply->id, 'thread_id' => $root->id]);
    }
}
