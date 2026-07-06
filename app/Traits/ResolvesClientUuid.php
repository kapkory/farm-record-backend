<?php

namespace App\Traits;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Lets create endpoints accept an optional client-generated uuid so the
 * offline-first frontend can retry a queued create without producing
 * duplicate records: replaying a create with a uuid that already exists
 * returns the stored record instead of inserting a second one.
 */
trait ResolvesClientUuid
{
    /**
     * Returns [$uuid, $existing, $foreign]:
     * - $uuid     the uuid to persist (the client's, or a fresh ordered uuid)
     * - $existing the already-stored record for a replayed create, null otherwise
     * - $foreign  true when the uuid belongs to a record the user cannot access
     *
     * @param  class-string<Model>  $modelClass
     * @param  Closure(Model): bool  $ownedBy
     */
    protected function resolveClientUuid(Request $request, string $modelClass, Closure $ownedBy): array
    {
        $clientUuid = $request->input('uuid');

        if (! $clientUuid) {
            return [(string) Str::orderedUuid(), null, false];
        }

        $existing = $modelClass::query()->where('uuid', $clientUuid)->first();

        if ($existing && ! $ownedBy($existing)) {
            return [$clientUuid, null, true];
        }

        return [$clientUuid, $existing, false];
    }

    /**
     * Two concurrent replays of the same create can both pass the lookup and
     * race on the insert; the loser hits the unique index. Fetch the row the
     * winner inserted so the request can still respond with it.
     *
     * @param  class-string<Model>  $modelClass
     */
    protected function findAfterUniqueViolation(\Throwable $e, string $modelClass, ?string $uuid): ?Model
    {
        if ($uuid && $this->causedByUniqueViolation($e)) {
            return $modelClass::query()->where('uuid', $uuid)->first();
        }

        return null;
    }

    protected function clientUuidTakenResponse(): JsonResponse
    {
        return $this->errorResponse('Validation failed', 422, [
            'uuid' => ['This identifier is already in use.'],
        ]);
    }

    private function causedByUniqueViolation(\Throwable $e): bool
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof UniqueConstraintViolationException) {
                return true;
            }
        }

        return false;
    }
}
