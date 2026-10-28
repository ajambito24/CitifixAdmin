<?php
/**
 * screen_util.php
 * Responsiveness helper for the CitiFix landing pages.
 *
 * Include ONCE as the first thing inside <head> of every landing page.
 * It outputs: meta tags, font, Tailwind (CDN) + config, and fluid sizing helpers.
 *
 * Fluid helpers (scale smoothly from 320px phones to 4K screens):
 *   .su-container    page width + side gutters (safe-area aware)
 *   .su-section      vertical section spacing
 *   .su-hero-title   hero headline size
 *   .su-h2           section heading size
 *   .su-lead         intro paragraph size
 *
 * Extra Tailwind breakpoints: xs (375px) and 3xl (1920px).
 */
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1d4ed8">
<link rel="icon" href="../../images/logo.png">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        theme: {
            screens: {
                xs: '375px',
                sm: '640px',
                md: '768px',
                lg: '1024px',
                xl: '1280px',
                '2xl': '1536px',
                '3xl': '1920px'
            },
            extend: {
                colors: {
                    // Same blues as the admin dashboard (blue-500/600 buttons, #1d4ed8 chart)
                    brand: {
                        50:  '#eff6ff',
                        100: '#dbeafe',
                        200: '#bfdbfe',
                        500: '#3b82f6',
                        600: '#2563eb',
                        700: '#1d4ed8',
                        800: '#1e40af',
                        900: '#1e3a8a'
                    }
                },
                fontFamily: {
                    sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif']
                }
            }
        }
    };
</script>

<style>
    :root {
        --su-hero:    clamp(2.25rem, 1.25rem + 4.4vw, 4.75rem);
        --su-h2:      clamp(1.625rem, 1.1rem + 2.2vw, 2.875rem);
        --su-lead:    clamp(1rem, 0.9rem + 0.45vw, 1.3125rem);
        --su-section: clamp(3.5rem, 2rem + 6vw, 8rem);
        --su-gutter:  clamp(1rem, 0.5rem + 2.5vw, 2.5rem);
        --su-max:     80rem;
    }

    @media (min-width: 1920px) {
        :root { --su-max: 96rem; }
    }

    html {
        scroll-behavior: smooth;
        -webkit-text-size-adjust: 100%;
    }

    body {
        min-height: 100vh;
        min-height: 100dvh;
        font-family: "Plus Jakarta Sans", ui-sans-serif, system-ui, sans-serif;
        overflow-x: hidden;
    }

    .su-container {
        width: 100%;
        max-width: var(--su-max);
        margin-inline: auto;
        padding-left: max(var(--su-gutter), env(safe-area-inset-left, 0px));
        padding-right: max(var(--su-gutter), env(safe-area-inset-right, 0px));
    }

    .su-section {
        padding-block: var(--su-section);
    }

    .su-hero-title {
        font-size: var(--su-hero);
        line-height: 1.04;
        letter-spacing: -0.035em;
        font-weight: 800;
    }

    .su-h2 {
        font-size: var(--su-h2);
        line-height: 1.12;
        letter-spacing: -0.025em;
        font-weight: 800;
    }

    .su-lead {
        font-size: var(--su-lead);
        line-height: 1.6;
    }

    :focus-visible {
        outline: 3px solid #3b82f6;
        outline-offset: 2px;
        border-radius: 0.375rem;
    }

    @media (prefers-reduced-motion: reduce) {
        html { scroll-behavior: auto; }
        *, *::before, *::after {
            animation-duration: 0.01ms !important;
            transition-duration: 0.01ms !important;
        }
    }
</style>