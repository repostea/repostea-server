<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class VoteStatsResource extends JsonResource
{
    /**
     * @param  Request  $request
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'votes_count' => $this->resource->votes_count,
            'vote_details' => $this->resource->votes->map(static fn ($vote) => [
                'value' => $vote->value,
                'type' => $vote->type,
                'created_at' => $vote->created_at,
            ]),
            'vote_types' => $this->getVoteTypeStats(),
        ];
    }

    private function getVoteTypeStats()
    {
        $voteTypes = [];

        $positiveVotes = $this->resource->votes->where('value', 1);
        foreach ($positiveVotes->groupBy('type') as $type => $votes) {
            $actualType = $type !== null && $type !== '' ? $type : 'unspecified';
            $voteTypes[$actualType] = $votes->count();
        }

        $negativeVotes = $this->resource->votes->where('value', -1);
        foreach ($negativeVotes->groupBy('type') as $type => $votes) {
            $actualType = $type !== null && $type !== '' ? $type : 'unspecified';
            $voteTypes[$actualType] = -$votes->count();
        }

        return $voteTypes;
    }
}
