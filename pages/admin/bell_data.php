<?php


if (!function_exists('cf_bell_fetch')) {
    function cf_bell_fetch($db, bool $isSuper, $dept): array
    {
        $items = [];

        try {
            $query = $db->collection('reports');
            if (!$isSuper) {
                $query = $query->where('departmentId', '=', $dept);
            }

            foreach ($query->documents() as $doc) {
                if (!$doc->exists()) {
                    continue;
                }

                $data   = $doc->data();
                $status = strtolower(trim($data['status'] ?? 'pending'));
                if ($status !== 'pending') {
                    continue;
                }

                $ts = null;
                $created = $data['createdAt'] ?? null;
                if (is_object($created) && method_exists($created, 'get')) {
                    $created = $created->get();
                }
                if ($created instanceof DateTimeInterface) {
                    $ts = $created->getTimestamp();
                } elseif (is_numeric($created)) {
                    $ts = (int) $created;
                    if ($ts > 9999999999) {
                        $ts = (int) ($ts / 1000);
                    }
                } elseif (is_string($created) && strtotime($created) !== false) {
                    $ts = strtotime($created);
                }

                $items[] = [
                    'id'       => $doc->id(),
                    'category' => $data['category'] ?? 'Unknown',
                    'ts'       => $ts,
                ];
            }
        } catch (Exception $e) {
            return ['ok' => false, 'total' => 0, 'items' => []];
        }

        usort($items, function ($a, $b) {
            return ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0);
        });

        return [
            'ok'    => true,
            'total' => count($items),
            'items' => array_slice($items, 0, 5),
        ];
    }
}