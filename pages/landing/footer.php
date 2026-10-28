<?php

?>
<footer class="bg-slate-900 text-slate-300" style="padding-bottom: env(safe-area-inset-bottom, 0px);">
    <div class="su-container grid gap-10 py-12 md:grid-cols-[1.5fr_1fr_1fr] md:py-16">

        <div class="max-w-sm">
            <a href="homepages.php" class="flex items-center gap-2.5" aria-label="CitiFix Manila home">
                <img src="../../images/logo.png" alt="" class="h-10 w-10 object-contain">
                <span class="leading-none">
                    <span class="block text-lg font-extrabold tracking-tight text-white">CitiFix</span>
                    <span class="block text-xs font-medium text-slate-400">Manila</span>
                </span>
            </a>
            <p class="mt-4 text-sm leading-relaxed text-slate-400">
                Report community problems in District 4 (Sampaloc), Manila, and follow them until they are resolved.
            </p>
        </div>

        <div>
            <h2 class="text-sm font-semibold text-white">Explore</h2>
            <ul class="mt-4 space-y-2.5 text-sm">
                <li><a href="homepages.php" class="text-slate-400 transition-colors hover:text-white">Home</a></li>
                <li><a href="about.php" class="text-slate-400 transition-colors hover:text-white">About CitiFix</a></li>
                <li><a href="download.php" class="text-slate-400 transition-colors hover:text-white">Download and how to use</a></li>
            </ul>
        </div>

        <div>
            <h2 class="text-sm font-semibold text-white">Coverage</h2>
            <p class="mt-4 text-sm leading-relaxed text-slate-400">
                District 4 (Sampaloc), Manila, Philippines.
            </p>
            <p class="mt-3 text-sm leading-relaxed text-slate-400">
                For non-emergency concerns only. In an emergency, contact emergency services directly.
            </p>
        </div>
    </div>

    <div class="border-t border-slate-800">
        <div class="su-container py-5 text-xs text-slate-500">
            &copy; <?= date('Y') ?> CitiFix Manila. All rights reserved.
        </div>
    </div>
</footer>