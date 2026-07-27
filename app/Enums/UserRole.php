<?php

namespace App\Enums;

/**
 * The role a User plays. Layered on top of the pure-authentication User concept, so a
 * single identity can be a human Creator, a passwordless commenting Client (ADR-0003),
 * or a non-human Agent (ADR-0006). Authorization keys off this discriminator rather
 * than inferring a role from "has a password", which breaks once a Client upgrades.
 */
enum UserRole: string
{
    case Creator = 'creator';
    case Client = 'client';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::Creator => 'Creator',
            self::Client => 'Client',
            self::Agent => 'Agent',
        };
    }

    /**
     * The badge colour this role wears in the admin area, so every surface reads
     * the operator (red), the audience (zinc) and the non-human (purple) alike.
     */
    public function color(): string
    {
        return match ($this) {
            self::Creator => 'red',
            self::Client => 'zinc',
            self::Agent => 'purple',
        };
    }

    /**
     * Whether this role is played by a human. Distinguishes human edits from agent
     * edits when Revision provenance is surfaced (#15/#17).
     */
    public function isHuman(): bool
    {
        return $this !== self::Agent;
    }
}
