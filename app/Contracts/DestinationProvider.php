<?php

namespace App\Contracts;

interface DestinationProvider
{
    /** @return list<array{place_id: string, name: string, address: string, latitude: float, longitude: float}> */
    public function search(string $query, string $language): array;

    /** @return array{place_id: string, name: string, address: string, latitude: float, longitude: float} */
    public function place(string $placeId, string $language): array;

    public function attribution(): string;
}
