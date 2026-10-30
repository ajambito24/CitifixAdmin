<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/firebase.php';
require_once __DIR__ . '/../../config/fcm.php';

$canPost = !empty($IS_SUPER_ADMIN);

const ANN_MAX_TEXT  = 2000;
const ANN_MAX_IMAGE = 5 * 1024 * 1024;           // 5 MB

// Cloudinary (same cloud + unsigned preset the Flutter app uses for report photos)
const ANN_CLOUD_NAME   = 'e7d4ahld';
const ANN_CLOUD_PRESET = 'Citifix';
const ANN_CLOUD_FOLDER = 'announcements';

// ---- CSRF token ----
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf'];


function ann_parse_date($value): ?\DateTime
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

function ann_ago(\DateTime $dt): string
{
    $diff = max(0, time() - $dt->getTimestamp());
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff / 60) . ' min ago';
    if ($diff < 86400)  return floor($diff / 3600) . ' hr ago';
    if ($diff < 172800) return 'Yesterday';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return $dt->format('M j, Y');
}

function ann_flash(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
    header('Location: announcement.php');
    exit;
}

/**
 * Uploads an image to Cloudinary (unsigned preset) and returns its secure https URL.
 * Uses cURL when available, otherwise a plain HTTPS stream. Throws Exception on failure.
 */
function ann_cloudinary_upload(string $tmpPath, string $mime, string $ext): string
{
    $url    = 'https://api.cloudinary.com/v1_1/' . ANN_CLOUD_NAME . '/image/upload';
    $fields = ['upload_preset' => ANN_CLOUD_PRESET, 'folder' => ANN_CLOUD_FOLDER];
    $name   = 'announcement.' . $ext;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $fields + ['file' => new CURLFile($tmpPath, $mime, $name)],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 60,
        ]);
        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception($curlErr !== '' ? $curlErr : 'Could not reach Cloudinary.');
        }
    } else {
        $boundary = '----cf' . bin2hex(random_bytes(8));
        $body = '';
        foreach ($fields as $key => $value) {
            $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$key}\"\r\n\r\n{$value}\r\n";
        }
        $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"{$name}\"\r\n"
               . "Content-Type: {$mime}\r\n\r\n" . file_get_contents($tmpPath) . "\r\n--{$boundary}--\r\n";

        $context = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: multipart/form-data; boundary={$boundary}\r\n",
            'content'       => $body,
            'timeout'       => 60,
            'ignore_errors' => true,
        ]]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new Exception('Could not reach Cloudinary.');
        }
    }

    $json = json_decode($response, true);
    if (!is_array($json) || empty($json['secure_url'])) {
        throw new Exception($json['error']['message'] ?? 'Unexpected response from Cloudinary.');
    }

    return $json['secure_url'];
}

// ---- Handle POST (create / delete): super admin only ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$canPost) {
        http_response_code(403);
        die('Only super administrators can manage announcements.');
    }

    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        ann_flash('error', 'Your session expired. Please try again.');
    }

    $action = $_POST['action'] ?? '';

    // ---------- CREATE ----------
    if ($action === 'create') {

        $text = trim($_POST['text'] ?? '');
        $imagePath = '';

        if (mb_strlen($text) > ANN_MAX_TEXT) {
            ann_flash('error', 'Announcement text is too long (max ' . ANN_MAX_TEXT . ' characters).');
        }

        $file = $_FILES['image'] ?? null;
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {

            if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
                ann_flash('error', 'Image is too large (max 5 MB).');
            }
            if ($file['error'] !== UPLOAD_ERR_OK) {
                ann_flash('error', 'Image upload failed. Please try again.');
            }
            if ($file['size'] > ANN_MAX_IMAGE) {
                ann_flash('error', 'Image is too large (max 5 MB).');
            }

            // getimagesize() reads the real image header, so it needs no extra PHP extension.
            $info   = @getimagesize($file['tmp_name']);
            $mime   = is_array($info) ? ($info['mime'] ?? '') : '';
            $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

            if (!isset($extMap[$mime])) {
                ann_flash('error', 'Only JPG, PNG or WebP images are allowed.');
            }

            try {
                $imagePath = ann_cloudinary_upload($file['tmp_name'], $mime, $extMap[$mime]);
            } catch (Exception $e) {
                ann_flash('error', 'Photo upload failed: ' . $e->getMessage());
            }
        }

        if ($text === '' && $imagePath === '') {
            ann_flash('error', 'Write something or attach a photo before posting.');
        }

        try {
            $newDoc = $db->collection('announcements')->add([
                'text'       => $text,
                'imageUrl'   => $imagePath,
                'authorId'   => $ADMIN_ID,
                'authorName' => $ADMIN_NAME,
                'authorRole' => $ADMIN_ROLE,
                'createdAt'  => new \Google\Cloud\Core\Timestamp(new \DateTime()),
            ]);
        } catch (Exception $e) {
            ann_flash('error', 'Post failed: ' . $e->getMessage());
        }

        // Push to every resident subscribed to the "announcements" topic.
        // Best effort: the announcement is already saved, so a push failure
        // must not undo or hide it.
        $pushSent = true;
        try {
            fcm_send_to_topic(
                'New announcement',
                $text !== '' ? fcm_excerpt($text) : 'Photo',
                ['type' => 'announcement', 'id' => $newDoc->id()]
            );
        } catch (Throwable $e) {
            $pushSent = false;
            error_log('FCM announcement push failed: ' . $e->getMessage());
        }

        ann_flash(
            'success',
            $pushSent
                ? 'Announcement posted.'
                : 'Announcement posted, but the push notification could not be sent.'
        );
    }

    // ---------- DELETE ----------
    if ($action === 'delete') {

        $postId = trim($_POST['id'] ?? '');
        if ($postId === '') {
            ann_flash('error', 'Missing announcement.');
        }

        try {
            $ref  = $db->collection('announcements')->document($postId);
            $snap = $ref->snapshot();

            // The unsigned Cloudinary preset can't delete, so the photo stays in the Cloudinary library.
            if ($snap->exists()) {
                $ref->delete();
            }
        } catch (Exception $e) {
            ann_flash('error', 'Delete failed: ' . $e->getMessage());
        }

        ann_flash('success', 'Announcement deleted.');
    }

    ann_flash('error', 'Unknown action.');
}

// ---- Load feed ----
$posts = [];
$error = '';
$hasMore = false;

$limit = (int) ($_GET['limit'] ?? 20);
$limit = max(20, min($limit, 200));

try {
    $documents = $db->collection('announcements')
        ->orderBy('createdAt', 'DESC')
        ->limit($limit + 1)
        ->documents();

    foreach ($documents as $document) {
        if (!$document->exists()) continue;

        $data = $document->data();
        $dt   = ann_parse_date($data['createdAt'] ?? null);

        $posts[] = [
            'id'         => $document->id(),
            'text'       => $data['text'] ?? '',
            'imageUrl'   => $data['imageUrl'] ?? '',
            'authorName' => $data['authorName'] ?? 'Administrator',
            'authorRole' => $data['authorRole'] ?? 'super_admin',
            'ago'        => $dt ? ann_ago($dt) : 'Unknown time',
            'full'       => $dt ? $dt->format('M d, Y · g:i A') : '',
            'iso'        => $dt ? $dt->format('c') : '',
        ];
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

if (count($posts) > $limit) {
    $hasMore = true;
    $posts = array_slice($posts, 0, $limit);
}

// ---- Session flash (one-time) ----
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ---- Design-only helpers (no data logic) ----
$activePage = 'announcement';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/../utils/screen_util.php'; ?>
<title>CitiFix Announcements</title>
</head>

<body class="bg-slate-50 text-slate-700 antialiased">

<?php require __DIR__ . '/sidebar.php'; ?>

<div class="cf-content">

    <!-- TOP BAR -->
    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur" style="padding-top: env(safe-area-inset-top, 0px);">
        <div class="flex h-16 items-center justify-between gap-3 px-[var(--su-gutter)]">

            <div class="flex min-w-0 items-center gap-3">
                <button type="button" data-cf-open aria-label="Open menu"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-slate-700 hover:bg-slate-100 lg:hidden">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
                <div class="min-w-0">
                    <h1 class="truncate text-lg font-extrabold tracking-tight text-slate-900 sm:text-xl">Announcements</h1>
                    <p class="truncate text-xs text-slate-500 sm:text-sm">Updates for the CitiFix community</p>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-3">
                <?php require __DIR__ . '/bell.php'; ?>
                <div class="hidden text-right leading-tight sm:block">
                    <strong class="block text-sm text-slate-800"><?= htmlspecialchars($ADMIN_NAME) ?></strong>
                    <small class="hidden text-xs text-slate-500 md:block">
                        <?= htmlspecialchars($ADMIN_EMAIL) ?>
                        &middot; <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $ADMIN_ROLE))) ?>
                    </small>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-700 font-extrabold text-white">
                    <?= htmlspecialchars(strtoupper(substr($ADMIN_NAME, 0, 1))) ?>
                </div>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-2xl px-[var(--su-gutter)] py-6 sm:py-8">

        <?php if ($flash !== null): ?>
            <?php
                $flashType = $flash['type'] ?? 'success';
                $flashMsg  = $flash['msg']  ?? '';
                $flashClasses = $flashType === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-700';
                $flashIcon = $flashType === 'success' ? '✅' : '⚠️';
            ?>
            <div class="mb-4 flex items-center gap-2 rounded-xl border px-4 py-3 text-sm <?= $flashClasses ?>" role="status">
                <span><?= $flashIcon ?></span>
                <span><?= htmlspecialchars($flashMsg) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($canPost): ?>
        <!-- COMPOSER (super admin only) -->
        <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <form method="POST" action="announcement.php" enctype="multipart/form-data" id="ann-form">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="create">

                <div class="flex gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-700 font-extrabold text-white">
                        <?= htmlspecialchars(strtoupper(substr($ADMIN_NAME, 0, 1))) ?>
                    </div>
                    <div class="min-w-0 flex-1">
                        <textarea name="text" id="ann-text" rows="3" maxlength="<?= ANN_MAX_TEXT ?>"
                                  placeholder="What do you want to announce?"
                                  class="w-full resize-y rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-100"></textarea>

                        <div id="ann-preview" class="relative mt-3 hidden">
                            <img id="ann-preview-img" src="" alt="Selected photo preview" class="max-h-72 w-full rounded-xl border border-slate-200 object-cover">
                            <button type="button" id="ann-remove" aria-label="Remove photo"
                                    class="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-full bg-slate-900/70 text-lg leading-none text-white hover:bg-slate-900">×</button>
                        </div>

                        <input type="file" name="image" id="ann-image" accept="image/jpeg,image/png,image/webp" class="hidden">

                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <label for="ann-image" class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-200">
                                    <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 16l4-4a3 3 0 014 0l4 4m-2-2l1-1a3 3 0 014 0l2 2M4 6a2 2 0 012-2h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 3h.01"/></svg>
                                    Photo
                                </label>
                                <span id="ann-count" class="text-xs text-slate-400">0 / <?= ANN_MAX_TEXT ?></span>
                            </div>
                            <button type="submit" id="ann-post" disabled
                                    class="rounded-lg bg-brand-700 px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-brand-800 disabled:cursor-not-allowed disabled:bg-slate-300">Post</button>
                        </div>
                        <p class="mt-2 text-xs text-slate-400">JPG, PNG or WebP, up to 5 MB.</p>
                    </div>
                </div>
            </form>
        </section>
        <?php endif; ?>

        <!-- FEED -->
        <?php if (empty($posts) && $error === ''): ?>
            <div class="rounded-2xl border border-slate-200 bg-white py-14 text-center shadow-sm">
                <div class="text-4xl">📣</div>
                <p class="mt-2 text-sm text-slate-500">No announcements yet.</p>
                <?php if ($canPost): ?>
                    <p class="text-xs text-slate-400">Write your first post above.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="space-y-5">
                <?php foreach ($posts as $post):
                    $src = '';
                    if ($post['imageUrl'] !== '') {
                        $src = preg_match('#^https?://#i', $post['imageUrl'])
                            ? $post['imageUrl']
                            : '../../' . ltrim($post['imageUrl'], '/');
                    }
                ?>
                    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-start justify-between gap-3 p-4 sm:p-5 sm:pb-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-700 font-extrabold text-white">
                                    <?= htmlspecialchars(strtoupper(substr($post['authorName'], 0, 1))) ?>
                                </div>
                                <div class="min-w-0 leading-tight">
                                    <strong class="block truncate text-sm text-slate-900"><?= htmlspecialchars($post['authorName']) ?></strong>
                                    <time datetime="<?= htmlspecialchars($post['iso']) ?>" title="<?= htmlspecialchars($post['full']) ?>" class="block text-xs text-slate-500">
                                        <?= htmlspecialchars($post['ago']) ?>
                                        <?php if ($post['full'] !== ''): ?>
                                            <span class="text-slate-400">&middot; <?= htmlspecialchars($post['full']) ?></span>
                                        <?php endif; ?>
                                    </time>
                                </div>
                            </div>

                            <?php if ($canPost): ?>
                                <form method="POST" action="announcement.php" onsubmit="return confirm('Delete this announcement? This cannot be undone.');">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($post['id']) ?>">
                                    <button type="submit" class="shrink-0 rounded-lg bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-100">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <?php if ($post['text'] !== ''): ?>
                            <p class="whitespace-pre-wrap break-words px-4 pb-4 text-sm leading-relaxed text-slate-800 sm:px-5 sm:text-base"><?= htmlspecialchars($post['text']) ?></p>
                        <?php endif; ?>

                        <?php if ($src !== ''): ?>
                            <a href="<?= htmlspecialchars($src) ?>" target="_blank" rel="noopener" class="block border-t border-slate-100 bg-slate-100">
                                <img src="<?= htmlspecialchars($src) ?>" alt="Announcement photo" loading="lazy"
                                     class="max-h-[32rem] w-full object-cover"
                                     onerror="this.closest('a').style.display='none'">
                            </a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($hasMore): ?>
                <div class="mt-6 text-center">
                    <a href="?limit=<?= $limit + 20 ?>" class="inline-block rounded-lg bg-brand-50 px-5 py-2.5 text-sm font-semibold text-brand-700 transition-colors hover:bg-brand-100">Show older</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </main>
</div>

<?php if ($canPost): ?>
<script>
(function () {
    var MAX_BYTES = <?= ANN_MAX_IMAGE ?>;
    var text    = document.getElementById('ann-text');
    var count   = document.getElementById('ann-count');
    var input   = document.getElementById('ann-image');
    var preview = document.getElementById('ann-preview');
    var img     = document.getElementById('ann-preview-img');
    var remove  = document.getElementById('ann-remove');
    var post    = document.getElementById('ann-post');
    var form    = document.getElementById('ann-form');

    function refresh() {
        count.textContent = text.value.length + ' / <?= ANN_MAX_TEXT ?>';
        post.disabled = text.value.trim() === '' && input.files.length === 0;
    }

    function clearImage() {
        input.value = '';
        img.removeAttribute('src');
        preview.classList.add('hidden');
        refresh();
    }

    text.addEventListener('input', refresh);

    input.addEventListener('change', function () {
        var file = input.files[0];
        if (!file) { clearImage(); return; }

        if (file.size > MAX_BYTES) {
            alert('Image is too large (max 5 MB).');
            clearImage();
            return;
        }

        var reader = new FileReader();
        reader.onload = function (e) {
            img.src = e.target.result;
            preview.classList.remove('hidden');
            refresh();
        };
        reader.readAsDataURL(file);
    });

    remove.addEventListener('click', clearImage);

    form.addEventListener('submit', function () {
        post.disabled = true;
        post.textContent = 'Posting…';
    });

    refresh();
})();
</script>
<?php endif; ?>

</body>
</html>