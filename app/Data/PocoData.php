<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Poco;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Container\Attributes\RouteParameter;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class PocoData extends Data
{
    public function __construct(
        public string|Optional|null $owner_alias,
        public int|Optional|null $reinforce_exit_start,
        public int|Optional|null $reinforce_exit_end,
        #[WithCast(DateTimeInterfaceCast::class)]
        public DateTimeImmutable|Optional|null $reinforced_until,
        public string|Optional|null $notes,
    ) {}

    public static function rules(): array
    {
        return [
            'owner_alias' => ['nullable', 'sometimes', 'string', 'max:255'],
            'reinforce_exit_start' => ['nullable', 'sometimes', 'integer', 'min:0', 'max:23'],
            'reinforce_exit_end' => ['nullable', 'sometimes', 'integer', 'min:0', 'max:23'],
            'reinforced_until' => ['nullable', 'sometimes', "date_format:Y-m-d\TH:i:sP"],
            'notes' => ['nullable', 'sometimes', 'string', 'max:1000'],
        ];
    }

    public static function authorize(#[CurrentUser] User $user, #[RouteParameter('poco')] Poco $poco): bool
    {
        return $user->can('update', $poco);
    }
}
