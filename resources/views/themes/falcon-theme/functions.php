<?php

/**
 * Lazy Theme Functions & Hooks
 */

// Example hook: Filter site title
add_falcon_filter('site_title', function($title) {
    return $title . ' | Falcon CMS';
});

// Add more theme-specific logic here

/**
 * EXAMPLE 1: ACTION HOOK
 * This function will run whenever 'falcon_admin_footer' is triggered.
 * It adds a custom copyright notice to the admin dashboard footer.
 */
add_falcon_action('falcon_admin_footer', function() {
    echo '<div style="padding: 10px; color: #646970; border-top: 1px solid #dcdcde; margin-top: 20px;">
            &copy; ' . date('Y') . ' Theme developed by Falcon CMS
          </div>';
});

/**
 * EXAMPLE 2: FILTER HOOK
 * This filter modifies the content of posts.
 * It appends a "Read more on Lazy Panda" link to every post content.
 */
/*
add_falcon_filter('the_content', function($content) {
    return $content . '<p><i>Originally published on Lazy Panda.</i></p>';
});
*/
add_falcon_filter('falcon_the_content', function($content) {
    return $content . '<p><i>Originally published on Falcon CMS.</i></p>';
});

/**
 * EXAMPLE 3: REMOVING AN ACTION
 * If you want to remove the previously added footer action:
 */
// remove_falcon_action('falcon_admin_footer', 'your_function_name_if_not_anonymous');

// Note: To remove an anonymous function (like Example 1), 
// you would need to store the closure in a variable first.
