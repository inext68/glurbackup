<?php

namespace PluginGlurbackup;

final class LocationResolver
{
    /**
     * Restituisce l'ID della root location partendo da una location qualsiasi.
     * Se la location è già root, restituisce la stessa.
     * Se non trova dati validi, restituisce l'ID originale (o 0).
     */
    public static function getRootLocationId(int $locationId): int
    {
        global $DB;

        if ($locationId <= 0) {
            return 0;
        }

        if (!$DB->tableExists('glpi_locations')) {
            return $locationId;
        }

        $currentId = $locationId;
        $visited   = [];

        while ($currentId > 0 && !isset($visited[$currentId])) {
            $visited[$currentId] = true;

            $row = $DB->request([
                'FROM'  => 'glpi_locations',
                'WHERE' => ['id' => $currentId],
                'LIMIT' => 1,
            ])->current();

            if (!$row) {
                return $locationId;
            }

            $parentId = (int) ($row['locations_id'] ?? 0);
            if ($parentId <= 0) {
                return $currentId;
            }

            $currentId = $parentId;
        }

        return $locationId;
    }
}
