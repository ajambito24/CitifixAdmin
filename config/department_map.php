<?php
/**
 * Department routing — driven entirely by Firestore.
 *
 * categories/{catId}.departmentId  →  departments/{deptId}
 *
 * Handles case-insensitive category matching so that reports with
 * "Pothole" still map to a category document with ID "pothole".
 */

function resolve_department($db, string $categoryId): ?string
{
    static $cache = [];

    $categoryId = trim($categoryId);
    if ($categoryId === '') return null;

    if (array_key_exists($categoryId, $cache)) {
        return $cache[$categoryId];
    }

    try {
        // 1️⃣ Try exact document ID first
        $doc = $db->collection('categories')->document($categoryId)->snapshot();

        // 2️⃣ If not found, search case-insensitively
        if (!$doc->exists()) {
            foreach ($db->collection('categories')->documents() as $cat) {
                if (!$cat->exists()) continue;
                if (strcasecmp($cat->id(), $categoryId) === 0) {
                    $doc = $cat;
                    break;
                }
            }
        }

        if ($doc && $doc->exists()) {
            $dept = trim($doc->data()['departmentId'] ?? '');
            $cache[$categoryId] = $dept !== '' ? $dept : null;
            return $cache[$categoryId];
        }
    } catch (Exception $e) {
        // fall through
    }

    $cache[$categoryId] = null;
    return null;
}

/**
 * Fetch a department's display name from Firestore.
 */
function department_name($db, ?string $deptId): string
{
    static $cache = [];

    if (!$deptId) return 'N/A';
    if (isset($cache[$deptId])) return $cache[$deptId];

    try {
        $doc = $db->collection('departments')->document($deptId)->snapshot();
        $cache[$deptId] = $doc->exists()
            ? ($doc->data()['name'] ?? $deptId)
            : $deptId;
    } catch (Exception $e) {
        $cache[$deptId] = $deptId;
    }
    return $cache[$deptId];
}

/**
 * All departments as [id => name] — for dropdowns.
 */
function all_departments($db): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    $cache = [];
    try {
        foreach ($db->collection('departments')->documents() as $doc) {
            if (!$doc->exists()) continue;
            $cache[$doc->id()] = $doc->data()['name'] ?? $doc->id();
        }
        asort($cache);
    } catch (Exception $e) {}

    return $cache;
}