<?php

return [
    /*
     * Keyword-driven classification rules for shows.
     *
     * This is intentionally "soft" and easy to extend: add keywords and the
     * app will auto-create categories/content warnings (if missing) and attach
     * them to shows during metadata sync + backfill commands.
     */

    'categories' => [
        // Anime sub-genres / community terms
        'isekai' => [
            'keywords' => [
                'isekai',
                'other world',
                'reincarnat', // reincarnated/reincarnation
                'transported to',
            ],
        ],
        'mecha' => [
            'keywords' => ['mecha', 'giant robot', 'mobile suit', 'gundam'],
        ],
        'shounen' => [
            'keywords' => ['shounen', 'shonen'],
        ],
        'shoujo' => [
            'keywords' => ['shoujo', 'shojo'],
        ],
        'seinen' => [
            'keywords' => ['seinen'],
        ],
        'slice-of-life' => [
            'keywords' => ['slice of life', 'school life', 'everyday life'],
        ],
        'romance' => [
            'keywords' => ['romance', 'love story', '恋'],
        ],
        'comedy' => [
            'keywords' => ['comedy', 'funny', 'hilarious', 'parody'],
        ],
        'horror' => [
            'keywords' => ['horror', 'haunted', 'ghost', 'curse', 'cursed'],
        ],
        'thriller' => [
            'keywords' => ['thriller', 'suspense', 'psychological'],
        ],
        'sports' => [
            'keywords' => ['sports', 'tournament', 'championship'],
        ],
        'fantasy' => [
            'keywords' => ['fantasy', 'magic', 'wizard', 'sorcer', 'dragon'],
        ],
        'sci-fi' => [
            'keywords' => ['sci-fi', 'science fiction', 'space', 'alien', 'cyberpunk'],
        ],
    ],

    'content_warnings' => [
        // Always include an explicit 18+ warning.
        '18+' => [
            'severity' => 'strong',
            'keywords' => [
                '18+',
                'adult',
                'nsfw',
                'hentai',
                'ecchi',
                'erotic',
                'uncensored',
                'uncensor',
                'explicit',
                'porn',
                'pornographic',
            ],
        ],

        'sexual-content' => [
            'name' => 'Sexual Content',
            'severity' => 'strong',
            'keywords' => [
                'sex',
                'sexual',
                'intercourse',
                'rape',
                'assault',
                'nudity',
                'nude',
                'lewd',
                'fanservice',
            ],
        ],

        'nudity' => [
            'severity' => 'moderate',
            'keywords' => ['nudity', 'nude', 'topless', 'bare'],
        ],

        'gore' => [
            'severity' => 'strong',
            'keywords' => ['gore', 'graphic', 'dismember', 'blood', 'bloody', 'viscera'],
        ],

        'violence' => [
            'severity' => 'moderate',
            'keywords' => ['violence', 'violent', 'murder', 'kill', 'assassin', 'war', 'torture'],
        ],

        'self-harm' => [
            'name' => 'Self-Harm',
            'severity' => 'strong',
            'keywords' => ['self-harm', 'suicide', 'cutting', 'kill myself'],
        ],

        'substance-use' => [
            'name' => 'Substance Use',
            'severity' => 'moderate',
            'keywords' => ['drugs', 'drug', 'substance', 'cocaine', 'heroin', 'meth', 'overdose', 'addiction'],
        ],
    ],
];

