<?php

namespace App\Mcp\Tools;

use App\Mcp\AgentAbilities;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description("Read an artifact's current content — markdown body, file metadata, or an HTML artifact's sandbox reference.")]
class ReadArtifact extends AgentTool
{
    protected function requiredAbility(): string
    {
        return AgentAbilities::ARTIFACT_READ;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'artifact' => $schema->integer()->description('The artifact id.')->required(),
        ];
    }

    protected function perform(Request $request, User $agent): Response
    {
        $artifact = $this->artifact($request);

        if ($artifact === null) {
            return Response::error('Artifact not found.');
        }

        $content = [
            'id' => $artifact->id,
            'type' => $artifact->type->value,
            'title' => $artifact->title,
        ];

        $content += match (true) {
            $artifact->isMarkdown() => ['body' => (string) $artifact->body],
            $artifact->isHtml() => ['sandbox_url' => $artifact->sandboxUrl(), 'entry_file' => $artifact->entry_file],
            default => [
                'original_filename' => $artifact->original_filename,
                'mime_type' => $artifact->mime_type,
                'size_bytes' => $artifact->size_bytes,
            ],
        };

        return Response::json($content);
    }
}
