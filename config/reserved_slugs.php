<?php

declare(strict_types=1);

// Slugs that cannot be registered as tenant subdomains (ADR-016).
// Validated by App\Rules\ReservedSlug.
return [

    // Operational
    'admin', 'app', 'api', 'www', 'mail', 'ftp', 'smtp',
    'pop3', 'imap', 'dns', 'ns', 'mx', 'blog', 'help',
    'support', 'docs',

    // Marketing
    'landing', 'home', 'site',

    // Technical
    'dev', 'staging', 'test', 'preview', 'qa', 'beta',
    'cdn', 'assets', 'static', 'media', 'files',

    // Brand
    'acho', 'noreply', 'contato', 'contact', 'oficial',

    // Generic
    'about', 'privacy', 'terms', 'login', 'signup', 'register',

];
