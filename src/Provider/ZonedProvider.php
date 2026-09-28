<?php

namespace Ddns\Provider;

use Ddns\ApiException;
use Ddns\DdnsException;

/**
 * A provider whose record lookups need the zone (root domain) the record lives in.
 * The zone is looked up once and cached per account + domain.
 */
abstract class ZonedProvider extends Provider
{
    /**
     * @return array<string, int|string> zone name => zone id
     */
    abstract protected function listZones(): array;

    abstract protected function findRecordInZone(Zone $zone, string $domain, string $type): ?Record;

    protected function findRecord(string $domain, string $type): Record
    {
        $cacheKey = implode("\0", [static::class, $this->accessId, $domain]);
        $cached = $this->cache->get($cacheKey);

        if (isset($cached['name'], $cached['id'])) {
            try {
                $record = $this->findRecordInZone(new Zone($cached['name'], $cached['id']), $domain, $type);

                if ($record !== null) {
                    return $record;
                }
            } catch (ApiException) {
                // The zone may have been removed or moved; look it up again below.
            }

            $this->cache->delete($cacheKey);
        }

        $zone = Zone::match($domain, $this->listZones());

        if ($zone === null) {
            throw new DdnsException('Domain not found');
        }

        $this->cache->set($cacheKey, ['name' => $zone->name, 'id' => $zone->id]);

        return $this->findRecordInZone($zone, $domain, $type)
            ?? throw new DdnsException('Record not found');
    }
}
