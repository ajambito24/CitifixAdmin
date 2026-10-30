<?php

$cfRoot = dirname(__DIR__, 2);   

if (!isset($db) && is_file($cfRoot . '/service-account/service-account.json') && is_file($cfRoot . '/config/firebase.php')) {
    require_once $cfRoot . '/config/firebase.php';   // defines $db
}

function cf_parse_date($value): ?\DateTime
{
    if ($value === null) return null;

    $dt = null;
    if (is_object($value) && method_exists($value, 'get')) {
        $value = $value->get();
    }
    if ($value instanceof \DateTimeInterface) {
        $dt = (new \DateTime())->setTimestamp($value->getTimestamp());
    } elseif (is_numeric($value)) {
        $ts = (int) $value;
        if ($ts > 9999999999) $ts = (int) ($ts / 1000);
        $dt = (new \DateTime())->setTimestamp($ts);
    } elseif (is_string($value) && strtotime($value) !== false) {
        $dt = (new \DateTime())->setTimestamp(strtotime($value));
    }

    if ($dt !== null) {
        $dt->setTimezone(new \DateTimeZone('Asia/Manila'));
    }
    return $dt;
}

function cf_ago(\DateTime $dt): string
{
    $diff = max(0, time() - $dt->getTimestamp());
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff / 60) . ' min ago';
    if ($diff < 86400)  return floor($diff / 3600) . ' hr ago';
    if ($diff < 172800) return 'Yesterday';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return $dt->format('M j, Y');
}

/**
 * Latest announcements, newest first. Returns [] on any failure so the page keeps working.
 */
function cf_fetch_announcements(int $limit = 8): array
{
    global $db;

    if (!isset($db) || !is_object($db)) return [];

    $posts = [];

    try {
        $documents = $db->collection('announcements')
            ->orderBy('createdAt', 'DESC')
            ->limit($limit)
            ->documents();

        foreach ($documents as $document) {
            if (!$document->exists()) continue;

            $data = $document->data();
            $dt   = cf_parse_date($data['createdAt'] ?? null);

            $image = (string) ($data['imageUrl'] ?? '');
            
            if ($image !== '' && !preg_match('#^https?://#i', $image)) {
                $image = '../../' . ltrim($image, '/');
            }

            $posts[] = [
                'id'     => $document->id(),
                'text'   => (string) ($data['text'] ?? ''),
                'image'  => $image,
                'author' => (string) ($data['authorName'] ?? 'Administrator'),
                'ago'    => $dt ? cf_ago($dt) : '',
                'full'   => $dt ? $dt->format('F j, Y, g:i A') : '',
            ];
        }
    } catch (\Throwable $e) {
        return [];
    }

    return $posts;
}