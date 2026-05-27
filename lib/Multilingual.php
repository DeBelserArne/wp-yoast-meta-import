<?php
/**
 * Multilingual abstraction layer for WPML and Polylang.
 * Auto-detects which plugin is active and provides unified functions.
 * When neither is active, all functions return identity/empty → zero behavior change.
 */

if ( !defined('ABSPATH')) {
    exit;
}

/**
 * Check if any supported multilingual plugin is active.
 */
function ymi_ml_is_active() {
    return ymi_ml_is_wpml() || ymi_ml_is_polylang();
}

function ymi_ml_is_wpml() {
    return function_exists('icl_object_id');
}

function ymi_ml_is_polylang() {
    return function_exists('pll_get_post');
}

/**
 * Get list of active languages.
 * Returns [ 'code' => 'Native Name', ... ] e.g. [ 'nl' => 'Nederlands', 'en' => 'English' ]
 */
function ymi_ml_get_languages() {
    if (ymi_ml_is_wpml()) {
        $langs=apply_filters('wpml_active_languages', null, ['skip_missing'=> 0]);

        if ( !empty($langs) && is_array($langs)) {
            $result=[];

            foreach ($langs as $l) {
                $code=$l['code'] ?? $l['language_code'] ?? '';
                $name=$l['native_name'] ?? $l['display_name'] ?? $code;

                if ($code) {
                    $result[$code]=$name;
                }
            }

            if ( !empty($result)) {
                return $result;
            }
        }
    }

    if (ymi_ml_is_polylang()) {
        $langs=pll_languages_list(['fields'=> []]);
        $result=[];

        foreach ($langs as $l) {
            $code=$l['slug'] ?? '';
            $name=$l['name'] ?? $code;

            if ($code) {
                $result[$code]=$name;
            }
        }

        return $result;
    }

    return [];
}

/**
 * Get the default language code.
 */
function ymi_ml_default_lang() {
    if (ymi_ml_is_wpml()) {
        return apply_filters('wpml_default_language', null);
    }

    if (ymi_ml_is_polylang() && function_exists('pll_default_language')) {
        return pll_default_language();
    }

    return '';
}

/**
 * Detect language from a URL by checking the first path segment against active languages.
 * Returns language code or '' if no match.
 */
function ymi_ml_detect_lang_from_url($url) {
    $parsed=parse_url($url);
    $path=isset($parsed['path']) ? trim($parsed['path'], '/'): '';

    if (empty($path)) {
        return '';
    }

    $segments=explode('/', $path);
    $first=$segments[0];

    $langs=ymi_ml_get_languages();

    if (isset($langs[$first])) {
        return $first;
    }

    return '';
}

/**
 * Strip the language prefix from a URL path.
 * e.g. /nl/about/ → /about/
 */
function ymi_ml_strip_lang_prefix($url, $lang) {
    if (empty($lang)) {
        return $url;
    }

    $parsed=parse_url($url);
    $path=isset($parsed['path']) ? trim($parsed['path'], '/') : '';

    if (strpos($path, $lang . '/')===0) {
        $new_path='/'. substr($path, strlen($lang) + 1);
        $new_path=rtrim($new_path, '/');
    }

    elseif ($path===$lang) {
        $new_path='/';
    }

    else {
        return $url; // No prefix to strip
    }

    // Rebuild URL
    $scheme=$parsed['scheme'] ?? 'https';
    $host=$parsed['host'] ?? '';
    $query=isset($parsed['query']) ? '?'. $parsed['query'] : '';
    return $scheme . '://'. $host . $new_path . $query;
}

/**
 * Get the translated post ID for a given language.
 * Returns the original $post_id if no translation exists or no plugin active.
 */
function ymi_ml_get_translated_post($post_id, $lang) {
    if (empty($lang) || empty($post_id)) {
        return $post_id;
    }

    if (ymi_ml_is_wpml()) {
        $translated=apply_filters('wpml_object_id', $post_id, 'post', false, $lang);
        return $translated ? (int) $translated: $post_id;
    }

    if (ymi_ml_is_polylang()) {
        $translated=pll_get_post($post_id, $lang);
        return $translated ? (int) $translated: $post_id;
    }

    return $post_id;
}

/**
 * Get the translated term ID for a given language.
 * Returns the original $term_id if no translation exists or no plugin active.
 */
function ymi_ml_get_translated_term($term_id, $taxonomy, $lang) {
    if (empty($lang) || empty($term_id) || empty($taxonomy)) {
        return $term_id;
    }

    if (ymi_ml_is_wpml()) {
        $translated=apply_filters('wpml_object_id', $term_id, $taxonomy, false, $lang);
        return $translated ? (int) $translated: $term_id;
    }

    if (ymi_ml_is_polylang()) {
        $translated=pll_get_term($term_id, $lang);
        return $translated ? (int) $translated: $term_id;
    }

    return $term_id;
}

/**
 * Get the home page ID for a specific language.
 */
function ymi_ml_get_home_page_id($lang) {
    if (empty($lang)) {
        return get_option('page_on_front');
    }

    if (ymi_ml_is_wpml()) {
        $page_on_front=get_option('page_on_front');

        if ($page_on_front) {
            $translated=apply_filters('wpml_object_id', $page_on_front, 'page', false, $lang);
            return $translated ? (int) $translated: $page_on_front;
        }
    }

    if (ymi_ml_is_polylang()) {
        $page_on_front=get_option('page_on_front');

        if ($page_on_front) {
            $translated=pll_get_post($page_on_front, $lang);
            return $translated ? (int) $translated: $page_on_front;
        }
    }

    return get_option('page_on_front');
}

/**
 * Get current Yoast SEO title/description for a post, considering language.
 * The core Yoast storage for non-post entities (home, ptarchive) is per-language
 * in the wpseo_titles option when WPML String Translation is active.
 * For simplicity in v1, we use the same keys; WPML handles display via string translation.
 */
function ymi_ml_get_post_yoast($post_id) {
    return [ 'title'=>get_post_meta($post_id, '_yoast_wpseo_title', true) ?: '',
        'desc'=> get_post_meta($post_id, '_yoast_wpseo_metadesc', true) ?: '',
        ];
}