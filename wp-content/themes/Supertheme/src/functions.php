<?php
require_once __DIR__.'/../App/bootstrap.php';

/** @var $container \Symfony\Component\DependencyInjection\Container */
/** @var $twig \Twig_Environment */
$twig = $container->get('twig.environment');

// register scripts/styles
add_action('wp_enqueue_scripts', function() use($container) {
    // styles
    if($container->hasParameter('wordpress.styles')) {
        foreach ($container->getParameter('wordpress.styles') as $args) {
            wp_register_style($args['id'], $container->getParameterBag()->resolveValue($args['source']), $args['deps'], false, 'all');
            wp_enqueue_style($args['id']);
        }
    }

    // config
    if($container->hasParameter('wordpress.scripts')) {
        foreach ($container->getParameter('wordpress.scripts') as $args) {
            wp_register_script($args['id'], $container->getParameterBag()->resolveValue($args['source']), $args['deps'], false, $args['header']);
            wp_enqueue_script($args['id']);
        }
    }
});

add_action('init', function () use($container) {
    // always start a session
    if (!session_id()) {
        session_start();
    }

    // post types
    if($container->hasParameter('wordpress.post_types')) {
        foreach ($container->getParameter('wordpress.post_types', []) as $post_type => $args) {
            register_post_type($post_type, $args);
        }
    }
});

add_action('after_setup_theme', function() use($container) {
    // load languages directory
    if($container->hasParameter('wordpress.translations')) {
        load_theme_textdomain('supertheme', $container->getParameter('wordpress.translations'));
    }

    // image sizes from config
    if($container->hasParameter('wordpress.image_sizes')) {
        foreach ($container->getParameter('wordpress.image_sizes') as $imageSize) {
            call_user_func_array('add_image_size', $imageSize);
        }
    }

    // theme supports
    if($container->hasParameter('wordpress.theme_support')) {
        foreach ($container->getParameter('wordpress.theme_support') as $support) {
            add_theme_support($support);
        }
    }

    // theme sidebars
    if($container->hasParameter('wordpress.sidebars')) {
        foreach ($container->getParameter('wordpress.sidebars') as $sidebar) {
            register_sidebar($sidebar);
        }
    }

    // menus from config
    if($container->hasParameter('wordpress.menus')) {
        register_nav_menus($container->getParameter('wordpress.menus'));
    }
});