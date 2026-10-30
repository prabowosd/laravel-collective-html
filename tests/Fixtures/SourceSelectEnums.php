<?php

enum SourceSelectStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

enum SourceSelectRole: int
{
    case Admin = 1;
    case Member = 2;

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Member => 'Team Member',
        };
    }
}
