<?php

namespace App\Mcp;

/**
 * The capability boundary of the agent MCP surface, encoded as Sanctum token
 * abilities (ADR-0006): the agent shapes content, humans control exposure. There
 * is deliberately no ability for resolving Threads or for any project-lifecycle
 * action — those stay Creator-only and are not exposed as tools at all.
 */
final class AgentAbilities
{
    public const ARTIFACT_READ = 'artifact:read';

    public const ARTIFACT_WRITE = 'artifact:write';

    public const COMMENT_READ = 'comment:read';

    public const COMMENT_REPLY = 'comment:reply';

    /**
     * The full set granted to a provisioned Agent token.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::ARTIFACT_READ,
            self::ARTIFACT_WRITE,
            self::COMMENT_READ,
            self::COMMENT_REPLY,
        ];
    }
}
