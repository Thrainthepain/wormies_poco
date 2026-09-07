<?php

declare(strict_types=1);

namespace App\Actions\Pocos;

use App\Models\Poco;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class DeletePocoAction
{
    /**
     * @throws Throwable
     */
    public function handle(Poco $poco): bool
    {
        return DB::transaction(fn (): bool => (bool) $poco->delete());
    }
}
